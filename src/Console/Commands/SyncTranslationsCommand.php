<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Services\TranslationService;

class SyncTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translator:sync
                          {--language= : Sync specific language code}
                          {--group= : Sync specific group only}
                          {--auto-translate : Automatically translate missing keys using AI}
                          {--dry-run : Show what would be synced without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync missing translations from default language';

    /**
     * Translation service
     */
    protected TranslationService $translationService;

    /**
     * Create a new command instance.
     */
    public function __construct(TranslationService $translationService)
    {
        parent::__construct();
        $this->translationService = $translationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Syncing translations...');
        $this->newLine();

        // Get options
        $languageCode = $this->option('language');
        $group = $this->option('group');
        $autoTranslate = $this->option('auto-translate');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get default language
        $defaultLanguage = Language::where('is_default', true)->first();

        if (!$defaultLanguage) {
            $this->error('✗ No default language found. Please set a default language first.');
            return Command::FAILURE;
        }

        $this->info("Default language: {$defaultLanguage->name} ({$defaultLanguage->code})");
        $this->newLine();

        // Get target languages
        if ($languageCode) {
            $languages = Language::where('code', $languageCode)
                ->where('is_active', true)
                ->get();

            if ($languages->isEmpty()) {
                $this->error("✗ Language '{$languageCode}' not found or inactive");
                return Command::FAILURE;
            }
        } else {
            $languages = Language::where('is_active', true)
                ->where('id', '!=', $defaultLanguage->id)
                ->get();
        }

        if ($languages->isEmpty()) {
            $this->warn('No target languages to sync');
            return Command::SUCCESS;
        }

        $totalSynced = 0;

        foreach ($languages as $language) {
            $synced = $this->syncLanguage($defaultLanguage, $language, $group, $autoTranslate, $dryRun);
            $totalSynced += $synced;
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("Would create {$totalSynced} missing translations");
        } else {
            $this->info("✓ Synced {$totalSynced} missing translations");
        }

        return Command::SUCCESS;
    }

    /**
     * Sync translations for a specific language
     */
    protected function syncLanguage(
        Language $defaultLanguage,
        Language $targetLanguage,
        ?string $group,
        bool $autoTranslate,
        bool $dryRun
    ): int {
        $this->line("Processing: {$targetLanguage->name} ({$targetLanguage->code})");

        // Get all keys from default language
        $query = Translation::where('language_id', $defaultLanguage->id);

        if ($group) {
            $query->where('group', $group);
        }

        $defaultTranslations = $query->get();

        // Get existing keys in target language
        $existingKeysQuery = Translation::where('language_id', $targetLanguage->id);

        if ($group) {
            $existingKeysQuery->where('group', $group);
        }

        $existingKeys = $existingKeysQuery->pluck('key')->toArray();

        // Find missing keys
        $missingTranslations = $defaultTranslations->filter(function ($translation) use ($existingKeys) {
            return !in_array($translation->key, $existingKeys);
        });

        if ($missingTranslations->isEmpty()) {
            $this->line('  ✓ No missing translations');
            return 0;
        }

        $this->line("  Found {$missingTranslations->count()} missing translations");

        if ($dryRun) {
            foreach ($missingTranslations->take(5) as $translation) {
                $this->line("    - {$translation->key}");
            }
            if ($missingTranslations->count() > 5) {
                $this->line("    ... and " . ($missingTranslations->count() - 5) . " more");
            }
            return $missingTranslations->count();
        }

        // Create missing translations
        $bar = $this->output->createProgressBar($missingTranslations->count());
        $bar->start();

        $synced = 0;

        foreach ($missingTranslations as $defaultTranslation) {
            $value = '';
            $isAutoTranslated = false;

            if ($autoTranslate) {
                try {
                    // Auto-translate using AI
                    $translatedValue = $this->translationService->translateText(
                        $defaultTranslation->value,
                        $defaultLanguage->code,
                        [$targetLanguage->code]
                    );

                    $value = $translatedValue[$targetLanguage->code] ?? '';
                    $isAutoTranslated = !empty($value);
                } catch (\Exception $e) {
                    // If translation fails, create empty translation
                    $value = '';
                }
            }

            Translation::create([
                'key' => $defaultTranslation->key,
                'value' => $value,
                'language_id' => $targetLanguage->id,
                'group' => $defaultTranslation->group,
                'is_active' => !empty($value),
                'is_auto_translated' => $isAutoTranslated,
            ]);

            $synced++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        return $synced;
    }
}
