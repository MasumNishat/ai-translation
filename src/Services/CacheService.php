<?php

namespace Masum\AiTranslator\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheService
{
    protected int $ttl;
    protected string $prefix;
    protected bool $enabled;
    protected string $driver;

    public function __construct()
    {
        $this->ttl = config('ai-translator.cache.ttl', 3600);
        $this->prefix = config('ai-translator.cache.prefix', 'ai_translator');
        $this->enabled = config('ai-translator.cache.enabled', true);
        $this->driver = config('cache.default');
    }

    /**
     * Remember a value in cache with tags
     *
     * @param string $key Translation key
     * @param string $locale Language code
     * @param string|null $group Translation group
     * @param callable $callback Callback to get value if not cached
     * @return mixed
     */
    public function remember(string $key, string $locale, ?string $group, callable $callback)
    {
        if (!$this->enabled) {
            return $callback();
        }

        $cacheKey = $this->getCacheKey($key, $locale, $group);
        $tags = $this->getTags($locale, $group);

        try {
            // Cache tagging is not supported by all drivers
            if ($this->supportsTagging()) {
                return Cache::tags($tags)->remember($cacheKey, $this->ttl, $callback);
            }

            // Fallback to simple caching without tags
            return Cache::remember($cacheKey, $this->ttl, $callback);
        } catch (\Exception $e) {
            Log::warning('Cache remember failed, executing callback directly', [
                'key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            return $callback();
        }
    }

    /**
     * Forget a specific cache entry
     *
     * @param string $key Translation key
     * @param string $locale Language code
     * @param string|null $group Translation group
     * @return void
     */
    public function forget(string $key, string $locale, ?string $group): void
    {
        if (!$this->enabled) {
            return;
        }

        $cacheKey = $this->getCacheKey($key, $locale, $group);
        $tags = $this->getTags($locale, $group);

        try {
            if ($this->supportsTagging()) {
                Cache::tags($tags)->forget($cacheKey);
            } else {
                Cache::forget($cacheKey);
            }
        } catch (\Exception $e) {
            Log::warning('Cache forget failed', [
                'key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Forget all cache entries for a language
     *
     * @param string $locale Language code
     * @return void
     */
    public function forgetByLanguage(string $locale): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            if ($this->supportsTagging()) {
                Cache::tags(["locale:{$locale}"])->flush();
            } else {
                // Fallback: flush entire cache (not ideal, but ensures consistency)
                $this->flushAll();
            }
        } catch (\Exception $e) {
            Log::warning('Cache flush by language failed', [
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Forget all cache entries for a group
     *
     * @param string $group Translation group
     * @return void
     */
    public function forgetByGroup(string $group): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            if ($this->supportsTagging()) {
                Cache::tags(["group:{$group}"])->flush();
            } else {
                // Fallback: flush entire cache
                $this->flushAll();
            }
        } catch (\Exception $e) {
            Log::warning('Cache flush by group failed', [
                'group' => $group,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Flush all translation cache
     *
     * @return void
     */
    public function flushAll(): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            if ($this->supportsTagging()) {
                Cache::tags(['translations'])->flush();
            } else {
                // Flush cache entries matching our prefix pattern
                Cache::flush();
            }
        } catch (\Exception $e) {
            Log::warning('Cache flush all failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get cache key
     *
     * @param string $key Translation key
     * @param string $locale Language code
     * @param string|null $group Translation group
     * @return string
     */
    protected function getCacheKey(string $key, string $locale, ?string $group): string
    {
        $parts = [$this->prefix];

        if ($group) {
            $parts[] = $group;
        }

        // Hash the key so long sentences never exceed varchar(255) on the cache table.
        $parts[] = md5($key);
        $parts[] = $locale;

        return implode('.', $parts);
    }

    /**
     * Get cache tags for a translation
     *
     * @param string $locale Language code
     * @param string|null $group Translation group
     * @return array
     */
    protected function getTags(string $locale, ?string $group): array
    {
        $tags = ['translations', "locale:{$locale}"];

        if ($group) {
            $tags[] = "group:{$group}";
        }

        return $tags;
    }

    /**
     * Check if current cache driver supports tagging
     *
     * @return bool
     */
    protected function supportsTagging(): bool
    {
        return in_array($this->driver, ['redis', 'memcached', 'array']);
    }

    /**
     * Get cache statistics
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'ttl' => $this->ttl,
            'prefix' => $this->prefix,
            'driver' => $this->driver,
            'supports_tagging' => $this->supportsTagging(),
        ];
    }

    /**
     * Warm up cache for a language
     *
     * @param string $locale Language code
     * @param string|null $group Optional group to warm up
     * @return int Number of translations cached
     */
    public function warmUp(string $locale, ?string $group = null): int
    {
        if (!$this->enabled) {
            return 0;
        }

        $query = \Masum\AiTranslator\Models\Translation::whereHas('language', function ($q) use ($locale) {
            $q->where('code', $locale);
        });

        if ($group) {
            $query->where('group', $group);
        }

        $translations = $query->get();
        $count = 0;

        foreach ($translations as $translation) {
            $this->remember(
                $translation->key,
                $locale,
                $translation->group,
                fn() => $translation->value
            );
            $count++;
        }

        return $count;
    }

    /**
     * Check if a key exists in cache
     *
     * @param string $key Translation key
     * @param string $locale Language code
     * @param string|null $group Translation group
     * @return bool
     */
    public function has(string $key, string $locale, ?string $group = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $cacheKey = $this->getCacheKey($key, $locale, $group);

        try {
            if ($this->supportsTagging()) {
                $tags = $this->getTags($locale, $group);
                return Cache::tags($tags)->has($cacheKey);
            }

            return Cache::has($cacheKey);
        } catch (\Exception $e) {
            return false;
        }
    }
}
