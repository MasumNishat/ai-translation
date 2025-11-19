<?php

namespace Masum\AiTranslator\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $key The translation key
     * @param array $results The translation results for each language
     * @param int|null $userId The user who initiated the translation
     * @param array $metadata Additional metadata
     */
    public function __construct(
        public string $key,
        public array $results,
        public ?int $userId = null,
        public array $metadata = []
    ) {}
}
