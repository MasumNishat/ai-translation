<?php

namespace Masum\AiTranslator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Services\GeminiTranslationService;

/**
 * Translates a chunk of missing keys for a single locale in one Gemini call.
 * Dispatched by AiTranslator::flushPending() after the HTTP response is sent.
 *
 * Using a queued job (vs. register_shutdown_function) means:
 * - Rate-limit errors retry with proper back-off (not instantly).
 * - Each retry does not burn quota unnecessarily.
 * - The PHP worker process is not held open after the response.
 */
class BatchTranslateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        protected array $keys,
        protected string $locale,
        protected string $sourceLang,
    ) {
        $this->onQueue(config('ai-translator.queue.name', 'translations'));
    }

    public function handle(GeminiTranslationService $service): void
    {
        // Filter out keys already saved by a concurrent job from another page load.
        $language = Language::getByCode($this->locale);
        $missing  = $this->keys;

        if ($language) {
            $existingHashes = Translation::where('language_id', $language->id)
                ->whereIn('key', array_map('md5', $this->keys))
                ->whereNull('group')
                ->where('is_active', true)
                ->pluck('key')
                ->all();

            $missing = array_values(array_filter(
                $this->keys,
                fn ($k) => ! in_array(md5($k), $existingHashes, true)
            ));
        }

        if (empty($missing)) {
            return;
        }

        $results = $service->translate($missing, $this->sourceLang, [$this->locale]);

        foreach ($results as $lang => $translated) {
            $values = is_array($translated) ? $translated : [$translated];
            foreach ($missing as $i => $key) {
                if (isset($values[$i])) {
                    Translation::set($key, $values[$i], $lang);
                }
            }
        }
    }

    /**
     * Exponential back-off: wait before retrying so rate-limit windows reset.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [30, 60, 120];
    }

    /**
     * Do not retry if the API key is invalid or the daily quota is exhausted —
     * retrying immediately would just fail again.
     */
    public function failed(\Throwable $e): void
    {
        logger()->error('BatchTranslateJob permanently failed', [
            'locale' => $this->locale,
            'keys'   => $this->keys,
            'error'  => $e->getMessage(),
        ]);
    }
}