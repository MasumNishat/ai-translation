<?php

namespace Masum\AiTranslator\Exceptions;

/**
 * Thrown when the Gemini API returns a quota / rate-limit error.
 * Carries the retry-after duration parsed from the API error message so
 * callers can sleep the exact amount before retrying.
 */
class QuotaExceededException extends \RuntimeException
{
    public function __construct(string $message, public readonly float $retryAfter = 60.0)
    {
        parent::__construct($message);
    }

    /**
     * Parse the retry-after seconds from a Gemini quota error message.
     * Falls back to $default if the message contains no parseable duration.
     *
     * Gemini format: "Please retry in 45.787990454s."
     */
    public static function fromMessage(string $message, float $default = 60.0): self
    {
        $seconds = $default;

        if (preg_match('/retry in (\d+(?:\.\d+)?)s/i', $message, $m)) {
            $seconds = (float) $m[1];
        }

        return new self($message, $seconds);
    }
}
