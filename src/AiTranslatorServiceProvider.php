<?php

namespace Masum\AiTranslator;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
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

        // Register Blade directives
        $this->registerBladeDirectives();

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
                \Masum\AiTranslator\Console\Commands\ClearTranslationCacheCommand::class,
                \Masum\AiTranslator\Console\Commands\TranslationStatsCommand::class,
                \Masum\AiTranslator\Console\Commands\SyncTranslationsCommand::class,
                \Masum\AiTranslator\Console\Commands\ExportTranslationsCommand::class,
                \Masum\AiTranslator\Console\Commands\ImportTranslationsCommand::class,
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
     * Register Blade directives.
     */
    protected function registerBladeDirectives(): void
    {
        // @aitrans('key', ['name' => 'John'])
        Blade::directive('aitrans', function ($expression) {
            return "<?php echo ai_trans($expression); ?>";
        });

        // @aitranschoice('key', $count)
        Blade::directive('aitranschoice', function ($expression) {
            return "<?php echo ai_trans_choice($expression); ?>";
        });

        // @language('en') ... @endlanguage
        Blade::directive('language', function ($expression) {
            return "<?php app()->setLocale($expression); ?>";
        });

        Blade::directive('endlanguage', function () {
            return "<?php app()->setLocale(config('app.locale')); ?>";
        });

        // @languages - loop through active languages
        Blade::directive('languages', function ($expression) {
            return "<?php foreach(ai_languages() as $expression): ?>";
        });

        Blade::directive('endlanguages', function () {
            return "<?php endforeach; ?>";
        });

        // @currentlang - get current language code
        Blade::directive('currentlang', function () {
            return "<?php echo app()->getLocale(); ?>";
        });

        // @defaultlang - get default language code
        Blade::directive('defaultlang', function () {
            return "<?php echo ai_default_language()?->code; ?>";
        });

        // @rtl - check if current language is RTL
        Blade::directive('rtl', function () {
            return "<?php if(ai_current_language()?->is_rtl ?? false): ?>";
        });

        Blade::directive('endrtl', function () {
            return "<?php endif; ?>";
        });

        // @ltr - check if current language is LTR
        Blade::directive('ltr', function () {
            return "<?php if(!(ai_current_language()?->is_rtl ?? true)): ?>";
        });

        Blade::directive('endltr', function () {
            return "<?php endif; ?>";
        });

        // @hastrans('key') ... @endhastrans
        Blade::directive('hastrans', function ($expression) {
            return "<?php if(ai_has_trans($expression)): ?>";
        });

        Blade::directive('endhastrans', function () {
            return "<?php endif; ?>";
        });

        // @transgroup('group') - output all translations in group as JSON
        Blade::directive('transgroup', function ($expression) {
            return "<?php echo json_encode(ai_trans_group($expression)); ?>";
        });

        // @missingtrans - show count of missing translations (dev mode only)
        Blade::directive('missingtrans', function ($expression) {
            return "<?php if(config('app.debug')): echo 'Missing: ' . ai_trans_missing($expression); endif; ?>";
        });

        // @translang - Display native language name
        Blade::directive('translang', function ($expression) {
            return "<?php echo \Masum\AiTranslator\Models\Language::where('code', $expression)->first()?->native_name ?? $expression; ?>";
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
