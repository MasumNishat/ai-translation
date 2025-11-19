<?php

namespace Masum\AiTranslator\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request with security checks.
     *
     * @param string|null $permission The required permission to check
     * @return bool
     */
    protected function authorizeWithSecurity(?string $permission = null): bool
    {
        // Check if public API mode is enabled (bypasses all auth)
        if (config('ai-translator.security.public_api', false)) {
            return true;
        }

        // Check if authentication is required
        $requireAuth = config('ai-translator.security.require_authentication', false);

        if ($requireAuth && !$this->user()) {
            return false; // Auth required but no user present
        }

        // If no user and guest access is allowed
        if (!$this->user()) {
            $authMode = config('ai-translator.security.authorization_mode', 'permissive');

            // In permissive mode, allow when no user (for testing/development)
            // In strict mode, deny when no user
            return $authMode === 'permissive';
        }

        // Check for superadmin permission (bypasses all other checks)
        $superadminPermission = config('ai-translator.security.superadmin_permission');
        if ($superadminPermission && $this->user()->can($superadminPermission)) {
            return true;
        }

        // Check the specific permission if provided
        if ($permission) {
            return $this->user()->can($permission);
        }

        // No specific permission required, user is authenticated
        return true;
    }
}
