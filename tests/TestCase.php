<?php

namespace Masum\AiTranslator\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Masum\AiTranslator\AiTranslatorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run package migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Define gates for testing
        $this->defineTestGates();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AiTranslatorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Setup package config
        $app['config']->set('ai-translator.gemini.api_key', 'test-api-key');
        $app['config']->set('ai-translator.gemini.model', 'gemini-pro');
        $app['config']->set('ai-translator.cache.ttl', 3600);
        $app['config']->set('ai-translator.cache.enabled', true);

        // Setup permissions
        $app['config']->set('ai-translator.permissions.manage_languages', 'manage-languages');
        $app['config']->set('ai-translator.permissions.manage_translations', 'manage-translations');
        $app['config']->set('ai-translator.permissions.auto_translate', 'auto-translate');
        $app['config']->set('ai-translator.permissions.manage_translator_settings', 'manage-translator-settings');
        $app['config']->set('ai-translator.permissions.view_translations', 'view-translations');
        $app['config']->set('ai-translator.permissions.delete_translations', 'delete-translations');

        // Disable authentication requirement for testing
        $app['config']->set('ai-translator.security.require_authentication', false);
    }

    /**
     * Define test gates
     */
    protected function defineTestGates(): void
    {
        // Allow all permissions for testing by default
        Gate::define('manage-languages', fn($user = null) => true);
        Gate::define('manage-translations', fn($user = null) => true);
        Gate::define('auto-translate', fn($user = null) => true);
        Gate::define('manage-translator-settings', fn($user = null) => true);
        Gate::define('view-translations', fn($user = null) => true);
        Gate::define('delete-translations', fn($user = null) => true);
        Gate::define('export-translations', fn($user = null) => true);
        Gate::define('import-translations', fn($user = null) => true);
        Gate::define('view-analytics', fn($user = null) => true);
    }

    /**
     * Define routes for testing
     */
    protected function defineRoutes($router): void
    {
        // Routes are automatically loaded by the service provider
    }
}
