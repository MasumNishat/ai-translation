<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Masum\AiTranslator\Models\Language;

class ClearTranslationCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'translator:clear-cache
                          {--language= : Clear cache for specific language code}
                          {--group= : Clear cache for specific group}
                          {--all : Clear all translation caches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear translation cache';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧹 Clearing translation cache...');
        $this->newLine();

        $languageCode = $this->option('language');
        $group = $this->option('group');
        $all = $this->option('all');

        if ($all) {
            return $this->clearAllCache();
        }

        if ($languageCode) {
            return $this->clearLanguageCache($languageCode, $group);
        }

        if ($group) {
            return $this->clearGroupCache($group);
        }

        // Default: clear all translation cache
        return $this->clearAllCache();
    }

    /**
     * Clear all translation cache
     */
    protected function clearAllCache(): int
    {
        // Clear tagged cache if supported
        if (in_array(config('cache.default'), ['redis', 'memcached', 'array'])) {
            Cache::tags(['translations'])->flush();
            $this->info('✓ Cleared all tagged translation caches');
        }

        // Clear individual cache entries
        $count = 0;
        $languages = Language::all();

        foreach ($languages as $language) {
            $pattern = "ai_translator.*.*." . $language->code;

            // This is a simple approach - in production you might want to track cache keys
            Cache::flush();
            $count++;
        }

        $this->info("✓ Cleared translation cache for {$languages->count()} languages");
        $this->newLine();

        return Command::SUCCESS;
    }

    /**
     * Clear cache for specific language
     */
    protected function clearLanguageCache(string $languageCode, ?string $group = null): int
    {
        $language = Language::where('code', $languageCode)->first();

        if (!$language) {
            $this->error("✗ Language '{$languageCode}' not found");
            return Command::FAILURE;
        }

        if (in_array(config('cache.default'), ['redis', 'memcached', 'array'])) {
            if ($group) {
                Cache::tags(['translations', "lang:{$languageCode}", "group:{$group}"])->flush();
            } else {
                Cache::tags(['translations', "lang:{$languageCode}"])->flush();
            }
        } else {
            // For file cache, we need to clear manually
            Cache::flush();
        }

        $message = $group
            ? "✓ Cleared cache for language '{$languageCode}', group '{$group}'"
            : "✓ Cleared cache for language '{$languageCode}'";

        $this->info($message);
        $this->newLine();

        return Command::SUCCESS;
    }

    /**
     * Clear cache for specific group
     */
    protected function clearGroupCache(string $group): int
    {
        if (in_array(config('cache.default'), ['redis', 'memcached', 'array'])) {
            Cache::tags(['translations', "group:{$group}"])->flush();
        } else {
            Cache::flush();
        }

        $this->info("✓ Cleared cache for group '{$group}'");
        $this->newLine();

        return Command::SUCCESS;
    }
}
