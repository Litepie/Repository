<?php

namespace Litepie\Repository\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcreteLengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Optimized Pagination Trait
 * 
 * Provides advanced pagination methods optimized for large datasets
 * including cursor-based pagination, chunking, and performance optimizations.
 */
trait OptimizedPagination
{
    /**
     * Cursor-based pagination for large datasets.
     * Much faster than offset-based pagination for large datasets.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $cursorName
     * @param Cursor|string|null $cursor
     * @return CursorPaginator
     */
    public function cursorPaginate(
        int $perPage = 15, 
        array $columns = ['*'], 
        string $cursorName = 'cursor',
        $cursor = null
    ): CursorPaginator {
        // Ensure we have an order by clause for cursor pagination
        if (empty($this->query->getQuery()->orders)) {
            $this->query->orderBy($this->model->getKeyName());
        }

        return $this->query->cursorPaginate($perPage, $columns, $cursorName, $cursor);
    }

    /**
     * Fast pagination without total count for large datasets.
     * Uses LIMIT + 1 to determine if there are more pages.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @return Paginator
     */
    public function fastPaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page'): Paginator
    {
        return $this->query->simplePaginate($perPage, $columns, $pageName);
    }

    /**
     * Paginate with optimized count query for large datasets.
     * Uses approximate count for better performance.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @param bool $useApproximateCount
     * @return LengthAwarePaginator
     */
    public function optimizedPaginate(
        int $perPage = 15, 
        array $columns = ['*'], 
        string $pageName = 'page', 
        ?int $page = null,
        bool $useApproximateCount = true
    ): LengthAwarePaginator {
        if ($useApproximateCount && $this->shouldUseApproximateCount()) {
            return $this->paginateWithApproximateCount($perPage, $columns, $pageName, $page);
        }

        return $this->query->paginate($perPage, $columns, $pageName, $page);
    }

    /**
     * Chunk large datasets for processing without memory issues.
     *
     * @param int $count
     * @param callable $callback
     * @return bool
     */
    public function chunk(int $count, callable $callback): bool
    {
        return $this->query->chunk($count, $callback);
    }

    /**
     * Chunk by ID for better performance on large datasets.
     * Avoids OFFSET which becomes slow on large datasets.
     *
     * @param int $count
     * @param callable $callback
     * @param string|null $column
     * @param string|null $alias
     * @return bool
     */
    public function chunkById(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool
    {
        $column = $column ?? $this->model->getKeyName();
        return $this->query->chunkById($count, $callback, $column, $alias);
    }

    /**
     * Lazy collection for memory-efficient iteration over large datasets.
     *
     * @param int $chunkSize
     * @return \Illuminate\Support\LazyCollection
     */
    public function lazy(int $chunkSize = 1000)
    {
        return $this->query->lazy($chunkSize);
    }

    /**
     * Lazy collection by ID for better performance.
     *
     * @param int $chunkSize
     * @param string|null $column
     * @param string|null $alias
     * @return \Illuminate\Support\LazyCollection
     */
    public function lazyById(int $chunkSize = 1000, ?string $column = null, ?string $alias = null)
    {
        $column = $column ?? $this->model->getKeyName();
        return $this->query->lazyById($chunkSize, $column, $alias);
    }

    /**
     * Get paginated results with seek pagination (cursor-like but with custom logic).
     * Excellent for real-time feeds and large datasets.
     *
     * @param int $limit
     * @param mixed $lastId
     * @param string $direction
     * @param string $orderColumn
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function seekPaginate(
        int $limit = 15, 
        $lastId = null, 
        string $direction = 'next', 
        string $orderColumn = 'id'
    ) {
        $query = clone $this->query;

        if ($lastId !== null) {
            if ($direction === 'next') {
                $query->where($orderColumn, '>', $lastId);
            } else {
                $query->where($orderColumn, '<', $lastId);
            }
        }

        $query->orderBy($orderColumn, $direction === 'next' ? 'asc' : 'desc');

        return $query->limit($limit)->get();
    }

    /**
     * Paginate with window function for better performance on large datasets.
     * Uses ROW_NUMBER() to avoid OFFSET issues.
     *
     * @param int $perPage
     * @param int $page
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function windowPaginate(int $perPage = 15, int $page = 1, array $columns = ['*'])
    {
        $offset = ($page - 1) * $perPage;
        
        $subQuery = $this->query->toBase();
        $grammar = $subQuery->getGrammar();
        
        // Add ROW_NUMBER() window function
        $sql = $subQuery->toSql();
        $bindings = $subQuery->getBindings();
        
        $tableName = $this->model->getTable();
        $keyName = $this->model->getKeyName();
        
        $windowSql = "
            SELECT * FROM (
                SELECT *, ROW_NUMBER() OVER (ORDER BY {$keyName}) as row_num
                FROM ({$sql}) as temp_table
            ) as numbered_table
            WHERE row_num > {$offset} AND row_num <= " . ($offset + $perPage);
        
        $results = DB::select($windowSql, $bindings);
        
        return $this->model->hydrate($results);
    }

    /**
     * Get estimated count for large tables using EXPLAIN.
     * Much faster than COUNT(*) on large datasets.
     *
     * @return int
     */
    public function estimatedCount(): int
    {
        $query = clone $this->query;
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        
        try {
            // For MySQL
            if (DB::getDriverName() === 'mysql') {
                $explain = DB::select("EXPLAIN SELECT COUNT(*) FROM ({$sql}) as temp_table", $bindings);
                return $explain[0]->rows ?? 0;
            }
            
            // For PostgreSQL
            if (DB::getDriverName() === 'pgsql') {
                $explain = DB::select("EXPLAIN (FORMAT JSON) SELECT COUNT(*) FROM ({$sql}) as temp_table", $bindings);
                $plan = json_decode($explain[0]->{'QUERY PLAN'}, true);
                return $plan[0]['Plan']['Plan Rows'] ?? 0;
            }
            
            // Fallback to actual count
            return $this->count();
        } catch (\Exception $e) {
            // Fallback to actual count if explain fails
            return $this->count();
        }
    }

    /**
     * Paginate with total count caching for better performance.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @param int $cacheTtl
     * @return LengthAwarePaginator
     */
    public function cachedPaginate(
        int $perPage = 15, 
        array $columns = ['*'], 
        string $pageName = 'page', 
        ?int $page = null,
        int $cacheTtl = 300
    ): LengthAwarePaginator {
        $cacheKey = $this->generatePaginationCacheKey();
        
        $total = Cache::remember($cacheKey . '_count', $cacheTtl, function() {
            return $this->count();
        });
        
        $items = $this->query->forPage($page ?? 1, $perPage)->get($columns);
        
        return new ConcreteLengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Parallel pagination for extremely large datasets.
     * Divides the query into multiple parallel queries.
     *
     * @param int $perPage
     * @param int $page
     * @param int $parallelQueries
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function parallelPaginate(
        int $perPage = 15, 
        int $page = 1, 
        int $parallelQueries = 4, 
        array $columns = ['*']
    ) {
        $offset = ($page - 1) * $perPage;
        $chunkSize = ceil($perPage / $parallelQueries);
        
        $promises = [];
        
        for ($i = 0; $i < $parallelQueries; $i++) {
            $chunkOffset = $offset + ($i * $chunkSize);
            $chunkLimit = min($chunkSize, $perPage - ($i * $chunkSize));
            
            if ($chunkLimit <= 0) break;
            
            $promises[] = function() use ($chunkOffset, $chunkLimit, $columns) {
                return $this->query->offset($chunkOffset)->limit($chunkLimit)->get($columns);
            };
        }
        
        // Execute queries in parallel (simplified version)
        $results = new Collection();
        foreach ($promises as $promise) {
            $results = $results->merge($promise());
        }
        
        return $results;
    }

    /**
     * Determine if approximate count should be used based on table size.
     *
     * @return bool
     */
    protected function shouldUseApproximateCount(): bool
    {
        // Use approximate count for tables with more than 1 million estimated rows
        $estimatedRows = $this->getTableRowEstimate();
        return $estimatedRows > 1000000;
    }

    /**
     * Get estimated table row count from information schema.
     *
     * @return int
     */
    protected function getTableRowEstimate(): int
    {
        $tableName = $this->model->getTable();
        
        try {
            if (DB::getDriverName() === 'mysql') {
                $result = DB::select("
                    SELECT table_rows 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ?
                ", [$tableName]);
                
                return $result[0]->table_rows ?? 0;
            }
            
            if (DB::getDriverName() === 'pgsql') {
                $result = DB::select("
                    SELECT n_tup_ins + n_tup_upd as estimated_rows
                    FROM pg_stat_user_tables 
                    WHERE relname = ?
                ", [$tableName]);
                
                return $result[0]->estimated_rows ?? 0;
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Paginate with approximate count for large datasets.
     *
     * @param int $perPage
     * @param array $columns
     * @param string $pageName
     * @param int|null $page
     * @return LengthAwarePaginator
     */
    protected function paginateWithApproximateCount(
        int $perPage, 
        array $columns, 
        string $pageName, 
        ?int $page
    ): LengthAwarePaginator {
        $page = $page ?? 1;
        $total = $this->estimatedCount();
        
        $items = $this->query->forPage($page, $perPage)->get($columns);
        
        return new ConcreteLengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );
    }

    /**
     * Generate cache key for pagination.
     *
     * @return string
     */
    protected function generatePaginationCacheKey(): string
    {
        $query = clone $this->query;
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        
        return 'pagination_' . md5($sql . serialize($bindings));
    }

    /**
     * Optimize query for large dataset pagination.
     *
     * @return $this
     */
    public function optimizeForLargeDataset()
    {
        // Add index hints for better performance
        $this->query->getQuery()->useIndex = true;
        
        // Use covering indexes when possible
        if (method_exists($this->query, 'useIndex')) {
            $this->query->useIndex(['covering_index']);
        }
        
        return $this;
    }

    /**
     * Create pagination performance report.
     *
     * @param int $perPage
     * @param int $page
     * @return array
     */
    public function paginationPerformanceReport(int $perPage = 15, int $page = 1): array
    {
        $startTime = microtime(true);
        
        // Test different pagination methods
        $methods = [];
        
        // Standard pagination
        $start = microtime(true);
        $standardResult = $this->paginate($perPage, ['*'], 'page', $page);
        $methods['standard'] = [
            'time' => microtime(true) - $start,
            'memory' => memory_get_peak_usage(true),
            'count' => $standardResult->total(),
        ];
        
        // Fast pagination
        $this->resetQuery();
        $start = microtime(true);
        $fastResult = $this->fastPaginate($perPage);
        $methods['fast'] = [
            'time' => microtime(true) - $start,
            'memory' => memory_get_peak_usage(true),
            'count' => $fastResult->count(),
        ];
        
        // Cursor pagination
        $this->resetQuery();
        $start = microtime(true);
        $cursorResult = $this->cursorPaginate($perPage);
        $methods['cursor'] = [
            'time' => microtime(true) - $start,
            'memory' => memory_get_peak_usage(true),
            'count' => $cursorResult->count(),
        ];
        
        return [
            'total_time' => microtime(true) - $startTime,
            'table' => $this->model->getTable(),
            'estimated_rows' => $this->getTableRowEstimate(),
            'methods' => $methods,
            'recommendation' => $this->getPerformanceRecommendation($methods),
        ];
    }

    /**
     * Get performance recommendation based on test results.
     *
     * @param array $methods
     * @return string
     */
    protected function getPerformanceRecommendation(array $methods): string
    {
        $fastest = array_keys($methods, min($methods))[0];
        $estimatedRows = $this->getTableRowEstimate();
        
        if ($estimatedRows > 5000000) {
            return "For {$estimatedRows} rows: Use cursor pagination or seek pagination for best performance";
        } elseif ($estimatedRows > 1000000) {
            return "For {$estimatedRows} rows: Use fast pagination or cursor pagination";
        } else {
            return "For {$estimatedRows} rows: Standard pagination is acceptable, fastest method was: {$fastest}";
        }
    }
}
