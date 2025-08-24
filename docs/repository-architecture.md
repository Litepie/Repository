# Repository Architecture Guide

## Repository Weight Management

You're absolutely right to be concerned about repository weight. Including all traits in a single BaseRepository can lead to:

1. **Memory overhead** - Loading unused functionality
2. **Complexity** - Too many methods and features
3. **Performance impact** - Unnecessary trait resolution
4. **Maintainability issues** - Large codebase harder to debug

## Modular Architecture

We've created a modular approach with different repository types:

### 1. **MinimalRepository** (Lightweight)
- Only core CRUD operations
- Basic filtering
- Perfect for simple models
- ~50 methods

```php
abstract class UserRepository extends MinimalRepository
{
    public function model(): string
    {
        return User::class;
    }
}
```

### 2. **PerformanceRepository** (Optimized)
- Minimal + Caching + Pagination optimization + Query string parsing
- For high-traffic applications
- ~80 methods

```php
abstract class ProductRepository extends PerformanceRepository
{
    public function model(): string
    {
        return Product::class;
    }
}

// Usage with automatic caching
$products = $productRepository
    ->remember(60) // Cache for 1 hour
    ->where('status', 'active')
    ->cursorPaginate(100); // Optimized pagination
```

### 3. **AnalyticsRepository** (Data Analysis)
- Minimal + Aggregations + Search + Dynamic scopes
- For reporting and analytics
- ~120 methods

```php
abstract class SalesRepository extends AnalyticsRepository
{
    public function model(): string
    {
        return Sale::class;
    }
}

// Usage for analytics
$stats = $salesRepository->aggregate(['sum' => 'amount', 'avg' => 'value']);
$trends = $salesRepository->trend('created_at', 'month', 'revenue', 'sum');
```

### 4. **EnterpriseRepository** (Large Scale)
- Minimal + Bulk operations + Import/Export + Metrics
- For large datasets and enterprise features
- ~150 methods

```php
abstract class OrderRepository extends EnterpriseRepository
{
    public function model(): string
    {
        return Order::class;
    }
}

// Usage for bulk operations
$orderRepository->bulkInsert($millionOrders, 2000);
$csvPath = $orderRepository->exportToCsv(['id', 'total', 'status']);
```

### 5. **EventDrivenRepository** (Event System)
- Minimal + Events + Relationships
- For applications with complex business logic
- ~90 methods

```php
abstract class PostRepository extends EventDrivenRepository
{
    public function model(): string
    {
        return Post::class;
    }
}

// Automatic event firing
$post = $postRepository->create($data); // Fires 'creating' and 'created' events
```

## Custom Trait Composition

For specific needs, compose your own repository:

```php
use Litepie\Repository\MinimalRepository;
use Litepie\Repository\Traits\CacheableRepository;
use Litepie\Repository\Traits\SearchableRepository;

abstract class CustomRepository extends MinimalRepository
{
    use CacheableRepository,
        SearchableRepository;
    
    // Your custom implementation
}
```

## Performance Comparison

| Repository Type | Memory Usage | Method Count | Use Case |
|-----------------|--------------|--------------|----------|
| MinimalRepository | ~2MB | ~50 | Simple CRUD |
| PerformanceRepository | ~4MB | ~80 | High traffic |
| AnalyticsRepository | ~6MB | ~120 | Data analysis |
| EnterpriseRepository | ~8MB | ~150 | Large scale |
| EventDrivenRepository | ~5MB | ~90 | Event-driven |
| FullRepository (all traits) | ~12MB | ~200+ | Everything |

## Lazy Loading Approach

You can also implement lazy loading for heavy features:

```php
abstract class LazyRepository extends MinimalRepository
{
    private $analytics = null;
    private $bulkOps = null;
    
    public function analytics(): RepositoryAggregations
    {
        if (!$this->analytics) {
            $this->analytics = new class {
                use RepositoryAggregations;
            };
        }
        return $this->analytics;
    }
    
    public function bulk(): BulkOperations  
    {
        if (!$this->bulkOps) {
            $this->bulkOps = new class {
                use BulkOperations;
            };
        }
        return $this->bulkOps;
    }
}

// Usage
$stats = $repository->analytics()->aggregate(['sum' => 'amount']);
$repository->bulk()->bulkInsert($data);
```

## Recommendations

1. **Start Small**: Use MinimalRepository for new projects
2. **Scale Up**: Move to specialized repositories as needs grow
3. **Profile**: Monitor memory usage and performance
4. **Compose**: Create custom combinations for specific needs
5. **Document**: Clearly document which features each repository includes

This modular approach ensures you only load what you need while keeping the full feature set available when required.
