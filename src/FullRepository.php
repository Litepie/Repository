<?php

namespace Litepie\Repository;

use Litepie\Repository\Traits\FilterableRepository;
use Litepie\Repository\Traits\OptimizedPagination;
use Litepie\Repository\Traits\QueryStringParser;
use Litepie\Repository\Traits\RepositoryEvents;
use Litepie\Repository\Traits\CacheableRepository;
use Litepie\Repository\Traits\RepositoryAggregations;
use Litepie\Repository\Traits\BulkOperations;
use Litepie\Repository\Traits\RelationshipManager;
use Litepie\Repository\Traits\DataPortability;
use Litepie\Repository\Traits\DynamicScopes;
use Litepie\Repository\Traits\RepositoryMetrics;
use Litepie\Repository\Traits\SearchableRepository;

/**
 * Full-featured repository with ALL available traits.
 * 
 * WARNING: This repository includes ALL features and consumes more memory (~12MB).
 * For better performance, consider using specialized repositories:
 * - MinimalRepository (~2MB) - basic CRUD + filtering
 * - PerformanceRepository (~4MB) - caching + optimization
 * - AnalyticsRepository (~6MB) - aggregations + search
 * - EnterpriseRepository (~8MB) - bulk operations + export
 * - EventDrivenRepository (~5MB) - events + relationships
 * 
 * Or compose your own with specific traits you need.
 */
abstract class FullRepository extends BaseRepository
{
    use FilterableRepository, 
        OptimizedPagination, 
        QueryStringParser,
        RepositoryEvents,
        CacheableRepository,
        RepositoryAggregations,
        BulkOperations,
        RelationshipManager,
        DataPortability,
        DynamicScopes,
        RepositoryMetrics {
            // Resolve method conflicts - CacheableRepository takes precedence for caching
            CacheableRepository::get insteadof RepositoryMetrics;
            CacheableRepository::first insteadof RepositoryMetrics;
            CacheableRepository::paginate insteadof RepositoryMetrics;
            
            // RepositoryEvents takes precedence for CRUD operations with events
            RepositoryEvents::create insteadof RepositoryMetrics;
            RepositoryEvents::update insteadof RepositoryMetrics;
            RepositoryEvents::delete insteadof RepositoryMetrics;
            RepositoryEvents::find insteadof RepositoryMetrics;
        }
    use SearchableRepository;

    /**
     * Constructor with all features initialized.
     */
    public function __construct()
    {
        parent::__construct();
        
        // Initialize all features
        $this->initializeFullFeatures();
    }

    /**
     * Initialize all repository features.
     */
    protected function initializeFullFeatures(): void
    {
        // Configure caching
        $this->configureCaching([
            'enabled' => true,
            'ttl' => 3600,
        ]);

        // Configure search
        $this->configureSearch([
            'engine' => 'database',
        ]);

        // Configure events
        $this->configureEvents([
            'enabled' => true,
            'events' => ['creating', 'created', 'updating', 'updated', 'deleting', 'deleted']
        ]);

        // Configure metrics tracking
        $this->configureMetrics([
            'enabled' => true,
            'track_queries' => true,
            'track_performance' => true
        ]);
    }
}
