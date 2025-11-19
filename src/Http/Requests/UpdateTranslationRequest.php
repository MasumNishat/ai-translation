<?php

namespace Masum\AiTranslator\Http\Requests;

use Masum\AiTranslator\Services\TranslationSanitizer;

class UpdateTranslationRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizeWithSecurity(
            config('ai-translator.permissions.manage_translations', 'manage-translations')
        );
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
