<?php

namespace Litepie\Repository\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait RelationshipManager
{
    /**
     * Relationships to load.
     */
    protected array $withRelations = [];

    /**
     * Relationships loaded counter.
     */
    protected array $relationshipStats = [];

    /**
     * Load specific relationships.
     */
    public function withRelations(array $relations): self
    {
        $this->withRelations = array_merge($this->withRelations, $relations);
        $this->query->with($relations);
        
        $this->fireEvent('relationships_loading', ['relations' => $relations]);
        
        return $this;
    }

    /**
     * Load relationships with constraints.
     */
    public function withRelationsWhere(array $relationsWithConstraints): self
    {
        foreach ($relationsWithConstraints as $relation => $constraint) {
            if (is_callable($constraint)) {
                $this->query->with([$relation => $constraint]);
            } else {
                $this->query->with($relation);
            }
        }
        
        return $this;
    }

    /**
     * Count relationships without loading them.
     */
    public function withCount(array $relations): self
    {
        $this->query->withCount($relations);
        return $this;
    }

    /**
     * Sync relationship data (for BelongsToMany).
     */
    public function syncRelation(string $relation, $id, array $data, bool $detaching = true): array
    {
        $model = $this->find($id);
        
        if (!$model) {
            throw new \InvalidArgumentException("Model with ID {$id} not found");
        }

        $relationInstance = $model->$relation();
        
        if (!$relationInstance instanceof BelongsToMany) {
            throw new \InvalidArgumentException("Relation {$relation} is not a BelongsToMany relationship");
        }

        $this->fireEvent('relationship_syncing', [
            'model_id' => $id,
            'relation' => $relation,
            'data' => $data
        ]);

        $result = $relationInstance->sync($data, $detaching);
        
        $this->fireEvent('relationship_synced', [
            'model_id' => $id,
            'relation' => $relation,
            'result' => $result
        ]);

        $this->invalidateCache();
        
        return $result;
    }

    /**
     * Attach to relationship.
     */
    public function attachToRelation(string $relation, $modelId, $relatedId, array $attributes = []): void
    {
        $model = $this->find($modelId);
        
        if (!$model) {
            throw new \InvalidArgumentException("Model with ID {$modelId} not found");
        }

        $relationInstance = $model->$relation();
        
        if (!$relationInstance instanceof BelongsToMany) {
            throw new \InvalidArgumentException("Relation {$relation} is not a BelongsToMany relationship");
        }

        $this->fireEvent('relationship_attaching', [
            'model_id' => $modelId,
            'relation' => $relation,
            'related_id' => $relatedId,
            'attributes' => $attributes
        ]);

        $relationInstance->attach($relatedId, $attributes);
        
        $this->fireEvent('relationship_attached', [
            'model_id' => $modelId,
            'relation' => $relation,
            'related_id' => $relatedId
        ]);

        $this->invalidateCache();
    }

    /**
     * Detach from relationship.
     */
    public function detachFromRelation(string $relation, $modelId, $relatedId = null): int
    {
        $model = $this->find($modelId);
        
        if (!$model) {
            throw new \InvalidArgumentException("Model with ID {$modelId} not found");
        }

        $relationInstance = $model->$relation();
        
        if (!$relationInstance instanceof BelongsToMany) {
            throw new \InvalidArgumentException("Relation {$relation} is not a BelongsToMany relationship");
        }

        $this->fireEvent('relationship_detaching', [
            'model_id' => $modelId,
            'relation' => $relation,
            'related_id' => $relatedId
        ]);

        $detached = $relationInstance->detach($relatedId);
        
        $this->fireEvent('relationship_detached', [
            'model_id' => $modelId,
            'relation' => $relation,
            'detached_count' => $detached
        ]);

        $this->invalidateCache();
        
        return $detached;
    }

    /**
     * Load missing relationships on existing collection.
     */
    public function loadMissingRelations(Collection $collection, array $relations): Collection
    {
        if ($collection->isEmpty()) {
            return $collection;
        }

        $this->fireEvent('relationships_loading_missing', [
            'count' => $collection->count(),
            'relations' => $relations
        ]);

        $collection->loadMissing($relations);
        
        $this->updateRelationshipStats($relations, $collection->count());
        
        return $collection;
    }

    /**
     * Create related model (for HasMany, HasOne).
     */
    public function createRelated(string $relation, $modelId, array $attributes = []): Model
    {
        $model = $this->find($modelId);
        
        if (!$model) {
            throw new \InvalidArgumentException("Model with ID {$modelId} not found");
        }

        $relationInstance = $model->$relation();
        
        if (!($relationInstance instanceof HasMany || $relationInstance instanceof HasOne)) {
            throw new \InvalidArgumentException("Relation {$relation} is not a HasMany or HasOne relationship");
        }

        $this->fireEvent('related_creating', [
            'model_id' => $modelId,
            'relation' => $relation,
            'attributes' => $attributes
        ]);

        $related = $relationInstance->create($attributes);
        
        $this->fireEvent('related_created', [
            'model_id' => $modelId,
            'relation' => $relation,
            'related_id' => $related->getKey()
        ]);

        $this->invalidateCache();
        
        return $related;
    }

    /**
     * Update related models.
     */
    public function updateRelated(string $relation, $modelId, array $attributes = [], array $where = []): int
    {
        $model = $this->find($modelId);
        
        if (!$model) {
            throw new \InvalidArgumentException("Model with ID {$modelId} not found");
        }

        $relationInstance = $model->$relation();
        
        if (!$relationInstance instanceof HasMany) {
            throw new \InvalidArgumentException("Relation {$relation} is not a HasMany relationship");
        }

        $query = $relationInstance->getQuery();
        
        foreach ($where as $field => $value) {
            $query->where($field, $value);
        }

        $this->fireEvent('related_updating', [
            'model_id' => $modelId,
            'relation' => $relation,
            'attributes' => $attributes,
            'where' => $where
        ]);

        $updated = $query->update($attributes);
        
        $this->fireEvent('related_updated', [
            'model_id' => $modelId,
            'relation' => $relation,
            'updated_count' => $updated
        ]);

        $this->invalidateCache();
        
        return $updated;
    }

    /**
     * Delete related models.
     */
    public function deleteRelated(string $relation, $modelId, array $where = []): int
    {
        $model = $this->find($modelId);
        
        if (!$model) {
            throw new \InvalidArgumentException("Model with ID {$modelId} not found");
        }

        $relationInstance = $model->$relation();
        
        if (!$relationInstance instanceof HasMany) {
            throw new \InvalidArgumentException("Relation {$relation} is not a HasMany relationship");
        }

        $query = $relationInstance->getQuery();
        
        foreach ($where as $field => $value) {
            $query->where($field, $value);
        }

        $this->fireEvent('related_deleting', [
            'model_id' => $modelId,
            'relation' => $relation,
            'where' => $where
        ]);

        $deleted = $query->delete();
        
        $this->fireEvent('related_deleted', [
            'model_id' => $modelId,
            'relation' => $relation,
            'deleted_count' => $deleted
        ]);

        $this->invalidateCache();
        
        return $deleted;
    }

    /**
     * Associate model with parent (for BelongsTo).
     */
    public function associateWith($modelId, string $relation, $parentId): bool
    {
        $model = $this->find($modelId);
        
        if (!$model) {
            throw new \InvalidArgumentException("Model with ID {$modelId} not found");
        }

        $relationInstance = $model->$relation();
        
        if (!$relationInstance instanceof BelongsTo) {
            throw new \InvalidArgumentException("Relation {$relation} is not a BelongsTo relationship");
        }

        $this->fireEvent('relationship_associating', [
            'model_id' => $modelId,
            'relation' => $relation,
            'parent_id' => $parentId
        ]);

        $relationInstance->associate($parentId);
        $saved = $model->save();
        
        $this->fireEvent('relationship_associated', [
            'model_id' => $modelId,
            'relation' => $relation,
            'parent_id' => $parentId
        ]);

        $this->invalidateCache();
        
        return $saved;
    }

    /**
     * Dissociate model from parent (for BelongsTo).
     */
    public function dissociateFrom($modelId, string $relation): bool
    {
        $model = $this->find($modelId);
        
        if (!$model) {
            throw new \InvalidArgumentException("Model with ID {$modelId} not found");
        }

        $relationInstance = $model->$relation();
        
        if (!$relationInstance instanceof BelongsTo) {
            throw new \InvalidArgumentException("Relation {$relation} is not a BelongsTo relationship");
        }

        $this->fireEvent('relationship_dissociating', [
            'model_id' => $modelId,
            'relation' => $relation
        ]);

        $relationInstance->dissociate();
        $saved = $model->save();
        
        $this->fireEvent('relationship_dissociated', [
            'model_id' => $modelId,
            'relation' => $relation
        ]);

        $this->invalidateCache();
        
        return $saved;
    }

    /**
     * Handle polymorphic relationships.
     */
    public function morphTo(string $relation, $modelId, Model $morphModel): bool
    {
        $model = $this->find($modelId);
        
        if (!$model) {
            throw new \InvalidArgumentException("Model with ID {$modelId} not found");
        }

        $relationInstance = $model->$relation();
        
        if (!$relationInstance instanceof MorphTo) {
            throw new \InvalidArgumentException("Relation {$relation} is not a MorphTo relationship");
        }

        $this->fireEvent('morph_associating', [
            'model_id' => $modelId,
            'relation' => $relation,
            'morph_type' => get_class($morphModel),
            'morph_id' => $morphModel->getKey()
        ]);

        $relationInstance->associate($morphModel);
        $saved = $model->save();
        
        $this->fireEvent('morph_associated', [
            'model_id' => $modelId,
            'relation' => $relation,
            'morph_type' => get_class($morphModel),
            'morph_id' => $morphModel->getKey()
        ]);

        $this->invalidateCache();
        
        return $saved;
    }

    /**
     * Get relationship statistics.
     */
    public function getRelationshipStats(): array
    {
        return $this->relationshipStats;
    }

    /**
     * Update relationship loading statistics.
     */
    protected function updateRelationshipStats(array $relations, int $count): void
    {
        foreach ($relations as $relation) {
            if (!isset($this->relationshipStats[$relation])) {
                $this->relationshipStats[$relation] = [
                    'loads' => 0,
                    'total_models' => 0
                ];
            }
            
            $this->relationshipStats[$relation]['loads']++;
            $this->relationshipStats[$relation]['total_models'] += $count;
        }
    }

    /**
     * Eager load relationships optimally.
     */
    public function eagerLoadOptimal(array $relations): self
    {
        // Analyze relationships and determine optimal loading strategy
        $optimizedRelations = $this->optimizeRelationshipLoading($relations);
        
        return $this->withRelations($optimizedRelations);
    }

    /**
     * Optimize relationship loading order.
     */
    protected function optimizeRelationshipLoading(array $relations): array
    {
        // Sort relations by complexity (simple relations first)
        usort($relations, function ($a, $b) {
            $aDepth = substr_count($a, '.');
            $bDepth = substr_count($b, '.');
            
            return $aDepth - $bDepth;
        });
        
        return $relations;
    }

    /**
     * Preload relationships for multiple models efficiently.
     */
    public function preloadRelationships(array $modelIds, array $relations): Collection
    {
        $models = $this->findMany($modelIds);
        
        if ($models->isEmpty()) {
            return $models;
        }

        $this->fireEvent('relationships_preloading', [
            'model_count' => $models->count(),
            'relations' => $relations
        ]);

        // Load relationships in batches for better performance
        foreach ($relations as $relation) {
            $models->load($relation);
        }
        
        $this->updateRelationshipStats($relations, $models->count());
        
        return $models;
    }

    /**
     * Get available relationships for the model.
     */
    public function getAvailableRelationships(): array
    {
        $model = $this->model;
        $reflection = new \ReflectionClass($model);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        $relationships = [];
        
        foreach ($methods as $method) {
            if ($method->class === get_class($model) && 
                !$method->isStatic() && 
                $method->getNumberOfParameters() === 0) {
                
                try {
                    $return = $method->invoke($model);
                    
                    if ($return instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
                        $relationships[] = [
                            'name' => $method->getName(),
                            'type' => get_class($return),
                            'related_model' => $return->getRelated()::class
                        ];
                    }
                } catch (\Throwable $e) {
                    // Skip methods that throw exceptions
                    continue;
                }
            }
        }
        
        return $relationships;
    }
}
