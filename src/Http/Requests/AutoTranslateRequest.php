<?php

namespace Masum\AiTranslator\Http\Requests;

class AutoTranslateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizeWithSecurity(
            config('ai-translator.permissions.auto_translate', 'auto-translate')
        );
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string'],
            'source_language' => ['required', 'string', 'exists:languages,code'],
            'target_languages' => ['required', 'array', 'min:1'],
            'target_languages.*' => ['string', 'exists:languages,code'],
            'group' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.required' => 'Translation key is required.',
            'value.required' => 'Translation value is required.',
            'source_language.required' => 'Source language is required.',
            'source_language.exists' => 'The selected source language does not exist.',
            'target_languages.required' => 'At least one target language is required.',
            'target_languages.min' => 'At least one target language is required.',
            'target_languages.*.exists' => 'One or more target languages do not exist.',
        ];
    }
}
