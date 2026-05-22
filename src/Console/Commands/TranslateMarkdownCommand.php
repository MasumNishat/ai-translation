<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Masum\AiTranslator\Exceptions\QuotaExceededException;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Services\MarkdownTranslationService;

/**
 * Translates markdown files into one or more locales and saves the results
 * alongside the originals under a locale sub-directory.
 *
 * Example output structure:
 *   feature-pages/
 *     map/cable-drawing.md          ← source (English)
 *     bn/map/cable-drawing.md       ← generated Bengali translation
 *     fr/map/cable-drawing.md       ← generated French translation
 *
 * Usage:
 *   php artisan translator:translate-markdown feature-pages/
 *   php artisan translator:translate-markdown feature-pages/ --locale=bn
 *   php artisan translator:translate-markdown feature-pages/ --locale=bn,fr --force
 *   php artisan translator:translate-markdown feature-pages/map/cable-drawing.md --locale=bn
 */
class TranslateMarkdownCommand extends Command
{
    protected $signature = 'translator:translate-markdown
        {path : Directory or single .md file to translate}
        {--locale= : Comma-separated target locale codes. Defaults to all active non-source locales}
        {--source= : Source language code (default: fallback_locale from config)}
        {--force : Re-translate and overwrite existing locale files}';

    protected $description = 'Translate markdown files into locale sub-directories (e.g. feature-pages/bn/map/cable-drawing.md)';

    public function handle(MarkdownTranslationService $service): int
    {
        $path = $this->argument('path');
        $sourceLang = $this->option('source') ?? config('ai-translator.translation.fallback_locale', 'en');
        $force = (bool) $this->option('force');

        // ── Resolve absolute base path ───────────────────────────────────────
        $absPath = str_starts_with($path, '/') ? $path : base_path($path);

        if (! file_exists($absPath)) {
            $this->error("Path not found: {$absPath}");

            return self::FAILURE;
        }

        // ── Resolve target locales ───────────────────────────────────────────
        $locales = $this->resolveLocales($sourceLang);

        if (empty($locales)) {
            $this->warn('No target locales found. Add languages in the translator admin or pass --locale=xx.');

            return self::SUCCESS;
        }

        // ── Collect source files ─────────────────────────────────────────────
        $files = $this->collectFiles($absPath);
        // For a directory: base is the directory itself.
        // For a single file: base is the grandparent (root/section/file.md → root/).
        $baseDir = is_dir($absPath) ? rtrim($absPath, '/') : dirname(dirname($absPath));

        if (empty($files)) {
            $this->warn("No .md files found in: {$absPath}");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Found %d file(s). Translating to: %s',
            count($files),
            implode(', ', $locales)
        ));

        // ── Translate ────────────────────────────────────────────────────────
        $done = 0;
        $skips = 0;
        $fails = 0;

        foreach ($files as $file) {
            $relative = ltrim(str_replace($baseDir, '', $file), '/');

            foreach ($locales as $locale) {
                $outPath = $baseDir.'/'.$locale.'/'.$relative;

                if (! $force && file_exists($outPath)) {
                    $this->line("  <fg=gray>skip</>  [{$locale}] {$relative}");
                    $skips++;

                    continue;
                }

                $this->line("  <fg=blue>trans</>  [{$locale}] {$relative}");

                try {
                    $content = $service->translateFile($file, $locale, $sourceLang);
                    $this->ensureDir(dirname($outPath));
                    file_put_contents($outPath, $content);
                    $this->line("  <fg=green>done</>   [{$locale}] {$relative}");
                    $done++;
                } catch (QuotaExceededException $e) {
                    $wait = (int) ceil($e->retryAfter) + 2;
                    $this->warn("  <fg=yellow>quota</>  [{$locale}] {$relative} — retry-after {$wait}s exhausted. Run again later.");
                    $fails++;
                } catch (\Throwable $e) {
                    $this->error("  failed [{$locale}] {$relative}: {$e->getMessage()}");
                    $fails++;
                }
            }
        }

        $this->newLine();
        $this->info("Done. translated={$done}  skipped={$skips}  failed={$fails}");

        return $fails > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return string[] */
    private function resolveLocales(string $sourceLang): array
    {
        $opt = $this->option('locale');

        if ($opt) {
            return array_filter(array_map('trim', explode(',', $opt)));
        }

        return Language::where('is_active', true)
            ->where('code', '!=', $sourceLang)
            ->pluck('code')
            ->all();
    }

    /**
     * Collect all .md files under the path, skipping locale sub-directories
     * (two-letter directory names at the first level) so we don't re-translate
     * already-translated files.
     *
     * @return string[]
     */
    private function collectFiles(string $absPath): array
    {
        if (is_file($absPath)) {
            return str_ends_with($absPath, '.md') ? [$absPath] : [];
        }

        $files = [];
        $baseDir = rtrim($absPath, '/');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($baseDir));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $relative = ltrim(str_replace($baseDir, '', $file->getPathname()), '/');
            $firstDir = explode('/', $relative)[0];

            // Skip locale sub-directories (2-letter alpha codes like bn/, fr/, ar/)
            if (strlen($firstDir) === 2 && ctype_alpha($firstDir)) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
