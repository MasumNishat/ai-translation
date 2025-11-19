<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Facades\File;

class ExportTranslationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translator:export
                          {path : Export file path}
                          {--language= : Export specific language only}
                          {--group= : Export specific group only}
                          {--format=json : Export format (json, csv, php)}
                          {--pretty : Pretty print JSON output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export translations to file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📤 Exporting translations...');
        $this->newLine();

        $path = $this->argument('path');
        $languageCode = $this->option('language');
        $group = $this->option('group');
        $format = $this->option('format');
        $pretty = $this->option('pretty');

        // Validate format
        if (!in_array($format, ['json', 'csv', 'php'])) {
            $this->error("✗ Invalid format '{$format}'. Supported: json, csv, php");
            return Command::FAILURE;
        }

        // Build query
        $query = Translation::with('language');

        if ($languageCode) {
            $query->whereHas('language', function ($q) use ($languageCode) {
                $q->where('code', $languageCode);
            });
        }

        if ($group) {
            $query->where('group', $group);
        }

        $translations = $query->get();

        if ($translations->isEmpty()) {
            $this->warn('No translations found to export');
            return Command::SUCCESS;
        }

        $this->info("Found {$translations->count()} translations to export");

        // Export based on format
        $content = match ($format) {
            'json' => $this->exportToJson($translations, $pretty),
            'csv' => $this->exportToCsv($translations),
            'php' => $this->exportToPhp($translations),
        };

        // Ensure directory exists
        $directory = dirname($path);
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Write file
        File::put($path, $content);

        $this->newLine();
        $this->info("✓ Exported to: {$path}");
        $this->info("✓ Format: {$format}");
        $this->info("✓ Size: " . File::size($path) . " bytes");

        return Command::SUCCESS;
    }

    /**
     * Export to JSON format
     */
    protected function exportToJson($translations, bool $pretty = false): string
    {
        $data = [
            'meta' => [
                'exported_at' => now()->toIso8601String(),
                'total_translations' => $translations->count(),
                'version' => '1.0.0',
            ],
            'languages' => [],
        ];

        // Group by language
        $byLanguage = $translations->groupBy('language.code');

        foreach ($byLanguage as $languageCode => $items) {
            $language = $items->first()->language;

            $data['languages'][$languageCode] = [
                'info' => [
                    'code' => $language->code,
                    'name' => $language->name,
                    'native_name' => $language->native_name,
                    'direction' => $language->direction,
                ],
                'translations' => [],
            ];

            // Group by group
            $byGroup = $items->groupBy('group');

            foreach ($byGroup as $group => $groupItems) {
                $data['languages'][$languageCode]['translations'][$group] = [];

                foreach ($groupItems as $translation) {
                    // Remove group prefix from key if it exists
                    $key = str_replace($group . '.', '', $translation->key);

                    $data['languages'][$languageCode]['translations'][$group][$key] = [
                        'value' => $translation->value,
                        'is_auto_translated' => $translation->is_auto_translated,
                        'is_active' => $translation->is_active,
                    ];
                }
            }
        }

        $flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE;

        return json_encode($data, $flags);
    }

    /**
     * Export to CSV format
     */
    protected function exportToCsv($translations): string
    {
        $csv = [];

        // Header
        $csv[] = ['Language Code', 'Language Name', 'Group', 'Key', 'Value', 'Auto Translated', 'Active'];

        // Data rows
        foreach ($translations as $translation) {
            $csv[] = [
                $translation->language->code,
                $translation->language->name,
                $translation->group,
                $translation->key,
                $translation->value,
                $translation->is_auto_translated ? 'yes' : 'no',
                $translation->is_active ? 'yes' : 'no',
            ];
        }

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');

        foreach ($csv as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Export to PHP array format
     */
    protected function exportToPhp($translations): string
    {
        $data = [];

        // Group by language and group
        $byLanguage = $translations->groupBy('language.code');

        foreach ($byLanguage as $languageCode => $items) {
            $data[$languageCode] = [];

            $byGroup = $items->groupBy('group');

            foreach ($byGroup as $group => $groupItems) {
                $data[$languageCode][$group] = [];

                foreach ($groupItems as $translation) {
                    // Remove group prefix from key
                    $key = str_replace($group . '.', '', $translation->key);
                    $data[$languageCode][$group][$key] = $translation->value;
                }
            }
        }

        return "<?php\n\nreturn " . var_export($data, true) . ";\n";
    }
}
