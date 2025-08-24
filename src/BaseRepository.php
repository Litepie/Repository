<?php

namespace Litepie\Repository;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\App;
use Litepie\Repository\Contracts\RepositoryInterface;
use Litepie\Repository\Exceptions\RepositoryException;

/**
 * Base repository with core CRUD functionality only.
 * 
 * This repository provides essential methods without any trait loading.
 * Specialized repositories extend this and add specific traits as needed.
 */
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * The model instance.
     */
    protected Model $model;

    /**
     * The query builder instance.
     */
    protected Builder $query;

    /**
     * Create a new repository instance.
     */
    public function __construct()
    {
        $this->makeModel();
        $this->resetQuery();
    }

    /**
     * Specify the model class name.
     */
    abstract public function model(): string;

    /**
     * Create a new instance of the model.
     */
    public function makeModel(): Model
    {
        $model = App::make($this->model());

        if (!$model instanceof Model) {
            throw new RepositoryException("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $this->model = $model;
    }

    /**
     * Get all records.
     */
    public function all(array $columns = ['*']): Collection
    {
        return $this->query->get($columns);
    }

    /**
     * Find a record by its primary key.
     */
    public function find(int $id, array $columns = ['*']): ?Model
    {
        return $this->query->find($id, $columns);
    }

    /**
     * Find a record by its primary key or throw an exception.
     */
    public function findOrFail(int $id, array $columns = ['*']): Model
    {
        return $this->query->findOrFail($id, $columns);
    }

    /**
     * Create a new record.
     */
    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    /**
     * Update a record.
     */
    public function update(int $id, array $data): Model
    {
        $model = $this->findOrFail($id);
        $model->update($data);
        
        return $model;
    }

    /**
     * Delete a record.
     */
    public function delete(int $id): bool
    {
        $model = $this->findOrFail($id);
        
        return $model->delete();
    }

    /**
     * Get records with where conditions.
     */
    public function findWhere(array $where, array $columns = ['*']): Collection
    {
        foreach ($where as $condition) {
            if (is_array($condition) && count($condition) >= 2) {
                $this->query->where(...$condition);
            }
        }

        return $this->query->get($columns);
    }

    /**
     * Get records where column value is in given array.
     */
    public function findWhereIn(string $column, array $values, array $columns = ['*']): Collection
    {
        return $this->query->whereIn($column, $values)->get($columns);
    }

    /**
     * Get paginated records.
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->query->paginate($perPage, $columns);
    }

    /**
     * Get simple paginated records.
     */
    public function simplePaginate(int $perPage = 15, array $columns = ['*']): Paginator
    {
        return $this->query->simplePaginate($perPage, $columns);
    }

    /**
     * Count all records.
     */
    public function count(): int
    {
        return $this->query->count();
    }

    /**
     * Check if record exists.
     */
    public function exists(int $id): bool
    {
        return $this->query->where($this->model->getKeyName(), $id)->exists();
    }

    /**
     * Get first record.
     */
    public function first(array $columns = ['*']): ?Model
    {
        return $this->query->first($columns);
    }

    /**
     * Get first record or throw exception.
     */
    public function firstOrFail(array $columns = ['*']): Model
    {
        return $this->query->firstOrFail($columns);
    }

    /**
     * Add a where clause to the query.
     */
    public function where(string $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query->where($column, $operator, $value);
        
        return $this;
    }

    /**
     * Add an or where clause to the query.
     */
    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static
    {
        $this->query->orWhere($column, $operator, $value);
        
        return $this;
    }

    /**
     * Add a where in clause to the query.
     */
    public function whereIn(string $column, array $values): static
    {
        $this->query->whereIn($column, $values);
        
        return $this;
    }

    /**
     * Add an order by clause to the query.
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->query->orderBy($column, $direction);
        
        return $this;
    }

    /**
     * Eager load relationships.
     */
    public function with(array|string $relations): static
    {
        $this->query->with($relations);
        
        return $this;
    }

    /**
     * Limit the number of results.
     */
    public function limit(int $limit): static
    {
        $this->query->limit($limit);
        
        return $this;
    }

    /**
     * Offset the results.
     */
    public function offset(int $offset): static
    {
        $this->query->offset($offset);
        
        return $this;
    }

    /**
     * Get the results of the query.
     */
    public function get(array $columns = ['*']): Collection
    {
        return $this->query->get($columns);
    }

    /**
     * Process records in chunks.
     */
    public function chunk(int $count, callable $callback): bool
    {
        return $this->query->chunk($count, $callback);
    }

    /**
     * Add a join clause to the query.
     */
    public function join(string $table, string $first, string $operator = null, string $second = null): static
    {
        $this->query->join($table, $first, $operator, $second);
        
        return $this;
    }

    /**
     * Add a left join clause to the query.
     */
    public function leftJoin(string $table, string $first, string $operator = null, string $second = null): static
    {
        $this->query->leftJoin($table, $first, $operator, $second);
        
        return $this;
    }

    /**
     * Add a right join clause to the query.
     */
    public function rightJoin(string $table, string $first, string $operator = null, string $second = null): static
    {
        $this->query->rightJoin($table, $first, $operator, $second);
        
        return $this;
    }

    /**
     * Add an inner join clause to the query.
     */
    public function innerJoin(string $table, string $first, string $operator = null, string $second = null): static
    {
        $this->query->innerJoin($table, $first, $operator, $second);
        
        return $this;
    }

    /**
     * Add a cross join clause to the query.
     */
    public function crossJoin(string $table, string $first = null, string $operator = null, string $second = null): static
    {
        if ($first === null) {
            $this->query->crossJoin($table);
        } else {
            $this->query->crossJoin($table, $first, $operator, $second);
        }
        
        return $this;
    }

    /**
     * Add a join clause with a closure to the query.
     */
    public function joinWhere(string $table, callable $callback): static
    {
        $this->query->join($table, $callback);
        
        return $this;
    }

    /**
     * Add a left join clause with a closure to the query.
     */
    public function leftJoinWhere(string $table, callable $callback): static
    {
        $this->query->leftJoin($table, $callback);
        
        return $this;
    }

    /**
     * Add a subquery join clause to the query.
     */
    public function joinSub(mixed $query, string $as, string $first, string $operator = null, string $second = null): static
    {
        $this->query->joinSub($query, $as, $first, $operator, $second);
        
        return $this;
    }

    /**
     * Add a left subquery join clause to the query.
     */
    public function leftJoinSub(mixed $query, string $as, string $first, string $operator = null, string $second = null): static
    {
        $this->query->leftJoinSub($query, $as, $first, $operator, $second);
        
        return $this;
    }

    /**
     * Add group by clause to the query.
     */
    public function groupBy(array|string $groups): static
    {
        $this->query->groupBy($groups);
        
        return $this;
    }

    /**
     * Add having clause to the query.
     */
    public function having(string $column, string $operator = null, mixed $value = null): static
    {
        $this->query->having($column, $operator, $value);
        
        return $this;
    }

    /**
     * Add or having clause to the query.
     */
    public function orHaving(string $column, string $operator = null, mixed $value = null): static
    {
        $this->query->orHaving($column, $operator, $value);
        
        return $this;
    }

    /**
     * Add having between clause to the query.
     */
    public function havingBetween(string $column, array $values): static
    {
        $this->query->havingBetween($column, $values);
        
        return $this;
    }

    /**
     * Add select raw clause to the query.
     */
    public function selectRaw(string $expression, array $bindings = []): static
    {
        $this->query->selectRaw($expression, $bindings);
        
        return $this;
    }

    /**
     * Add where raw clause to the query.
     */
    public function whereRaw(string $sql, array $bindings = []): static
    {
        $this->query->whereRaw($sql, $bindings);
        
        return $this;
    }

    /**
     * Add or where raw clause to the query.
     */
    public function orWhereRaw(string $sql, array $bindings = []): static
    {
        $this->query->orWhereRaw($sql, $bindings);
        
        return $this;
    }

    /**
     * Add distinct clause to the query.
     */
    public function distinct(): static
    {
        $this->query->distinct();
        
        return $this;
    }

    /**
     * Add select columns to the query.
     */
    public function select(array|string $columns = ['*']): static
    {
        $this->query->select($columns);
        
        return $this;
    }

    /**
     * Add additional select columns to the query.
     */
    public function addSelect(array|string $column): static
    {
        $this->query->addSelect($column);
        
        return $this;
    }

    /**
     * Add where between clause to the query.
     */
    public function whereBetween(string $column, array $values): static
    {
        $this->query->whereBetween($column, $values);
        
        return $this;
    }

    /**
     * Add where not between clause to the query.
     */
    public function whereNotBetween(string $column, array $values): static
    {
        $this->query->whereNotBetween($column, $values);
        
        return $this;
    }

    /**
     * Add where null clause to the query.
     */
    public function whereNull(string $column): static
    {
        $this->query->whereNull($column);
        
        return $this;
    }

    /**
     * Add where not null clause to the query.
     */
    public function whereNotNull(string $column): static
    {
        $this->query->whereNotNull($column);
        
        return $this;
    }

    /**
     * Add where date clause to the query.
     */
    public function whereDate(string $column, string $operator, mixed $value = null): static
    {
        $this->query->whereDate($column, $operator, $value);
        
        return $this;
    }

    /**
     * Add where time clause to the query.
     */
    public function whereTime(string $column, string $operator, mixed $value = null): static
    {
        $this->query->whereTime($column, $operator, $value);
        
        return $this;
    }

    /**
     * Add where year clause to the query.
     */
    public function whereYear(string $column, string $operator, mixed $value = null): static
    {
        $this->query->whereYear($column, $operator, $value);
        
        return $this;
    }

    /**
     * Add where month clause to the query.
     */
    public function whereMonth(string $column, string $operator, mixed $value = null): static
    {
        $this->query->whereMonth($column, $operator, $value);
        
        return $this;
    }

    /**
     * Add where day clause to the query.
     */
    public function whereDay(string $column, string $operator, mixed $value = null): static
    {
        $this->query->whereDay($column, $operator, $value);
        
        return $this;
    }

    /**
     * Add order by raw clause to the query.
     */
    public function orderByRaw(string $sql, array $bindings = []): static
    {
        $this->query->orderByRaw($sql, $bindings);
        
        return $this;
    }

    /**
     * Add where exists clause to the query.
     */
    public function whereExists(callable $callback): static
    {
        $this->query->whereExists($callback);
        
        return $this;
    }

    /**
     * Add where not exists clause to the query.
     */
    public function whereNotExists(callable $callback): static
    {
        $this->query->whereNotExists($callback);
        
        return $this;
    }

    /**
     * Add union clause to the query.
     */
    public function union(mixed $query): static
    {
        $this->query->union($query);
        
        return $this;
    }

    /**
     * Apply filters to the query.
     */
    public function filter(array $filters): static
    {
        foreach ($filters as $field => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                $this->applyFilter($field, $value);
            }
        }
        
        return $this;
    }

    /**
     * Apply a single filter condition.
     */
    public function applyFilter(string $field, mixed $value, string $operator = '='): static
    {
        if (is_array($value)) {
            if (in_array($operator, ['=', 'in'])) {
                $this->query->whereIn($field, $value);
            } elseif (in_array($operator, ['!=', 'not in', 'not_in'])) {
                $this->query->whereNotIn($field, $value);
            } elseif ($operator === 'between') {
                $this->query->whereBetween($field, $value);
            } elseif ($operator === 'not_between') {
                $this->query->whereNotBetween($field, $value);
            }
        } elseif ($operator === 'like') {
            $this->query->where($field, 'LIKE', "%{$value}%");
        } elseif ($operator === 'starts_with') {
            $this->query->where($field, 'LIKE', "{$value}%");
        } elseif ($operator === 'ends_with') {
            $this->query->where($field, 'LIKE', "%{$value}");
        } elseif ($operator === 'null') {
            $this->query->whereNull($field);
        } elseif ($operator === 'not_null') {
            $this->query->whereNotNull($field);
        } else {
            $this->query->where($field, $operator, $value);
        }
        
        return $this;
    }

    /**
     * Apply multiple filter conditions with AND logic.
     */
    public function applyFilters(array $filters, string $defaultOperator = '='): static
    {
        foreach ($filters as $field => $config) {
            if (is_array($config) && isset($config['value'])) {
                $value = $config['value'];
                $operator = $config['operator'] ?? $defaultOperator;
            } else {
                $value = $config;
                $operator = $defaultOperator;
            }

            if ($value !== null && $value !== '' && $value !== []) {
                $this->applyFilter($field, $value, $operator);
            }
        }
        
        return $this;
    }

    /**
     * Apply search across specified columns.
     */
    public function search(string $term, array $columns = []): static
    {
        if (empty($term)) {
            return $this;
        }

        if (empty($columns)) {
            // Try to get searchable columns from model if available
            $searchableColumns = method_exists($this->model, 'getSearchableColumns') 
                ? $this->model->getSearchableColumns() 
                : ['name', 'title', 'description'];
        } else {
            $searchableColumns = $columns;
        }

        $this->query->where(function ($query) use ($term, $searchableColumns) {
            foreach ($searchableColumns as $column) {
                $query->orWhere($column, 'LIKE', "%{$term}%");
            }
        });
        
        return $this;
    }

    /**
     * Apply date range filter.
     */
    public function dateRange(string $column, ?string $from = null, ?string $to = null): static
    {
        if ($from) {
            $this->query->whereDate($column, '>=', $from);
        }
        
        if ($to) {
            $this->query->whereDate($column, '<=', $to);
        }
        
        return $this;
    }

    /**
     * Apply scope filter.
     */
    public function scope(string $scope, ...$parameters): static
    {
        $methodName = 'scope' . ucfirst($scope);
        
        if (method_exists($this->model, $methodName)) {
            $this->query = $this->model->{$methodName}($this->query, ...$parameters);
        }
        
        return $this;
    }

    /**
     * Apply conditional where clause.
     */
    public function when(mixed $condition, callable $callback, ?callable $default = null): static
    {
        if ($condition) {
            $callback($this);
        } elseif ($default) {
            $default($this);
        }
        
        return $this;
    }

    /**
     * Apply unless conditional clause.
     */
    public function unless(mixed $condition, callable $callback, ?callable $default = null): static
    {
        if (!$condition) {
            $callback($this);
        } elseif ($default) {
            $default($this);
        }
        
        return $this;
    }

    /**
     * Apply filters from request parameters.
     */
    public function filterFromRequest(array $requestData, array $allowedFilters = []): static
    {
        $allowedFilters = empty($allowedFilters) ? $this->getDefaultFilterableColumns() : $allowedFilters;
        
        foreach ($allowedFilters as $filter) {
            if (is_array($filter)) {
                $field = $filter['field'];
                $operator = $filter['operator'] ?? '=';
                $requestKey = $filter['request_key'] ?? $field;
            } else {
                $field = $filter;
                $operator = '=';
                $requestKey = $filter;
            }

            if (isset($requestData[$requestKey])) {
                $value = $requestData[$requestKey];
                $this->applyFilter($field, $value, $operator);
            }
        }
        
        return $this;
    }

    /**
     * Apply sorting from request parameters.
     */
    public function sortFromRequest(array $requestData, array $allowedSorts = [], string $defaultSort = 'id', string $defaultDirection = 'asc'): static
    {
        $sortField = $requestData['sort'] ?? $defaultSort;
        $sortDirection = $requestData['direction'] ?? $requestData['sort_direction'] ?? $defaultDirection;
        
        // Validate sort direction
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : $defaultDirection;
        
        // Check if sort field is allowed
        if (!empty($allowedSorts) && !in_array($sortField, $allowedSorts)) {
            $sortField = $defaultSort;
        }
        
        $this->query->orderBy($sortField, $sortDirection);
        
        return $this;
    }

    /**
     * Get default filterable columns.
     */
    protected function getDefaultFilterableColumns(): array
    {
        if (method_exists($this->model, 'getFilterableColumns')) {
            return $this->model->getFilterableColumns();
        }
        
        return [];
    }

    /**
     * Reset the query builder.
     */
    public function resetQuery(): static
    {
        $this->query = $this->model->newQuery();
        
        return $this;
    }
}
