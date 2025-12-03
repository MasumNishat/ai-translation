<?php

namespace Masum\AiTranslator\Services;

use Gemini\Data\GenerationConfig;
use Gemini\Data\Schema;
use Gemini\Enums\DataType;
use Gemini\Enums\ResponseMimeType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Gemini\Laravel\Facades\Gemini;
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
        string | array $text,
        string $sourceLang,
        array $targetLangs,
        array $context = []
    ): array {
        $apiKey = $this->getApiKey();

        if (!$apiKey) {
            throw new \Exception('Gemini API key not configured. Please set it in database, config, or .env file.');
        }

        $translations = [];
        try {
            $prompt = $this->buildBatchTranslationPrompt(is_array($text)? $text : [$text], $sourceLang, $targetLangs);
            $translations = $this->callGeminiApi($prompt, $apiKey);

        } catch (\Exception $e) {
            logger()->error('Failed to translate text', [
                'text' => $text,
                'source' => $sourceLang,
                'target' => implode(", ", $targetLangs),
                'error' => $e->getMessage(),
            ]);

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
    protected function callGeminiApi(string $prompt, string $apiKey, int $attempt = 1): array
    {
        try {
            $model = config('ai-translator.gemini.model', 'gemini-2.0-flash');
            $body = json_decode($this->promptJson($prompt, $model), true);

            // Extract text from Gemini response
            if (isset($body) && is_array($body) && count($body) > 0) {
                return $body;
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
     * Build batch translation prompt.
     */
    protected function buildBatchTranslationPrompt(
        array $texts,
        string $sourceLang,
        array $targetLang
    ): string {
        $textsJson = json_encode($texts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $targetLang = implode(", ", $targetLang);
        return <<<PROMPT
You are a professional translator. Translate the following texts from local {$sourceLang} to local(s) {$targetLang}.
Local is ISO 639-1 language code (2 letters, lowercase) like en, bn, fr, es, de, ar, hi, zh, ja, ko etc.

Input is a JSON object where keys are identifiers and values are texts to translate.
Output MUST be a valid JSON object with the same keys and translated values.

Requirements:
- Provide ONLY a valid JSON object, no explanations
- Maintain the same keys as input
- Preserve formatting and placeholders in the texts
- Ensure consistent terminology across all translations
- Preserve placeholders like {variable}, {name}, etc. exactly
- Keep HTML tags and formatting: <b>, <i>, <a href="...">
- Maintain technical terms consistently
- Return ONLY the JSON object, no other text

Input JSON:
{$textsJson}
PROMPT;
    }

    private function promptJson($prompt, $model): string
    {
        config(['gemini.api_key' => $this->getApiKey()]);
        return Gemini::generativeModel(model: $model)
            ->withGenerationConfig(
                generationConfig: new GenerationConfig(
                    maxOutputTokens: 2048,
                    temperature: 0.3,
                    responseMimeType: ResponseMimeType::APPLICATION_JSON,
                    responseSchema: new Schema(
                        type: DataType::ARRAY,
                        items: new Schema(
                            type: DataType::OBJECT,
                            properties: [
                                'local' => new Schema(type: DataType::STRING),
                                'translated' => new Schema(type: DataType::STRING),
                            ],
                            required: ['local', 'translated']
                        ),
                    )
                )
            )
            ->generateContent($prompt)
            ->text();
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
