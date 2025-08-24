<?php

namespace Litepie\Repository\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

interface RepositoryInterface
{
    /**
     * Get all records.
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Find a record by its primary key.
     */
    public function find(int $id, array $columns = ['*']): ?Model;

    /**
     * Find a record by its primary key or throw an exception.
     */
    public function findOrFail(int $id, array $columns = ['*']): Model;

    /**
     * Create a new record.
     */
    public function create(array $data): Model;

    /**
     * Update a record.
     */
    public function update(int $id, array $data): Model;

    /**
     * Delete a record.
     */
    public function delete(int $id): bool;

    /**
     * Get records with where conditions.
     */
    public function findWhere(array $where, array $columns = ['*']): Collection;

    /**
     * Get records where column value is in given array.
     */
    public function findWhereIn(string $column, array $values, array $columns = ['*']): Collection;

    /**
     * Get paginated records.
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Get simple paginated records.
     */
    public function simplePaginate(int $perPage = 15, array $columns = ['*']): Paginator;

    /**
     * Count all records.
     */
    public function count(): int;

    /**
     * Check if record exists.
     */
    public function exists(int $id): bool;

    /**
     * Get first record.
     */
    public function first(array $columns = ['*']): ?Model;

    /**
     * Get first record or throw exception.
     */
    public function firstOrFail(array $columns = ['*']): Model;

    /**
     * Add a where clause to the query.
     */
    public function where(string $column, mixed $operator = null, mixed $value = null): static;

    /**
     * Add an or where clause to the query.
     */
    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static;

    /**
     * Add a where in clause to the query.
     */
    public function whereIn(string $column, array $values): static;

    /**
     * Add an order by clause to the query.
     */
    public function orderBy(string $column, string $direction = 'asc'): static;

    /**
     * Eager load relationships.
     */
    public function with(array|string $relations): static;

    /**
     * Limit the number of results.
     */
    public function limit(int $limit): static;

    /**
     * Offset the results.
     */
    public function offset(int $offset): static;

    /**
     * Get the results of the query.
     */
    public function get(array $columns = ['*']): Collection;

    /**
     * Process records in chunks.
     */
    public function chunk(int $count, callable $callback): bool;

    /**
     * Add a join clause to the query.
     */
    public function join(string $table, string $first, string $operator = null, string $second = null): static;

    /**
     * Add a left join clause to the query.
     */
    public function leftJoin(string $table, string $first, string $operator = null, string $second = null): static;

    /**
     * Add a right join clause to the query.
     */
    public function rightJoin(string $table, string $first, string $operator = null, string $second = null): static;

    /**
     * Add an inner join clause to the query.
     */
    public function innerJoin(string $table, string $first, string $operator = null, string $second = null): static;

    /**
     * Add a cross join clause to the query.
     */
    public function crossJoin(string $table, string $first = null, string $operator = null, string $second = null): static;

    /**
     * Add a join clause with a closure to the query.
     */
    public function joinWhere(string $table, callable $callback): static;

    /**
     * Add a left join clause with a closure to the query.
     */
    public function leftJoinWhere(string $table, callable $callback): static;

    /**
     * Add a subquery join clause to the query.
     */
    public function joinSub(mixed $query, string $as, string $first, string $operator = null, string $second = null): static;

    /**
     * Add a left subquery join clause to the query.
     */
    public function leftJoinSub(mixed $query, string $as, string $first, string $operator = null, string $second = null): static;

    /**
     * Add group by clause to the query.
     */
    public function groupBy(array|string $groups): static;

    /**
     * Add having clause to the query.
     */
    public function having(string $column, string $operator = null, mixed $value = null): static;

    /**
     * Add or having clause to the query.
     */
    public function orHaving(string $column, string $operator = null, mixed $value = null): static;

    /**
     * Add having between clause to the query.
     */
    public function havingBetween(string $column, array $values): static;

    /**
     * Add select raw clause to the query.
     */
    public function selectRaw(string $expression, array $bindings = []): static;

    /**
     * Add where raw clause to the query.
     */
    public function whereRaw(string $sql, array $bindings = []): static;

    /**
     * Add or where raw clause to the query.
     */
    public function orWhereRaw(string $sql, array $bindings = []): static;

    /**
     * Add distinct clause to the query.
     */
    public function distinct(): static;

    /**
     * Add select columns to the query.
     */
    public function select(array|string $columns = ['*']): static;

    /**
     * Add additional select columns to the query.
     */
    public function addSelect(array|string $column): static;

    /**
     * Add where between clause to the query.
     */
    public function whereBetween(string $column, array $values): static;

    /**
     * Add where not between clause to the query.
     */
    public function whereNotBetween(string $column, array $values): static;

    /**
     * Add where null clause to the query.
     */
    public function whereNull(string $column): static;

    /**
     * Add where not null clause to the query.
     */
    public function whereNotNull(string $column): static;

    /**
     * Add where date clause to the query.
     */
    public function whereDate(string $column, string $operator, mixed $value = null): static;

    /**
     * Add where time clause to the query.
     */
    public function whereTime(string $column, string $operator, mixed $value = null): static;

    /**
     * Add where year clause to the query.
     */
    public function whereYear(string $column, string $operator, mixed $value = null): static;

    /**
     * Add where month clause to the query.
     */
    public function whereMonth(string $column, string $operator, mixed $value = null): static;

    /**
     * Add where day clause to the query.
     */
    public function whereDay(string $column, string $operator, mixed $value = null): static;

    /**
     * Add order by raw clause to the query.
     */
    public function orderByRaw(string $sql, array $bindings = []): static;

    /**
     * Add where exists clause to the query.
     */
    public function whereExists(callable $callback): static;

    /**
     * Add where not exists clause to the query.
     */
    public function whereNotExists(callable $callback): static;

    /**
     * Add union clause to the query.
     */
    public function union(mixed $query): static;

    /**
     * Apply filters to the query.
     */
    public function filter(array $filters): static;

    /**
     * Apply a single filter condition.
     */
    public function applyFilter(string $field, mixed $value, string $operator = '='): static;

    /**
     * Apply multiple filter conditions with AND logic.
     */
    public function applyFilters(array $filters, string $defaultOperator = '='): static;

    /**
     * Apply search across specified columns.
     */
    public function search(string $term, array $columns = []): static;

    /**
     * Apply date range filter.
     */
    public function dateRange(string $column, ?string $from = null, ?string $to = null): static;

    /**
     * Apply scope filter.
     */
    public function scope(string $scope, ...$parameters): static;

    /**
     * Apply conditional where clause.
     */
    public function when(mixed $condition, callable $callback, ?callable $default = null): static;

    /**
     * Apply unless conditional clause.
     */
    public function unless(mixed $condition, callable $callback, ?callable $default = null): static;

    /**
     * Apply filters from request parameters.
     */
    public function filterFromRequest(array $requestData, array $allowedFilters = []): static;

    /**
     * Apply sorting from request parameters.
     */
    public function sortFromRequest(array $requestData, array $allowedSorts = [], string $defaultSort = 'id', string $defaultDirection = 'asc'): static;

    /**
     * Reset the query builder.
     */
    public function resetQuery(): static;

    /**
     * Get the model class name.
     */
    public function model(): string;

    /**
     * Create a new instance of the model.
     */
    public function makeModel(): Model;

    // Optimized Pagination Methods
    
    /**
     * Cursor-based pagination for large datasets.
     */
    public function cursorPaginate(int $perPage = 15, array $columns = ['*'], string $cursorName = 'cursor', $cursor = null);

    /**
     * Fast pagination without total count.
     */
    public function fastPaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page');

    /**
     * Optimized pagination with approximate count.
     */
    public function optimizedPaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null, bool $useApproximateCount = true);

    /**
     * Chunk by ID for better performance.
     */
    public function chunkById(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool;

    /**
     * Lazy collection for memory-efficient iteration.
     */
    public function lazy(int $chunkSize = 1000);

    /**
     * Lazy collection by ID.
     */
    public function lazyById(int $chunkSize = 1000, ?string $column = null, ?string $alias = null);

    /**
     * Seek pagination for real-time feeds.
     */
    public function seekPaginate(int $limit = 15, $lastId = null, string $direction = 'next', string $orderColumn = 'id');

    /**
     * Get estimated count for large tables.
     */
    public function estimatedCount(): int;

    /**
     * Cached pagination with total count caching.
     */
    public function cachedPaginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null, int $cacheTtl = 300);

    /**
     * Create pagination performance report.
     */
    public function paginationPerformanceReport(int $perPage = 15, int $page = 1): array;

    // Query String Parsing Methods
    
    /**
     * Parse and apply filters from query string format.
     */
    public function parseQueryFilters(string $filterString, array $allowedFields = []): static;

    /**
     * Parse and apply filters from request query parameters.
     */
    public function parseRequestFilters(array $requestData, array $allowedFields = []): static;
}
