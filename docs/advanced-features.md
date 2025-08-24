# Repository Advanced Features Guide

This guide covers all the advanced features available in the Litepie Repository package.

## Table of Contents

1. [Repository Events & Observers](#repository-events--observers)
2. [Advanced Caching Layer](#advanced-caching-layer)
3. [Repository Aggregations & Analytics](#repository-aggregations--analytics)
4. [Bulk Operations & Batch Processing](#bulk-operations--batch-processing)
5. [Repository Relationships Manager](#repository-relationships-manager)
6. [Data Export & Import](#data-export--import)
7. [Dynamic Scopes & Macros](#dynamic-scopes--macros)
8. [Repository Metrics & Performance](#repository-metrics--performance)
9. [Search Engine Integration](#search-engine-integration)

## Repository Events & Observers

The repository automatically fires events for all major operations, allowing you to hook into the repository lifecycle.

### Available Events

- `creating`, `created` - Before and after model creation
- `updating`, `updated` - Before and after model updates
- `deleting`, `deleted` - Before and after model deletion
- `finding`, `found` - Before and after finding models
- `caching`, `cached` - Before and after caching operations
- `bulk_updating`, `bulk_updated` - Before and after bulk operations
- `relationship_syncing`, `relationship_synced` - Before and after relationship operations

### Usage Examples

```php
// Listen to repository events
Event::listen('repository.User.created', function ($model, $data, $repository) {
    // Send welcome email, log activity, etc.
    Mail::to($model->email)->send(new WelcomeEmail($model));
});

// Add custom event listeners
$userRepository->addEventListener('created', function ($model, $data, $repository) {
    Cache::tags(['users'])->flush();
});

// Disable events temporarily
$userRepository->withoutEvents(function ($repo) {
    return $repo->bulkInsert($largeDataset);
});
```

## Advanced Caching Layer

Intelligent caching with automatic invalidation, tags, and multiple cache stores.

### Basic Caching

```php
// Cache for 1 hour
$users = $userRepository
    ->remember(60)
    ->where('status', 'active')
    ->get();

// Cache forever
$settings = $settingsRepository
    ->rememberForever()
    ->where('type', 'global')
    ->get();

// Cache with tags
$posts = $postRepository
    ->remember(30)
    ->tags(['posts', 'published'])
    ->where('status', 'published')
    ->get();
```

### Advanced Caching Features

```php
// Configure caching
$repository->configureCaching([
    'ttl' => 3600,
    'tags' => ['default'],
    'store' => 'redis',
]);

// Custom cache key
$data = $repository
    ->cacheKey('custom_key_123')
    ->remember(60)
    ->get();

// Cache warming
$repository->warmCache(['key1', 'key2'], function ($key) {
    return $this->find($key);
});

// Cache statistics
$stats = $repository->getCacheStats();
```

## Repository Aggregations & Analytics

Powerful analytics and aggregation functions for data analysis.

### Basic Aggregations

```php
// Multiple aggregations at once
$stats = $repository->aggregate([
    'count' => '*',
    'sum' => 'amount',
    'avg' => 'rating',
    'min' => 'price',
    'max' => 'price'
]);

// Group by with aggregations
$salesByCategory = $repository->groupBy('category', [
    'count' => '*',
    'sum' => 'amount',
    'avg' => 'price'
]);
```

### Advanced Analytics

```php
// Trend analysis
$trends = $repository->trend('created_at', 'day', 'revenue', 'sum');

// Percentiles
$percentiles = $repository->percentiles('price', [25, 50, 75, 90, 95]);

// Moving average
$movingAvg = $repository->movingAverage('sales', 7); // 7-day moving average

// Correlation between fields
$correlation = $repository->correlation('price', 'rating');

// Histogram data
$histogram = $repository->histogram('age', 10); // 10 bins

// Statistical summary
$summary = $repository->statisticalSummary('revenue');
```

### Pivot Tables

```php
// Create pivot table
$pivot = $repository->pivot('category', 'month', 'sales', 'sum');
// Results in: categories vs months with sales sums
```

## Bulk Operations & Batch Processing

Efficient handling of large datasets with bulk operations.

### Bulk Operations

```php
// Bulk insert
$repository->bulkInsert($largeDataArray, 1000); // 1000 per chunk

// Bulk update
$repository->bulkUpdate([
    ['id' => 1, 'status' => 'active'],
    ['id' => 2, 'status' => 'inactive'],
], 'id');

// Bulk delete
$repository->bulkDelete([1, 2, 3, 4, 5]);

// Bulk upsert (insert or update)
$repository->bulkUpsert($data, ['email'], ['name', 'status']);
```

### Batch Processing

```php
// Process in batches
$repository->batchProcess(function ($records) {
    foreach ($records as $record) {
        // Process each record
        $this->processRecord($record);
    }
}, 1000);

// Batch update with callback
$updated = $repository->batchUpdate(function ($record) {
    $record->processed_at = now();
    $record->status = 'processed';
}, 500);
```

### Conflict Resolution

```php
// Handle conflicts during bulk insert
$results = $repository->bulkInsertWithConflictResolution(
    $data,
    'ignore', // or 'update', 'error'
    ['email'], // conflict fields
    1000
);

// Results: ['inserted' => 150, 'updated' => 50, 'ignored' => 10, 'errors' => []]
```

## Repository Relationships Manager

Advanced relationship management with eager loading optimization.

### Basic Relationship Operations

```php
// Load relationships
$posts = $repository
    ->withRelations(['user', 'comments.user', 'tags'])
    ->get();

// Conditional relationship loading
$posts = $repository
    ->withRelationsWhere([
        'comments' => function ($query) {
            $query->where('approved', true);
        }
    ])
    ->get();

// Count relationships
$posts = $repository
    ->withCount(['comments', 'likes'])
    ->get();
```

### Relationship Synchronization

```php
// Sync many-to-many relationships
$repository->syncRelation('tags', $postId, [1, 2, 3]);

// Attach to relationship
$repository->attachToRelation('tags', $postId, $tagId, ['priority' => 1]);

// Detach from relationship
$repository->detachFromRelation('tags', $postId, $tagId);

// Create related models
$comment = $repository->createRelated('comments', $postId, [
    'content' => 'Great post!',
    'user_id' => auth()->id()
]);
```

### Polymorphic Relationships

```php
// Associate polymorphic relationship
$repository->morphTo('commentable', $commentId, $post);

// Load missing relationships
$collection = $repository->loadMissingRelations($posts, ['user', 'tags']);
```

## Data Export & Import

Comprehensive data portability with multiple formats.

### Export Data

```php
// Export to CSV
$csvPath = $repository
    ->where('status', 'active')
    ->exportToCsv(['name', 'email', 'created_at']);

// Export to JSON
$jsonPath = $repository
    ->exportToJson(['id', 'name', 'email']);

// Stream export for large datasets
return $repository
    ->where('created_at', '>', '2024-01-01')
    ->streamExport('csv', function ($record) {
        // Transform data before export
        return [
            'name' => $record->name,
            'email' => $record->email,
            'formatted_date' => $record->created_at->format('Y-m-d')
        ];
    });
```

### Import Data

```php
// Import from CSV
$imported = $repository->importFromCsv('users.csv', [
    'Name' => 'name',
    'Email Address' => 'email',
    'Status' => 'status'
], [
    'has_header' => true,
    'chunk_size' => 1000,
    'skip_errors' => true,
    'update_existing' => true,
    'unique_field' => 'email'
]);

// Import from JSON
$imported = $repository->importFromJson('data.json', $mapping, $options);
```

### Export Configuration

```php
// Configure export settings
$repository->configureExport([
    'chunk_size' => 2000,
    'memory_limit' => '1G',
    'disk' => 's3',
    'path' => 'exports/users'
]);

// Get export statistics
$stats = $repository->getExportStats();
```

## Dynamic Scopes & Macros

Extensible query building with custom scopes and macros.

### Dynamic Scopes

```php
// Add custom scope
$repository->addScope('recent', function ($query, $days = 7) {
    $query->where('created_at', '>=', now()->subDays($days));
});

// Use scope
$recentPosts = $repository->recent(30)->get();

// Conditional scopes
$repository->when($request->has('status'), function ($query) use ($request) {
    $query->where('status', $request->status);
});

// Add common scopes
$repository->addCommonScopes();
$activePosts = $repository->active()->recent()->get();
```

### Macros

```php
// Add custom macro
$repository->macro('getOrCreate', function ($repo, $attributes, $values = []) {
    return $repo->firstOrCreate($attributes, $values);
});

// Use macro
$user = $repository->getOrCreate(['email' => 'user@example.com'], ['name' => 'John']);

// Add common macros
$repository->addCommonMacros();
$randomUsers = $repository->random(5);
```

### Query Pipeline

```php
// Build query pipeline
$results = $repository->pipeline([
    'active',
    ['where', ['status', 'published']],
    function ($query) {
        $query->orderBy('created_at', 'desc');
    }
])->get();

// Save and restore query states
$repository->saveState('before_filters');
$repository->where('status', 'active');
$repository->restoreState('before_filters');
```

## Repository Metrics & Performance

Advanced performance monitoring and optimization.

### Performance Profiling

```php
// Enable profiling
$repository->enableProfiling();

// Execute operations
$users = $repository->with('posts')->paginate(50);

// Get performance metrics
$metrics = $repository->getMetrics();
// Returns: query count, execution time, memory usage, cache hit rate, etc.

// Get detailed performance report
$report = $repository->getPerformanceReport();
```

### Query Analysis

```php
// Explain queries
$explanation = $repository
    ->where('status', 'active')
    ->explain();

// Benchmark operations
$benchmark = $repository->benchmark(function ($repo) {
    return $repo->with('posts')->get();
}, 10); // 10 iterations

// Get slow queries
$slowQueries = $repository->getSlowQueries(100); // > 100ms

// Get duplicate queries
$duplicates = $repository->getDuplicateQueries();
```

### Memory and Cache Metrics

```php
// Monitor memory usage
$memoryUsed = $repository->getMemoryUsage();
$peakMemory = $repository->getPeakMemoryUsage();

// Cache statistics
$hitRate = $repository->getCacheHitRate();
$repository->recordCacheHit();
$repository->recordCacheMiss();
```

## Search Engine Integration

Full-text search with multiple engines and fuzzy matching.

### Database Search

```php
// Basic search
$results = $repository
    ->configureSearch(['fields' => ['name', 'description']])
    ->search('laravel repository');

// Full-text search (MySQL)
$results = $repository->fullTextSearch('web development', ['title', 'content']);

// Fuzzy search
$results = $repository->fuzzySearch('jhon doe', 0.7); // 70% similarity
```

### Elasticsearch Integration

```php
// Configure Elasticsearch
$repository->configureElastic([
    'host' => 'localhost:9200',
    'index' => 'products'
]);

// Search with Elasticsearch
$results = $repository->elasticSearch([
    'query' => [
        'multi_match' => [
            'query' => 'smartphone android',
            'fields' => ['name', 'description'],
            'fuzziness' => 'AUTO'
        ]
    ]
]);

// Reindex all data
$repository->reindex();
```

### Search Index Management

```php
// Build search index
$repository->buildSearchIndex(['name', 'description', 'tags']);

// Advanced search with weights
$repository->configureSearch([
    'fields' => ['title', 'content', 'tags'],
    'weights' => [
        'title' => 3,
        'content' => 1,
        'tags' => 2
    ]
]);

$results = $repository->search('laravel tutorial');
```

## Performance Best Practices

### Large Datasets

```php
// Use cursor pagination for large datasets
$users = $repository->cursorPaginate(['id', 'name'], 1000);

// Chunked processing
$repository->chunk(1000, function ($users) {
    // Process batch
});

// Optimize with indexes
$repository->enableProfiling();
// ... run queries
$report = $repository->getPerformanceReport();
// Check recommendations for indexes
```

### Caching Strategies

```php
// Cache frequently accessed data
$popularPosts = $repository
    ->remember(3600) // 1 hour
    ->tags(['posts', 'popular'])
    ->orderBy('views', 'desc')
    ->limit(10)
    ->get();

// Invalidate related caches
$repository->addEventListener('updated', function () {
    Cache::tags(['posts'])->flush();
});
```

### Memory Optimization

```php
// For large exports, use streaming
return $repository->streamExport('csv');

// For bulk operations, use appropriate chunk sizes
$optimalChunkSize = $repository->calculateOptimalChunkSize();
$repository->bulkInsert($data, $optimalChunkSize);
```

This advanced repository package provides enterprise-level features for handling complex data operations, analytics, and performance optimization in Laravel applications.
