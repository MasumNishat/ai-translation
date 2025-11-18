<?php

namespace Masum\AiTranslator;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Masum\AiTranslator\Gates\TranslationGates;
use Masum\AiTranslator\Http\Middleware\SetLocale;
use Masum\AiTranslator\Services\GeminiTranslationService;
use Masum\AiTranslator\Services\TranslationService;

class AiTranslatorServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__.'/../config/ai-translator.php',
            'ai-translator'
        );

        // Register services
        $this->app->singleton(GeminiTranslationService::class, function ($app) {
            return new GeminiTranslationService();
        });

        $this->app->singleton(TranslationService::class, function ($app) {
            return new TranslationService(
                $app->make(GeminiTranslationService::class)
            );
        });
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register gates for authorization
        TranslationGates::register();

        // Register middleware
        $this->registerMiddleware();

        // Load routes if enabled
        if (config('ai-translator.routes.enabled', true)) {
            $this->loadRoutes();
        }

        // Publish configuration
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ai-translator.php' => config_path('ai-translator.php'),
            ], 'ai-translator-config');

            // Publish migrations
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'ai-translator-migrations');

            // Register commands
            $this->commands([
                // Commands can be added here
            ]);
        }
    }

    /**
     * Register middleware.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        // Register middleware
        $router->aliasMiddleware('translator.locale', SetLocale::class);

        // Optionally auto-append to web and api middleware groups
        // Users can manually add it if they prefer
    }

    /**
     * Load package routes.
     */
    protected function loadRoutes(): void
    {
        $prefix = config('ai-translator.routes.prefix', 'api/translator');
        $middleware = config('ai-translator.routes.middleware', ['api']);
        $namePrefix = config('ai-translator.routes.name_prefix', 'translator.');

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Register routes with configuration
        \Illuminate\Support\Facades\Route::group([
            'prefix' => $prefix,
            'middleware' => $middleware,
            'as' => $namePrefix,
        ], function () {
            require __DIR__.'/../routes/api.php';
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            GeminiTranslationService::class,
            TranslationService::class,
        ];
    }
}
