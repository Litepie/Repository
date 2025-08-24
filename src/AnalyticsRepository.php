<?php

namespace Litepie\Repository;

use Litepie\Repository\Traits\FilterableRepository;
use Litepie\Repository\Traits\RepositoryAggregations;
use Litepie\Repository\Traits\SearchableRepository;
use Litepie\Repository\Traits\DynamicScopes;

/**
 * Analytics repository optimized for data analysis and reporting.
 * Extends BaseRepository and includes aggregations, search, and dynamic scopes.
 */
abstract class AnalyticsRepository extends BaseRepository
{
    use FilterableRepository,
        RepositoryAggregations,
        SearchableRepository,
        DynamicScopes;

    /**
     * Constructor with analytics features initialized.
     */
    public function __construct()
    {
        parent::__construct();
        
        // Configure search for analytics
        $this->configureSearch([
            'engine' => 'database',
            'fields' => $this->getAnalyticsFields()
        ]);
        
        // Add common analytics scopes
        $this->addCommonScopes();
    }

    /**
     * Get fields commonly used for analytics.
     */
    protected function getAnalyticsFields(): array
    {
        return ['name', 'title', 'description', 'content'];
    }
}
