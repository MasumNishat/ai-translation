<?php

namespace Masum\AiTranslator\Services;

use Masum\AiTranslator\Exceptions\QuotaExceededException;

/**
 * Translates a markdown file (front matter + body) into a target locale
 * and returns the fully reassembled markdown string.
 *
 * Front matter: translatable scalar fields (title, lead, description) are
 * batch-translated in a single Gemini call. Non-content fields (sort_order,
 * reading_time, icon, tags) are preserved as-is.
 *
 * Body: split on `## ` headings so each section is a separate Gemini call,
 * keeping token usage well within limits even for long articles.
 */
class MarkdownTranslationService
{
    /** Front matter keys whose values should be translated. */
    private const TRANSLATABLE_KEYS = ['title', 'lead', 'description', 'excerpt', 'summary'];

    public function __construct(protected GeminiTranslationService $gemini) {}

    /**
     * Translate a markdown file and return the translated markdown string.
     *
     * @param  string  $filePath  Absolute path to the source markdown file
     * @param  string  $locale  Target locale code (e.g. 'bn')
     * @param  string  $sourceLang  Source language code (default 'en')
     */
    public function translateFile(string $filePath, string $locale, string $sourceLang = 'en'): string
    {
        $raw = file_get_contents($filePath);

        [$frontMatter, $body] = $this->split($raw);

        $translatedFm = $frontMatter ? $this->translateFrontMatter($frontMatter, $locale, $sourceLang) : '';
        $translatedBody = $this->translateBody($body, $locale, $sourceLang);

        return $this->reassemble($translatedFm, $translatedBody);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    /**
     * Call a Gemini translate closure, retrying on quota errors by sleeping
     * for the API-specified duration (+ 2 s buffer). Max 5 retries.
     *
     * @param  callable(): array  $call
     */
    private function callWithRetry(callable $call, int $maxRetries = 5): array
    {
        $attempt = 0;

        while (true) {
            try {
                return $call();
            } catch (QuotaExceededException $e) {
                $attempt++;

                if ($attempt >= $maxRetries) {
                    throw $e;
                }

                $wait = (int) ceil($e->retryAfter) + 2;
                logger()->warning("Gemini quota exceeded — sleeping {$wait}s before retry {$attempt}/{$maxRetries}");
                sleep($wait);
            }
        }
    }

    /**
     * Split raw markdown into [frontMatter, body].
     * Front matter is the YAML block between the opening and closing `---` lines.
     *
     * @return array{string, string}
     */
    private function split(string $raw): array
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)/s', $raw, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return ['', trim($raw)];
    }

    /**
     * Translate only the designated translatable fields in YAML-style front matter.
     * Non-translatable fields (sort_order, reading_time, icon, tags, …) are kept verbatim.
     */
    private function translateFrontMatter(string $fm, string $locale, string $sourceLang): string
    {
        $lines = explode("\n", $fm);
        $values = [];   // original text values to translate (ordered)
        $index = [];   // line-index → position in $values

        foreach ($lines as $i => $line) {
            if (! preg_match('/^(\w+):\s*(.+)$/', $line, $kv)) {
                continue;
            }

            $key = trim($kv[1]);
            $val = trim($kv[2], '"\'');

            if (in_array($key, self::TRANSLATABLE_KEYS, true)) {
                $index[$i] = count($values);
                $values[] = $val;
            }
        }

        if (empty($values)) {
            return $fm;
        }

        $results = $this->callWithRetry(fn () => $this->gemini->translate($values, $sourceLang, [$locale]));
        $translated = $results[$locale] ?? [];

        foreach ($index as $lineIdx => $valueIdx) {
            $translatedVal = $translated[$valueIdx] ?? $values[$valueIdx];
            // Re-encode as a quoted YAML string
            preg_match('/^(\w+):\s*/', $lines[$lineIdx], $prefix);
            $lines[$lineIdx] = $prefix[0].'"'.addslashes($translatedVal).'"';
        }

        return implode("\n", $lines);
    }

    /**
     * Translate the markdown body.
     *
     * Splits on `## ` level-2 headings so each section becomes a separate
     * Gemini call, keeping token usage predictable for any article length.
     * If there are no `##` headings the whole body is sent as one call.
     */
    private function translateBody(string $body, string $locale, string $sourceLang): string
    {
        if (empty(trim($body))) {
            return $body;
        }

        // Split before every `## ` heading (keep the delimiter at the start of each chunk)
        $chunks = preg_split('/(?=^## )/m', $body, flags: PREG_SPLIT_NO_EMPTY);

        $translated = [];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);

            if ($chunk === '') {
                continue;
            }

            $results = $this->callWithRetry(fn () => $this->gemini->translate([$chunk], $sourceLang, [$locale]));
            $values = $results[$locale] ?? [];
            $translated[] = $values[0] ?? $chunk;
        }

        return implode("\n\n", $translated);
    }

    /**
     * Reassemble front matter and body into a complete markdown document.
     */
    private function reassemble(string $frontMatter, string $body): string
    {
        if ($frontMatter === '') {
            return $body;
        }

        return "---\n{$frontMatter}\n---\n\n{$body}";
    }
}
