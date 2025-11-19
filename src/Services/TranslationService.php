<?php

namespace Masum\AiTranslator\Services;

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TranslationService
{
    public function __construct(
        protected GeminiTranslationService $geminiService
    ) {
    }

    /**
     * Get translation with smart caching (cache → db → ai).
     * This is a wrapper around Translation::get() for service-based access.
     */
    public function get(
        string $key,
        ?string $locale = null,
        ?string $group = null,
        ?string $default = null
    ): string {
        return Translation::get($key, $locale, $group, $default);
    }

    /**
     * Set or update a translation.
     */
    public function set(
        string $key,
        string $value,
        ?string $locale = null,
        ?string $group = null,
        ?int $userId = null
    ): Translation {
        return Translation::set($key, $value, $locale, $group, $userId);
    }

    /**
     * Auto-translate a key to multiple target languages using AI.
     *
     * @param  string  $key  Translation key
     * @param  string  $sourceValue  Source text value
     * @param  string  $sourceLang  Source language code
     * @param  array  $targetLangs  Target language codes
     * @param  string|null  $group  Translation group
     * @param  int|null  $userId  User ID for audit trail
     * @return array Array of created translations ['bn' => Translation, 'fr' => Translation, ...]
     */
    public function autoTranslate(
        string $key,
        string $sourceValue,
        string $sourceLang,
        array $targetLangs,
        ?string $group = null,
        ?int $userId = null
    ): array {
        // First, save the source translation
        $this->set($key, $sourceValue, $sourceLang, $group, $userId);

        // Translate to target languages using AI
        $translations = $this->geminiService->translate(
            $sourceValue,
            $sourceLang,
            $targetLangs
        );

        $results = [];

        foreach ($translations as $targetLang => $translatedValue) {
            try {
                $language = Language::getByCode($targetLang);

                if (!$language) {
                    logger()->warning("Language '{$targetLang}' not found, skipping.");
                    continue;
                }

                $translation = Translation::updateOrCreate(
                    [
                        'language_id' => $language->id,
                        'group' => $group,
                        'key' => $key,
                    ],
                    [
                        'value' => $translatedValue,
                        'is_active' => true,
                        'is_auto_translated' => true,
                        'translated_by_user_id' => $userId,
                    ]
                );

                // Cache the translation immediately
                $cacheKey = Translation::getCacheKey($key, $targetLang, $group);
                Cache::put($cacheKey, $translatedValue, config('ai-translator.translation.cache_ttl', 3600));

                $results[$targetLang] = $translation;
            } catch (\Exception $e) {
                logger()->error('Failed to save AI translation', [
                    'key' => $key,
                    'language' => $targetLang,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Batch translate multiple keys.
     *
     * @param  array  $keyValues  Array of ['key' => 'value']
     * @param  string  $sourceLang  Source language code
     * @param  array  $targetLangs  Target language codes
     * @param  string|null  $group  Translation group
     * @return array Results of translations
     */
    public function batchTranslate(
        array $keyValues,
        string $sourceLang,
        array $targetLangs,
        ?string $group = null
    ): array {
        DB::beginTransaction();

        try {
            // Save source translations first
            foreach ($keyValues as $key => $value) {
                $this->set($key, $value, $sourceLang, $group);
            }

            // Get AI translations for all texts
            $aiTranslations = $this->geminiService->batchTranslate(
                $keyValues,
                $sourceLang,
                $targetLangs
            );

            $results = [];

            // Save all translations
            foreach ($aiTranslations as $key => $translations) {
                $results[$key] = [];

                foreach ($translations as $targetLang => $translatedValue) {
                    $language = Language::getByCode($targetLang);

                    if (!$language) {
                        continue;
                    }

                    $translation = Translation::updateOrCreate(
                        [
                            'language_id' => $language->id,
                            'group' => $group,
                            'key' => $key,
                        ],
                        [
                            'value' => $translatedValue,
                            'is_active' => true,
                            'is_auto_translated' => true,
                        ]
                    );

                    $results[$key][$targetLang] = $translation;
                }
            }

            DB::commit();

            return $results;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Get all translations for a specific language.
     */
    public function getAllForLanguage(string $languageCode): array
    {
        return Translation::getAllForLanguage($languageCode);
    }

    /**
     * Clear cache for a specific translation or all translations.
     */
    public function clearCache(?string $key = null, ?string $locale = null, ?string $group = null): void
    {
        if ($key && $locale) {
            // Clear specific translation cache
            $cacheKey = Translation::getCacheKey($key, $locale, $group);
            Cache::forget($cacheKey);
        } elseif ($locale) {
            // Clear all translations for a language
            $cacheKey = Translation::getCacheKey('all', $locale);
            Cache::forget($cacheKey);
        } else {
            // Clear all translation caches
            $prefix = config('ai-translator.translation.cache_prefix', 'ai_translator');
            Cache::flush(); // Note: This clears ALL cache, consider using tags in production
        }
    }

    /**
     * Sync missing translations - find keys that don't have translations for all active languages.
     */
    public function syncMissingTranslations(?string $group = null): array
    {
        $activeLanguages = Language::getActive();
        $missing = [];

        // Get all unique keys
        $query = Translation::query()
            ->select('key', 'group')
            ->distinct();

        if ($group !== null) {
            $query->where('group', $group);
        }

        $keys = $query->get();

        foreach ($keys as $keyData) {
            foreach ($activeLanguages as $language) {
                $exists = Translation::where('key', $keyData->key)
                    ->where('group', $keyData->group)
                    ->where('language_id', $language->id)
                    ->exists();

                if (!$exists) {
                    $missing[] = [
                        'key' => $keyData->key,
                        'group' => $keyData->group,
                        'language' => $language->code,
                    ];
                }
            }
        }

        return $missing;
    }

    /**
     * Delete a translation.
     */
    public function delete(int $translationId): bool
    {
        $translation = Translation::find($translationId);

        if (!$translation) {
            return false;
        }

        return $translation->delete();
    }

    /**
     * Get translation history.
     */
    public function getHistory(int $translationId, int $limit = 50): \Illuminate\Support\Collection
    {
        return \Masum\AiTranslator\Models\TranslationHistory::forTranslation($translationId)
            ->limit($limit)
            ->get();
    }

    /**
     * Get available groups.
     */
    public function getAvailableGroups(): array
    {
        return Translation::getAvailableGroups();
    }

    /**
     * Search translations.
     */
    public function search(
        ?string $query = null,
        ?string $languageCode = null,
        ?string $group = null,
        bool $activeOnly = true,
        int $perPage = 50
    ) {
        $builder = Translation::query()->with(['language', 'translatedBy:id,name,email']);

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('key', 'like', "%{$query}%")
                    ->orWhere('value', 'like', "%{$query}%");
            });
        }

        if ($languageCode) {
            $builder->byLanguage($languageCode);
        }

        if ($group !== null) {
            $builder->byGroup($group);
        }

        if ($activeOnly) {
            $builder->active();
        }

        return $builder->orderBy('key')->paginate($perPage);
    }
}
