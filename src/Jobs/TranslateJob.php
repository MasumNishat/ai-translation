<?php

namespace Masum\AiTranslator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Services\TranslationService;
use Illuminate\Support\Facades\Log;

class TranslateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * The maximum number of exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $key,
        protected string $value,
        protected string $sourceLang,
        protected array $targetLangs,
        protected ?string $group = null,
        protected ?int $userId = null,
        protected array $metadata = []
    ) {
        $this->onQueue(config('ai-translator.queue.name', 'translations'));
    }

    /**
     * Execute the job.
     */
    public function handle(TranslationService $service): void
    {
        try {
            Log::info('Translation job started', [
                'key' => $this->key,
                'source' => $this->sourceLang,
                'targets' => $this->targetLangs,
                'user_id' => $this->userId,
            ]);

            $results = [];
            try {
                // Translate using AI service
                $service->translate(
                    $this->key,
                    $this->value,
                    $this->sourceLang,
                    $this->targetLangs
                );
            } catch (\Exception $e) {
                Log::error("Translation failed", [
                    'key' => $this->key,
                    'error' => $e->getMessage(),
                ]);
            }

            // Dispatch completion event
            event(new \Masum\AiTranslator\Events\TranslationCompleted(
                $this->key,
                $results,
                $this->userId,
                $this->metadata
            ));

            Log::info('Translation job completed', [
                'key' => $this->key,
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            Log::error('Translation job failed', [
                'key' => $this->key,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Dispatch failure event
            event(new \Masum\AiTranslator\Events\TranslationFailed(
                $this->key,
                $e->getMessage(),
                $this->userId,
                $this->metadata
            ));

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Translation job failed permanently', [
            'key' => $this->key,
            'source' => $this->sourceLang,
            'targets' => $this->targetLangs,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Dispatch failure event
        event(new \Masum\AiTranslator\Events\TranslationFailed(
            $this->key,
            $exception->getMessage(),
            $this->userId,
            array_merge($this->metadata, [
                'permanently_failed' => true,
                'attempts' => $this->attempts(),
            ])
        ));
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [10, 30, 60]; // 10s, 30s, 60s
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'translation',
            "key:{$this->key}",
            "source:{$this->sourceLang}",
            "user:{$this->userId}",
        ];
    }
}
