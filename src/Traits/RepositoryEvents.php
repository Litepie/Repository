<?php

namespace Litepie\Repository\Traits;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

trait RepositoryEvents
{
    /**
     * Repository events.
     */
    protected array $repositoryEvents = [
        'creating', 'created', 'updating', 'updated', 
        'deleting', 'deleted', 'finding', 'found',
        'caching', 'cached', 'bulk_updating', 'bulk_updated',
        'relationship_syncing', 'relationship_synced'
    ];

    /**
     * Event listeners.
     */
    protected array $eventListeners = [];

    /**
     * Enable/disable events.
     */
    protected bool $eventsEnabled = true;

    /**
     * Fire a repository event.
     */
    protected function fireEvent(string $event, $data = null): void
    {
        if (!$this->eventsEnabled) {
            return;
        }

        $eventName = "repository.{$event}";
        $modelClass = Str::afterLast($this->model(), '\\');
        $fullEventName = "repository.{$modelClass}.{$event}";

        // Fire generic repository event
        Event::dispatch($eventName, [$this->model(), $data, $this]);

        // Fire model-specific repository event
        Event::dispatch($fullEventName, [$this->model(), $data, $this]);

        // Fire custom listeners
        if (isset($this->eventListeners[$event])) {
            foreach ($this->eventListeners[$event] as $listener) {
                call_user_func($listener, $this->model(), $data, $this);
            }
        }
    }

    /**
     * Add event listener.
     */
    public function addEventListener(string $event, callable $listener): self
    {
        if (!isset($this->eventListeners[$event])) {
            $this->eventListeners[$event] = [];
        }

        $this->eventListeners[$event][] = $listener;

        return $this;
    }

    /**
     * Remove event listener.
     */
    public function removeEventListener(string $event, callable $listener = null): self
    {
        if ($listener === null) {
            unset($this->eventListeners[$event]);
        } else {
            $this->eventListeners[$event] = array_filter(
                $this->eventListeners[$event] ?? [],
                fn($l) => $l !== $listener
            );
        }

        return $this;
    }

    /**
     * Enable events.
     */
    public function enableEvents(): self
    {
        $this->eventsEnabled = true;
        return $this;
    }

    /**
     * Disable events.
     */
    public function disableEvents(): self
    {
        $this->eventsEnabled = false;
        return $this;
    }

    /**
     * Execute callback without events.
     */
    public function withoutEvents(callable $callback)
    {
        $originalState = $this->eventsEnabled;
        $this->eventsEnabled = false;

        try {
            $result = $callback($this);
        } finally {
            $this->eventsEnabled = $originalState;
        }

        return $result;
    }

    /**
     * Get available events.
     */
    public function getAvailableEvents(): array
    {
        return $this->repositoryEvents;
    }

    /**
     * Configure events settings.
     */
    public function configureEvents(array $config): self
    {
        if (isset($config['enabled'])) {
            $this->eventsEnabled = $config['enabled'];
        }
        
        if (isset($config['events']) && is_array($config['events'])) {
            $this->repositoryEvents = $config['events'];
        }
        
        return $this;
    }

    /**
     * Override create method to fire events.
     */
    public function create(array $data): \Illuminate\Database\Eloquent\Model
    {
        $this->fireEvent('creating', $data);
        
        $result = parent::create($data);
        
        $this->fireEvent('created', $result);
        
        return $result;
    }

    /**
     * Override update method to fire events.
     */
    public function update(int $id, array $data): \Illuminate\Database\Eloquent\Model
    {
        $this->fireEvent('updating', ['id' => $id, 'data' => $data]);
        
        $result = parent::update($id, $data);
        
        $this->fireEvent('updated', $result);
        
        return $result;
    }

    /**
     * Override delete method to fire events.
     */
    public function delete(int $id): bool
    {
        $this->fireEvent('deleting', $id);
        
        $result = parent::delete($id);
        
        $this->fireEvent('deleted', $id);
        
        return $result;
    }

    /**
     * Override find method to fire events.
     */
    public function find(int $id, array $columns = ['*']): ?\Illuminate\Database\Eloquent\Model
    {
        $this->fireEvent('finding', $id);
        
        $result = parent::find($id, $columns);
        
        $this->fireEvent('found', $result);
        
        return $result;
    }
}
