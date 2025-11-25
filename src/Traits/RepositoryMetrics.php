<?php

namespace Litepie\Repository\Traits;

use Illuminate\Support\Facades\DB;

trait RepositoryMetrics
{
    /**
     * Performance metrics.
     */
    protected array $metrics = [
        'queries' => [],
        'execution_times' => [],
        'memory_usage' => [],
        'cache_hits' => 0,
        'cache_misses' => 0,
    ];

    /**
     * Profiling enabled flag.
     */
    protected bool $profilingEnabled = false;

    /**
     * Start time for operations.
     */
    protected float $startTime;

    /**
     * Start memory usage.
     */
    protected int $startMemory;

    /**
     * Configure metrics settings.
     */
    public function configureMetrics(array $config): self
    {
        if (isset($config['enabled'])) {
            if ($config['enabled']) {
                $this->enableProfiling();
            } else {
                $this->disableProfiling();
            }
        }
        
        if (isset($config['track_queries']) && $config['track_queries']) {
            DB::enableQueryLog();
        }
        
        return $this;
    }

    /**
     * Enable performance profiling.
     */
    public function enableProfiling(): self
    {
        $this->profilingEnabled = true;
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        
        // Enable query logging
        DB::enableQueryLog();
        
        $this->fireEvent('profiling_enabled');
        
        return $this;
    }

    /**
     * Disable performance profiling.
     */
    public function disableProfiling(): self
    {
        $this->profilingEnabled = false;
        
        // Disable query logging
        DB::disableQueryLog();
        
        $this->fireEvent('profiling_disabled');
        
        return $this;
    }

    /**
     * Get total query execution time.
     */
    public function getQueryTime(): float
    {
        return array_sum($this->metrics['execution_times']);
    }

    /**
     * Get current memory usage.
     */
    public function getMemoryUsage(): int
    {
        return memory_get_usage(true) - $this->startMemory;
    }

    /**
     * Get peak memory usage.
     */
    public function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage(true);
    }

    /**
     * Get total number of queries executed.
     */
    public function getQueryCount(): int
    {
        return count($this->metrics['queries']);
    }

    /**
     * Get query log.
     */
    public function getQueryLog(): array
    {
        return $this->metrics['queries'];
    }

    /**
     * Explain current query.
     */
    public function explain(): array
    {
        $sql = $this->query->toSql();
        $bindings = $this->query->getBindings();
        
        // Replace placeholders with actual values for EXPLAIN
        $fullSql = $sql;
        foreach ($bindings as $binding) {
            $fullSql = preg_replace('/\?/', is_string($binding) ? "'$binding'" : $binding, $fullSql, 1);
        }
        
        $explain = DB::select("EXPLAIN $fullSql");
        
        return [
            'sql' => $sql,
            'bindings' => $bindings,
            'full_sql' => $fullSql,
            'explain' => $explain
        ];
    }

    /**
     * Benchmark a callback function.
     */
    public function benchmark(callable $callback, int $iterations = 1): array
    {
        $times = [];
        $memoryUsages = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            $result = $callback($this);
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);
            
            $times[] = $endTime - $startTime;
            $memoryUsages[] = $endMemory - $startMemory;
        }
        
        return [
            'iterations' => $iterations,
            'times' => $times,
            'avg_time' => array_sum($times) / count($times),
            'min_time' => min($times),
            'max_time' => max($times),
            'memory_usages' => $memoryUsages,
            'avg_memory' => array_sum($memoryUsages) / count($memoryUsages),
            'min_memory' => min($memoryUsages),
            'max_memory' => max($memoryUsages),
        ];
    }

    /**
     * Profile query execution.
     */
    protected function profileQuery(callable $callback, string $operation = 'query')
    {
        if (!$this->profilingEnabled) {
            return $callback();
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $queryCountBefore = count(DB::getQueryLog());
        
        $result = $callback();
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $executionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        
        // Record metrics
        $this->metrics['execution_times'][] = $executionTime;
        $this->metrics['memory_usage'][] = $memoryUsed;
        
        // Get new queries
        $allQueries = DB::getQueryLog();
        $newQueries = array_slice($allQueries, $queryCountBefore);
        
        foreach ($newQueries as $query) {
            $this->metrics['queries'][] = [
                'sql' => $query['query'],
                'bindings' => $query['bindings'],
                'time' => $query['time'],
                'operation' => $operation,
                'timestamp' => now()
            ];
        }
        
        $this->fireEvent('query_profiled', [
            'operation' => $operation,
            'execution_time' => $executionTime,
            'memory_used' => $memoryUsed,
            'queries_count' => count($newQueries)
        ]);
        
        return $result;
    }

    /**
     * Get performance metrics.
     */
    public function getMetrics(): array
    {
        return [
            'profiling_enabled' => $this->profilingEnabled,
            'total_queries' => $this->getQueryCount(),
            'total_execution_time' => $this->getQueryTime(),
            'average_query_time' => $this->getQueryCount() > 0 ? $this->getQueryTime() / $this->getQueryCount() : 0,
            'memory_usage' => $this->getMemoryUsage(),
            'peak_memory_usage' => $this->getPeakMemoryUsage(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'slow_queries' => $this->getSlowQueries(),
            'duplicate_queries' => $this->getDuplicateQueries(),
        ];
    }

    /**
     * Get cache hit rate.
     */
    public function getCacheHitRate(): float
    {
        $total = $this->metrics['cache_hits'] + $this->metrics['cache_misses'];
        
        if ($total === 0) {
            return 0.0;
        }
        
        return ($this->metrics['cache_hits'] / $total) * 100;
    }

    /**
     * Get slow queries (queries taking more than threshold).
     */
    public function getSlowQueries(float $threshold = 100.0): array
    {
        return array_filter($this->metrics['queries'], function ($query) use ($threshold) {
            return $query['time'] > $threshold;
        });
    }

    /**
     * Get duplicate queries.
     */
    public function getDuplicateQueries(): array
    {
        $queryHashes = [];
        $duplicates = [];
        
        foreach ($this->metrics['queries'] as $query) {
            $hash = md5($query['sql'] . serialize($query['bindings']));
            
            if (isset($queryHashes[$hash])) {
                $duplicates[] = $query;
            } else {
                $queryHashes[$hash] = true;
            }
        }
        
        return $duplicates;
    }

    /**
     * Generate performance report.
     */
    public function getPerformanceReport(): array
    {
        $metrics = $this->getMetrics();
        $recommendations = $this->generateRecommendations($metrics);
        
        return [
            'summary' => [
                'total_queries' => $metrics['total_queries'],
                'total_time' => round($metrics['total_execution_time'] * 1000, 2) . 'ms',
                'average_time' => round($metrics['average_query_time'] * 1000, 2) . 'ms',
                'memory_used' => $this->formatBytes($metrics['memory_usage']),
                'cache_hit_rate' => round($metrics['cache_hit_rate'], 2) . '%',
            ],
            'issues' => [
                'slow_queries_count' => count($metrics['slow_queries']),
                'duplicate_queries_count' => count($metrics['duplicate_queries']),
                'high_memory_usage' => $metrics['memory_usage'] > 50 * 1024 * 1024, // 50MB
            ],
            'recommendations' => $recommendations,
            'detailed_metrics' => $metrics,
        ];
    }

    /**
     * Generate performance recommendations.
     */
    protected function generateRecommendations(array $metrics): array
    {
        $recommendations = [];
        
        // Check for slow queries
        if (count($metrics['slow_queries']) > 0) {
            $recommendations[] = [
                'type' => 'slow_queries',
                'message' => 'Consider adding database indexes or optimizing slow queries',
                'count' => count($metrics['slow_queries'])
            ];
        }
        
        // Check for duplicate queries
        if (count($metrics['duplicate_queries']) > 0) {
            $recommendations[] = [
                'type' => 'duplicate_queries',
                'message' => 'Enable caching or optimize to reduce duplicate queries',
                'count' => count($metrics['duplicate_queries'])
            ];
        }
        
        // Check cache hit rate
        if ($metrics['cache_hit_rate'] < 80 && $metrics['cache_hit_rate'] > 0) {
            $recommendations[] = [
                'type' => 'low_cache_hit_rate',
                'message' => 'Consider adjusting cache TTL or caching strategy',
                'current_rate' => $metrics['cache_hit_rate']
            ];
        }
        
        // Check memory usage
        if ($metrics['memory_usage'] > 100 * 1024 * 1024) { // 100MB
            $recommendations[] = [
                'type' => 'high_memory_usage',
                'message' => 'Consider using chunked processing for large datasets',
                'current_usage' => $this->formatBytes($metrics['memory_usage'])
            ];
        }
        
        // Check query count
        if ($metrics['total_queries'] > 50) {
            $recommendations[] = [
                'type' => 'high_query_count',
                'message' => 'Consider using eager loading or reducing N+1 query problems',
                'query_count' => $metrics['total_queries']
            ];
        }
        
        return $recommendations;
    }

    /**
     * Reset metrics.
     */
    public function resetMetrics(): self
    {
        $this->metrics = [
            'queries' => [],
            'execution_times' => [],
            'memory_usage' => [],
            'cache_hits' => 0,
            'cache_misses' => 0,
        ];
        
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        
        // Clear query log
        DB::flushQueryLog();
        
        $this->fireEvent('metrics_reset');
        
        return $this;
    }

    /**
     * Record cache hit.
     */
    public function recordCacheHit(): void
    {
        $this->metrics['cache_hits']++;
    }

    /**
     * Record cache miss.
     */
    public function recordCacheMiss(): void
    {
        $this->metrics['cache_misses']++;
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

    /**
     * Override methods to add profiling.
     */
    public function get(array $columns = ['*']): \Illuminate\Database\Eloquent\Collection
    {
        return $this->profileQuery(function () use ($columns) {
            return parent::get($columns);
        }, 'get');
    }

    public function first(array $columns = ['*']): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->profileQuery(function () use ($columns) {
            return parent::first($columns);
        }, 'first');
    }

    public function find(int $id, array $columns = ['*']): ?\Illuminate\Database\Eloquent\Model
    {
        return $this->profileQuery(function () use ($id, $columns) {
            return parent::find($id, $columns);
        }, 'find');
    }

    public function create(array $data): \Illuminate\Database\Eloquent\Model
    {
        return $this->profileQuery(function () use ($data) {
            return parent::create($data);
        }, 'create');
    }

    public function update(int $id, array $data): \Illuminate\Database\Eloquent\Model
    {
        return $this->profileQuery(function () use ($id, $data) {
            return parent::update($id, $data);
        }, 'update');
    }

    public function delete(int $id): bool
    {
        return $this->profileQuery(function () use ($id) {
            return parent::delete($id);
        }, 'delete');
    }
}
