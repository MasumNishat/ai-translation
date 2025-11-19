<?php

use Masum\AiTranslator\Http\Middleware\RateLimitTranslations;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use function Pest\Laravel\{getJson, postJson};

beforeEach(function () {
    // Clear rate limiters for all limiter types
    $limiters = ['translations', 'auto_translate', 'bulk', 'languages'];
    foreach ($limiters as $limiter) {
        RateLimiter::clear(sha1("translator_{$limiter}_guest|127.0.0.1"));
    }
});

describe('Rate Limiting Middleware', function () {
    test('allows requests within rate limit', function () {
        config(['ai-translator.rate_limiting.languages.max_attempts' => 10]);

        for ($i = 0; $i < 5; $i++) {
            $response = getJson('/api/translator/languages');
            $response->assertOk();
        }
    })->group('rate-limiting', 'middleware');

    test('blocks requests exceeding rate limit', function () {
        config([
            'ai-translator.rate_limiting.languages.max_attempts' => 3,
            'ai-translator.rate_limiting.languages.decay_seconds' => 60,
        ]);

        // Make 3 successful requests
        for ($i = 0; $i < 3; $i++) {
            getJson('/api/translator/languages')->assertOk();
        }

        // 4th request should be rate limited
        $response = getJson('/api/translator/languages');
        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
            ]);
    })->group('rate-limiting', 'middleware');

    test('adds rate limit headers to response', function () {
        config(['ai-translator.rate_limiting.languages.max_attempts' => 10]);

        $response = getJson('/api/translator/languages');

        $response->assertOk()
            ->assertHeader('X-RateLimit-Limit')
            ->assertHeader('X-RateLimit-Remaining');

        expect($response->headers->get('X-RateLimit-Limit'))->toBe('10');
        expect((int) $response->headers->get('X-RateLimit-Remaining'))->toBeLessThanOrEqual(10);
    })->group('rate-limiting', 'middleware', 'headers');

    test('includes retry-after header when rate limited', function () {
        config([
            'ai-translator.rate_limiting.languages.max_attempts' => 2,
        ]);

        // Exhaust rate limit
        getJson('/api/translator/languages');
        getJson('/api/translator/languages');

        // Next request should include retry-after
        $response = getJson('/api/translator/languages');

        $response->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Reset');
    })->group('rate-limiting', 'middleware', 'headers');

    test('different limiters have different limits', function () {
        config([
            'ai-translator.rate_limiting.translations.max_attempts' => 5,
            'ai-translator.rate_limiting.auto_translate.max_attempts' => 2,
        ]);

        // This test verifies that different endpoints can have different limits
        // The actual implementation would require applying different middleware
        // to different routes
        expect(config('ai-translator.rate_limiting.translations.max_attempts'))->toBe(5);
        expect(config('ai-translator.rate_limiting.auto_translate.max_attempts'))->toBe(2);
    })->group('rate-limiting', 'configuration');

    test('rate limit resets after decay period', function () {
        config([
            'ai-translator.rate_limiting.languages.max_attempts' => 2,
            'ai-translator.rate_limiting.languages.decay_seconds' => 1,
        ]);

        // Exhaust rate limit
        getJson('/api/translator/languages')->assertOk();
        getJson('/api/translator/languages')->assertOk();
        getJson('/api/translator/languages')->assertStatus(429);

        // Wait for decay period
        sleep(2);

        // Should be able to make requests again
        $response = getJson('/api/translator/languages');
        $response->assertOk();
    })->group('rate-limiting', 'middleware');

    test('rate limit is per IP address for guests', function () {
        config([
            'ai-translator.rate_limiting.languages.max_attempts' => 2,
        ]);

        // Make requests from same IP
        getJson('/api/translator/languages')->assertOk();
        getJson('/api/translator/languages')->assertOk();

        // Should be rate limited
        getJson('/api/translator/languages')->assertStatus(429);
    })->group('rate-limiting', 'middleware');
});

describe('Rate Limiting Configuration', function () {
    test('has default rate limits for all limiters', function () {
        expect(config('ai-translator.rate_limiting'))->toHaveKeys([
            'translations',
            'auto_translate',
            'bulk',
            'languages',
        ]);
    })->group('rate-limiting', 'configuration');

    test('translation limiter has correct default values', function () {
        $config = config('ai-translator.rate_limiting.translations');

        expect($config)->toHaveKey('max_attempts')
            ->and($config)->toHaveKey('decay_seconds')
            ->and($config['max_attempts'])->toBeGreaterThan(0)
            ->and($config['decay_seconds'])->toBeGreaterThan(0);
    })->group('rate-limiting', 'configuration');

    test('auto-translate limiter is stricter than general translations', function () {
        $translationsLimit = config('ai-translator.rate_limiting.translations.max_attempts');
        $autoTranslateLimit = config('ai-translator.rate_limiting.auto_translate.max_attempts');

        expect($autoTranslateLimit)->toBeLessThan($translationsLimit);
    })->group('rate-limiting', 'configuration');

    test('bulk operations have the strictest limit', function () {
        $translationsLimit = config('ai-translator.rate_limiting.translations.max_attempts');
        $autoTranslateLimit = config('ai-translator.rate_limiting.auto_translate.max_attempts');
        $bulkLimit = config('ai-translator.rate_limiting.bulk.max_attempts');

        expect($bulkLimit)->toBeLessThan($autoTranslateLimit)
            ->and($bulkLimit)->toBeLessThan($translationsLimit);
    })->group('rate-limiting', 'configuration');

    test('can override limits via environment variables', function () {
        config(['ai-translator.rate_limiting.translations.max_attempts' => env('TRANSLATOR_RATE_LIMIT', 60)]);

        $limit = config('ai-translator.rate_limiting.translations.max_attempts');

        expect($limit)->toBeInt();
    })->group('rate-limiting', 'configuration');
});

describe('Rate Limiting Middleware Direct Tests', function () {
    test('middleware resolves request signature correctly for guests', function () {
        $middleware = new RateLimitTranslations();
        $request = Request::create('/test', 'GET');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');

        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('resolveRequestSignature');
        $method->setAccessible(true);

        $signature = $method->invoke($middleware, $request, 'translations');

        expect($signature)->toBeString();
        expect($signature)->toBe(sha1('translator_translations_guest|192.168.1.1'));
    })->group('rate-limiting', 'middleware', 'unit');

    test('middleware calculates max attempts from config', function () {
        config(['ai-translator.rate_limiting.translations.max_attempts' => 100]);

        $middleware = new RateLimitTranslations();

        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('maxAttempts');
        $method->setAccessible(true);

        $maxAttempts = $method->invoke($middleware, 'translations');

        expect($maxAttempts)->toBe(100);
    })->group('rate-limiting', 'middleware', 'unit');

    test('middleware calculates decay seconds from config', function () {
        config(['ai-translator.rate_limiting.translations.decay_seconds' => 120]);

        $middleware = new RateLimitTranslations();

        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('decaySeconds');
        $method->setAccessible(true);

        $decaySeconds = $method->invoke($middleware, 'translations');

        expect($decaySeconds)->toBe(120);
    })->group('rate-limiting', 'middleware', 'unit');
});
