<?php

namespace Masum\AiTranslator\Services;

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MissingTranslationService
{
    /**
     * Find missing translations for a language
     *
     * @param string $languageCode The language code to check
     * @param string|null $group Optional group to filter by
     * @return Collection Collection of missing translation data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findMissing(string $languageCode, ?string $group = null): Collection
    {
        $language = Language::where('code', $languageCode)->firstOrFail();
        $defaultLanguage = Language::where('is_default', true)->firstOrFail();

        // Get all keys from default language
        $defaultKeysQuery = Translation::where('language_id', $defaultLanguage->id);

        if ($group) {
            $defaultKeysQuery->where('group', $group);
        }

        $defaultTranslations = $defaultKeysQuery->get();

        // Get existing translations for target language
        $existingKeysQuery = Translation::where('language_id', $language->id);

        if ($group) {
            $existingKeysQuery->where('group', $group);
        }

        $existingKeys = $existingKeysQuery->pluck('key')->toArray();

        // Find missing translations
        $missingTranslations = $defaultTranslations->filter(function ($translation) use ($existingKeys) {
            return !in_array($translation->key, $existingKeys);
        })->map(function ($translation) use ($languageCode) {
            return [
                'key' => $translation->key,
                'source_value' => $translation->value,
                'source_language' => $translation->language->code,
                'target_language' => $languageCode,
                'group' => $translation->group,
                'created_at' => $translation->created_at->toIso8601String(),
            ];
        });

        return $missingTranslations;
    }

    /**
     * Generate missing translation report for all languages
     *
     * @param string|null $group Optional group to filter by
     * @return array Comprehensive report of missing translations
     */
    public function generateReport(?string $group = null): array
    {
        $languages = Language::where('is_active', true)->get();
        $defaultLanguage = Language::where('is_default', true)->first();

        if (!$defaultLanguage) {
            return [
                'error' => 'No default language set',
                'generated_at' => now()->toIso8601String(),
            ];
        }

        $report = [
            'generated_at' => now()->toIso8601String(),
            'default_language' => $defaultLanguage->code,
            'filter' => $group ? ['group' => $group] : null,
            'languages' => [],
            'summary' => [],
        ];

        $defaultTotal = Translation::where('language_id', $defaultLanguage->id);
        if ($group) {
            $defaultTotal->where('group', $group);
        }
        $defaultTotalCount = $defaultTotal->count();

        foreach ($languages as $language) {
            if ($language->is_default) {
                continue;
            }

            $missing = $this->findMissing($language->code, $group);

            $total = Translation::where('language_id', $language->id);
            if ($group) {
                $total->where('group', $group);
            }
            $totalCount = $total->count();

            $completionPercentage = $defaultTotalCount > 0
                ? round(($totalCount / $defaultTotalCount) * 100, 2)
                : 0;

            $report['languages'][$language->code] = [
                'language' => [
                    'code' => $language->code,
                    'name' => $language->name,
                    'native_name' => $language->native_name,
                ],
                'total_translations' => $totalCount,
                'expected_translations' => $defaultTotalCount,
                'missing_count' => $missing->count(),
                'completion_percentage' => $completionPercentage,
                'missing_translations' => $missing->take(100)->toArray(), // Limit to first 100 for performance
            ];

            $report['summary'][] = [
                'language' => $language->code,
                'language_name' => $language->name,
                'missing' => $missing->count(),
                'completion' => $completionPercentage,
                'status' => $this->getStatusFromCompletion($completionPercentage),
            ];
        }

        // Sort summary by completion percentage (ascending)
        usort($report['summary'], fn($a, $b) => $a['completion'] <=> $b['completion']);

        return $report;
    }

    /**
     * Get status label based on completion percentage
     *
     * @param float $completionPercentage
     * @return string
     */
    protected function getStatusFromCompletion(float $completionPercentage): string
    {
        return match (true) {
            $completionPercentage >= 100 => 'complete',
            $completionPercentage >= 90 => 'excellent',
            $completionPercentage >= 75 => 'good',
            $completionPercentage >= 50 => 'fair',
            $completionPercentage >= 25 => 'poor',
            default => 'critical',
        };
    }

    /**
     * Get completion statistics for a language
     *
     * @param string $languageCode
     * @param string|null $group
     * @return array
     */
    public function getCompletionStats(string $languageCode, ?string $group = null): array
    {
        $language = Language::where('code', $languageCode)->firstOrFail();
        $defaultLanguage = Language::where('is_default', true)->firstOrFail();

        $totalQuery = Translation::where('language_id', $language->id);
        $defaultTotalQuery = Translation::where('language_id', $defaultLanguage->id);

        if ($group) {
            $totalQuery->where('group', $group);
            $defaultTotalQuery->where('group', $group);
        }

        $totalCount = $totalQuery->count();
        $defaultTotalCount = $defaultTotalQuery->count();
        $missingCount = $this->findMissing($languageCode, $group)->count();

        $completionPercentage = $defaultTotalCount > 0
            ? round(($totalCount / $defaultTotalCount) * 100, 2)
            : 0;

        return [
            'language' => [
                'code' => $language->code,
                'name' => $language->name,
            ],
            'total_translations' => $totalCount,
            'expected_translations' => $defaultTotalCount,
            'missing_count' => $missingCount,
            'completion_percentage' => $completionPercentage,
            'status' => $this->getStatusFromCompletion($completionPercentage),
        ];
    }

    /**
     * Get missing translations grouped by group
     *
     * @param string $languageCode
     * @return array
     */
    public function getMissingByGroup(string $languageCode): array
    {
        $language = Language::where('code', $languageCode)->firstOrFail();
        $defaultLanguage = Language::where('is_default', true)->firstOrFail();

        // Get all groups from default language
        $groups = Translation::where('language_id', $defaultLanguage->id)
            ->distinct()
            ->pluck('group')
            ->toArray();

        $result = [];

        foreach ($groups as $group) {
            $missing = $this->findMissing($languageCode, $group);

            $totalInGroup = Translation::where('language_id', $defaultLanguage->id)
                ->where('group', $group)
                ->count();

            $existingInGroup = Translation::where('language_id', $language->id)
                ->where('group', $group)
                ->count();

            $completionPercentage = $totalInGroup > 0
                ? round(($existingInGroup / $totalInGroup) * 100, 2)
                : 0;

            $result[$group] = [
                'group' => $group,
                'total_expected' => $totalInGroup,
                'total_existing' => $existingInGroup,
                'missing_count' => $missing->count(),
                'completion_percentage' => $completionPercentage,
                'status' => $this->getStatusFromCompletion($completionPercentage),
            ];
        }

        // Sort by completion percentage (ascending)
        uasort($result, fn($a, $b) => $a['completion_percentage'] <=> $b['completion_percentage']);

        return $result;
    }

    /**
     * Get languages with the most missing translations
     *
     * @param int $limit Number of languages to return
     * @return Collection
     */
    public function getLanguagesNeedingAttention(int $limit = 5): Collection
    {
        $languages = Language::where('is_active', true)
            ->where('is_default', false)
            ->get();

        $languagesWithStats = $languages->map(function ($language) {
            $stats = $this->getCompletionStats($language->code);
            return [
                'code' => $language->code,
                'name' => $language->name,
                'missing_count' => $stats['missing_count'],
                'completion_percentage' => $stats['completion_percentage'],
                'status' => $stats['status'],
            ];
        });

        // Sort by missing count (descending) and limit
        return $languagesWithStats->sortByDesc('missing_count')->take($limit)->values();
    }

    /**
     * Check if a specific key is missing in a language
     *
     * @param string $key
     * @param string $languageCode
     * @return bool
     */
    public function isKeyMissing(string $key, string $languageCode): bool
    {
        $language = Language::where('code', $languageCode)->first();

        if (!$language) {
            return true;
        }

        return !Translation::where('key', $key)
            ->where('language_id', $language->id)
            ->exists();
    }

    /**
     * Get all keys that exist in default language but missing in target
     *
     * @param string $languageCode
     * @param string|null $group
     * @return array Array of missing keys
     */
    public function getMissingKeys(string $languageCode, ?string $group = null): array
    {
        $missing = $this->findMissing($languageCode, $group);
        return $missing->pluck('key')->toArray();
    }
}
