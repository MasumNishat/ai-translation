<?php

namespace Masum\AiTranslator\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Masum\AiTranslator\Services\TranslationService;

class BatchTranslateJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;
    public int $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected array $translations,
        protected string $sourceLang,
        protected array $targetLangs,
        protected ?int $userId = null,
        protected array $options = []
    ) {
        $this->onQueue(config('ai-translator.queue.bulk_name', 'translations-bulk'));
    }

    /**
     * Execute the job.
     */
    public function handle(TranslationService $service): void
    {
        // Check if batch has been cancelled
        if ($this->batch()?->cancelled()) {
            Log::info('Batch translate job cancelled');
            return;
        }

        try {
            Log::info('Batch translation job started', [
                'count' => count($this->translations),
                'source' => $this->sourceLang,
                'targets' => $this->targetLangs,
                'user_id' => $this->userId,
            ]);

            foreach ($this->translations as $translationData) {
                // Check if batch has been cancelled during processing
                if ($this->batch()?->cancelled()) {
                    Log::info('Batch translate job cancelled during processing');
                    return;
                }

                try {
                    // Dispatch individual translation job
                    TranslateJob::dispatch(
                        $translationData['key'],
                        $translationData['value'],
                        $this->sourceLang,
                        $this->targetLangs,
                        $translationData['group'] ?? null,
                        $this->userId,
                        [
                            'batch_id' => $this->batch()?->id,
                            'batch_name' => $this->batch()?->name,
                        ]
                    );

                    Log::debug('Dispatched translation job for key', [
                        'key' => $translationData['key'],
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch translation job', [
                        'key' => $translationData['key'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Batch translation job completed', [
                'count' => count($this->translations),
            ]);
        } catch (\Exception $e) {
            Log::error('Batch translation job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Batch translation job failed permanently', [
            'count' => count($this->translations),
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): array
    {
        return [15, 45, 90]; // 15s, 45s, 90s
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'batch-translation',
            "source:{$this->sourceLang}",
            "user:{$this->userId}",
            "count:" . count($this->translations),
        ];
    }
}
