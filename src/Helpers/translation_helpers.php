<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Services\TranslationService;

if (!function_exists('__t')) {
    /**
     * Get translation with smart caching (cache → db → ai).
     *
     * @param  string  $key  Translation key
     * @param  string|null  $group  Translation group/namespace
     * @param  string|null  $default  Default value if translation not found
     * @param  string|null  $locale  Language code (defaults to app locale)
     * @return string Translated text
     */
    function __t(string $key, ?string $group = null, ?string $default = null, ?string $locale = null): string
    {
        return Translation::get($key, $locale, $group, $default);
    }
}

if (!function_exists('trans_set')) {
    /**
     * Set or update a translation.
     *
     * @param  string  $key  Translation key
     * @param  string  $value  Translation value
     * @param  string|null  $locale  Language code (defaults to app locale)
     * @param  string|null  $group  Translation group/namespace
     * @param  int|null  $userId  User ID for audit trail
     * @return Translation
     */
    function trans_set(
        string $key,
        string $value,
        ?string $locale = null,
        ?string $group = null,
        ?int $userId = null
    ): Translation {
        return Translation::set($key, $value, $locale, $group, $userId);
    }
}

if (!function_exists('trans_auto')) {
    /**
     * Auto-translate a key to multiple languages using AI.
     *
     * @param  string  $key  Translation key
     * @param  string  $value  Source text value
     * @param  string  $sourceLang  Source language code
     * @param  array  $targetLangs  Target language codes
     * @param  string|null  $group  Translation group
     * @return array Array of translations
     */
    function trans_auto(
        string $key,
        string $value,
        string $sourceLang,
        array $targetLangs,
        ?string $group = null
    ): array {
        $service = app(TranslationService::class);

        return $service->autoTranslate($key, $value, $sourceLang, $targetLangs, $group);
    }
}

if (!function_exists('trans_all')) {
    /**
     * Get all translations for a specific language.
     *
     * @param  string|null  $locale  Language code (defaults to app locale)
     * @return array All translations as key-value pairs
     */
    function trans_all(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();

        return Translation::getAllForLanguage($locale);
    }
}

if (!function_exists('trans_clear_cache')) {
    /**
     * Clear translation cache.
     *
     * @param  string|null  $key  Translation key (null to clear all)
     * @param  string|null  $locale  Language code (null to clear all)
     * @param  string|null  $group  Translation group
     */
    function trans_clear_cache(?string $key = null, ?string $locale = null, ?string $group = null): void
    {
        $service = app(TranslationService::class);
        $service->clearCache($key, $locale, $group);
    }
}

if (!function_exists('available_languages')) {
    /**
     * Get all active languages.
     *
     * @return \Illuminate\Support\Collection
     */
    function available_languages(): \Illuminate\Support\Collection
    {
        return Language::getActive();
    }
}

if (!function_exists('default_language')) {
    /**
     * Get the default language.
     *
     * @return Language|null
     */
    function default_language(): ?Language
    {
        return Language::getDefault();
    }
}

if (!function_exists('language_to_country')) {
    /**
     * Get country information for a language code.
     *
     * @param  string  $langCode  Language code (e.g., 'bn', 'en')
     * @return array Country information
     */
    function language_to_country(string $langCode): array
    {
        $language = Language::getByCode($langCode);

        if (!$language) {
            return [
                'language_code' => $langCode,
                'language_name' => null,
                'country' => null,
                'country_code' => null,
                'region' => null,
            ];
        }

        return $language->getCountryInfo();
    }
}

if (!function_exists('trans_number')) {
    /**
     * Translate individual digits (0-9) for a locale.
     * Useful for Bengali and other languages with different numeral systems.
     *
     * @param  int|string  $number  Number to translate
     * @param  string|null  $locale  Language code (defaults to app locale)
     * @return string Translated number
     */
    function trans_number($number, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $number = (string) $number;

        // Map of digits in different locales
        $digitMaps = [
            'bn' => ['0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪', '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯'],
            'ar' => ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩'],
            'fa' => ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹'],
        ];

        if (!isset($digitMaps[$locale])) {
            return $number;
        }

        return strtr($number, $digitMaps[$locale]);
    }
}

if (!function_exists('trans_time')) {
    /**
     * Translate time format for a locale.
     *
     * @param  string  $time  Time in format like "10:30 AM"
     * @param  string|null  $locale  Language code (defaults to app locale)
     * @return string Translated time
     */
    function trans_time(string $time, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        // Translate AM/PM
        $time = str_replace('AM', __t('time.am', 'common', 'AM', $locale), $time);
        $time = str_replace('PM', __t('time.pm', 'common', 'PM', $locale), $time);

        // Translate numbers (for Bengali, etc.)
        $time = trans_number($time, $locale);

        return $time;
    }
}

if (!function_exists('trans_working_hours')) {
    /**
     * Translate working hours display.
     *
     * @param  string  $days  Days of the week
     * @param  string  $startTime  Start time
     * @param  string  $endTime  End time
     * @param  string|null  $locale  Language code (defaults to app locale)
     * @return string Translated working hours
     */
    function trans_working_hours(string $days, string $startTime, string $endTime, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        $days = __t('days.'.$days, 'common', $days, $locale);
        $startTime = trans_time($startTime, $locale);
        $endTime = trans_time($endTime, $locale);

        return "{$days}: {$startTime} - {$endTime}";
    }
}

if (!function_exists('trans_placeholders')) {
    /**
     * Replace placeholders in text with translations.
     *
     * @param  string  $text  Text with placeholders (e.g., "Hello {{name}}")
     * @param  array  $replacements  Key-value pairs for replacements
     * @param  string|null  $locale  Language code (defaults to app locale)
     * @return string Text with replaced placeholders
     */
    function trans_placeholders(string $text, array $replacements, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        foreach ($replacements as $key => $value) {
            // Translate the value if it's a translation key
            if (is_string($value) && strpos($value, '.') !== false) {
                $parts = explode('.', $value, 2);

                if (count($parts) === 2) {
                    $value = __t($parts[1], $parts[0], $value, $locale);
                }
            }

            // Replace both {{key}} and :key formats
            $text = str_replace(['{{'.$key.'}}', ':'.$key], $value, $text);
        }

        return $text;
    }
}

if (!function_exists('trans_history')) {
    /**
     * Get translation history for a specific translation.
     *
     * @param  int  $translationId  Translation ID
     * @param  int  $limit  Number of history entries to retrieve
     * @return \Illuminate\Support\Collection
     */
    function trans_history(int $translationId, int $limit = 50): \Illuminate\Support\Collection
    {
        $service = app(TranslationService::class);

        return $service->getHistory($translationId, $limit);
    }
}

if (!function_exists('trans_groups')) {
    /**
     * Get all available translation groups.
     *
     * @return array List of group names
     */
    function trans_groups(): array
    {
        return Translation::getAvailableGroups();
    }
}

if (!function_exists('ai_trans')) {
    /**
     * Translate the given key with AI fallback (alias for __t).
     *
     * @param  string  $key  Translation key
     * @param  array  $replace  Replacement values
     * @param  string|null  $locale  Language code
     * @return string
     */
    function ai_trans(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $translation = Translation::get($key, $locale);

        // Apply replacements if any
        foreach ($replace as $key => $value) {
            $translation = str_replace([':'.$key, '{'.$key.'}', '{{'.$key.'}}'], $value, $translation);
        }

        return $translation;
    }
}

if (!function_exists('ai_trans_choice')) {
    /**
     * Translate with pluralization.
     *
     * @param  string  $key  Translation key
     * @param  int  $count  Count for pluralization
     * @param  array  $replace  Replacement values
     * @param  string|null  $locale  Language code
     * @return string
     */
    function ai_trans_choice(string $key, int $count, array $replace = [], ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        // Simple pluralization logic
        $replace['count'] = $count;
        $pluralKey = $count === 1 ? "{$key}.singular" : "{$key}.plural";

        $translation = Translation::get($pluralKey, $locale);

        // Apply replacements
        foreach ($replace as $replaceKey => $value) {
            $translation = str_replace([':'.$replaceKey, '{'.$replaceKey.'}', '{{'.$replaceKey.'}}'], $value, $translation);
        }

        return $translation;
    }
}

if (!function_exists('ai_has_trans')) {
    /**
     * Check if translation exists.
     *
     * @param  string  $key  Translation key
     * @param  string|null  $locale  Language code
     * @return bool
     */
    function ai_has_trans(string $key, ?string $locale = null): bool
    {
        $locale = $locale ?? app()->getLocale();
        $language = Language::where('code', $locale)->first();

        if (!$language) {
            return false;
        }

        return Translation::where('key', $key)
            ->where('language_id', $language->id)
            ->exists();
    }
}

if (!function_exists('ai_trans_array')) {
    /**
     * Get translations for multiple keys.
     *
     * @param  array  $keys  Translation keys
     * @param  string|null  $locale  Language code
     * @return array
     */
    function ai_trans_array(array $keys, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $language = Language::where('code', $locale)->first();

        if (!$language) {
            return array_fill_keys($keys, '');
        }

        $translations = Translation::where('language_id', $language->id)
            ->whereIn('key', $keys)
            ->pluck('value', 'key')
            ->toArray();

        return array_merge(array_fill_keys($keys, ''), $translations);
    }
}

if (!function_exists('ai_trans_group')) {
    /**
     * Get all translations for a group.
     *
     * @param  string  $group  Translation group
     * @param  string|null  $locale  Language code
     * @return array
     */
    function ai_trans_group(string $group, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $language = Language::where('code', $locale)->first();

        if (!$language) {
            return [];
        }

        return Translation::where('language_id', $language->id)
            ->where('group', $group)
            ->pluck('value', 'key')
            ->toArray();
    }
}

if (!function_exists('ai_languages')) {
    /**
     * Get all active languages.
     *
     * @param  bool  $activeOnly  Only active languages
     * @return \Illuminate\Support\Collection
     */
    function ai_languages(bool $activeOnly = true): \Illuminate\Support\Collection
    {
        $query = Language::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }
}

if (!function_exists('ai_default_language')) {
    /**
     * Get default language.
     *
     * @return Language|null
     */
    function ai_default_language(): ?Language
    {
        return Language::where('is_default', true)->first();
    }
}

if (!function_exists('ai_current_language')) {
    /**
     * Get current language based on locale.
     *
     * @return Language|null
     */
    function ai_current_language(): ?Language
    {
        $locale = app()->getLocale();
        return Language::where('code', $locale)->first();
    }
}

if (!function_exists('ai_set_language')) {
    /**
     * Set application locale.
     *
     * @param  string  $languageCode  Language code
     * @return bool
     */
    function ai_set_language(string $languageCode): bool
    {
        $language = Language::where('code', $languageCode)
            ->where('is_active', true)
            ->first();

        if (!$language) {
            return false;
        }

        app()->setLocale($languageCode);
        session(['language' => $languageCode]);

        return true;
    }
}

if (!function_exists('ai_trans_missing')) {
    /**
     * Get count of missing translations for a language.
     *
     * @param  string  $languageCode  Language code
     * @return int
     */
    function ai_trans_missing(string $languageCode): int
    {
        $defaultLanguage = Language::where('is_default', true)->first();
        $targetLanguage = Language::where('code', $languageCode)->first();

        if (!$defaultLanguage || !$targetLanguage) {
            return 0;
        }

        $defaultCount = Translation::where('language_id', $defaultLanguage->id)->count();
        $targetCount = Translation::where('language_id', $targetLanguage->id)->count();

        return max(0, $defaultCount - $targetCount);
    }
}
