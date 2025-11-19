<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;

class ImportTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translator:import
                          {path : Import file path}
                          {--format=json : Import format (json, csv, php)}
                          {--language= : Import only this language}
                          {--group= : Import only this group}
                          {--update : Update existing translations}
                          {--create-languages : Create missing languages automatically}
                          {--dry-run : Show what would be imported without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import translations from file';

    /**
     * Statistics
     */
    protected int $created = 0;
    protected int $updated = 0;
    protected int $skipped = 0;
    protected array $errors = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📥 Importing translations...');
        $this->newLine();

        $path = $this->argument('path');
        $format = $this->option('format');
        $languageFilter = $this->option('language');
        $groupFilter = $this->option('group');
        $update = $this->option('update');
        $createLanguages = $this->option('create-languages');
        $dryRun = $this->option('dry-run');

        // Check file exists
        if (!File::exists($path)) {
            $this->error("✗ File not found: {$path}");
            return Command::FAILURE;
        }

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Read file
        $content = File::get($path);

        // Parse based on format
        $data = match ($format) {
            'json' => $this->parseJson($content),
            'csv' => $this->parseCsv($content),
            'php' => $this->parsePhp($path),
            default => null,
        };

        if (!$data) {
            $this->error("✗ Failed to parse file as {$format}");
            return Command::FAILURE;
        }

        // Import data
        DB::transaction(function () use ($data, $languageFilter, $groupFilter, $update, $createLanguages, $dryRun) {
            $this->importData($data, $languageFilter, $groupFilter, $update, $createLanguages, $dryRun);

            if ($dryRun) {
                // Rollback transaction in dry-run mode
                DB::rollBack();
            }
        });

        // Show results
        $this->newLine();
        $this->displayResults($dryRun);

        return empty($this->errors) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Parse JSON format
     */
    protected function parseJson(string $content): ?array
    {
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }

    /**
     * Parse CSV format
     */
    protected function parseCsv(string $content): ?array
    {
        $lines = str_getcsv($content, "\n");
        $data = ['languages' => []];
        $header = null;

        foreach ($lines as $line) {
            $row = str_getcsv($line);

            if (!$header) {
                $header = $row;
                continue;
            }

            $languageCode = $row[0] ?? null;
            $group = $row[2] ?? 'default';
            $key = $row[3] ?? null;
            $value = $row[4] ?? '';

            if (!$languageCode || !$key) {
                continue;
            }

            if (!isset($data['languages'][$languageCode])) {
                $data['languages'][$languageCode] = [
                    'info' => ['code' => $languageCode],
                    'translations' => [],
                ];
            }

            if (!isset($data['languages'][$languageCode]['translations'][$group])) {
                $data['languages'][$languageCode]['translations'][$group] = [];
            }

            $data['languages'][$languageCode]['translations'][$group][$key] = [
                'value' => $value,
                'is_auto_translated' => ($row[5] ?? 'no') === 'yes',
                'is_active' => ($row[6] ?? 'yes') === 'yes',
            ];
        }

        return $data;
    }

    /**
     * Parse PHP array format
     */
    protected function parsePhp(string $path): ?array
    {
        $rawData = include $path;

        if (!is_array($rawData)) {
            return null;
        }

        // Convert to our standard format
        $data = ['languages' => []];

        foreach ($rawData as $languageCode => $groups) {
            $data['languages'][$languageCode] = [
                'info' => ['code' => $languageCode],
                'translations' => [],
            ];

            foreach ($groups as $group => $translations) {
                $data['languages'][$languageCode]['translations'][$group] = [];

                foreach ($translations as $key => $value) {
                    $data['languages'][$languageCode]['translations'][$group][$key] = [
                        'value' => $value,
                        'is_auto_translated' => false,
                        'is_active' => true,
                    ];
                }
            }
        }

        return $data;
    }

    /**
     * Import parsed data
     */
    protected function importData(
        array $data,
        ?string $languageFilter,
        ?string $groupFilter,
        bool $update,
        bool $createLanguages,
        bool $dryRun
    ): void {
        $languages = $data['languages'] ?? [];

        foreach ($languages as $languageCode => $languageData) {
            // Filter by language
            if ($languageFilter && $languageCode !== $languageFilter) {
                continue;
            }

            // Get or create language
            $language = Language::where('code', $languageCode)->first();

            if (!$language) {
                if (!$createLanguages) {
                    $this->errors[] = "Language '{$languageCode}' not found (use --create-languages to create)";
                    continue;
                }

                $info = $languageData['info'] ?? [];
                $language = Language::create([
                    'code' => $languageCode,
                    'name' => $info['name'] ?? ucfirst($languageCode),
                    'native_name' => $info['native_name'] ?? ucfirst($languageCode),
                    'direction' => $info['direction'] ?? 'ltr',
                    'is_active' => true,
                ]);

                $this->line("Created language: {$languageCode}");
            }

            // Import translations
            $translations = $languageData['translations'] ?? [];

            foreach ($translations as $group => $items) {
                // Filter by group
                if ($groupFilter && $group !== $groupFilter) {
                    continue;
                }

                $this->importGroup($language, $group, $items, $update, $dryRun);
            }
        }
    }

    /**
     * Import translations for a group
     */
    protected function importGroup(Language $language, string $group, array $items, bool $update, bool $dryRun): void
    {
        foreach ($items as $key => $data) {
            // Build full key
            $fullKey = $group . '.' . $key;

            // Get translation value and metadata
            $value = is_array($data) ? ($data['value'] ?? '') : $data;
            $isAutoTranslated = is_array($data) ? ($data['is_auto_translated'] ?? false) : false;
            $isActive = is_array($data) ? ($data['is_active'] ?? true) : true;

            // Check if exists
            $existing = Translation::where('key', $fullKey)
                ->where('language_id', $language->id)
                ->first();

            if ($existing) {
                if ($update) {
                    if (!$dryRun) {
                        $existing->update([
                            'value' => $value,
                            'is_auto_translated' => $isAutoTranslated,
                            'is_active' => $isActive,
                        ]);
                    }
                    $this->updated++;
                } else {
                    $this->skipped++;
                }
            } else {
                if (!$dryRun) {
                    Translation::create([
                        'key' => $fullKey,
                        'value' => $value,
                        'language_id' => $language->id,
                        'group' => $group,
                        'is_auto_translated' => $isAutoTranslated,
                        'is_active' => $isActive,
                    ]);
                }
                $this->created++;
            }
        }
    }

    /**
     * Display import results
     */
    protected function displayResults(bool $dryRun): void
    {
        $verb = $dryRun ? 'Would be' : 'Were';

        $this->table(
            ['Action', 'Count'],
            [
                ['Created', number_format($this->created)],
                ['Updated', number_format($this->updated)],
                ['Skipped', number_format($this->skipped)],
                ['Errors', count($this->errors)],
            ]
        );

        if (!empty($this->errors)) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($this->errors as $error) {
                $this->line("  - {$error}");
            }
        } else {
            $this->newLine();
            $message = $dryRun
                ? "✓ {$verb} {$this->created} created, {$this->updated} updated, {$this->skipped} skipped"
                : "✓ Successfully imported {$this->created} new translations";
            $this->info($message);
        }
    }
}
