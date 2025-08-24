# Query String Filter Parser Documentation

This guide explains how to use the Query String Parser feature to handle complex filter expressions in URLs.

## Table of Contents

1. [Overview](#overview)
2. [Filter Syntax](#filter-syntax)
3. [Available Operators](#available-operators)
4. [Usage Examples](#usage-examples)
5. [Security Considerations](#security-considerations)
6. [Frontend Integration](#frontend-integration)
7. [API Examples](#api-examples)
8. [Best Practices](#best-practices)

## Overview

The Query String Parser allows you to parse complex filter expressions from URL query strings and apply them to your repository queries. This is particularly useful for APIs and search interfaces where users need advanced filtering capabilities.

**Example filter string:**
```
category:IN(Apartment,Bungalow);leads:IN(1,3);manager_of:IN(1449282);status:IN(Published);bua:BETWEEN(5000,3000);rental_period:IN(monthly);sbeds:IN(1,2,3);portals:IN(bayut)
```

## Filter Syntax

### Basic Format
```
field:OPERATOR(value1,value2,...)
```

### Multiple Filters
Separate multiple filters with semicolons:
```
field1:OPERATOR(value);field2:OPERATOR(value1,value2);field3:OPERATOR(value)
```

### Value Types
- **Strings**: `name:EQ(John)` or `name:EQ("John Doe")` (quotes for values with commas/spaces)
- **Numbers**: `price:GT(1000)` or `age:BETWEEN(25,35)`
- **Booleans**: `active:EQ(true)` or `verified:EQ(false)`
- **Null**: `deleted_at:IS_NULL()` or `email:IS_NOT_NULL()`

## Available Operators

### Comparison Operators
| Operator | Description | Example | SQL Equivalent |
|----------|-------------|---------|----------------|
| `EQ` | Equals | `status:EQ(active)` | `WHERE status = 'active'` |
| `NEQ` | Not equals | `status:NEQ(inactive)` | `WHERE status != 'inactive'` |
| `GT` | Greater than | `price:GT(1000)` | `WHERE price > 1000` |
| `GTE` | Greater than or equal | `price:GTE(1000)` | `WHERE price >= 1000` |
| `LT` | Less than | `price:LT(5000)` | `WHERE price < 5000` |
| `LTE` | Less than or equal | `price:LTE(5000)` | `WHERE price <= 5000` |

### Array Operators
| Operator | Description | Example | SQL Equivalent |
|----------|-------------|---------|----------------|
| `IN` | Value in list | `category:IN(A,B,C)` | `WHERE category IN ('A','B','C')` |
| `NOT_IN` | Value not in list | `status:NOT_IN(draft,archived)` | `WHERE status NOT IN ('draft','archived')` |

### Range Operators
| Operator | Description | Example | SQL Equivalent |
|----------|-------------|---------|----------------|
| `BETWEEN` | Between two values | `price:BETWEEN(1000,5000)` | `WHERE price BETWEEN 1000 AND 5000` |
| `NOT_BETWEEN` | Not between values | `price:NOT_BETWEEN(1000,5000)` | `WHERE price NOT BETWEEN 1000 AND 5000` |

### String Operators
| Operator | Description | Example | SQL Equivalent |
|----------|-------------|---------|----------------|
| `LIKE` | Contains substring | `name:LIKE(john)` | `WHERE name LIKE '%john%'` |
| `NOT_LIKE` | Doesn't contain | `name:NOT_LIKE(test)` | `WHERE name NOT LIKE '%test%'` |
| `STARTS_WITH` | Starts with | `name:STARTS_WITH(Mr)` | `WHERE name LIKE 'Mr%'` |
| `ENDS_WITH` | Ends with | `email:ENDS_WITH(.com)` | `WHERE email LIKE '%.com'` |

### Null Operators
| Operator | Description | Example | SQL Equivalent |
|----------|-------------|---------|----------------|
| `IS_NULL` | Is null | `deleted_at:IS_NULL()` | `WHERE deleted_at IS NULL` |
| `IS_NOT_NULL` | Is not null | `email_verified_at:IS_NOT_NULL()` | `WHERE email_verified_at IS NOT NULL` |

### Date Operators
| Operator | Description | Example | SQL Equivalent |
|----------|-------------|---------|----------------|
| `DATE_EQ` | Date equals | `created_at:DATE_EQ(2024-01-01)` | `WHERE DATE(created_at) = '2024-01-01'` |
| `DATE_GT` | Date after | `created_at:DATE_GT(2024-01-01)` | `WHERE DATE(created_at) > '2024-01-01'` |
| `DATE_GTE` | Date from | `created_at:DATE_GTE(2024-01-01)` | `WHERE DATE(created_at) >= '2024-01-01'` |
| `DATE_LT` | Date before | `created_at:DATE_LT(2024-12-31)` | `WHERE DATE(created_at) < '2024-12-31'` |
| `DATE_LTE` | Date to | `created_at:DATE_LTE(2024-12-31)` | `WHERE DATE(created_at) <= '2024-12-31'` |
| `DATE_BETWEEN` | Date range | `created_at:DATE_BETWEEN(2024-01-01,2024-12-31)` | `WHERE created_at BETWEEN '2024-01-01' AND '2024-12-31'` |
| `YEAR` | Year equals | `created_at:YEAR(2024)` | `WHERE YEAR(created_at) = 2024` |
| `MONTH` | Month equals | `created_at:MONTH(12)` | `WHERE MONTH(created_at) = 12` |
| `DAY` | Day equals | `created_at:DAY(25)` | `WHERE DAY(created_at) = 25` |

### JSON Operators (for JSON columns)
| Operator | Description | Example | SQL Equivalent |
|----------|-------------|---------|----------------|
| `JSON_CONTAINS` | JSON contains value | `tags:JSON_CONTAINS(important)` | `WHERE JSON_CONTAINS(tags, '"important"')` |
| `JSON_LENGTH` | JSON array length | `tags:JSON_LENGTH(3)` | `WHERE JSON_LENGTH(tags) = 3` |

### Advanced Operators
| Operator | Description | Example | SQL Equivalent |
|----------|-------------|---------|----------------|
| `REGEX` | Regular expression | `code:REGEX(^[A-Z]{3})` | `WHERE code REGEXP '^[A-Z]{3}'` |

## Usage Examples

### Repository Usage

```php
use App\Repositories\PropertyRepository;

class PropertyController extends Controller
{
    public function search(Request $request, PropertyRepository $repository)
    {
        $filterString = $request->get('filters');
        
        // Define allowed fields for security
        $allowedFields = ['category', 'price', 'status', 'bedrooms', 'location'];
        
        // Parse and apply filters
        $properties = $repository
            ->parseQueryFilters($filterString, $allowedFields)
            ->with(['images', 'location'])
            ->cursorPaginate(20);
            
        return response()->json([
            'data' => $properties->items(),
            'pagination' => [
                'next_cursor' => $properties->nextCursor()?->encode(),
                'has_more' => $properties->hasMorePages(),
            ]
        ]);
    }
}
```

### Real Estate Example

```php
// URL: /api/properties?filters=category:IN(Apartment,Villa);price:BETWEEN(100000,500000);bedrooms:IN(2,3,4);status:EQ(Published);location:LIKE(Dubai)

$filterString = "category:IN(Apartment,Villa);price:BETWEEN(100000,500000);bedrooms:IN(2,3,4);status:EQ(Published);location:LIKE(Dubai)";

$properties = $propertyRepository
    ->parseQueryFilters($filterString, [
        'category', 'price', 'bedrooms', 'status', 'location'
    ])
    ->orderBy('price', 'asc')
    ->paginate(20);
```

### E-commerce Example

```php
// URL: /api/products?filters=category:IN(Electronics,Gaming);price:BETWEEN(50,500);brand:IN(Apple,Samsung);in_stock:EQ(true);rating:GTE(4)

$filterString = "category:IN(Electronics,Gaming);price:BETWEEN(50,500);brand:IN(Apple,Samsung);in_stock:EQ(true);rating:GTE(4)";

$products = $productRepository
    ->parseQueryFilters($filterString, [
        'category', 'price', 'brand', 'in_stock', 'rating'
    ])
    ->with(['images', 'reviews'])
    ->optimizedPaginate(24);
```

### User Management Example

```php
// URL: /admin/users?filters=role:IN(admin,moderator);status:EQ(active);created_at:DATE_BETWEEN(2024-01-01,2024-12-31);last_login:IS_NOT_NULL()

$filterString = "role:IN(admin,moderator);status:EQ(active);created_at:DATE_BETWEEN(2024-01-01,2024-12-31);last_login:IS_NOT_NULL()";

$users = $userRepository
    ->parseQueryFilters($filterString, [
        'role', 'status', 'created_at', 'last_login', 'email_verified_at'
    ])
    ->with(['profile', 'permissions'])
    ->fastPaginate(25);
```

## Security Considerations

### 1. Field Whitelisting
Always specify allowed fields to prevent unauthorized access:

```php
// Good - Secure
$allowedFields = ['name', 'email', 'status', 'created_at'];
$repository->parseQueryFilters($filterString, $allowedFields);

// Bad - Insecure (allows any field)
$repository->parseQueryFilters($filterString);
```

### 2. Input Validation
Validate filter strings before processing:

```php
use Litepie\Repository\Traits\QueryStringParser;

$validation = QueryStringParser::validateFilterString($filterString);
if (!$validation['valid']) {
    return response()->json([
        'error' => 'Invalid filter format',
        'details' => $validation['errors']
    ], 400);
}
```

### 3. Rate Limiting
Implement rate limiting for complex filter queries:

```php
// In your controller
public function search(Request $request)
{
    // Limit complex queries
    if (strlen($request->get('filters', '')) > 500) {
        return response()->json(['error' => 'Filter too complex'], 400);
    }
    
    // Rate limit
    if (RateLimiter::tooManyAttempts('search:' . $request->ip(), 60)) {
        return response()->json(['error' => 'Too many requests'], 429);
    }
    
    // Process filters...
}
```

## Frontend Integration

### JavaScript Filter Builder

```javascript
class FilterBuilder {
    constructor() {
        this.filters = {};
    }
    
    addFilter(field, operator, values) {
        this.filters[field] = {
            operator: operator,
            values: Array.isArray(values) ? values : [values]
        };
        return this;
    }
    
    removeFilter(field) {
        delete this.filters[field];
        return this;
    }
    
    build() {
        const conditions = [];
        
        for (const [field, condition] of Object.entries(this.filters)) {
            const valueString = condition.values
                .map(v => typeof v === 'string' && v.includes(',') ? `"${v}"` : v)
                .join(',');
            conditions.push(`${field}:${condition.operator}(${valueString})`);
        }
        
        return conditions.join(';');
    }
    
    static parse(filterString) {
        const builder = new FilterBuilder();
        
        if (!filterString) return builder;
        
        const conditions = filterString.split(';');
        conditions.forEach(condition => {
            const match = condition.match(/^([^:]+):([^(]+)\(([^)]*)\)$/);
            if (match) {
                const [, field, operator, valueString] = match;
                const values = valueString.split(',').map(v => {
                    v = v.trim();
                    if (v.startsWith('"') && v.endsWith('"')) {
                        return v.slice(1, -1);
                    }
                    return isNaN(v) ? v : Number(v);
                });
                builder.addFilter(field, operator, values);
            }
        });
        
        return builder;
    }
}

// Usage
const filters = new FilterBuilder()
    .addFilter('category', 'IN', ['Apartment', 'Villa'])
    .addFilter('price', 'BETWEEN', [100000, 500000])
    .addFilter('status', 'EQ', 'Published');

const filterString = filters.build();
// Output: "category:IN(Apartment,Villa);price:BETWEEN(100000,500000);status:EQ(Published)"

// Use in API call
fetch(`/api/properties?filters=${encodeURIComponent(filterString)}`)
    .then(response => response.json())
    .then(data => console.log(data));
```

### Vue.js Filter Component

```vue
<template>
  <div class="filter-builder">
    <div v-for="(filter, field) in filters" :key="field" class="filter-item">
      <select v-model="filter.operator">
        <option v-for="op in getOperatorsForField(field)" :value="op">
          {{ op }}
        </option>
      </select>
      
      <input 
        v-if="filter.operator === 'BETWEEN'"
        v-model="filter.values[0]"
        placeholder="Min"
        type="number"
      >
      <input 
        v-if="filter.operator === 'BETWEEN'"
        v-model="filter.values[1]"
        placeholder="Max"
        type="number"
      >
      
      <select 
        v-else-if="isMultiSelect(filter.operator)"
        v-model="filter.values"
        multiple
      >
        <option v-for="option in getOptionsForField(field)" :value="option">
          {{ option }}
        </option>
      </select>
      
      <input 
        v-else
        v-model="filter.values[0]"
        :type="getInputType(field)"
      >
      
      <button @click="removeFilter(field)">Remove</button>
    </div>
    
    <button @click="addFilter">Add Filter</button>
    <button @click="applyFilters">Apply</button>
  </div>
</template>

<script>
export default {
  data() {
    return {
      filters: {},
      availableFields: {
        category: { type: 'select', options: ['Apartment', 'Villa', 'Townhouse'] },
        price: { type: 'number' },
        bedrooms: { type: 'select', options: ['1', '2', '3', '4', '5+'] },
        status: { type: 'select', options: ['Published', 'Draft'] },
      }
    };
  },
  
  methods: {
    addFilter() {
      // Show field selector modal
    },
    
    removeFilter(field) {
      delete this.filters[field];
    },
    
    applyFilters() {
      const filterString = this.buildFilterString();
      this.$emit('filtersChanged', filterString);
    },
    
    buildFilterString() {
      const conditions = [];
      
      for (const [field, filter] of Object.entries(this.filters)) {
        const valueString = filter.values.join(',');
        conditions.push(`${field}:${filter.operator}(${valueString})`);
      }
      
      return conditions.join(';');
    },
    
    getOperatorsForField(field) {
      const fieldConfig = this.availableFields[field];
      if (fieldConfig.type === 'select') {
        return ['IN', 'NOT_IN', 'EQ'];
      } else if (fieldConfig.type === 'number') {
        return ['EQ', 'GT', 'GTE', 'LT', 'LTE', 'BETWEEN'];
      }
      return ['EQ', 'LIKE', 'STARTS_WITH', 'ENDS_WITH'];
    },
    
    isMultiSelect(operator) {
      return ['IN', 'NOT_IN'].includes(operator);
    },
    
    getInputType(field) {
      return this.availableFields[field]?.type || 'text';
    },
    
    getOptionsForField(field) {
      return this.availableFields[field]?.options || [];
    }
  }
};
</script>
```

## API Examples

### REST API Endpoints

```php
// routes/api.php
Route::get('/properties', [PropertyController::class, 'index']);
Route::get('/properties/search', [PropertyController::class, 'search']);
Route::get('/properties/filter-options', [PropertyController::class, 'filterOptions']);
Route::post('/properties/build-filter-url', [PropertyController::class, 'buildFilterUrl']);
Route::post('/properties/validate-filters', [PropertyController::class, 'validateFilters']);
```

### Example API Calls

```bash
# Basic search with filters
curl "https://api.example.com/properties?filters=category:IN(Apartment,Villa);price:BETWEEN(100000,500000)"

# Complex real estate search
curl "https://api.example.com/properties?filters=category:IN(Apartment,Bungalow);leads:IN(1,3);manager_of:IN(1449282);status:IN(Published);bua:BETWEEN(5000,3000);rental_period:IN(monthly);sbeds:IN(1,2,3);portals:IN(bayut)"

# E-commerce product search
curl "https://api.example.com/products?filters=category:IN(Electronics);price:LTE(1000);brand:IN(Apple,Samsung);rating:GTE(4);in_stock:EQ(true)"

# User management search
curl "https://api.example.com/users?filters=role:IN(admin,moderator);status:EQ(active);created_at:DATE_BETWEEN(2024-01-01,2024-12-31)"
```

## Best Practices

### 1. Performance Optimization

```php
// Use appropriate indexes
// For filters like: category:IN(A,B,C);status:EQ(active);created_at:DATE_BETWEEN(...)
// Create indexes:
// - category, status, created_at (composite)
// - category (single)
// - status (single)

// Use cursor pagination for large datasets
$properties = $repository
    ->parseQueryFilters($filterString, $allowedFields)
    ->cursorPaginate(20);

// Cache expensive filter combinations
$cacheKey = 'search:' . md5($filterString);
$results = Cache::remember($cacheKey, 300, function() use ($repository, $filterString) {
    return $repository->parseQueryFilters($filterString)->get();
});
```

### 2. Error Handling

```php
try {
    $validation = QueryStringParser::validateFilterString($filterString);
    
    if (!$validation['valid']) {
        return response()->json([
            'error' => 'Invalid filter format',
            'details' => $validation['errors'],
            'help' => 'Use format: field:OPERATOR(value1,value2)'
        ], 400);
    }
    
    $results = $repository->parseQueryFilters($filterString, $allowedFields);
    
} catch (QueryException $e) {
    Log::error('Database error in filter query', [
        'filter_string' => $filterString,
        'error' => $e->getMessage()
    ]);
    
    return response()->json([
        'error' => 'Search temporarily unavailable'
    ], 500);
}
```

### 3. Documentation and Help

```php
// Provide filter help endpoint
public function filterHelp(): JsonResponse
{
    return response()->json([
        'syntax' => 'field:OPERATOR(value1,value2)',
        'operators' => QueryStringParser::getAvailableOperators(),
        'examples' => [
            'equals' => 'status:EQ(active)',
            'in_list' => 'category:IN(Apartment,Villa,Townhouse)',
            'range' => 'price:BETWEEN(100000,500000)',
            'date_range' => 'created_at:DATE_BETWEEN(2024-01-01,2024-12-31)',
            'text_search' => 'title:LIKE(luxury)',
            'null_check' => 'deleted_at:IS_NULL()',
        ],
        'tips' => [
            'Use quotes for values containing commas: name:EQ("Smith, John")',
            'Separate multiple filters with semicolons',
            'Use BETWEEN for numeric and date ranges',
            'Use IN for multiple values',
        ]
    ]);
}
```

### 4. Monitoring and Analytics

```php
// Log popular filter combinations for optimization
Log::info('Search performed', [
    'filter_string' => $filterString,
    'field_count' => substr_count($filterString, ':'),
    'user_id' => auth()->id(),
    'ip' => request()->ip(),
    'execution_time' => $executionTime,
]);

// Track slow queries
if ($executionTime > 1.0) {
    Log::warning('Slow filter query', [
        'filter_string' => $filterString,
        'execution_time' => $executionTime,
    ]);
}
```

This comprehensive query string parser provides a powerful and flexible way to handle complex filtering requirements while maintaining security and performance.
