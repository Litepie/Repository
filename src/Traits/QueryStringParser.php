<?php

namespace Litepie\Repository\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Query String Filter Parser Trait
 * 
 * Parses complex filter expressions from query strings and applies them to the repository.
 * Supports operators like IN, BETWEEN, LIKE, GT, LT, EQ, etc.
 */
trait QueryStringParser
{
    /**
     * Parse and apply filters from query string format.
     * 
     * Example: "category:IN(Apartment,Bungalow);price:BETWEEN(1000,5000);status:EQ(active)"
     *
     * @param string $filterString The filter string to parse
     * @param array $allowedFields Optional array of allowed field names for security
     * @return $this
     */
    public function parseQueryFilters(string $filterString, array $allowedFields = []): static
    {
        if (empty($filterString)) {
            return $this;
        }

        $filters = $this->parseFilterString($filterString);
        
        foreach ($filters as $field => $condition) {
            // Security check - only allow specified fields if provided
            if (!empty($allowedFields) && !in_array($field, $allowedFields)) {
                continue;
            }
            
            $this->applyQueryStringFilterCondition($field, $condition);
        }
        
        return $this;
    }

    /**
     * Parse the filter string into structured conditions.
     *
     * @param string $filterString
     * @return array
     */
    protected function parseFilterString(string $filterString): array
    {
        $filters = [];
        
        // Split by semicolon to get individual filter conditions
        $conditions = explode(';', $filterString);
        
        foreach ($conditions as $condition) {
            $condition = trim($condition);
            if (empty($condition)) {
                continue;
            }
            
            // Parse each condition: field:OPERATOR(value1,value2)
            if (preg_match('/^([^:]+):([^(]+)\(([^)]*)\)$/', $condition, $matches)) {
                $field = trim($matches[1]);
                $operator = strtoupper(trim($matches[2]));
                $values = $this->parseValues($matches[3]);
                
                $filters[$field] = [
                    'operator' => $operator,
                    'values' => $values
                ];
            }
        }
        
        return $filters;
    }

    /**
     * Parse values from the condition string.
     *
     * @param string $valueString
     * @return array
     */
    protected function parseValues(string $valueString): array
    {
        if (empty($valueString)) {
            return [];
        }
        
        // Split by comma and clean up values
        $values = array_map(function($value) {
            $value = trim($value);
            
            // Remove quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            
            // Convert to appropriate type
            if (is_numeric($value)) {
                return is_float($value) ? (float)$value : (int)$value;
            }
            
            if (strtolower($value) === 'true') {
                return true;
            }
            
            if (strtolower($value) === 'false') {
                return false;
            }
            
            if (strtolower($value) === 'null') {
                return null;
            }
            
            return $value;
        }, explode(',', $valueString));
        
        return $values;
    }

    /**
     * Apply a filter condition to the query builder.
     *
     * @param string $field
     * @param array $condition
     * @return void
     */
    protected function applyQueryStringFilterCondition(string $field, array $condition): void
    {
        $operator = $condition['operator'];
        $values = $condition['values'];
        
        switch ($operator) {
            case 'IN':
                if (!empty($values)) {
                    $this->query->whereIn($field, $values);
                }
                break;
                
            case 'NOT_IN':
            case 'NOTIN':
                if (!empty($values)) {
                    $this->query->whereNotIn($field, $values);
                }
                break;
                
            case 'BETWEEN':
                if (count($values) >= 2) {
                    $this->query->whereBetween($field, [$values[0], $values[1]]);
                }
                break;
                
            case 'NOT_BETWEEN':
            case 'NOTBETWEEN':
                if (count($values) >= 2) {
                    $this->query->whereNotBetween($field, [$values[0], $values[1]]);
                }
                break;
                
            case 'EQ':
            case 'EQUALS':
                if (!empty($values)) {
                    $this->query->where($field, '=', $values[0]);
                }
                break;
                
            case 'NEQ':
            case 'NOT_EQUALS':
            case 'NOTEQUALS':
                if (!empty($values)) {
                    $this->query->where($field, '!=', $values[0]);
                }
                break;
                
            case 'GT':
            case 'GREATER_THAN':
                if (!empty($values)) {
                    $this->query->where($field, '>', $values[0]);
                }
                break;
                
            case 'GTE':
            case 'GREATER_THAN_EQUALS':
                if (!empty($values)) {
                    $this->query->where($field, '>=', $values[0]);
                }
                break;
                
            case 'LT':
            case 'LESS_THAN':
                if (!empty($values)) {
                    $this->query->where($field, '<', $values[0]);
                }
                break;
                
            case 'LTE':
            case 'LESS_THAN_EQUALS':
                if (!empty($values)) {
                    $this->query->where($field, '<=', $values[0]);
                }
                break;
                
            case 'LIKE':
                if (!empty($values)) {
                    $this->query->where($field, 'LIKE', '%' . $values[0] . '%');
                }
                break;
                
            case 'NOT_LIKE':
            case 'NOTLIKE':
                if (!empty($values)) {
                    $this->query->where($field, 'NOT LIKE', '%' . $values[0] . '%');
                }
                break;
                
            case 'STARTS_WITH':
            case 'STARTSWITH':
                if (!empty($values)) {
                    $this->query->where($field, 'LIKE', $values[0] . '%');
                }
                break;
                
            case 'ENDS_WITH':
            case 'ENDSWITH':
                if (!empty($values)) {
                    $this->query->where($field, 'LIKE', '%' . $values[0]);
                }
                break;
                
            case 'IS_NULL':
            case 'ISNULL':
                $this->query->whereNull($field);
                break;
                
            case 'IS_NOT_NULL':
            case 'ISNOTNULL':
            case 'NOT_NULL':
            case 'NOTNULL':
                $this->query->whereNotNull($field);
                break;
                
            case 'DATE_EQ':
            case 'DATE_EQUALS':
                if (!empty($values)) {
                    $this->query->whereDate($field, '=', $values[0]);
                }
                break;
                
            case 'DATE_GT':
            case 'DATE_AFTER':
                if (!empty($values)) {
                    $this->query->whereDate($field, '>', $values[0]);
                }
                break;
                
            case 'DATE_GTE':
            case 'DATE_FROM':
                if (!empty($values)) {
                    $this->query->whereDate($field, '>=', $values[0]);
                }
                break;
                
            case 'DATE_LT':
            case 'DATE_BEFORE':
                if (!empty($values)) {
                    $this->query->whereDate($field, '<', $values[0]);
                }
                break;
                
            case 'DATE_LTE':
            case 'DATE_TO':
                if (!empty($values)) {
                    $this->query->whereDate($field, '<=', $values[0]);
                }
                break;
                
            case 'DATE_BETWEEN':
                if (count($values) >= 2) {
                    $this->query->whereBetween($field, [$values[0], $values[1]]);
                }
                break;
                
            case 'YEAR':
                if (!empty($values)) {
                    $this->query->whereYear($field, $values[0]);
                }
                break;
                
            case 'MONTH':
                if (!empty($values)) {
                    $this->query->whereMonth($field, $values[0]);
                }
                break;
                
            case 'DAY':
                if (!empty($values)) {
                    $this->query->whereDay($field, $values[0]);
                }
                break;
                
            case 'JSON_CONTAINS':
                if (!empty($values)) {
                    $this->query->whereJsonContains($field, $values[0]);
                }
                break;
                
            case 'JSON_LENGTH':
                if (!empty($values)) {
                    $this->query->whereJsonLength($field, $values[0]);
                }
                break;
                
            case 'REGEX':
            case 'REGEXP':
                if (!empty($values)) {
                    $this->query->where($field, 'REGEXP', $values[0]);
                }
                break;
                
            default:
                // For unknown operators, try to apply as simple equality
                if (!empty($values)) {
                    $this->query->where($field, '=', $values[0]);
                }
                break;
        }
    }

    /**
     * Parse and apply filters from request query parameters.
     * 
     * Supports both simple filters and complex filter strings.
     *
     * @param array $requestData
     * @param array $allowedFields
     * @return $this
     */
    public function parseRequestFilters(array $requestData, array $allowedFields = []): static
    {
        // Check for filter string parameter
        if (isset($requestData['filters']) && is_string($requestData['filters'])) {
            $this->parseQueryFilters($requestData['filters'], $allowedFields);
        }
        
        // Check for filter parameter
        if (isset($requestData['filter']) && is_string($requestData['filter'])) {
            $this->parseQueryFilters($requestData['filter'], $allowedFields);
        }
        
        // Check for individual filter parameters
        foreach ($requestData as $key => $value) {
            if (str_starts_with($key, 'filter_') && !empty($value)) {
                $field = substr($key, 7); // Remove 'filter_' prefix
                
                if (!empty($allowedFields) && !in_array($field, $allowedFields)) {
                    continue;
                }
                
                // Simple filter - assume equality
                if (is_array($value)) {
                    $this->query->whereIn($field, $value);
                } else {
                    $this->query->where($field, '=', $value);
                }
            }
        }
        
        return $this;
    }

    /**
     * Build a filter string from an array of conditions.
     * 
     * Useful for generating URLs with filters.
     *
     * @param array $filters
     * @return string
     */
    public static function buildFilterString(array $filters): string
    {
        $conditions = [];
        
        foreach ($filters as $field => $condition) {
            if (is_array($condition) && isset($condition['operator'], $condition['values'])) {
                $operator = $condition['operator'];
                $values = is_array($condition['values']) ? $condition['values'] : [$condition['values']];
                
                // Escape and quote string values
                $escapedValues = array_map(function($value) {
                    if (is_string($value) && (strpos($value, ',') !== false || strpos($value, ')') !== false)) {
                        return '"' . addslashes($value) . '"';
                    }
                    return $value;
                }, $values);
                
                $valueString = implode(',', $escapedValues);
                $conditions[] = "{$field}:{$operator}({$valueString})";
            } elseif (is_array($condition)) {
                // Simple IN condition
                $valueString = implode(',', $condition);
                $conditions[] = "{$field}:IN({$valueString})";
            } else {
                // Simple equality condition
                $conditions[] = "{$field}:EQ({$condition})";
            }
        }
        
        return implode(';', $conditions);
    }

    /**
     * Get available filter operators.
     *
     * @return array
     */
    public static function getAvailableOperators(): array
    {
        return [
            'IN' => 'Field value is in the provided list',
            'NOT_IN' => 'Field value is not in the provided list',
            'BETWEEN' => 'Field value is between two values',
            'NOT_BETWEEN' => 'Field value is not between two values',
            'EQ' => 'Field value equals the provided value',
            'NEQ' => 'Field value does not equal the provided value',
            'GT' => 'Field value is greater than the provided value',
            'GTE' => 'Field value is greater than or equal to the provided value',
            'LT' => 'Field value is less than the provided value',
            'LTE' => 'Field value is less than or equal to the provided value',
            'LIKE' => 'Field value contains the provided string',
            'NOT_LIKE' => 'Field value does not contain the provided string',
            'STARTS_WITH' => 'Field value starts with the provided string',
            'ENDS_WITH' => 'Field value ends with the provided string',
            'IS_NULL' => 'Field value is null',
            'IS_NOT_NULL' => 'Field value is not null',
            'DATE_EQ' => 'Date field equals the provided date',
            'DATE_GT' => 'Date field is after the provided date',
            'DATE_GTE' => 'Date field is on or after the provided date',
            'DATE_LT' => 'Date field is before the provided date',
            'DATE_LTE' => 'Date field is on or before the provided date',
            'DATE_BETWEEN' => 'Date field is between two dates',
            'YEAR' => 'Year of date field equals the provided year',
            'MONTH' => 'Month of date field equals the provided month',
            'DAY' => 'Day of date field equals the provided day',
            'JSON_CONTAINS' => 'JSON field contains the provided value',
            'JSON_LENGTH' => 'JSON field has the specified length',
            'REGEX' => 'Field value matches the provided regular expression',
        ];
    }

    /**
     * Validate a filter string format.
     *
     * @param string $filterString
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public static function validateFilterString(string $filterString): array
    {
        $errors = [];
        $valid = true;
        
        if (empty($filterString)) {
            return ['valid' => true, 'errors' => []];
        }
        
        $conditions = explode(';', $filterString);
        $availableOperators = array_keys(static::getAvailableOperators());
        
        foreach ($conditions as $index => $condition) {
            $condition = trim($condition);
            if (empty($condition)) {
                continue;
            }
            
            if (!preg_match('/^([^:]+):([^(]+)\(([^)]*)\)$/', $condition, $matches)) {
                $errors[] = "Invalid condition format at position {$index}: '{$condition}'";
                $valid = false;
                continue;
            }
            
            $field = trim($matches[1]);
            $operator = strtoupper(trim($matches[2]));
            $values = trim($matches[3]);
            
            if (empty($field)) {
                $errors[] = "Empty field name in condition: '{$condition}'";
                $valid = false;
            }
            
            if (!in_array($operator, $availableOperators)) {
                $errors[] = "Unknown operator '{$operator}' in condition: '{$condition}'";
                $valid = false;
            }
            
            // Validate operator-specific requirements
            if (in_array($operator, ['BETWEEN', 'NOT_BETWEEN', 'DATE_BETWEEN'])) {
                $valueArray = explode(',', $values);
                if (count($valueArray) < 2) {
                    $errors[] = "Operator '{$operator}' requires at least 2 values in condition: '{$condition}'";
                    $valid = false;
                }
            }
            
            if (in_array($operator, ['IS_NULL', 'IS_NOT_NULL']) && !empty($values)) {
                $errors[] = "Operator '{$operator}' should not have values in condition: '{$condition}'";
                $valid = false;
            }
        }
        
        return ['valid' => $valid, 'errors' => $errors];
    }
}
