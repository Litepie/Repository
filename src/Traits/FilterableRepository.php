<?php

namespace Litepie\Repository\Traits;

use Illuminate\Database\Eloquent\Builder;

trait FilterableRepository
{
    /**
     * Advanced filter method with complex conditions.
     */
    public function advancedFilter(array $filters): static
    {
        foreach ($filters as $filterConfig) {
            $this->applyAdvancedFilter($filterConfig);
        }
        
        return $this;
    }

    /**
     * Apply a single advanced filter.
     */
    protected function applyAdvancedFilter(array $config): void
    {
        $field = $config['field'] ?? null;
        $value = $config['value'] ?? null;
        $operator = $config['operator'] ?? '=';
        $logic = $config['logic'] ?? 'and';
        $relation = $config['relation'] ?? null;

        if (!$field || ($value === null && !in_array($operator, ['null', 'not_null']))) {
            return;
        }

        $method = $logic === 'or' ? 'orWhere' : 'where';

        if ($relation) {
            $this->query->whereHas($relation, function (Builder $query) use ($field, $value, $operator) {
                $this->applyFilterCondition($query, $field, $value, $operator);
            });
        } else {
            $this->applyFilterCondition($this->query, $field, $value, $operator, $method);
        }
    }

    /**
     * Apply filter condition to query.
     */
    protected function applyFilterCondition(Builder $query, string $field, mixed $value, string $operator, string $method = 'where'): void
    {
        switch ($operator) {
            case 'like':
                $query->{$method}($field, 'LIKE', "%{$value}%");
                break;
            case 'starts_with':
                $query->{$method}($field, 'LIKE', "{$value}%");
                break;
            case 'ends_with':
                $query->{$method}($field, 'LIKE', "%{$value}");
                break;
            case 'in':
                $query->whereIn($field, is_array($value) ? $value : [$value]);
                break;
            case 'not_in':
                $query->whereNotIn($field, is_array($value) ? $value : [$value]);
                break;
            case 'between':
                if (is_array($value) && count($value) === 2) {
                    $query->whereBetween($field, $value);
                }
                break;
            case 'not_between':
                if (is_array($value) && count($value) === 2) {
                    $query->whereNotBetween($field, $value);
                }
                break;
            case 'null':
                $query->whereNull($field);
                break;
            case 'not_null':
                $query->whereNotNull($field);
                break;
            case 'date':
                $query->whereDate($field, $value);
                break;
            case 'date_range':
                if (is_array($value)) {
                    if (isset($value['from'])) {
                        $query->whereDate($field, '>=', $value['from']);
                    }
                    if (isset($value['to'])) {
                        $query->whereDate($field, '<=', $value['to']);
                    }
                }
                break;
            case 'year':
                $query->whereYear($field, $value);
                break;
            case 'month':
                $query->whereMonth($field, $value);
                break;
            case 'day':
                $query->whereDay($field, $value);
                break;
            case 'time':
                $query->whereTime($field, $value);
                break;
            default:
                $query->{$method}($field, $operator, $value);
        }
    }

    /**
     * Apply multiple filters with OR logic.
     */
    public function orFilter(array $filters): static
    {
        $this->query->where(function (Builder $query) use ($filters) {
            $first = true;
            foreach ($filters as $field => $value) {
                $method = $first ? 'where' : 'orWhere';
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->{$method}($field, $value);
                }
                $first = false;
            }
        });
        
        return $this;
    }

    /**
     * Apply filters with nested conditions.
     */
    public function nestedFilter(callable $callback): static
    {
        $this->query->where($callback);
        
        return $this;
    }

    /**
     * Apply filters to relationships.
     */
    public function filterByRelation(string $relation, array $filters): static
    {
        $this->query->whereHas($relation, function (Builder $query) use ($filters) {
            foreach ($filters as $field => $value) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
        });
        
        return $this;
    }

    /**
     * Apply filters with relationship counts.
     */
    public function filterByRelationCount(string $relation, string $operator = '>', int $count = 0): static
    {
        $this->query->has($relation, $operator, $count);
        
        return $this;
    }

    /**
     * Apply text search with ranking.
     */
    public function searchWithRanking(string $term, array $columns, array $weights = []): static
    {
        if (empty($term)) {
            return $this;
        }

        $selectRaw = [];
        $whereConditions = [];
        
        foreach ($columns as $index => $column) {
            $weight = $weights[$index] ?? 1;
            $selectRaw[] = "CASE WHEN {$column} LIKE '%{$term}%' THEN {$weight} ELSE 0 END";
            $whereConditions[] = "{$column} LIKE '%{$term}%'";
        }

        $this->query->selectRaw('*, (' . implode(' + ', $selectRaw) . ') as search_rank')
                   ->whereRaw('(' . implode(' OR ', $whereConditions) . ')')
                   ->orderBy('search_rank', 'desc');
        
        return $this;
    }

    /**
     * Apply geo-location filters.
     */
    public function nearLocation(float $latitude, float $longitude, float $radius, string $unit = 'km'): static
    {
        $multiplier = $unit === 'miles' ? 3959 : 6371;
        
        $this->query->selectRaw("
            *, (
                {$multiplier} * acos(
                    cos(radians(?)) * 
                    cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + 
                    sin(radians(?)) * 
                    sin(radians(latitude))
                )
            ) AS distance
        ", [$latitude, $longitude, $latitude])
        ->havingRaw('distance <= ?', [$radius])
        ->orderBy('distance');
        
        return $this;
    }

    /**
     * Apply dynamic filters based on configuration.
     */
    public function dynamicFilter(array $requestData, array $filterConfig): static
    {
        foreach ($filterConfig as $config) {
            $requestKey = $config['request_key'] ?? $config['field'];
            
            if (!isset($requestData[$requestKey])) {
                continue;
            }

            $value = $requestData[$requestKey];
            
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            // Apply transformation if specified
            if (isset($config['transform'])) {
                $value = $this->transformFilterValue($value, $config['transform']);
            }

            // Apply filter
            $this->applyAdvancedFilter([
                'field' => $config['field'],
                'value' => $value,
                'operator' => $config['operator'] ?? '=',
                'logic' => $config['logic'] ?? 'and',
                'relation' => $config['relation'] ?? null,
            ]);
        }
        
        return $this;
    }

    /**
     * Transform filter value based on type.
     */
    protected function transformFilterValue(mixed $value, string $transform): mixed
    {
        return match($transform) {
            'array' => is_array($value) ? $value : explode(',', $value),
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'date' => date('Y-m-d', strtotime($value)),
            'datetime' => date('Y-m-d H:i:s', strtotime($value)),
            'lowercase' => strtolower($value),
            'uppercase' => strtoupper($value),
            default => $value,
        };
    }

    /**
     * Apply pagination with filters.
     */
    public function filterAndPaginate(array $filters, int $perPage = 15, array $columns = ['*']): mixed
    {
        return $this->filter($filters)->paginate($perPage, $columns);
    }

    /**
     * Get filtered results with total count.
     */
    public function getFilteredWithCount(array $filters): array
    {
        $totalQuery = clone $this->query;
        $total = $totalQuery->count();
        
        $filtered = $this->filter($filters)->get();
        $filteredCount = $filtered->count();
        
        return [
            'data' => $filtered,
            'total' => $total,
            'filtered' => $filteredCount,
            'filters_applied' => !empty($filters),
        ];
    }
}
