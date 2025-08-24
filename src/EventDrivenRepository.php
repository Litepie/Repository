<?php

namespace Litepie\Repository;

use Litepie\Repository\Traits\FilterableRepository;
use Litepie\Repository\Traits\RelationshipManager;
use Litepie\Repository\Traits\RepositoryEvents;

/**
 * Event-driven repository for applications with complex workflows.
 * Extends BaseRepository and includes events and relationship management.
 */
abstract class EventDrivenRepository extends BaseRepository
{
    use FilterableRepository,
        RelationshipManager,
        RepositoryEvents;

    /**
     * Constructor with event system enabled.
     */
    public function __construct()
    {
        parent::__construct();
        
        // Events are enabled by default
        $this->enableEvents();
    }
}
