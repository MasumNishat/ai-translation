<?php

namespace Masum\AiTranslator\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $key The translation key that failed
     * @param string $error The error message
     * @param int|null $userId The user who initiated the translation
     * @param array $metadata Additional metadata about the failure
     */
    public function __construct(
        public string $key,
        public string $error,
        public ?int $userId = null,
        public array $metadata = []
    ) {}
}
