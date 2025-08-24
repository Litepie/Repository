<?php

namespace Litepie\Repository\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

trait CacheableRepository
{
    /**
     * Cache configuration.
     */
    protected array $cacheConfig = [
        'enabled' => true,
        'ttl' => 3600, // 1 hour default
        'prefix' => 'repo',
        'tags' => [],
        'store' => null,
    ];

    /**
     * Cache key for current query.
     */
    protected ?string $cacheKey = null;

    /**
     * Cache TTL for current query.
     */
    protected ?int $cacheTtl = null;

    /**
     * Cache tags for current query.
     */
    protected array $cacheTags = [];

    /**
     * Whether to cache forever.
     */
    protected bool $cacheForever = false;

    /**
     * Cache the next query for specified minutes.
     */
    public function remember(int $minutes = null): self
    {
        $this->cacheTtl = $minutes ?? $this->cacheConfig['ttl'];
        $this->cacheForever = false;
        return $this;
    }

    /**
     * Cache the next query forever.
     */
    public function rememberForever(): self
    {
        $this->cacheForever = true;
        $this->cacheTtl = null;
        return $this;
    }

    /**
     * Set cache tags.
     */
    public function tags(array $tags): self
    {
        $this->cacheTags = array_merge($this->cacheTags, $tags);
        return $this;
    }

    /**
     * Set custom cache key.
     */
    public function cacheKey(string $key): self
    {
        $this->cacheKey = $key;
        return $this;
    }

    /**
     * Forget cached result by key.
     */
    public function forget(string $key): bool
    {
        $cache = $this->getCacheStore();
        
        if (!empty($this->cacheTags)) {
            return $cache->tags($this->cacheTags)->forget($key);
        }
        
        return $cache->forget($key);
    }

    /**
     * Flush all cached results.
     */
    public function flush(): bool
    {
        $cache = $this->getCacheStore();
        
        if (!empty($this->getAllCacheTags())) {
            return $cache->tags($this->getAllCacheTags())->flush();
        }
        
        return $cache->flush();
    }

    /**
     * Get cache store.
     */
    protected function getCacheStore()
    {
        return $this->cacheConfig['store'] 
            ? Cache::store($this->cacheConfig['store'])
            : Cache::getFacadeRoot();
    }

    /**
     * Generate cache key for current query.
     */
    protected function generateCacheKey(): string
    {
        if ($this->cacheKey) {
            return $this->cacheKey;
        }

        $modelClass = Str::afterLast($this->model(), '\\');
        $queryHash = md5($this->query->toSql() . serialize($this->query->getBindings()));
        
        return sprintf(
            '%s:%s:%s',
            $this->cacheConfig['prefix'],
            Str::snake($modelClass),
            $queryHash
        );
    }

    /**
     * Get all cache tags.
     */
    protected function getAllCacheTags(): array
    {
        $modelClass = Str::afterLast($this->model(), '\\');
        $defaultTags = [
            Str::snake($modelClass),
            'repository'
        ];
        
        return array_unique(array_merge(
            $defaultTags,
            $this->cacheConfig['tags'],
            $this->cacheTags
        ));
    }

    /**
     * Execute query with caching.
     */
    protected function executeWithCache(callable $callback)
    {
        if (!$this->shouldCache()) {
            return $callback();
        }

        $key = $this->generateCacheKey();
        $cache = $this->getCacheStore();
        $tags = $this->getAllCacheTags();

        $this->fireEvent('caching', ['key' => $key, 'tags' => $tags]);

        if (!empty($tags)) {
            $cache = $cache->tags($tags);
        }

        $result = $this->cacheForever 
            ? $cache->rememberForever($key, $callback)
            : $cache->remember($key, $this->cacheTtl ?? $this->cacheConfig['ttl'], $callback);

        $this->fireEvent('cached', ['key' => $key, 'result' => $result]);

        // Reset cache settings
        $this->resetCacheSettings();

        return $result;
    }

    /**
     * Check if should cache.
     */
    protected function shouldCache(): bool
    {
        return $this->cacheConfig['enabled'] && 
               ($this->cacheTtl !== null || $this->cacheForever);
    }

    /**
     * Reset cache settings.
     */
    protected function resetCacheSettings(): void
    {
        $this->cacheKey = null;
        $this->cacheTtl = null;
        $this->cacheTags = [];
        $this->cacheForever = false;
    }

    /**
     * Configure cache settings.
     */
    public function configureCaching(array $config): self
    {
        $this->cacheConfig = array_merge($this->cacheConfig, $config);
        return $this;
    }

    /**
     * Enable caching.
     */
    public function enableCaching(): self
    {
        $this->cacheConfig['enabled'] = true;
        return $this;
    }

    /**
     * Disable caching.
     */
    public function disableCaching(): self
    {
        $this->cacheConfig['enabled'] = false;
        return $this;
    }

    /**
     * Warm cache with data.
     */
    public function warmCache(array $keys, callable $dataProvider = null): array
    {
        $warmed = [];
        $cache = $this->getCacheStore();
        $tags = $this->getAllCacheTags();

        if (!empty($tags)) {
            $cache = $cache->tags($tags);
        }

        foreach ($keys as $key) {
            if (!$cache->has($key)) {
                $data = $dataProvider ? $dataProvider($key) : $this->find($key);
                $cache->put($key, $data, $this->cacheConfig['ttl']);
                $warmed[] = $key;
            }
        }

        return $warmed;
    }

    /**
     * Get cache statistics.
     */
    public function getCacheStats(): array
    {
        $modelClass = Str::afterLast($this->model(), '\\');
        
        return [
            'model' => $modelClass,
            'enabled' => $this->cacheConfig['enabled'],
            'ttl' => $this->cacheConfig['ttl'],
            'tags' => $this->getAllCacheTags(),
            'store' => $this->cacheConfig['store'] ?? 'default'
        ];
    }

    /**
     * Invalidate model cache on changes.
     */
    protected function invalidateCache(): void
    {
        $this->flush();
    }

    /**
     * Override get method with caching.
     */
    public function get(array $columns = ['*'])
    {
        if (!$this->shouldCache()) {
            return parent::get($columns);
        }

        return $this->executeWithCache(function () use ($columns) {
            return parent::get($columns);
        });
    }

    /**
     * Override first method with caching.
     */
    public function first(array $columns = ['*'])
    {
        if (!$this->shouldCache()) {
            return parent::first($columns);
        }

        return $this->executeWithCache(function () use ($columns) {
            return parent::first($columns);
        });
    }

    /**
     * Override paginate method with caching.
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null)
    {
        if (!$this->shouldCache()) {
            return parent::paginate($perPage, $columns, $pageName, $page);
        }

        return $this->executeWithCache(function () use ($perPage, $columns, $pageName, $page) {
            return parent::paginate($perPage, $columns, $pageName, $page);
        });
    }
}
