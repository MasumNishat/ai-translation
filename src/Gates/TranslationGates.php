<?php

namespace Masum\AiTranslator\Gates;

use Illuminate\Support\Facades\Gate;

class TranslationGates
{
    /**
     * Register all translation-related gates.
     */
    public static function register(): void
    {
        // Manage languages (add, edit, delete languages)
        Gate::define(
            config('ai-translator.permissions.manage_languages', 'manage-languages'),
            function ($user) {
                // Default implementation - customize in your application
                // Example: return $user->hasRole('admin');
                return method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
            }
        );

        // Manage translations (CRUD operations on translations)
        Gate::define(
            config('ai-translator.permissions.manage_translations', 'manage-translations'),
            function ($user) {
                // Default implementation - customize in your application
                return method_exists($user, 'isAdmin') || (method_exists($user, 'can') && $user->can('edit content'));
            }
        );

        // Auto-translate (trigger AI translations)
        Gate::define(
            config('ai-translator.permissions.auto_translate', 'auto-translate'),
            function ($user) {
                // Default implementation - customize in your application
                return method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
            }
        );

        // Manage settings (update package settings including API key)
        Gate::define(
            config('ai-translator.permissions.manage_settings', 'manage-translator-settings'),
            function ($user) {
                // Default implementation - only admins can manage settings
                return method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
            }
        );

        // View translations
        Gate::define(
            config('ai-translator.permissions.view_translations', 'view-translations'),
            function ($user) {
                // Default implementation - most authenticated users can view
                return true;
            }
        );

        // Delete translations
        Gate::define(
            config('ai-translator.permissions.delete_translations', 'delete-translations'),
            function ($user) {
                // Default implementation - only admins can delete
                return method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
            }
        );

        // Export translations
        Gate::define(
            config('ai-translator.permissions.export_translations', 'export-translations'),
            function ($user) {
                // Default implementation - admins and editors can export
                return method_exists($user, 'isAdmin') || (method_exists($user, 'can') && $user->can('edit content'));
            }
        );

        // Import translations
        Gate::define(
            config('ai-translator.permissions.import_translations', 'import-translations'),
            function ($user) {
                // Default implementation - only admins can import
                return method_exists($user, 'isAdmin') ? $user->isAdmin() : false;
            }
        );
    }
}
