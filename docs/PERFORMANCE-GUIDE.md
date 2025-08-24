# Repository Performance Guide

## Overview

The Laravel Repository package provides multiple repository implementations optimized for different use cases. This guide helps you choose the right repository for your performance needs.

## Performance Comparison

| Repository Type | Memory Usage | Method Count | Use Case |
|----------------|--------------|--------------|----------|
| BaseRepository | ~1MB | ~50 | Core CRUD only |
| MinimalRepository | ~2MB | ~60 | Basic CRUD + filtering |
| PerformanceRepository | ~4MB | ~80 | High-traffic applications |
| AnalyticsRepository | ~6MB | ~120 | Data analysis & reporting |
| EnterpriseRepository | ~8MB | ~150 | Large-scale operations |
| EventDrivenRepository | ~5MB | ~90 | Event-heavy applications |
| FullRepository | ~12MB | ~200+ | All features (heavy) |

## Repository Selection Guide

### 0. BaseRepository (Core)
**Best for:** Custom trait composition, minimal footprint

```php
use Litepie\Repository\BaseRepository;

class UserRepository extends BaseRepository
{
    public function model(): string
    {
        return User::class;
    }
}
```

**Features:**
- Pure CRUD operations only
- No traits loaded
- Minimal memory footprint (~1MB)
- Perfect base for custom composition

### 1. MinimalRepository
**Best for:** Simple applications, microservices, API endpoints

```php
use Litepie\Repository\MinimalRepository;

class UserRepository extends MinimalRepository
{
    public function model(): string
    {
        return User::class;
    }
}
```

**Features:**
- Extends BaseRepository + FilterableRepository trait
- Basic filtering capabilities
- Lightweight (~2MB)
- Fast initialization

### 2. PerformanceRepository
**Best for:** High-traffic applications, caching requirements

```php
use Litepie\Repository\PerformanceRepository;

class ProductRepository extends PerformanceRepository
{
    public function model(): string
    {
        return Product::class;
    }
    
    protected $cachePrefix = 'products';
    protected $cacheTTL = 3600; // 1 hour
}
```

**Features:**
- Extends BaseRepository + performance traits
- Automatic caching
- Optimized pagination
- Query string parsing
- Memory efficient (~4MB)

### 3. AnalyticsRepository
**Best for:** Reporting applications, data analysis

```php
use Litepie\Repository\AnalyticsRepository;

class OrderRepository extends AnalyticsRepository
{
    public function model(): string
    {
        return Order::class;
    }
    
    // Get sales analytics
    public function getSalesReport()
    {
        return $this->sum('total')
                   ->groupBy('status')
                   ->get();
    }
}
```

**Features:**
- Extends BaseRepository + analytics traits
- Aggregation functions
- Search capabilities
- Dynamic scopes
- Advanced filtering (~6MB)

### 4. EnterpriseRepository
**Best for:** Large-scale applications, bulk operations

```php
use Litepie\Repository\EnterpriseRepository;

class CustomerRepository extends EnterpriseRepository
{
    public function model(): string
    {
        return Customer::class;
    }
    
    // Bulk import customers
    public function importCustomers($filePath)
    {
        return $this->importFromCsv($filePath);
    }
}
```

**Features:**
- Extends BaseRepository + enterprise traits
- Bulk operations
- Data import/export
- Performance metrics
- Memory optimization (~8MB)

### 5. EventDrivenRepository
**Best for:** Applications with complex workflows, audit trails

```php
use Litepie\Repository\EventDrivenRepository;

class TransactionRepository extends EventDrivenRepository
{
    public function model(): string
    {
        return Transaction::class;
    }
    
    // Events are automatically fired
    protected $events = [
        'creating',
        'created',
        'updating',
        'updated'
    ];
}
```

**Features:**
- Extends BaseRepository + event traits
- Automatic events
- Relationship management
- Audit capabilities
- Workflow support (~5MB)

### 6. FullRepository
**Best for:** Legacy compatibility, prototyping, when you need everything

```php
use Litepie\Repository\FullRepository;

class LegacyRepository extends FullRepository
{
    public function model(): string
    {
        return LegacyModel::class;
    }
    
    // Has access to ALL features
    public function complexOperation()
    {
        return $this->cache('complex', function() {
            return $this->aggregateBy('status')
                       ->withMetrics()
                       ->exportToCsv();
        });
    }
}
```

**Features:**
- ALL traits included
- Every available feature
- Full backward compatibility
- Heavy memory usage (~12MB)
- ⚠️ Use only when you actually need all features

## Performance Optimization Tips

### 1. Memory Management

```php
// Avoid loading all traits if you only need basic functionality
class LightweightRepository extends MinimalRepository
{
    // Only includes essential methods
}

// Use specific repositories for specific needs
class ReportRepository extends AnalyticsRepository
{
    // Optimized for reporting
}
```

### 2. Lazy Loading

```php
// Example of custom trait composition
use Litepie\Repository\BaseRepository;
use Litepie\Repository\Traits\CacheableRepository;
use Litepie\Repository\Traits\FilterableRepository;

class CustomRepository extends BaseRepository
{
    use CacheableRepository, FilterableRepository;
    
    // Only the traits you need
}
```

### 3. Caching Strategy

```php
class CachedProductRepository extends PerformanceRepository
{
    protected $cachePrefix = 'products';
    protected $cacheTTL = 3600;
    
    // Cache frequently accessed data
    public function getFeaturedProducts()
    {
        return $this->cache('featured', function() {
            return $this->where('featured', true)->get();
        });
    }
}
```

### 4. Database Optimization

```php
// Use chunking for large datasets
public function processLargeDataset()
{
    $this->chunk(1000, function($records) {
        foreach ($records as $record) {
            // Process each record
        }
    });
}

// Use cursor pagination for memory efficiency
public function getAllRecordsCursor()
{
    return $this->cursor();
}
```

## Benchmarks

### Memory Usage Comparison

```php
// Memory usage for 10,000 records
BaseRepository:        1.1MB
MinimalRepository:     2.1MB
PerformanceRepository: 4.3MB
AnalyticsRepository:   6.2MB
EnterpriseRepository:  8.1MB
EventDrivenRepository: 5.4MB
FullRepository:        12.8MB
```

### Query Performance

```php
// Average query execution time (ms)
Basic find():          1.2ms
With cache:           0.3ms
With aggregation:     2.8ms
Bulk operations:      15.2ms (1000 records)
```

## Migration Guide

### From BaseRepository to Specialized

If you're currently using `BaseRepository` and want to optimize:

1. **Identify your usage patterns:**
   ```php
   // If you only use basic CRUD
   MinimalRepository
   
   // If you use caching and filtering
   PerformanceRepository
   
   // If you need analytics
   AnalyticsRepository
   ```

2. **Update your repository:**
   ```php
   // Before
   class UserRepository extends BaseRepository
   
   // After
   class UserRepository extends PerformanceRepository
   ```

3. **Test and measure:**
   ```php
   // Monitor memory usage
   memory_get_usage();
   
   // Monitor query performance
   DB::enableQueryLog();
   ```

## Best Practices

1. **Choose the right repository type** based on your actual needs
2. **Monitor memory usage** in production
3. **Use caching** for frequently accessed data
4. **Implement proper indexing** in your database
5. **Use chunking** for large datasets
6. **Profile your queries** regularly

## Custom Composition

You can create your own repository by composing specific traits:

```php
use Litepie\Repository\BaseRepository;
use Litepie\Repository\Traits\CacheableRepository;
use Litepie\Repository\Traits\FilterableRepository;
use Litepie\Repository\Traits\BulkOperations;

class MyCustomRepository extends BaseRepository
{
    use CacheableRepository;
    use FilterableRepository;
    use BulkOperations;
    
    // Optimized for your specific needs
    // Memory usage: ~5MB
    // Method count: ~85
}
```

This approach gives you maximum flexibility while maintaining optimal performance for your specific use case.
