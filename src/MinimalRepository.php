<?php

namespace Litepie\Repository;

use Litepie\Repository\Traits\FilterableRepository;

/**
 * Minimal Base Repository with only core functionality + basic filtering.
 * Extends BaseRepository and adds only essential filtering capabilities.
 */
abstract class MinimalRepository extends BaseRepository
{
    use FilterableRepository;
    
    /**
     * Provides basic CRUD + simple filtering.
     * Memory usage: ~2MB
     */
}
