<?php

namespace Litepie\Repository\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

trait BulkOperations
{
    /**
     * Bulk insert data.
     */
    public function bulkInsert(array $data, int $chunkSize = 1000): bool
    {
        if (empty($data)) {
            return true;
        }

        $this->fireEvent('bulk_inserting', ['count' => count($data), 'chunk_size' => $chunkSize]);

        try {
            DB::transaction(function () use ($data, $chunkSize) {
                $chunks = array_chunk($data, $chunkSize);
                
                foreach ($chunks as $chunk) {
                    // Add timestamps if not present
                    $chunk = array_map(function ($item) {
                        if (!isset($item['created_at'])) {
                            $item['created_at'] = now();
                        }
                        if (!isset($item['updated_at'])) {
                            $item['updated_at'] = now();
                        }
                        return $item;
                    }, $chunk);
                    
                    $this->model->insert($chunk);
                }
            });

            $this->fireEvent('bulk_inserted', ['count' => count($data)]);
            $this->invalidateCache();

            return true;
        } catch (\Exception $e) {
            $this->fireEvent('bulk_insert_failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Bulk update data by key.
     */
    public function bulkUpdate(array $data, string $key = 'id', int $chunkSize = 1000): int
    {
        if (empty($data)) {
            return 0;
        }

        $this->fireEvent('bulk_updating', ['count' => count($data), 'key' => $key]);

        $updated = 0;

        try {
            DB::transaction(function () use ($data, $key, $chunkSize, &$updated) {
                $chunks = array_chunk($data, $chunkSize);
                
                foreach ($chunks as $chunk) {
                    foreach ($chunk as $item) {
                        if (!isset($item[$key])) {
                            continue;
                        }
                        
                        $keyValue = $item[$key];
                        unset($item[$key]);
                        
                        // Add updated_at timestamp
                        $item['updated_at'] = now();
                        
                        $affected = $this->model
                            ->where($key, $keyValue)
                            ->update($item);
                            
                        $updated += $affected;
                    }
                }
            });

            $this->fireEvent('bulk_updated', ['count' => $updated]);
            $this->invalidateCache();

            return $updated;
        } catch (\Exception $e) {
            $this->fireEvent('bulk_update_failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Bulk delete by IDs.
     */
    public function bulkDelete(array $ids, string $key = 'id', int $chunkSize = 1000): int
    {
        if (empty($ids)) {
            return 0;
        }

        $this->fireEvent('bulk_deleting', ['count' => count($ids), 'key' => $key]);

        $deleted = 0;

        try {
            DB::transaction(function () use ($ids, $key, $chunkSize, &$deleted) {
                $chunks = array_chunk($ids, $chunkSize);
                
                foreach ($chunks as $chunk) {
                    $affected = $this->model
                        ->whereIn($key, $chunk)
                        ->delete();
                        
                    $deleted += $affected;
                }
            });

            $this->fireEvent('bulk_deleted', ['count' => $deleted]);
            $this->invalidateCache();

            return $deleted;
        } catch (\Exception $e) {
            $this->fireEvent('bulk_delete_failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Bulk upsert (insert or update).
     */
    public function bulkUpsert(array $data, array $uniqueBy, array $update = null, int $chunkSize = 1000): int
    {
        if (empty($data)) {
            return 0;
        }

        $this->fireEvent('bulk_upserting', [
            'count' => count($data), 
            'unique_by' => $uniqueBy,
            'update' => $update
        ]);

        $affected = 0;

        try {
            DB::transaction(function () use ($data, $uniqueBy, $update, $chunkSize, &$affected) {
                $chunks = array_chunk($data, $chunkSize);
                
                foreach ($chunks as $chunk) {
                    // Add timestamps if not present
                    $chunk = array_map(function ($item) {
                        if (!isset($item['created_at'])) {
                            $item['created_at'] = now();
                        }
                        if (!isset($item['updated_at'])) {
                            $item['updated_at'] = now();
                        }
                        return $item;
                    }, $chunk);
                    
                    // Use Laravel's upsert method if available (Laravel 8+)
                    if (method_exists($this->model, 'upsert')) {
                        $updateFields = $update ?? array_keys($chunk[0] ?? []);
                        $updateFields = array_filter($updateFields, function ($field) use ($uniqueBy) {
                            return !in_array($field, $uniqueBy) && $field !== 'created_at';
                        });
                        
                        $affected += $this->model->upsert($chunk, $uniqueBy, $updateFields);
                    } else {
                        // Fallback for older Laravel versions
                        foreach ($chunk as $item) {
                            $whereClause = [];
                            foreach ($uniqueBy as $field) {
                                if (isset($item[$field])) {
                                    $whereClause[$field] = $item[$field];
                                }
                            }
                            
                            $existing = $this->model->where($whereClause)->first();
                            
                            if ($existing) {
                                $updateData = $update ? array_intersect_key($item, array_flip($update)) : $item;
                                $updateData['updated_at'] = now();
                                unset($updateData['created_at']);
                                
                                $existing->update($updateData);
                            } else {
                                $this->model->create($item);
                            }
                            
                            $affected++;
                        }
                    }
                }
            });

            $this->fireEvent('bulk_upserted', ['count' => $affected]);
            $this->invalidateCache();

            return $affected;
        } catch (\Exception $e) {
            $this->fireEvent('bulk_upsert_failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Batch process records with callback.
     */
    public function batchProcess(callable $callback, int $chunkSize = 1000, array $columns = ['*']): void
    {
        $this->fireEvent('batch_processing_started', ['chunk_size' => $chunkSize]);

        $processed = 0;

        $this->query->chunk($chunkSize, function (Collection $records) use ($callback, &$processed) {
            $callback($records);
            $processed += $records->count();
            
            $this->fireEvent('batch_chunk_processed', [
                'chunk_size' => $records->count(),
                'total_processed' => $processed
            ]);
        });

        $this->fireEvent('batch_processing_completed', ['total_processed' => $processed]);
    }

    /**
     * Batch update with callback.
     */
    public function batchUpdate(callable $callback, int $chunkSize = 1000, array $columns = ['*']): int
    {
        $updated = 0;

        $this->batchProcess(function (Collection $records) use ($callback, &$updated) {
            foreach ($records as $record) {
                $originalAttributes = $record->getAttributes();
                $callback($record);
                
                if ($record->isDirty()) {
                    $record->save();
                    $updated++;
                }
            }
        }, $chunkSize, $columns);

        $this->invalidateCache();
        
        return $updated;
    }

    /**
     * Parallel bulk insert using multiple connections.
     */
    public function parallelBulkInsert(array $data, int $threads = 4, int $chunkSize = 1000): bool
    {
        if (empty($data)) {
            return true;
        }

        $totalChunks = array_chunk($data, $chunkSize);
        $threadChunks = array_chunk($totalChunks, ceil(count($totalChunks) / $threads));

        $this->fireEvent('parallel_bulk_inserting', [
            'total_records' => count($data),
            'threads' => $threads,
            'chunk_size' => $chunkSize
        ]);

        try {
            // Process chunks in parallel (simplified version)
            // In a real implementation, you might use Laravel's Queue system
            foreach ($threadChunks as $chunks) {
                DB::transaction(function () use ($chunks) {
                    foreach ($chunks as $chunk) {
                        $this->bulkInsert($chunk);
                    }
                });
            }

            $this->fireEvent('parallel_bulk_inserted', ['count' => count($data)]);
            
            return true;
        } catch (\Exception $e) {
            $this->fireEvent('parallel_bulk_insert_failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Bulk insert with conflict resolution.
     */
    public function bulkInsertWithConflictResolution(
        array $data, 
        string $strategy = 'ignore', 
        array $conflictFields = ['id'],
        int $chunkSize = 1000
    ): array {
        $results = [
            'inserted' => 0,
            'updated' => 0,
            'ignored' => 0,
            'errors' => []
        ];

        try {
            $chunks = array_chunk($data, $chunkSize);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $item) {
                    $whereClause = [];
                    foreach ($conflictFields as $field) {
                        if (isset($item[$field])) {
                            $whereClause[$field] = $item[$field];
                        }
                    }

                    $existing = $this->model->where($whereClause)->first();

                    if ($existing) {
                        switch ($strategy) {
                            case 'update':
                                $existing->update($item);
                                $results['updated']++;
                                break;
                            case 'ignore':
                                $results['ignored']++;
                                break;
                            case 'error':
                                $results['errors'][] = "Conflict on: " . json_encode($whereClause);
                                break;
                        }
                    } else {
                        $this->model->create($item);
                        $results['inserted']++;
                    }
                }
            }

            $this->invalidateCache();
        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Get bulk operation statistics.
     */
    public function getBulkStats(): array
    {
        return [
            'optimal_chunk_size' => $this->calculateOptimalChunkSize(),
            'estimated_memory_usage' => $this->estimateMemoryUsage(),
            'recommended_batch_size' => $this->getRecommendedBatchSize(),
        ];
    }

    /**
     * Calculate optimal chunk size based on available memory.
     */
    protected function calculateOptimalChunkSize(): int
    {
        $availableMemory = $this->getAvailableMemory();
        $estimatedRowSize = 1024; // 1KB per row estimate
        
        return min(
            max(100, intval($availableMemory * 0.1 / $estimatedRowSize)),
            10000
        );
    }

    /**
     * Estimate memory usage for bulk operations.
     */
    protected function estimateMemoryUsage(int $records = 1000): string
    {
        $estimatedRowSize = 1024; // 1KB per row
        $totalSize = $records * $estimatedRowSize;
        
        return $this->formatBytes($totalSize);
    }

    /**
     * Get recommended batch size.
     */
    protected function getRecommendedBatchSize(): int
    {
        $tableSize = $this->getTableSize();
        
        if ($tableSize < 10000) {
            return 500;
        } elseif ($tableSize < 100000) {
            return 1000;
        } elseif ($tableSize < 1000000) {
            return 2000;
        } else {
            return 5000;
        }
    }

    /**
     * Get available memory in bytes.
     */
    protected function getAvailableMemory(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return 1024 * 1024 * 1024; // 1GB default
        }
        
        return $this->parseMemoryLimit($memoryLimit);
    }

    /**
     * Parse memory limit string to bytes.
     */
    protected function parseMemoryLimit(string $limit): int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return (int) $limit;
        }
    }

    /**
     * Format bytes to human readable.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unit = 0;
        
        while ($bytes >= 1024 && $unit < count($units) - 1) {
            $bytes /= 1024;
            $unit++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unit];
    }

    /**
     * Get approximate table size.
     */
    protected function getTableSize(): int
    {
        try {
            return $this->model->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
