<?php

namespace Masum\AiTranslator\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Masum\AiTranslator\Models\PackageSetting;

class GeminiTranslationService
{
    protected Client $client;
    protected int $maxRetries;
    protected int $timeout;

    public function __construct()
    {
        $this->client = new Client();
        $this->maxRetries = config('ai-translator.gemini.max_retries', 3);
        $this->timeout = config('ai-translator.gemini.timeout', 30);
    }

    /**
     * Translate text from source language to multiple target languages.
     *
     * @param  string  $text  The text to translate
     * @param  string  $sourceLang  Source language code (e.g., 'en')
     * @param  array  $targetLangs  Target language codes (e.g., ['bn', 'fr', 'es'])
     * @param  array  $context  Optional context for better translations
     * @return array Array of translations ['bn' => 'translated text', 'fr' => '...']
     */
    public function translate(
        string $text,
        string $sourceLang,
        array $targetLangs,
        array $context = []
    ): array {
        $apiKey = $this->getApiKey();

        if (!$apiKey) {
            throw new \Exception('Gemini API key not configured. Please set it in database, config, or .env file.');
        }

        $translations = [];

        foreach ($targetLangs as $targetLang) {
            try {
                $prompt = $this->buildTranslationPrompt($text, $sourceLang, $targetLang, $context);
                $translation = $this->callGeminiApi($prompt, $apiKey);

                if ($translation) {
                    $translations[$targetLang] = $translation;
                }
            } catch (\Exception $e) {
                logger()->error('Failed to translate text', [
                    'text' => $text,
                    'source' => $sourceLang,
                    'target' => $targetLang,
                    'error' => $e->getMessage(),
                ]);

                // Continue with next language instead of failing completely
                continue;
            }
        }

        return $translations;
    }

    /**
     * Batch translate multiple texts.
     *
     * @param  array  $texts  Array of texts to translate ['key1' => 'text1', 'key2' => 'text2']
     * @param  string  $sourceLang  Source language code
     * @param  array  $targetLangs  Target language codes
     * @return array Nested array ['key1' => ['bn' => 'translation', 'fr' => '...'], ...]
     */
    public function batchTranslate(
        array $texts,
        string $sourceLang,
        array $targetLangs
    ): array {
        $apiKey = $this->getApiKey();

        if (!$apiKey) {
            throw new \Exception('Gemini API key not configured.');
        }

        $results = [];

        // Process in batches to avoid token limits
        $batchSize = config('ai-translator.translation.batch_size', 50);
        $chunks = array_chunk($texts, $batchSize, true);

        foreach ($chunks as $chunk) {
            foreach ($targetLangs as $targetLang) {
                try {
                    $prompt = $this->buildBatchTranslationPrompt($chunk, $sourceLang, $targetLang);
                    $response = $this->callGeminiApi($prompt, $apiKey);

                    // Parse JSON response
                    $translations = json_decode($response, true);

                    if (is_array($translations)) {
                        foreach ($translations as $key => $translation) {
                            if (!isset($results[$key])) {
                                $results[$key] = [];
                            }
                            $results[$key][$targetLang] = $translation;
                        }
                    }
                } catch (\Exception $e) {
                    logger()->error('Batch translation failed', [
                        'target' => $targetLang,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $results;
    }

    /**
     * Detect the language of the given text.
     *
     * @param  string  $text  The text to analyze
     * @return string Detected language code
     */
    public function detectLanguage(string $text): string
    {
        $apiKey = $this->getApiKey();

        if (!$apiKey) {
            throw new \Exception('Gemini API key not configured.');
        }

        $prompt = $this->buildLanguageDetectionPrompt($text);

        try {
            $response = $this->callGeminiApi($prompt, $apiKey);

            // Parse language code from response
            $languageCode = trim(strtolower($response));

            return $languageCode;
        } catch (\Exception $e) {
            logger()->error('Language detection failed', [
                'text' => substr($text, 0, 100),
                'error' => $e->getMessage(),
            ]);

            // Return default fallback
            return config('ai-translator.translation.fallback_locale', 'en');
        }
    }

    /**
     * Get API key with priority: Database → Config → Environment.
     */
    protected function getApiKey(): ?string
    {
        // 1. Check database
        $dbKey = PackageSetting::get('gemini_api_key');

        if ($dbKey) {
            return $dbKey;
        }

        // 2. Check config file
        $configKey = config('ai-translator.gemini.api_key');

        if ($configKey) {
            return $configKey;
        }

        // 3. Check environment variable
        return env('GEMINI_API_KEY');
    }

    /**
     * Call Gemini API with retry logic.
     */
    protected function callGeminiApi(string $prompt, string $apiKey, int $attempt = 1): string
    {
        $baseUrl = config('ai-translator.gemini.api_url', 'https://generativelanguage.googleapis.com/v1beta');
        $model = config('ai-translator.gemini.model', 'gemini-pro');
        $url = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";

        try {
            $response = $this->client->post($url, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.3,
                        'maxOutputTokens' => 2048,
                    ],
                ],
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            // Extract text from Gemini response
            if (isset($body['candidates'][0]['content']['parts'][0]['text'])) {
                return trim($body['candidates'][0]['content']['parts'][0]['text']);
            }

            throw new \Exception('Invalid response format from Gemini API');
        } catch (RequestException $e) {
            // Retry logic
            if ($attempt < $this->maxRetries) {
                // Exponential backoff: 1s, 2s, 4s
                sleep(pow(2, $attempt - 1));

                return $this->callGeminiApi($prompt, $apiKey, $attempt + 1);
            }

            // Get error details
            $errorMessage = $e->getMessage();

            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $errorData = json_decode($errorBody, true);

                if (isset($errorData['error']['message'])) {
                    $errorMessage = $errorData['error']['message'];
                }
            }

            throw new \Exception("Gemini API call failed after {$this->maxRetries} attempts: {$errorMessage}");
        }
    }

    /**
     * Build translation prompt for Gemini.
     */
    protected function buildTranslationPrompt(
        string $text,
        string $sourceLang,
        string $targetLang,
        array $context = []
    ): string {
        $contextInfo = '';

        if (!empty($context)) {
            $contextInfo = "\n\nContext: ".json_encode($context);
        }

        return <<<PROMPT
You are a professional translator. Translate the following text from {$sourceLang} to {$targetLang}.

Requirements:
- Provide ONLY the translated text, no explanations or additional comments
- Maintain the original tone and style
- Keep any formatting (line breaks, punctuation)
- Preserve any placeholder variables (e.g., {{name}}, :attribute)
- If the text is a single word or technical term, provide the most appropriate translation

Source text:
{$text}{$contextInfo}

Translation:
PROMPT;
    }

    /**
     * Build batch translation prompt.
     */
    protected function buildBatchTranslationPrompt(
        array $texts,
        string $sourceLang,
        string $targetLang
    ): string {
        $textsJson = json_encode($texts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a professional translator. Translate the following texts from {$sourceLang} to {$targetLang}.

Input is a JSON object where keys are identifiers and values are texts to translate.
Output MUST be a valid JSON object with the same keys and translated values.

Requirements:
- Provide ONLY a valid JSON object, no explanations
- Maintain the same keys as input
- Preserve formatting and placeholders in the texts
- Ensure consistent terminology across all translations

Input JSON:
{$textsJson}

Output JSON (translated):
PROMPT;
    }

    /**
     * Build language detection prompt.
     */
    protected function buildLanguageDetectionPrompt(string $text): string
    {
        return <<<PROMPT
Detect the language of the following text and respond with ONLY the ISO 639-1 language code (2 letters, lowercase).
Examples: en, bn, fr, es, de, ar, hi, zh, ja, ko

Text:
{$text}

Language code:
PROMPT;
    }
}
