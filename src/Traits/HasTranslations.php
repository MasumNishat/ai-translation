<?php

namespace Masum\AiTranslator\Traits;

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Services\TranslationService;

trait HasTranslations
{
    /**
     * Get the translatable fields for this model.
     * Override this property in your model.
     */
    protected array $translatableFields = [];

    /**
     * Get the translation group for this model.
     * Override this method in your model.
     */
    public function getTranslationGroup(): string
    {
        return strtolower(class_basename($this)).'s';
    }

    /**
     * Get the translation key prefix for this model.
     * Override this method to customize.
     */
    public function getTranslationKeyPrefix(): string
    {
        // Use slug if available, otherwise use ID
        return $this->slug ?? $this->getKey();
    }

    /**
     * Save translations for this model.
     *
     * @param  array  $translations  Format: ['en' => ['name' => '...', 'description' => '...'], 'bn' => [...]]
     * @param  bool  $autoTranslate  Whether to auto-translate missing languages
     */
    public function saveTranslations(array $translations, bool $autoTranslate = false): void
    {
        $group = $this->getTranslationGroup();
        $prefix = $this->getTranslationKeyPrefix();

        foreach ($translations as $locale => $fields) {
            foreach ($fields as $field => $value) {
                if (in_array($field, $this->translatableFields)) {
                    $key = "{$prefix}.{$field}";
                    trans_set($key, $value, $locale, $group);
                }
            }
        }

        // Auto-translate if requested
        if ($autoTranslate && !empty($translations)) {
            $sourceLocale = array_key_first($translations);
            $targetLocales = Language::getActive()
                ->pluck('code')
                ->reject(fn ($code) => $code === $sourceLocale)
                ->toArray();

            if (!empty($targetLocales)) {
                $this->autoTranslateFields(
                    array_keys($translations[$sourceLocale]),
                    $sourceLocale
                );
            }
        }
    }

    /**
     * Get translation for a specific field.
     *
     * @param  string  $field  Field name (e.g., 'name', 'description')
     * @param  string|null  $locale  Language code (defaults to app locale)
     * @return string Translated value or fallback
     */
    public function getTranslation(string $field, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $group = $this->getTranslationGroup();
        $prefix = $this->getTranslationKeyPrefix();
        $key = "{$prefix}.{$field}";

        // Get translation with fallback to model attribute
        $fallback = $this->getAttribute($field) ?? '';

        return __t($key, $group, $fallback, $locale);
    }

    /**
     * Get all translations for a field across all languages.
     *
     * @param  string  $field  Field name
     * @return array Format: ['en' => 'value', 'bn' => 'value', ...]
     */
    public function getTranslations(string $field): array
    {
        $group = $this->getTranslationGroup();
        $prefix = $this->getTranslationKeyPrefix();
        $key = "{$prefix}.{$field}";

        $activeLanguages = Language::getActive();
        $translations = [];

        foreach ($activeLanguages as $language) {
            $translations[$language->code] = __t($key, $group, '', $language->code);
        }

        return $translations;
    }

    /**
     * Auto-translate fields using AI.
     *
     * @param  array  $fields  Field names to translate
     * @param  string  $sourceLocale  Source language code
     */
    public function autoTranslateFields(array $fields, string $sourceLocale): void
    {
        $group = $this->getTranslationGroup();
        $prefix = $this->getTranslationKeyPrefix();
        $service = app(TranslationService::class);

        $targetLocales = Language::getActive()
            ->pluck('code')
            ->reject(fn ($code) => $code === $sourceLocale)
            ->toArray();

        foreach ($fields as $field) {
            if (!in_array($field, $this->translatableFields)) {
                continue;
            }

            $key = "{$prefix}.{$field}";
            $sourceValue = __t($key, $group, '', $sourceLocale);

            if (!empty($sourceValue)) {
                $service->autoTranslate(
                    $key,
                    $sourceValue,
                    $sourceLocale,
                    $targetLocales,
                    $group
                );
            }
        }
    }

    /**
     * Delete all translations for this model.
     */
    public function deleteTranslations(): void
    {
        $group = $this->getTranslationGroup();
        $prefix = $this->getTranslationKeyPrefix();

        foreach ($this->translatableFields as $field) {
            $key = "{$prefix}.{$field}";

            Translation::where('group', $group)
                ->where('key', $key)
                ->delete();
        }
    }

    /**
     * Check if a field has translations.
     *
     * @param  string  $field  Field name
     * @return bool
     */
    public function hasTranslations(string $field): bool
    {
        $group = $this->getTranslationGroup();
        $prefix = $this->getTranslationKeyPrefix();
        $key = "{$prefix}.{$field}";

        return Translation::where('group', $group)
            ->where('key', $key)
            ->exists();
    }

    /**
     * Magic method to get translated attributes.
     * Call like: $model->getTranslatedName() or $model->getTranslatedDescription($locale)
     */
    public function __call($method, $parameters)
    {
        // Check if method starts with "getTranslated"
        if (str_starts_with($method, 'getTranslated')) {
            $field = lcfirst(substr($method, 13)); // Remove "getTranslated"
            $field = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $field)); // Convert camelCase to snake_case

            if (in_array($field, $this->translatableFields)) {
                $locale = $parameters[0] ?? null;

                return $this->getTranslation($field, $locale);
            }
        }

        return parent::__call($method, $parameters);
    }
}
