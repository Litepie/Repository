<?php

namespace Litepie\Repository\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use League\Csv\Writer;
use League\Csv\Reader;

trait DataPortability
{
    /**
     * Export configuration.
     */
    protected array $exportConfig = [
        'chunk_size' => 1000,
        'memory_limit' => '512M',
        'disk' => 'local',
        'path' => 'exports',
    ];

    /**
     * Export data to CSV.
     */
    public function exportToCsv(array $columns = [], string $filename = null): string
    {
        $filename = $filename ?: $this->generateExportFilename('csv');
        $headers = $this->getExportHeaders($columns);
        
        $this->fireEvent('export_starting', [
            'format' => 'csv',
            'filename' => $filename,
            'columns' => $headers
        ]);

        $csv = Writer::createFromString();
        $csv->insertOne($headers);

        $exported = 0;
        
        $this->query->chunk($this->exportConfig['chunk_size'], function ($records) use ($csv, $headers, &$exported) {
            $rows = [];
            
            foreach ($records as $record) {
                $row = [];
                foreach ($headers as $column) {
                    $row[] = $this->getColumnValue($record, $column);
                }
                $rows[] = $row;
            }
            
            $csv->insertAll($rows);
            $exported += count($rows);
            
            $this->fireEvent('export_chunk_processed', [
                'format' => 'csv',
                'chunk_size' => count($rows),
                'total_exported' => $exported
            ]);
        });

        $content = $csv->toString();
        $path = $this->saveExportFile($filename, $content);
        
        $this->fireEvent('export_completed', [
            'format' => 'csv',
            'filename' => $filename,
            'path' => $path,
            'total_exported' => $exported
        ]);

        return $path;
    }

    /**
     * Export data to Excel.
     */
    public function exportToExcel(array $columns = [], string $filename = null): string
    {
        $filename = $filename ?: $this->generateExportFilename('xlsx');
        
        // For Excel export, you would typically use a package like PhpSpreadsheet
        // This is a simplified version that creates CSV format
        return $this->exportToCsv($columns, str_replace('.xlsx', '.csv', $filename));
    }

    /**
     * Export data to JSON.
     */
    public function exportToJson(array $columns = [], string $filename = null): string
    {
        $filename = $filename ?: $this->generateExportFilename('json');
        $columns = $this->getExportHeaders($columns);
        
        $this->fireEvent('export_starting', [
            'format' => 'json',
            'filename' => $filename,
            'columns' => $columns
        ]);

        $data = [];
        $exported = 0;
        
        $this->query->chunk($this->exportConfig['chunk_size'], function ($records) use (&$data, $columns, &$exported) {
            foreach ($records as $record) {
                $item = [];
                foreach ($columns as $column) {
                    $item[$column] = $this->getColumnValue($record, $column);
                }
                $data[] = $item;
            }
            
            $exported += $records->count();
            
            $this->fireEvent('export_chunk_processed', [
                'format' => 'json',
                'chunk_size' => $records->count(),
                'total_exported' => $exported
            ]);
        });

        $content = json_encode($data, JSON_PRETTY_PRINT);
        $path = $this->saveExportFile($filename, $content);
        
        $this->fireEvent('export_completed', [
            'format' => 'json',
            'filename' => $filename,
            'path' => $path,
            'total_exported' => $exported
        ]);

        return $path;
    }

    /**
     * Stream export for large datasets.
     */
    public function streamExport(string $format, callable $callback = null): Response
    {
        $filename = $this->generateExportFilename($format);
        
        $this->fireEvent('stream_export_starting', [
            'format' => $format,
            'filename' => $filename
        ]);

        $headers = [
            'Content-Type' => $this->getContentType($format),
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($format, $callback) {
            $output = fopen('php://output', 'w');
            
            if ($format === 'csv') {
                $headers = $this->getExportHeaders();
                fputcsv($output, $headers);
            } elseif ($format === 'json') {
                fwrite($output, "[\n");
            }

            $exported = 0;
            $first = true;
            
            $this->query->chunk($this->exportConfig['chunk_size'], function ($records) use ($output, $format, $callback, &$exported, &$first) {
                foreach ($records as $record) {
                    if ($callback) {
                        $record = $callback($record);
                    }

                    switch ($format) {
                        case 'csv':
                            $row = [];
                            foreach ($this->getExportHeaders() as $column) {
                                $row[] = $this->getColumnValue($record, $column);
                            }
                            fputcsv($output, $row);
                            break;
                            
                        case 'json':
                            if (!$first) {
                                fwrite($output, ",\n");
                            }
                            $first = false;
                            fwrite($output, json_encode($record->toArray()));
                            break;
                    }
                }
                
                $exported += $records->count();
                
                $this->fireEvent('stream_export_chunk_processed', [
                    'format' => $format,
                    'chunk_size' => $records->count(),
                    'total_exported' => $exported
                ]);
            });

            if ($format === 'json') {
                fwrite($output, "\n]");
            }

            fclose($output);
            
            $this->fireEvent('stream_export_completed', [
                'format' => $format,
                'total_exported' => $exported
            ]);
        }, 200, $headers);
    }

    /**
     * Import data from CSV.
     */
    public function importFromCsv(string $file, array $mapping = [], array $options = []): int
    {
        $options = array_merge([
            'has_header' => true,
            'chunk_size' => $this->exportConfig['chunk_size'],
            'skip_errors' => false,
            'update_existing' => false,
            'unique_field' => 'id'
        ], $options);

        $this->fireEvent('import_starting', [
            'format' => 'csv',
            'file' => $file,
            'mapping' => $mapping,
            'options' => $options
        ]);

        $reader = Reader::createFromPath($file, 'r');
        
        if ($options['has_header']) {
            $reader->setHeaderOffset(0);
            $headers = $reader->getHeader();
        }

        $imported = 0;
        $errors = [];
        $chunk = [];

        foreach ($reader->getRecords() as $offset => $record) {
            try {
                $data = $this->mapImportData($record, $mapping);
                $chunk[] = $data;

                if (count($chunk) >= $options['chunk_size']) {
                    $imported += $this->processCsvChunk($chunk, $options);
                    $chunk = [];
                }
            } catch (\Exception $e) {
                if (!$options['skip_errors']) {
                    throw $e;
                }
                
                $errors[] = [
                    'line' => $offset + 1,
                    'error' => $e->getMessage(),
                    'data' => $record
                ];
            }
        }

        // Process remaining chunk
        if (!empty($chunk)) {
            $imported += $this->processCsvChunk($chunk, $options);
        }

        $this->fireEvent('import_completed', [
            'format' => 'csv',
            'file' => $file,
            'total_imported' => $imported,
            'errors' => $errors
        ]);

        $this->invalidateCache();

        return $imported;
    }

    /**
     * Import data from Excel.
     */
    public function importFromExcel(string $file, array $mapping = [], array $options = []): int
    {
        // For Excel import, you would typically use PhpSpreadsheet
        // This is a simplified version that assumes CSV format
        return $this->importFromCsv($file, $mapping, $options);
    }

    /**
     * Import data from JSON.
     */
    public function importFromJson(string $file, array $mapping = [], array $options = []): int
    {
        $options = array_merge([
            'chunk_size' => $this->exportConfig['chunk_size'],
            'skip_errors' => false,
            'update_existing' => false,
            'unique_field' => 'id'
        ], $options);

        $this->fireEvent('import_starting', [
            'format' => 'json',
            'file' => $file,
            'mapping' => $mapping,
            'options' => $options
        ]);

        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON file');
        }

        $imported = 0;
        $errors = [];
        $chunks = array_chunk($data, $options['chunk_size']);

        foreach ($chunks as $chunk) {
            try {
                $mappedChunk = [];
                
                foreach ($chunk as $item) {
                    $mappedChunk[] = $this->mapImportData($item, $mapping);
                }
                
                $imported += $this->processJsonChunk($mappedChunk, $options);
            } catch (\Exception $e) {
                if (!$options['skip_errors']) {
                    throw $e;
                }
                
                $errors[] = [
                    'chunk' => count($errors) + 1,
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->fireEvent('import_completed', [
            'format' => 'json',
            'file' => $file,
            'total_imported' => $imported,
            'errors' => $errors
        ]);

        $this->invalidateCache();

        return $imported;
    }

    /**
     * Get export headers.
     */
    protected function getExportHeaders(array $columns = []): array
    {
        if (!empty($columns)) {
            return $columns;
        }

        // Get model fillable attributes or all columns
        $model = $this->model;
        
        if (method_exists($model, 'getFillable') && !empty($model->getFillable())) {
            return $model->getFillable();
        }

        // Fallback to common columns
        return ['id', 'created_at', 'updated_at'];
    }

    /**
     * Get column value from model.
     */
    protected function getColumnValue($record, string $column)
    {
        if (strpos($column, '.') !== false) {
            // Handle nested relationships
            $parts = explode('.', $column);
            $value = $record;
            
            foreach ($parts as $part) {
                $value = $value->$part ?? null;
                if ($value === null) {
                    break;
                }
            }
            
            return $value;
        }

        return $record->$column ?? null;
    }

    /**
     * Generate export filename.
     */
    protected function generateExportFilename(string $extension): string
    {
        $modelName = class_basename($this->model());
        $timestamp = now()->format('Y-m-d_H-i-s');
        
        return "{$modelName}_export_{$timestamp}.{$extension}";
    }

    /**
     * Save export file.
     */
    protected function saveExportFile(string $filename, string $content): string
    {
        $path = $this->exportConfig['path'] . '/' . $filename;
        
        Storage::disk($this->exportConfig['disk'])->put($path, $content);
        
        return $path;
    }

    /**
     * Get content type for format.
     */
    protected function getContentType(string $format): string
    {
        $types = [
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
        ];

        return $types[$format] ?? 'application/octet-stream';
    }

    /**
     * Map import data using mapping configuration.
     */
    protected function mapImportData(array $data, array $mapping): array
    {
        if (empty($mapping)) {
            return $data;
        }

        $mapped = [];
        
        foreach ($mapping as $importField => $modelField) {
            if (isset($data[$importField])) {
                $mapped[$modelField] = $data[$importField];
            }
        }

        return $mapped;
    }

    /**
     * Process CSV import chunk.
     */
    protected function processCsvChunk(array $chunk, array $options): int
    {
        if ($options['update_existing']) {
            return $this->bulkUpsert($chunk, [$options['unique_field']]);
        } else {
            $this->bulkInsert($chunk);
            return count($chunk);
        }
    }

    /**
     * Process JSON import chunk.
     */
    protected function processJsonChunk(array $chunk, array $options): int
    {
        return $this->processCsvChunk($chunk, $options);
    }

    /**
     * Configure export settings.
     */
    public function configureExport(array $config): self
    {
        $this->exportConfig = array_merge($this->exportConfig, $config);
        return $this;
    }

    /**
     * Get export statistics.
     */
    public function getExportStats(): array
    {
        $model = $this->model;
        $totalRecords = $this->query->count();
        
        return [
            'model' => class_basename($model),
            'total_records' => $totalRecords,
            'estimated_csv_size' => $this->estimateExportSize('csv', $totalRecords),
            'estimated_json_size' => $this->estimateExportSize('json', $totalRecords),
            'recommended_chunk_size' => $this->getRecommendedChunkSize($totalRecords),
        ];
    }

    /**
     * Estimate export file size.
     */
    protected function estimateExportSize(string $format, int $recordCount): string
    {
        $avgRowSize = $format === 'csv' ? 100 : 200; // bytes per record estimate
        $totalSize = $recordCount * $avgRowSize;
        
        return $this->formatBytes($totalSize);
    }

    /**
     * Get recommended chunk size for export.
     */
    protected function getRecommendedChunkSize(int $totalRecords): int
    {
        if ($totalRecords < 1000) {
            return 100;
        } elseif ($totalRecords < 10000) {
            return 500;
        } elseif ($totalRecords < 100000) {
            return 1000;
        } else {
            return 2000;
        }
    }

    /**
     * Format bytes to human readable format.
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
}
