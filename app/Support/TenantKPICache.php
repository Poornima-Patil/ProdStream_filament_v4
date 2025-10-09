<?php

namespace App\Support;

use App\Models\Factory;
use Illuminate\Support\Facades\Cache;

class TenantKPICache
{
    protected Factory $factory;
    protected string $store;

    public function __construct(Factory $factory, string $store = 'kpi_cache')
    {
        $this->factory = $factory;
        $this->store = $store;
    }

    /**
     * Get cached value with multi-tenant isolation
     */
    public function get(string $key, string $tier, callable $callback, int $ttl): mixed
    {
        $fullKey = $this->buildKey($key, $tier);
        $tags = $this->buildTags($tier);

        return Cache::store($this->store)
            ->tags($tags)
            ->remember($fullKey, $ttl, $callback);
    }

    /**
     * Put value in cache
     */
    public function put(string $key, string $tier, mixed $value, int $ttl): bool
    {
        $fullKey = $this->buildKey($key, $tier);
        $tags = $this->buildTags($tier);

        return Cache::store($this->store)
            ->tags($tags)
            ->put($fullKey, $value, $ttl);
    }

    /**
     * Forget specific cache key
     */
    public function forget(string $key, string $tier): bool
    {
        $fullKey = $this->buildKey($key, $tier);
        return Cache::store($this->store)->forget($fullKey);
    }

    /**
     * Flush all KPIs for this factory
     */
    public function flush(): bool
    {
        return Cache::store($this->store)
            ->tags(["factory_{$this->factory->id}"])
            ->flush();
    }

    /**
     * Flush specific tier for this factory
     */
    public function flushTier(string $tier): bool
    {
        return Cache::store($this->store)
            ->tags(["factory_{$this->factory->id}", "tier_{$tier}"])
            ->flush();
    }

    /**
     * Build cache key with factory namespace
     */
    protected function buildKey(string $key, string $tier): string
    {
        return "factory_{$this->factory->id}::tier_{$tier}::{$key}";
    }

    /**
     * Build cache tags for multi-tenant isolation
     */
    protected function buildTags(string $tier): array
    {
        return [
            "factory_{$this->factory->id}",
            "tier_{$tier}",
            "kpi"
        ];
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        // Implementation depends on Redis commands
        return [
            'factory_id' => $this->factory->id,
            'store' => $this->store,
            'tags' => $this->buildTags('all'),
        ];
    }
}
