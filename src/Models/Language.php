<?php

namespace Masum\AiTranslator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Language extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'direction',
        'is_active',
        'is_default',
        'country_code',
        'region',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Masum\AiTranslator\Database\Factories\LanguageFactory::new();
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::saved(function ($language) {
            self::clearCache();

            // If this language is set as default, unset all other defaults
            if ($language->is_default) {
                self::where('id', '!=', $language->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        static::deleted(function () {
            self::clearCache();
        });
    }

    /**
     * Get translations for this language.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }

    /**
     * Scope to get only active languages.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive languages.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to get default language.
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope by direction (ltr/rtl).
     */
    public function scopeByDirection($query, string $direction)
    {
        return $query->where('direction', $direction);
    }

    /**
     * Scope by region.
     */
    public function scopeByRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Get all active languages from cache or database.
     */
    public static function getActive(): \Illuminate\Support\Collection
    {
        return Cache::remember(
            self::getCacheKey('active'),
            config('ai-translator.translation.cache_ttl', 3600),
            fn () => self::active()->orderBy('name')->get()
        );
    }

    /**
     * Get the default language.
     */
    public static function getDefault(): ?self
    {
        return Cache::remember(
            self::getCacheKey('default'),
            config('ai-translator.translation.cache_ttl', 3600),
            fn () => self::where('is_default', true)->first()
        );
    }

    /**
     * Get language by code.
     */
    public static function getByCode(string $code): ?self
    {
        return Cache::remember(
            self::getCacheKey("code.{$code}"),
            config('ai-translator.translation.cache_ttl', 3600),
            fn () => self::where('code', $code)->first()
        );
    }

    /**
     * Get country information for this language.
     */
    public function getCountryInfo(): array
    {
        $mapping = config("ai-translator.language_country_map.{$this->code}", []);

        return [
            'language_code' => $this->code,
            'language_name' => $this->name,
            'country' => $this->country_code ? $mapping['country'] ?? null : null,
            'country_code' => $this->country_code,
            'region' => $this->region ?? $mapping['region'] ?? null,
        ];
    }

    /**
     * Clear all language caches.
     */
    public static function clearCache(): void
    {
        $prefix = config('ai-translator.translation.cache_prefix', 'ai_translator');

        Cache::forget("{$prefix}.languages.active");
        Cache::forget("{$prefix}.languages.default");

        // Clear individual language code caches
        self::all()->each(function ($language) use ($prefix) {
            Cache::forget("{$prefix}.languages.code.{$language->code}");
        });
    }

    /**
     * Get cache key for languages.
     */
    protected static function getCacheKey(string $suffix): string
    {
        $prefix = config('ai-translator.translation.cache_prefix', 'ai_translator');

        return "{$prefix}.languages.{$suffix}";
    }

    /**
     * Check if this is an RTL language.
     */
    public function isRtl(): bool
    {
        return $this->direction === 'rtl';
    }

    /**
     * Activate the language.
     */
    public function activate(): bool
    {
        $this->is_active = true;

        return $this->save();
    }

    /**
     * Deactivate the language.
     */
    public function deactivate(): bool
    {
        // Prevent deactivating default language
        if ($this->is_default) {
            return false;
        }

        $this->is_active = false;

        return $this->save();
    }

    /**
     * Set as default language.
     */
    public function setAsDefault(): bool
    {
        $this->is_default = true;
        $this->is_active = true;

        return $this->save();
    }
}
