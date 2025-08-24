<?php

namespace Litepie\Repository;

use Illuminate\Support\Facades\App;
use Litepie\Repository\Traits\FilterableRepository;
use Litepie\Repository\Traits\CacheableRepository;
use Litepie\Repository\Traits\BulkOperations;
use Litepie\Repository\Traits\DataPortability;
use Litepie\Repository\Traits\RepositoryMetrics;

/**
 * Enterprise repository for large-scale operations.
 * Extends BaseRepository and includes bulk operations, data portability, and metrics.
 */
abstract class EnterpriseRepository extends BaseRepository
{
    use FilterableRepository,
        CacheableRepository,
        BulkOperations,
        DataPortability,
        RepositoryMetrics;

    /**
     * Constructor with enterprise features configured.
     */
    public function __construct()
    {
        parent::__construct();
        
        // Configure export settings
        $this->configureExport([
            'chunk_size' => 2000,
            'memory_limit' => '1G',
            'disk' => 'local'
        ]);
        
        // Enable profiling in development
        if (App::environment('local', 'testing')) {
            $this->enableProfiling();
        }
    }
}
