<?php

namespace Masum\AiTranslator\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Translation extends Model
{
    use HasFactory;
    protected $fillable = [
        'language_id',
        'group',
        'key',
        'value',
        'is_active',
        'is_auto_translated',
        'translated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_auto_translated' => 'boolean',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Masum\AiTranslator\Database\Factories\TranslationFactory::new();
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::saved(function ($translation) {
            $translation->clearCache();
            $translation->recordHistory('updated');
        });

        static::created(function ($translation) {
            $translation->recordHistory('created');
        });

        static::deleted(function ($translation) {
            $translation->clearCache();
            $translation->recordHistory('deleted');
        });
    }

    /**
     * Get the language that owns the translation.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the translation histories.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(TranslationHistory::class);
    }

    /**
     * Scope to get only active translations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by language code.
     */
    public function scopeByLanguage($query, string $languageCode)
    {
        return $query->whereHas('language', function ($q) use ($languageCode) {
            $q->where('code', $languageCode);
        });
    }

    /**
     * Scope by group.
     */
    public function scopeByGroup($query, ?string $group)
    {
        if ($group === null) {
            return $query->whereNull('group');
        }

        return $query->where('group', $group);
    }

    /**
     * Scope by key.
     */
    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    /**
     * Get translation with smart caching (cache → db → ai).
     * This is the core method that implements the retrieval flow.
     */
    public static function get(
        string $key,
        ?string $languageCode = null,
        ?string $group = null,
        ?string $default = null
    ): string {
        $languageCode = $languageCode ?? app()->getLocale();
        $cacheKey = self::getCacheKey($key, $languageCode, $group);

        // Step 1: Check cache
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Step 2: Check database
        $language = Language::getByCode($languageCode);

        if ($language) {
            $translation = self::where('language_id', $language->id)
                ->where('key', $key)
                ->when($group !== null, fn ($q) => $q->where('group', $group))
                ->when($group === null, fn ($q) => $q->whereNull('group'))
                ->where('is_active', true)
                ->first();

            if ($translation) {
                // Found in DB, cache it and return
                self::setCacheValue($cacheKey, $translation->value);

                return $translation->value;
            }
        }

        // Step 3: Try AI translation if enabled
        if (config('ai-translator.translation.auto_translate_enabled', true)) {
            $aiTranslation = self::translateWithAi($key, $languageCode, $group, $default);

            if ($aiTranslation !== null) {
                return $aiTranslation;
            }
        }

        // Step 4: Fallback chain
        $fallbackLocale = config('ai-translator.translation.fallback_locale', 'en');

        if ($languageCode !== $fallbackLocale) {
            $fallbackValue = self::get($key, $fallbackLocale, $group, null);

            if ($fallbackValue !== $key) {
                return $fallbackValue;
            }
        }

        // Return default or key itself
        return $default ?? $key;
    }

    /**
     * Set or update a translation.
     */
    public static function set(
        string $key,
        string $value,
        ?string $languageCode = null,
        ?string $group = null,
        ?int $userId = null
    ): self {
        $languageCode = $languageCode ?? app()->getLocale();
        $language = Language::getByCode($languageCode);

        if (!$language) {
            throw new \InvalidArgumentException("Language '{$languageCode}' not found.");
        }

        $translation = self::updateOrCreate(
            [
                'language_id' => $language->id,
                'group' => $group,
                'key' => $key,
            ],
            [
                'value' => $value,
                'translated_by_user_id' => $userId ?? auth()->id(),
                'is_active' => true,
            ]
        );

        // Clear cache immediately after save
        $translation->clearCache();

        return $translation;
    }

    /**
     * Translate using AI and save to database.
     */
    protected static function translateWithAi(
        string $key,
        string $targetLanguage,
        ?string $group,
        ?string $sourceText
    ): ?string {
        // Get source text if not provided
        if ($sourceText === null) {
            $fallbackLocale = config('ai-translator.translation.fallback_locale', 'en');
            $sourceText = self::get($key, $fallbackLocale, $group, null);

            // If source text is same as key, nothing to translate
            if ($sourceText === $key) {
                return null;
            }
        }

        try {
            // Use the GeminiTranslationService
            $geminiService = app(\Masum\AiTranslator\Services\GeminiTranslationService::class);
            $fallbackLocale = config('ai-translator.translation.fallback_locale', 'en');

            $translations = $geminiService->translate(
                $sourceText,
                $fallbackLocale,
                [$targetLanguage]
            );

            if (isset($translations[$targetLanguage])) {
                $translatedValue = $translations[$targetLanguage];

                // Save to database
                $language = Language::getByCode($targetLanguage);

                if ($language) {
                    $translation = self::create([
                        'language_id' => $language->id,
                        'group' => $group,
                        'key' => $key,
                        'value' => $translatedValue,
                        'is_active' => true,
                        'is_auto_translated' => true,
                    ]);

                    // Cache the translation
                    $cacheKey = self::getCacheKey($key, $targetLanguage, $group);
                    self::setCacheValue($cacheKey, $translatedValue);

                    return $translatedValue;
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail
            logger()->error('AI translation failed', [
                'key' => $key,
                'language' => $targetLanguage,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get all translations for a specific language.
     */
    public static function getAllForLanguage(string $languageCode): array
    {
        $cacheKey = self::getCacheKey('all', $languageCode);

        return Cache::remember(
            $cacheKey,
            config('ai-translator.translation.cache_ttl', 3600),
            function () use ($languageCode) {
                $language = Language::getByCode($languageCode);

                if (!$language) {
                    return [];
                }

                $translations = self::where('language_id', $language->id)
                    ->where('is_active', true)
                    ->get();

                $result = [];

                foreach ($translations as $translation) {
                    $fullKey = $translation->group
                        ? "{$translation->group}.{$translation->key}"
                        : $translation->key;

                    $result[$fullKey] = $translation->value;
                }

                return $result;
            }
        );
    }

    /**
     * Clear cache for this translation.
     */
    public function clearCache(): void
    {
        if ($this->language) {
            $cacheKey = self::getCacheKey($this->key, $this->language->code, $this->group);
            Cache::forget($cacheKey);

            // Also clear the "all" cache for this language
            $allCacheKey = self::getCacheKey('all', $this->language->code);
            Cache::forget($allCacheKey);
        }
    }

    /**
     * Record history for this translation.
     */
    protected function recordHistory(string $changeType): void
    {
        if (!config('ai-translator.audit.enabled', true)) {
            return;
        }

        TranslationHistory::create([
            'translation_id' => $this->id,
            'old_value' => $this->getOriginal('value'),
            'new_value' => $this->value,
            'changed_by_user_id' => $this->translated_by_user_id ?? auth()->id(),
            'change_type' => $changeType,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    /**
     * Get cache key for a translation.
     */
    protected static function getCacheKey(string $key, string $languageCode, ?string $group = null): string
    {
        $prefix = config('ai-translator.translation.cache_prefix', 'ai_translator');
        $groupPart = $group ? ".{$group}" : '';

        return "{$prefix}{$groupPart}.{$key}.{$languageCode}";
    }

    /**
     * Set cache value with TTL.
     */
    protected static function setCacheValue(string $cacheKey, string $value): void
    {
        $ttl = config('ai-translator.translation.cache_ttl', 3600);
        Cache::put($cacheKey, $value, $ttl);
    }

    /**
     * Get available groups.
     */
    public static function getAvailableGroups(): array
    {
        $cacheKey = config('ai-translator.translation.cache_prefix', 'ai_translator').'.groups';

        return Cache::remember(
            $cacheKey,
            config('ai-translator.translation.cache_ttl', 3600),
            fn () => self::distinct()
                ->whereNotNull('group')
                ->orderBy('group')
                ->pluck('group')
                ->toArray()
        );
    }
}
