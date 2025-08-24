<?php

namespace Litepie\Repository\Traits;

trait DynamicScopes
{
    /**
     * Dynamic scopes collection.
     */
    protected array $dynamicScopes = [];

    /**
     * Registered macros.
     */
    protected array $macros = [];

    /**
     * Add dynamic scope.
     */
    public function addScope(string $name, callable $scope): self
    {
        $this->dynamicScopes[$name] = $scope;
        
        $this->fireEvent('scope_added', ['name' => $name]);
        
        return $this;
    }

    /**
     * Remove dynamic scope.
     */
    public function removeScope(string $name): self
    {
        unset($this->dynamicScopes[$name]);
        
        $this->fireEvent('scope_removed', ['name' => $name]);
        
        return $this;
    }

    /**
     * Apply dynamic scope.
     */
    public function scope(string $name, ...$parameters): self
    {
        if (!isset($this->dynamicScopes[$name])) {
            throw new \InvalidArgumentException("Scope '{$name}' is not defined");
        }

        $scope = $this->dynamicScopes[$name];
        
        $this->fireEvent('scope_applying', ['name' => $name, 'parameters' => $parameters]);
        
        $scope($this->query, ...$parameters);
        
        $this->fireEvent('scope_applied', ['name' => $name]);
        
        return $this;
    }

    /**
     * Conditional query execution.
     */
    public function when(bool $condition, callable $callback, callable $default = null): self
    {
        if ($condition) {
            $callback($this->query, $this);
        } elseif ($default) {
            $default($this->query, $this);
        }
        
        return $this;
    }

    /**
     * Inverse conditional query execution.
     */
    public function unless(bool $condition, callable $callback, callable $default = null): self
    {
        return $this->when(!$condition, $callback, $default);
    }

    /**
     * Tap into the query without affecting the result.
     */
    public function tap(callable $callback): self
    {
        $callback($this->query, $this);
        return $this;
    }

    /**
     * Add macro to repository.
     */
    public function macro(string $name, callable $macro): self
    {
        $this->macros[$name] = $macro;
        
        $this->fireEvent('macro_added', ['name' => $name]);
        
        return $this;
    }

    /**
     * Remove macro from repository.
     */
    public function removeMacro(string $name): self
    {
        unset($this->macros[$name]);
        
        $this->fireEvent('macro_removed', ['name' => $name]);
        
        return $this;
    }

    /**
     * Check if macro exists.
     */
    public function hasMacro(string $name): bool
    {
        return isset($this->macros[$name]);
    }

    /**
     * Call macro method.
     */
    public function callMacro(string $name, array $parameters = [])
    {
        if (!$this->hasMacro($name)) {
            throw new \BadMethodCallException("Method '{$name}' does not exist");
        }

        $macro = $this->macros[$name];
        
        $this->fireEvent('macro_calling', ['name' => $name, 'parameters' => $parameters]);
        
        $result = $macro($this, ...$parameters);
        
        $this->fireEvent('macro_called', ['name' => $name, 'result' => $result]);
        
        return $result;
    }

    /**
     * Handle dynamic method calls.
     */
    public function __call(string $method, array $parameters)
    {
        // Check for macros first
        if ($this->hasMacro($method)) {
            return $this->callMacro($method, $parameters);
        }

        // Check for dynamic scopes
        if (isset($this->dynamicScopes[$method])) {
            return $this->scope($method, ...$parameters);
        }

        // Check for query builder methods
        if (method_exists($this->query, $method)) {
            $result = $this->query->$method(...$parameters);
            
            // If method returns query builder, return repository instance
            if ($result === $this->query) {
                return $this;
            }
            
            return $result;
        }

        throw new \BadMethodCallException("Method '{$method}' does not exist");
    }

    /**
     * Get all available scopes.
     */
    public function getAvailableScopes(): array
    {
        return array_keys($this->dynamicScopes);
    }

    /**
     * Get all available macros.
     */
    public function getAvailableMacros(): array
    {
        return array_keys($this->macros);
    }

    /**
     * Register multiple scopes at once.
     */
    public function registerScopes(array $scopes): self
    {
        foreach ($scopes as $name => $scope) {
            $this->addScope($name, $scope);
        }
        
        return $this;
    }

    /**
     * Register multiple macros at once.
     */
    public function registerMacros(array $macros): self
    {
        foreach ($macros as $name => $macro) {
            $this->macro($name, $macro);
        }
        
        return $this;
    }

    /**
     * Create a fluent scope.
     */
    public function fluent(string $name, array $methods): self
    {
        $this->addScope($name, function ($query) use ($methods) {
            foreach ($methods as $method => $parameters) {
                if (is_numeric($method)) {
                    // Single method call
                    $query->$parameters();
                } else {
                    // Method with parameters
                    $query->$method(...(array) $parameters);
                }
            }
        });
        
        return $this;
    }

    /**
     * Create a parameterized scope.
     */
    public function parameterized(string $name, callable $scopeBuilder): self
    {
        $this->addScope($name, function ($query, ...$parameters) use ($scopeBuilder) {
            $scope = $scopeBuilder(...$parameters);
            
            if (is_callable($scope)) {
                $scope($query);
            } elseif (is_array($scope)) {
                foreach ($scope as $method => $args) {
                    if (is_numeric($method)) {
                        $query->$args();
                    } else {
                        $query->$method(...(array) $args);
                    }
                }
            }
        });
        
        return $this;
    }

    /**
     * Create conditional scope.
     */
    public function conditionalScope(string $name, callable $condition, callable $scope): self
    {
        $this->addScope($name, function ($query, ...$parameters) use ($condition, $scope) {
            if ($condition(...$parameters)) {
                $scope($query, ...$parameters);
            }
        });
        
        return $this;
    }

    /**
     * Add common scopes.
     */
    public function addCommonScopes(): self
    {
        // Recent scope
        $this->addScope('recent', function ($query, int $days = 7) {
            $query->where('created_at', '>=', now()->subDays($days));
        });

        // Active scope
        $this->addScope('active', function ($query) {
            $query->where('status', 'active');
        });

        // Popular scope (requires views or similar field)
        $this->addScope('popular', function ($query, string $field = 'views', int $threshold = 100) {
            $query->where($field, '>=', $threshold)->orderBy($field, 'desc');
        });

        // Search scope
        $this->addScope('search', function ($query, string $term, array $fields = ['name', 'title']) {
            $query->where(function ($q) use ($term, $fields) {
                foreach ($fields as $field) {
                    $q->orWhere($field, 'LIKE', "%{$term}%");
                }
            });
        });

        // Date range scope
        $this->addScope('dateRange', function ($query, $start, $end, string $field = 'created_at') {
            $query->whereBetween($field, [$start, $end]);
        });

        return $this;
    }

    /**
     * Add common macros.
     */
    public function addCommonMacros(): self
    {
        // Get random records
        $this->macro('random', function ($repository, int $count = 1) {
            return $repository->query->inRandomOrder()->limit($count)->get();
        });

        // Get or create
        $this->macro('getOrCreate', function ($repository, array $attributes, array $values = []) {
            $instance = $repository->findBy($attributes);
            
            if ($instance) {
                return $instance;
            }
            
            return $repository->create(array_merge($attributes, $values));
        });

        // Increment field
        $this->macro('incrementField', function ($repository, $id, string $field, int $amount = 1) {
            return $repository->query->where('id', $id)->increment($field, $amount);
        });

        // Decrement field
        $this->macro('decrementField', function ($repository, $id, string $field, int $amount = 1) {
            return $repository->query->where('id', $id)->decrement($field, $amount);
        });

        // Toggle boolean field
        $this->macro('toggle', function ($repository, $id, string $field) {
            $model = $repository->find($id);
            if ($model) {
                $model->$field = !$model->$field;
                $model->save();
                return $model;
            }
            return null;
        });

        return $this;
    }

    /**
     * Create query pipeline.
     */
    public function pipeline(array $pipes): self
    {
        foreach ($pipes as $pipe) {
            if (is_string($pipe) && isset($this->dynamicScopes[$pipe])) {
                $this->scope($pipe);
            } elseif (is_callable($pipe)) {
                $pipe($this->query, $this);
            } elseif (is_array($pipe) && count($pipe) >= 2) {
                [$method, $parameters] = $pipe;
                if (isset($this->dynamicScopes[$method])) {
                    $this->scope($method, ...(array) $parameters);
                } else {
                    $this->query->$method(...(array) $parameters);
                }
            }
        }
        
        return $this;
    }

    /**
     * Save current query state.
     */
    public function saveState(string $name): self
    {
        $this->queryStates[$name] = clone $this->query;
        return $this;
    }

    /**
     * Restore query state.
     */
    public function restoreState(string $name): self
    {
        if (isset($this->queryStates[$name])) {
            $this->query = clone $this->queryStates[$name];
        }
        
        return $this;
    }

    /**
     * Fork current query.
     */
    public function fork(): self
    {
        $forked = clone $this;
        $forked->query = clone $this->query;
        return $forked;
    }
}
