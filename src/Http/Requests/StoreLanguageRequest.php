<?php

namespace Masum\AiTranslator\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if authentication is required
        if (config('ai-translator.security.require_authentication', false)) {
            if (!$this->user()) {
                return false; // Deny if auth required but no user
            }
        }

        // If no user and guest access allowed (default for APIs/testing)
        if (!$this->user()) {
            return config('ai-translator.security.allow_guest_access', true);
        }

        // Check for superadmin permission
        $superadminPermission = config('ai-translator.security.superadmin_permission');
        if ($superadminPermission && $this->user()->can($superadminPermission)) {
            return true; // Superadmin bypasses all checks
        }

        // Check the specific permission
        return $this->user()->can(
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
