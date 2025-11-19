<?php

namespace Masum\AiTranslator\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Services\TranslationSanitizer;

class StoreTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!$this->user()) { return true; } return $this->user()->can(config("ai-translator.permissions.manage_translations", "manage-translations"));
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        if (!config('ai-translator.sanitization.enabled', true)) {
            return;
        }

        if (!config('ai-translator.sanitization.sanitize_on_input', true)) {
            return;
        }

        $sanitizer = app(TranslationSanitizer::class);

        // Sanitize translation value
        if ($this->has('value')) {
            $this->merge([
                'value' => $sanitizer->sanitize($this->input('value')),
            ]);
        }

        // Sanitize key
        if ($this->has('key')) {
            $this->merge([
                'key' => $sanitizer->sanitizeKey($this->input('key')),
            ]);
        }

        // Sanitize group
        if ($this->has('group')) {
            $this->merge([
                'group' => $sanitizer->sanitizeGroup($this->input('group')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string'],
            'group' => ['nullable', 'string', 'max:255'],
            'language_code' => ['required', 'string', 'exists:languages,code'],
            'is_active' => ['boolean'],
            'auto_translate' => ['boolean'],
            'target_languages' => ['nullable', 'array'],
            'target_languages.*' => ['string', 'exists:languages,code'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.required' => 'Translation key is required.',
            'value.required' => 'Translation value is required.',
            'language_code.required' => 'Language code is required.',
            'language_code.exists' => 'The selected language does not exist.',
            'target_languages.*.exists' => 'One or more target languages do not exist.',
        ];
    }

    /**
     * Get validated data with language_id instead of language_code.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if (isset($validated['language_code'])) {
            $language = Language::getByCode($validated['language_code']);
            $validated['language_id'] = $language?->id;
            unset($validated['language_code']);
        }

        return $validated;
    }
}
