# Filter Features Documentation

The Litepie Repository package provides powerful filtering capabilities that make it easy to query and filter data with complex conditions. This document covers all available filtering methods and their usage.

## Table of Contents

- [Basic Filtering](#basic-filtering)
- [Advanced Filtering](#advanced-filtering)
- [Search Functionality](#search-functionality)
- [Date Range Filtering](#date-range-filtering)
- [Conditional Filtering](#conditional-filtering)
- [Relationship Filtering](#relationship-filtering)
- [Request-Based Filtering](#request-based-filtering)
- [Dynamic Filtering](#dynamic-filtering)
- [Filter Configuration](#filter-configuration)

## Basic Filtering

### Simple Filter
```php
// Filter by single field
$users = $userRepository->filter(['status' => 'active'])->get();

// Filter by multiple fields
$users = $userRepository->filter([
    'status' => 'active',
    'role' => 'admin'
])->get();

// Filter with array values (IN operator)
$users = $userRepository->filter([
    'status' => ['active', 'pending']
])->get();
```

### Apply Single Filter
```php
// Basic equality
$users = $userRepository->applyFilter('status', 'active')->get();

// With different operators
$users = $userRepository->applyFilter('name', 'John', 'like')->get();
$users = $userRepository->applyFilter('age', 18, '>=')->get();
$users = $userRepository->applyFilter('email', 'gmail', 'ends_with')->get();
```

### Multiple Filters with Configuration
```php
$filters = [
    'name' => ['value' => 'John', 'operator' => 'like'],
    'age' => ['value' => 18, 'operator' => '>='],
    'status' => ['value' => ['active', 'verified'], 'operator' => 'in']
];

$users = $userRepository->applyFilters($filters)->get();
```

## Advanced Filtering

### Available Operators

| Operator | Description | Example |
|----------|-------------|---------|
| `=` | Equal (default) | `['status' => 'active']` |
| `!=` | Not equal | `['status' => 'inactive', 'operator' => '!=']` |
| `like` | Contains | `['name' => 'john', 'operator' => 'like']` |
| `starts_with` | Starts with | `['email' => 'admin', 'operator' => 'starts_with']` |
| `ends_with` | Ends with | `['email' => 'gmail.com', 'operator' => 'ends_with']` |
| `in` | In array | `['status' => ['active', 'pending']]` |
| `not_in` | Not in array | `['status' => ['blocked'], 'operator' => 'not_in']` |
| `between` | Between values | `['age' => [18, 65], 'operator' => 'between']` |
| `not_between` | Not between | `['age' => [13, 17], 'operator' => 'not_between']` |
| `null` | Is null | `['deleted_at' => null, 'operator' => 'null']` |
| `not_null` | Is not null | `['email_verified_at' => null, 'operator' => 'not_null']` |

### Advanced Filter Configuration
```php
$filters = [
    [
        'field' => 'name',
        'value' => 'John',
        'operator' => 'like',
        'logic' => 'and'
    ],
    [
        'field' => 'status',
        'value' => ['active', 'verified'],
        'operator' => 'in',
        'logic' => 'and'
    ],
    [
        'field' => 'created_at',
        'value' => ['2024-01-01', '2024-12-31'],
        'operator' => 'between',
        'logic' => 'and'
    ]
];

$users = $userRepository->advancedFilter($filters)->get();
```

### OR Filtering
```php
// Simple OR conditions
$users = $userRepository->orFilter([
    'name' => 'John Doe',
    'email' => 'admin@example.com'
])->get();

// Complex OR with nested conditions
$users = $userRepository->nestedFilter(function ($query) {
    $query->where('status', 'active')
          ->orWhere(function ($subQuery) {
              $subQuery->where('status', 'pending')
                       ->where('verified', true);
          });
})->get();
```

## Search Functionality

### Basic Search
```php
// Search across default searchable columns
$users = $userRepository->search('john')->get();

// Search across specific columns
$users = $userRepository->search('john', ['name', 'email', 'bio'])->get();
```

### Search with Ranking
```php
// Search with weighted ranking
$users = $userRepository->searchWithRanking(
    'john', 
    ['name', 'email', 'bio'],  // columns
    [3, 2, 1]                  // weights (name=3, email=2, bio=1)
)->get();
```

### Model Configuration for Search
```php
// In your model
class User extends Model
{
    public function getSearchableColumns(): array
    {
        return ['name', 'email', 'bio', 'company'];
    }
}
```

## Date Range Filtering

### Simple Date Range
```php
// Filter by date range
$users = $userRepository->dateRange('created_at', '2024-01-01', '2024-12-31')->get();

// From date only
$users = $userRepository->dateRange('created_at', '2024-01-01')->get();

// To date only
$users = $userRepository->dateRange('created_at', null, '2024-12-31')->get();
```

### Date Operators
```php
$filters = [
    [
        'field' => 'created_at',
        'value' => '2024-01-01',
        'operator' => 'date'
    ],
    [
        'field' => 'created_at',
        'value' => 2024,
        'operator' => 'year'
    ],
    [
        'field' => 'created_at',
        'value' => 12,
        'operator' => 'month'
    ]
];

$users = $userRepository->advancedFilter($filters)->get();
```

## Conditional Filtering

### When/Unless Conditions
```php
$status = request('status');
$searchTerm = request('search');

$users = $userRepository
    ->when($status, function ($query) use ($status) {
        $query->where('status', $status);
    })
    ->when($searchTerm, function ($query) use ($searchTerm) {
        $query->search($searchTerm);
    })
    ->unless(auth()->user()->isAdmin(), function ($query) {
        $query->where('visibility', 'public');
    })
    ->get();
```

### Scope Filtering
```php
// Apply model scopes
$users = $userRepository
    ->scope('active')
    ->scope('recent', 30)  // with parameters
    ->get();
```

## Relationship Filtering

### Filter by Relationship
```php
// Users who have posts with specific status
$users = $userRepository->filterByRelation('posts', [
    'status' => 'published'
])->get();

// Multiple relationship filters
$users = $userRepository->filterByRelation('posts', [
    'status' => 'published',
    'featured' => true
])->get();
```

### Filter by Relationship Count
```php
// Users with at least 5 posts
$users = $userRepository->filterByRelationCount('posts', '>=', 5)->get();

// Users with no posts
$users = $userRepository->filterByRelationCount('posts', '=', 0)->get();
```

### Advanced Relationship Filtering
```php
$filters = [
    [
        'field' => 'status',
        'value' => 'published',
        'operator' => '=',
        'relation' => 'posts'
    ],
    [
        'field' => 'featured',
        'value' => true,
        'operator' => '=',
        'relation' => 'posts'
    ]
];

$users = $userRepository->advancedFilter($filters)->get();
```

## Request-Based Filtering

### Filter from Request Data
```php
// Define allowed filters
$allowedFilters = [
    'name',
    'email',
    'status',
    ['field' => 'created_at', 'operator' => 'date_range', 'request_key' => 'date_range']
];

// Apply filters from request
$users = $userRepository
    ->filterFromRequest(request()->all(), $allowedFilters)
    ->sortFromRequest(request()->all(), ['name', 'email', 'created_at'])
    ->paginate(15);
```

### Request Parameters Example
```php
// URL: /users?name=john&status=active,verified&sort=name&direction=asc
$requestData = [
    'name' => 'john',
    'status' => 'active,verified',
    'sort' => 'name',
    'direction' => 'asc'
];

$users = $userRepository
    ->filterFromRequest($requestData, $allowedFilters)
    ->sortFromRequest($requestData, ['name', 'email', 'created_at'])
    ->paginate(15);
```

## Dynamic Filtering

### Dynamic Filter Configuration
```php
$filterConfig = [
    [
        'field' => 'name',
        'request_key' => 'name',
        'operator' => 'like'
    ],
    [
        'field' => 'status',
        'request_key' => 'status',
        'operator' => 'in',
        'transform' => 'array'  // Transform comma-separated to array
    ],
    [
        'field' => 'created_at',
        'request_key' => 'created_from',
        'operator' => '>=',
        'transform' => 'date'
    ],
    [
        'field' => 'created_at',
        'request_key' => 'created_to',
        'operator' => '<=',
        'transform' => 'date'
    ]
];

$users = $userRepository->dynamicFilter(request()->all(), $filterConfig)->get();
```

### Value Transformations

| Transform | Description | Example |
|-----------|-------------|---------|
| `array` | Split string to array | `"a,b,c"` → `["a", "b", "c"]` |
| `int` | Convert to integer | `"123"` → `123` |
| `float` | Convert to float | `"12.34"` → `12.34` |
| `bool` | Convert to boolean | `"true"` → `true` |
| `date` | Convert to date | `"2024-01-01"` → `"2024-01-01"` |
| `datetime` | Convert to datetime | `"2024-01-01 12:00"` → `"2024-01-01 12:00:00"` |
| `lowercase` | Convert to lowercase | `"HELLO"` → `"hello"` |
| `uppercase` | Convert to uppercase | `"hello"` → `"HELLO"` |

## Filter Configuration

### Model Configuration
```php
class User extends Model
{
    /**
     * Define searchable columns
     */
    public function getSearchableColumns(): array
    {
        return ['name', 'email', 'bio'];
    }

    /**
     * Define filterable columns
     */
    public function getFilterableColumns(): array
    {
        return [
            'name',
            'email',
            'status',
            ['field' => 'created_at', 'operator' => 'date_range']
        ];
    }

    /**
     * Scope for active users
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for recent users
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
```

### Repository Implementation
```php
class UserRepository extends BaseRepository
{
    use FilterableRepository;

    public function model(): string
    {
        return User::class;
    }

    /**
     * Get filtered users with pagination
     */
    public function getFilteredUsers(array $requestData)
    {
        $filterConfig = [
            [
                'field' => 'name',
                'request_key' => 'search',
                'operator' => 'like'
            ],
            [
                'field' => 'status',
                'request_key' => 'status',
                'operator' => 'in',
                'transform' => 'array'
            ],
            [
                'field' => 'created_at',
                'request_key' => 'date_from',
                'operator' => '>=',
                'transform' => 'date'
            ]
        ];

        return $this->dynamicFilter($requestData, $filterConfig)
                   ->sortFromRequest($requestData, ['name', 'email', 'created_at'])
                   ->paginate(request('per_page', 15));
    }

    /**
     * Advanced user search
     */
    public function advancedSearch(array $criteria)
    {
        return $this->when(!empty($criteria['search']), function ($query) use ($criteria) {
                       $query->searchWithRanking($criteria['search'], ['name', 'email'], [2, 1]);
                   })
                   ->when(!empty($criteria['location']), function ($query) use ($criteria) {
                       $lat = $criteria['location']['lat'];
                       $lng = $criteria['location']['lng'];
                       $radius = $criteria['location']['radius'] ?? 50;
                       $query->nearLocation($lat, $lng, $radius);
                   })
                   ->filter($criteria['filters'] ?? [])
                   ->get();
    }
}
```

## Utility Methods

### Get Filtered Results with Statistics
```php
$result = $userRepository->getFilteredWithCount(['status' => 'active']);

// Returns:
[
    'data' => Collection,      // Filtered results
    'total' => 100,           // Total records before filtering
    'filtered' => 75,         // Records after filtering
    'filters_applied' => true // Whether filters were applied
]
```

### Filter and Paginate
```php
$users = $userRepository->filterAndPaginate([
    'status' => 'active'
], 15); // 15 per page
```

### Geolocation Filtering
```php
// Find users within 25km of coordinates
$users = $userRepository->nearLocation(
    40.7128,  // latitude
    -74.0060, // longitude
    25,       // radius
    'km'      // unit (km or miles)
)->get();
```

## Performance Tips

1. **Index Filter Columns**: Ensure database indexes on commonly filtered columns
2. **Limit Search Columns**: Don't search across too many text columns
3. **Use Specific Operators**: Use specific operators instead of broad LIKE searches when possible
4. **Optimize Date Ranges**: Use proper date column types and indexes
5. **Relationship Filtering**: Consider eager loading when filtering by relationships
6. **Pagination**: Always use pagination for large datasets

## Complete Example

```php
// Controller method
public function index(Request $request)
{
    $users = $this->userRepository->advancedUserFilter([
        'search' => $request->get('search'),
        'status' => $request->get('status'),
        'role' => $request->get('role'),
        'date_range' => [
            'from' => $request->get('from_date'),
            'to' => $request->get('to_date')
        ],
        'location' => [
            'lat' => $request->get('lat'),
            'lng' => $request->get('lng'),
            'radius' => $request->get('radius', 50)
        ]
    ]);

    return view('users.index', compact('users'));
}

// Repository method
public function advancedUserFilter(array $criteria)
{
    return $this->when(!empty($criteria['search']), function ($query) use ($criteria) {
                   $query->search($criteria['search']);
               })
               ->when(!empty($criteria['status']), function ($query) use ($criteria) {
                   $query->applyFilter('status', $criteria['status'], 'in');
               })
               ->when(!empty($criteria['role']), function ($query) use ($criteria) {
                   $query->filterByRelation('roles', ['name' => $criteria['role']]);
               })
               ->when(!empty($criteria['date_range']['from']), function ($query) use ($criteria) {
                   $query->dateRange('created_at', $criteria['date_range']['from'], $criteria['date_range']['to']);
               })
               ->when(!empty($criteria['location']['lat']), function ($query) use ($criteria) {
                   $query->nearLocation(
                       $criteria['location']['lat'],
                       $criteria['location']['lng'],
                       $criteria['location']['radius']
                   );
               })
               ->paginate(15);
}
```

This comprehensive filtering system provides maximum flexibility while maintaining clean, readable code and optimal performance.
