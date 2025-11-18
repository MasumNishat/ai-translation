<?php

namespace Masum\AiTranslator\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('ai-translator.permissions.manage_translations', 'manage-translations')) ?? false;
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
