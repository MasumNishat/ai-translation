<?php

namespace Masum\AiTranslator\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class PackageSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'is_encrypted',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::saving(function ($setting) {
            // Auto-encrypt sensitive settings
            if ($setting->is_encrypted && $setting->isDirty('value')) {
                $setting->value = Crypt::encryptString($setting->value);
            }
        });

        static::saved(function ($setting) {
            // Clear cache when setting is updated
            self::clearCache($setting->key);
        });

        static::deleted(function ($setting) {
            self::clearCache($setting->key);
        });
    }

    /**
     * Get a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::getCacheKey($key);

        $value = Cache::remember(
            $cacheKey,
            config('ai-translator.translation.cache_ttl', 3600),
            function () use ($key, $default) {
                $setting = self::where('key', $key)->first();

                if (!$setting) {
                    return $default;
                }

                return $setting->getValue();
            }
        );

        return $value;
    }

    /**
     * Set a setting value.
     */
    public static function set(string $key, mixed $value, string $type = 'string', bool $encrypt = false, ?string $description = null): self
    {
        // Convert value based on type
        $valueToStore = self::convertValueToString($value, $type);

        $setting = self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $valueToStore,
                'type' => $type,
                'is_encrypted' => $encrypt,
                'description' => $description,
            ]
        );

        return $setting;
    }

    /**
     * Get the actual value (decrypt if needed, cast to proper type).
     */
    public function getValue(): mixed
    {
        $value = $this->value;

        // Decrypt if encrypted
        if ($this->is_encrypted) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                logger()->error('Failed to decrypt setting', [
                    'key' => $this->key,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        // Cast to proper type
        return self::castValue($value, $this->type);
    }

    /**
     * Convert value to string for storage.
     */
    protected static function convertValueToString(mixed $value, string $type): string
    {
        return match ($type) {
            'json', 'array' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            'integer' => (string) $value,
            default => (string) $value,
        };
    }

    /**
     * Cast value to proper type.
     */
    protected static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'json', 'array' => json_decode($value, true),
            'boolean' => (bool) $value,
            'integer' => (int) $value,
            default => $value,
        };
    }

    /**
     * Delete a setting.
     */
    public static function remove(string $key): bool
    {
        $setting = self::where('key', $key)->first();

        if ($setting) {
            return $setting->delete();
        }

        return false;
    }

    /**
     * Check if a setting exists.
     */
    public static function has(string $key): bool
    {
        return self::where('key', $key)->exists();
    }

    /**
     * Get all settings as key-value array.
     */
    public static function getAll(): array
    {
        $cacheKey = self::getCacheKey('all');

        return Cache::remember(
            $cacheKey,
            config('ai-translator.translation.cache_ttl', 3600),
            function () {
                $settings = self::all();
                $result = [];

                foreach ($settings as $setting) {
                    $result[$setting->key] = $setting->getValue();
                }

                return $result;
            }
        );
    }

    /**
     * Clear cache for a setting.
     */
    protected static function clearCache(string $key): void
    {
        $cacheKey = self::getCacheKey($key);
        Cache::forget($cacheKey);

        // Also clear the "all" cache
        $allCacheKey = self::getCacheKey('all');
        Cache::forget($allCacheKey);
    }

    /**
     * Get cache key.
     */
    protected static function getCacheKey(string $key): string
    {
        $prefix = config('ai-translator.translation.cache_prefix', 'ai_translator');

        return "{$prefix}.settings.{$key}";
    }

    /**
     * Scope to get only encrypted settings.
     */
    public function scopeEncrypted($query)
    {
        return $query->where('is_encrypted', true);
    }

    /**
     * Scope to get settings by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
