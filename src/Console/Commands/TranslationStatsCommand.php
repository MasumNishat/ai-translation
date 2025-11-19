<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Facades\DB;

class TranslationStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translator:stats
                          {--language= : Show stats for specific language}
                          {--detailed : Show detailed statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display translation statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📊 Translation Statistics');
        $this->newLine();

        $languageCode = $this->option('language');
        $detailed = $this->option('detailed');

        if ($languageCode) {
            return $this->showLanguageStats($languageCode, $detailed);
        }

        return $this->showOverallStats($detailed);
    }

    /**
     * Show overall statistics
     */
    protected function showOverallStats(bool $detailed = false): int
    {
        $totalLanguages = Language::count();
        $activeLanguages = Language::where('is_active', true)->count();
        $totalTranslations = Translation::count();
        $totalGroups = Translation::distinct('group')->count();

        // Basic stats
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Languages', $totalLanguages],
                ['Active Languages', $activeLanguages],
                ['Total Translations', number_format($totalTranslations)],
                ['Translation Groups', $totalGroups],
            ]
        );

        $this->newLine();

        // Per-language breakdown
        $this->info('📋 Per-Language Breakdown:');
        $this->newLine();

        $languages = Language::withCount('translations')->get();

        $rows = $languages->map(function ($language) {
            $status = $language->is_active ? '✓' : '✗';
            $default = $language->is_default ? '⭐' : '';

            return [
                $language->code,
                $language->name,
                $status,
                $default,
                number_format($language->translations_count),
            ];
        });

        $this->table(
            ['Code', 'Name', 'Active', 'Default', 'Translations'],
            $rows
        );

        if ($detailed) {
            $this->newLine();
            $this->showDetailedStats();
        }

        return Command::SUCCESS;
    }

    /**
     * Show language-specific statistics
     */
    protected function showLanguageStats(string $languageCode, bool $detailed = false): int
    {
        $language = Language::where('code', $languageCode)->first();

        if (!$language) {
            $this->error("✗ Language '{$languageCode}' not found");
            return Command::FAILURE;
        }

        $this->info("Language: {$language->name} ({$language->code})");
        $this->newLine();

        $totalTranslations = Translation::where('language_id', $language->id)->count();
        $activeTranslations = Translation::where('language_id', $language->id)
            ->where('is_active', true)
            ->count();
        $autoTranslated = Translation::where('language_id', $language->id)
            ->where('is_auto_translated', true)
            ->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Translations', number_format($totalTranslations)],
                ['Active Translations', number_format($activeTranslations)],
                ['Auto-Translated', number_format($autoTranslated)],
                ['Manual Translations', number_format($totalTranslations - $autoTranslated)],
            ]
        );

        $this->newLine();

        // Group breakdown
        $this->info('📁 Groups:');
        $this->newLine();

        $groups = Translation::where('language_id', $language->id)
            ->select('group', DB::raw('COUNT(*) as count'))
            ->groupBy('group')
            ->orderByDesc('count')
            ->get();

        $this->table(
            ['Group', 'Count'],
            $groups->map(fn($g) => [$g->group, number_format($g->count)])
        );

        if ($detailed) {
            $this->newLine();
            $this->showMissingTranslations($language);
        }

        return Command::SUCCESS;
    }

    /**
     * Show detailed statistics
     */
    protected function showDetailedStats(): void
    {
        $this->info('📈 Top Translation Groups:');
        $this->newLine();

        $topGroups = Translation::select('group', DB::raw('COUNT(*) as count'))
            ->groupBy('group')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $this->table(
            ['Group', 'Count'],
            $topGroups->map(fn($g) => [$g->group, number_format($g->count)])
        );

        $this->newLine();

        // Recently updated
        $this->info('🕒 Recently Updated:');
        $this->newLine();

        $recent = Translation::with('language')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get();

        $this->table(
            ['Key', 'Language', 'Updated'],
            $recent->map(fn($t) => [
                $t->key,
                $t->language->code,
                $t->updated_at->diffForHumans(),
            ])
        );
    }

    /**
     * Show missing translations for a language
     */
    protected function showMissingTranslations(Language $language): void
    {
        // Get default language
        $defaultLanguage = Language::where('is_default', true)->first();

        if (!$defaultLanguage || $defaultLanguage->id === $language->id) {
            return;
        }

        $this->info('🔍 Missing Translations:');
        $this->newLine();

        // Find keys in default language that don't exist in target language
        $defaultKeys = Translation::where('language_id', $defaultLanguage->id)
            ->pluck('key');

        $existingKeys = Translation::where('language_id', $language->id)
            ->pluck('key');

        $missingKeys = $defaultKeys->diff($existingKeys);

        if ($missingKeys->isEmpty()) {
            $this->info('✓ No missing translations!');
        } else {
            $this->warn("Found {$missingKeys->count()} missing translations");
            $this->newLine();

            if ($missingKeys->count() <= 20) {
                foreach ($missingKeys as $key) {
                    $this->line("  - {$key}");
                }
            } else {
                foreach ($missingKeys->take(20) as $key) {
                    $this->line("  - {$key}");
                }
                $remaining = $missingKeys->count() - 20;
                $this->line("  ... and {$remaining} more");
            }

            $this->newLine();
            $this->comment("💡 Run: php artisan translator:sync --language={$language->code}");
        }
    }
}
