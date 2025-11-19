<?php

namespace Masum\AiTranslator\Http\Requests;

class StoreLanguageRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->authorizeWithSecurity(
            config('ai-translator.permissions.manage_languages', 'manage-languages')
        );
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', 'unique:languages,code'],
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['required', 'string', 'max:255'],
            'direction' => ['required', 'in:ltr,rtl'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'region' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Language code is required.',
            'code.unique' => 'This language code already exists.',
            'name.required' => 'Language name is required.',
            'native_name.required' => 'Native language name is required.',
            'direction.required' => 'Text direction is required.',
            'direction.in' => 'Text direction must be either ltr or rtl.',
        ];
    }
}
