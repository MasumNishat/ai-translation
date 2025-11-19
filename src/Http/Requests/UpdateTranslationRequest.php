<?php

namespace Masum\AiTranslator\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Masum\AiTranslator\Services\TranslationSanitizer;

class UpdateTranslationRequest extends FormRequest
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
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required' => 'Translation value is required.',
        ];
    }
}
