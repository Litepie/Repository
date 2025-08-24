<?php

namespace Litepie\Repository;

use Illuminate\Support\Str;
use Litepie\Repository\Traits\CacheableRepository;
use Litepie\Repository\Traits\FilterableRepository;
use Litepie\Repository\Traits\OptimizedPagination;
use Litepie\Repository\Traits\QueryStringParser;

/**
 * Performance-optimized repository for high-traffic applications.
 * Extends BaseRepository and includes caching, optimized pagination, and query parsing.
 */
abstract class PerformanceRepository extends BaseRepository
{
    use CacheableRepository;
    use FilterableRepository;
    use OptimizedPagination;
    use QueryStringParser;

    /**
     * Constructor with performance optimizations enabled by default.
     */
    public function __construct()
    {
        parent::__construct();
        
        // Enable caching by default
        $this->configureCaching([
            'enabled' => true,
            'ttl' => 3600, // 1 hour
            'tags' => [strtolower(Str::afterLast($this->model(), '\\'))]
        ]);
    }
}
