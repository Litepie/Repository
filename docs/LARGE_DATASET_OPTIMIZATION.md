# Large Dataset Pagination Optimization Guide

This guide explains how to optimize pagination for large datasets (5+ million records) using the Litepie Repository package.

## Table of Contents

1. [The Problem with Large Dataset Pagination](#the-problem)
2. [Optimization Strategies](#optimization-strategies)
3. [Cursor-Based Pagination](#cursor-based-pagination)
4. [Seek Pagination](#seek-pagination)
5. [Fast Pagination](#fast-pagination)
6. [Database Optimizations](#database-optimizations)
7. [Caching Strategies](#caching-strategies)
8. [Performance Testing](#performance-testing)
9. [Best Practices](#best-practices)

## The Problem

Traditional OFFSET-based pagination becomes extremely slow on large datasets:

```sql
-- This becomes very slow as offset increases
SELECT * FROM users LIMIT 15 OFFSET 5000000;
```

**Problems:**
- Database must count and skip millions of rows
- Performance degrades linearly with page number
- Memory usage increases
- Timeouts on large offsets

## Optimization Strategies

### 1. Cursor-Based Pagination (Recommended for Large Datasets)

Uses a cursor token instead of page numbers, eliminating OFFSET issues.

```php
use App\Repositories\UserRepository;

class UserController extends Controller
{
    public function index(UserRepository $repository)
    {
        // Much faster than traditional pagination
        $users = $repository
            ->where('status', 'active')
            ->cursorPaginate(20);
            
        return view('users.index', compact('users'));
    }
}
```

**Advantages:**
- ✅ Constant time complexity O(1)
- ✅ Works with any dataset size
- ✅ Real-time consistency
- ❌ No jumping to specific pages
- ❌ No total count

### 2. Seek Pagination (Custom Implementation)

For APIs and real-time feeds where you need custom cursor logic.

```php
class PostRepository extends BaseRepository
{
    public function getPostsFeed($lastPostId = null, $limit = 20)
    {
        return $this->seekPaginate($limit, $lastPostId, 'next', 'id');
    }
    
    public function getPostsSince($sinceId, $limit = 20)
    {
        return $this->where('id', '>', $sinceId)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();
    }
}

// Usage in API
class PostController extends Controller
{
    public function feed(Request $request, PostRepository $repository)
    {
        $posts = $repository->getPostsFeed(
            $request->get('last_id'),
            $request->get('limit', 20)
        );
        
        return response()->json([
            'data' => $posts,
            'next_cursor' => $posts->last()?->id,
            'has_more' => $posts->count() === $request->get('limit', 20)
        ]);
    }
}
```

### 3. Fast Pagination (No Total Count)

Uses LIMIT + 1 to determine if there are more pages, avoiding expensive COUNT queries.

```php
class UserRepository extends BaseRepository
{
    public function getActiveUsersPage($page = 1)
    {
        // No total count calculation
        return $this->where('status', 'active')
            ->fastPaginate(25);
    }
}
```

**Benefits:**
- ✅ No expensive COUNT(*) query
- ✅ Faster page loads
- ✅ Better for infinite scroll
- ❌ No total pages information

### 4. Optimized Pagination with Approximate Count

Uses database statistics for quick total estimates.

```php
class ProductRepository extends BaseRepository
{
    public function searchProducts($query, $page = 1)
    {
        return $this->search($query, ['name', 'description'])
            ->where('status', 'active')
            ->optimizedPaginate(20, ['*'], 'page', $page, true); // Use approximate count
    }
}
```

### 5. Chunked Processing

For processing large datasets without pagination UI.

```php
class UserRepository extends BaseRepository
{
    public function processAllUsers(callable $processor)
    {
        // Process in chunks to avoid memory issues
        $this->where('status', 'active')
            ->chunkById(1000, function ($users) use ($processor) {
                foreach ($users as $user) {
                    $processor($user);
                }
            });
    }
    
    public function exportUsers()
    {
        // Memory-efficient iteration
        return $this->where('status', 'active')
            ->lazy(1000) // Returns LazyCollection
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            });
    }
}
```

## Database Optimizations

### 1. Proper Indexing

```sql
-- Covering index for cursor pagination
CREATE INDEX idx_users_status_id ON users (status, id);

-- Covering index for filtered queries
CREATE INDEX idx_posts_status_created_id ON posts (status, created_at, id);

-- Composite index for complex filters
CREATE INDEX idx_orders_user_status_date ON orders (user_id, status, created_at);
```

### 2. Partitioning Large Tables

```sql
-- Partition by date for time-series data
CREATE TABLE posts (
    id BIGINT PRIMARY KEY,
    user_id BIGINT,
    title VARCHAR(255),
    content TEXT,
    status VARCHAR(50),
    created_at TIMESTAMP
) PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2022 VALUES LESS THAN (2023),
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

### 3. Archive Old Data

```php
class UserRepository extends BaseRepository
{
    public function archiveInactiveUsers()
    {
        // Move old data to archive table
        $this->where('last_login_at', '<', now()->subYears(2))
            ->where('status', 'inactive')
            ->chunkById(1000, function ($users) {
                // Insert into archive table
                DB::table('users_archive')->insert(
                    $users->toArray()
                );
                
                // Delete from main table
                $this->whereIn('id', $users->pluck('id'))->delete();
            });
    }
}
```

## Caching Strategies

### 1. Cache Total Counts

```php
class PostRepository extends BaseRepository
{
    public function getPaginatedPosts($page = 1)
    {
        return $this->where('status', 'published')
            ->cachedPaginate(20, ['*'], 'page', $page, 300); // Cache for 5 minutes
    }
}
```

### 2. Cache Expensive Filters

```php
class UserRepository extends BaseRepository
{
    public function getTopUsers($page = 1)
    {
        $cacheKey = "top_users_page_{$page}";
        
        return Cache::remember($cacheKey, 600, function () use ($page) {
            return $this->withCount(['posts', 'comments'])
                ->having('posts_count', '>', 10)
                ->orderByDesc('posts_count')
                ->paginate(20, ['*'], 'page', $page);
        });
    }
}
```

### 3. Cache Query Results

```php
class ProductRepository extends BaseRepository
{
    public function getFeaturedProducts()
    {
        return Cache::tags(['products', 'featured'])
            ->remember('featured_products', 3600, function () {
                return $this->where('featured', true)
                    ->where('status', 'active')
                    ->orderBy('featured_at', 'desc')
                    ->limit(100)
                    ->get();
            });
    }
}
```

## Performance Testing

### 1. Benchmark Different Pagination Methods

```php
class UserRepository extends BaseRepository
{
    public function testPaginationPerformance()
    {
        $report = $this->where('status', 'active')
            ->paginationPerformanceReport(20, 1000); // Test page 1000
            
        /*
        Expected output:
        [
            'total_time' => 0.95,
            'table' => 'users',
            'estimated_rows' => 5000000,
            'methods' => [
                'standard' => ['time' => 0.85, 'memory' => 2048576, 'count' => 5000000],
                'fast' => ['time' => 0.05, 'memory' => 1024576, 'count' => 20],
                'cursor' => ['time' => 0.03, 'memory' => 1024576, 'count' => 20],
            ],
            'recommendation' => 'For 5000000 rows: Use cursor pagination or seek pagination for best performance'
        ]
        */
    }
}
```

### 2. Monitor Query Performance

```php
class UserController extends Controller
{
    public function index(UserRepository $repository)
    {
        $startTime = microtime(true);
        
        $users = $repository->where('status', 'active')
            ->cursorPaginate(20);
            
        $executionTime = microtime(true) - $startTime;
        
        // Log slow queries
        if ($executionTime > 0.5) {
            Log::warning('Slow pagination query', [
                'execution_time' => $executionTime,
                'table' => 'users',
                'method' => 'cursorPaginate'
            ]);
        }
        
        return view('users.index', compact('users'));
    }
}
```

## Best Practices

### 1. Choose the Right Pagination Method

```php
class PostRepository extends BaseRepository
{
    public function getPosts($filters = [], $pagination = 'standard')
    {
        $query = $this->filter($filters);
        
        switch ($pagination) {
            case 'cursor':
                // Best for large datasets, real-time feeds
                return $query->cursorPaginate(20);
                
            case 'fast':
                // Good for infinite scroll, no total needed
                return $query->fastPaginate(20);
                
            case 'optimized':
                // Good balance for large datasets with approximate totals
                return $query->optimizedPaginate(20);
                
            default:
                // Standard pagination for smaller datasets
                return $query->paginate(20);
        }
    }
}
```

### 2. Implement Smart Pagination Detection

```php
abstract class BaseRepository implements RepositoryInterface
{
    /**
     * Automatically choose best pagination method based on dataset size
     */
    public function smartPaginate(int $perPage = 15, array $columns = ['*'])
    {
        $estimatedRows = $this->getTableRowEstimate();
        
        if ($estimatedRows > 5000000) {
            // Very large dataset - use cursor pagination
            return $this->cursorPaginate($perPage, $columns);
        } elseif ($estimatedRows > 1000000) {
            // Large dataset - use fast pagination
            return $this->fastPaginate($perPage, $columns);
        } elseif ($estimatedRows > 100000) {
            // Medium dataset - use optimized pagination
            return $this->optimizedPaginate($perPage, $columns);
        } else {
            // Small dataset - standard pagination is fine
            return $this->paginate($perPage, $columns);
        }
    }
}
```

### 3. Optimize Frontend for Cursor Pagination

```javascript
// Vue.js example for cursor pagination
export default {
    data() {
        return {
            posts: [],
            nextCursor: null,
            hasMore: true,
            loading: false
        }
    },
    
    async methods: {
        async loadPosts(cursor = null) {
            this.loading = true;
            
            try {
                const response = await axios.get('/api/posts', {
                    params: { cursor }
                });
                
                if (cursor) {
                    // Append to existing posts
                    this.posts.push(...response.data.data);
                } else {
                    // Replace posts (first load)
                    this.posts = response.data.data;
                }
                
                this.nextCursor = response.data.next_cursor;
                this.hasMore = response.data.has_more;
            } finally {
                this.loading = false;
            }
        },
        
        async loadMore() {
            if (this.hasMore && !this.loading) {
                await this.loadPosts(this.nextCursor);
            }
        }
    },
    
    mounted() {
        this.loadPosts();
    }
}
```

### 4. Database Connection Optimization

```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
    'options' => [
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false, // Important for large datasets
    ],
],
```

## Real-World Examples

### E-commerce Product Catalog

```php
class ProductRepository extends BaseRepository
{
    public function searchProducts($filters, $sort = 'relevance')
    {
        $query = $this->filter($filters);
        
        // For product catalogs, cursor pagination works well
        // because users typically browse sequentially
        return $query->orderBy($this->getSortColumn($sort))
            ->cursorPaginate(24); // Typical product grid
    }
    
    private function getSortColumn($sort)
    {
        return match($sort) {
            'price_low' => 'price',
            'price_high' => 'price',
            'newest' => 'created_at',
            'popularity' => 'sales_count',
            default => 'id' // For cursor pagination consistency
        };
    }
}
```

### Social Media Feed

```php
class PostRepository extends BaseRepository
{
    public function getUserFeed($userId, $lastPostId = null)
    {
        return $this->join('follows', 'posts.user_id', '=', 'follows.followed_id')
            ->where('follows.follower_id', $userId)
            ->where('posts.status', 'published')
            ->when($lastPostId, function($query, $lastPostId) {
                $query->where('posts.id', '<', $lastPostId);
            })
            ->orderByDesc('posts.id')
            ->limit(20)
            ->get(['posts.*']);
    }
}
```

### Analytics Dashboard

```php
class AnalyticsRepository extends BaseRepository
{
    public function getPageViews($startDate, $endDate, $page = 1)
    {
        // For analytics, we often need totals, so use optimized pagination
        return $this->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as views')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->optimizedPaginate(30, ['*'], 'page', $page);
    }
}
```

## Performance Comparison

| Method | 1M Records | 5M Records | 10M Records | Use Case |
|--------|------------|------------|-------------|----------|
| Standard Pagination | ~500ms | ~2000ms | ~5000ms+ | Small datasets |
| Fast Pagination | ~50ms | ~100ms | ~150ms | No total count needed |
| Cursor Pagination | ~10ms | ~15ms | ~20ms | Large datasets, feeds |
| Seek Pagination | ~5ms | ~10ms | ~15ms | Real-time APIs |
| Optimized (Approx) | ~100ms | ~200ms | ~300ms | Need approximate totals |

## Conclusion

For large datasets over 5 million records:

1. **Use cursor pagination** for the best performance
2. **Avoid OFFSET-based pagination** beyond the first few pages
3. **Implement proper indexing** for your query patterns
4. **Cache expensive operations** like total counts
5. **Choose the right method** based on your use case
6. **Monitor and optimize** query performance regularly

The Litepie Repository package provides all these optimization methods out of the box, making it easy to handle large datasets efficiently.
