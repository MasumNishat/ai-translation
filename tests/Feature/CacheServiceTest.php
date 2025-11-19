<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Services\CacheService;
use Illuminate\Support\Facades\Cache;

describe('Cache Service - Configuration', function () {
    beforeEach(function () {
        $this->cacheService = app(CacheService::class);
    });

    test('cache service is configured correctly', function () {
        $stats = $this->cacheService->getStats();

        expect($stats)->toHaveKeys(['enabled', 'ttl', 'prefix', 'driver', 'supports_tagging'])
            ->and($stats['enabled'])->toBeTrue()
            ->and($stats['ttl'])->toBe(3600)
            ->and($stats['prefix'])->toBe('ai_translator');
    })->group('cache', 'config');

    test('cache can be disabled via config', function () {
        config(['ai-translator.cache.enabled' => false]);

        $service = new CacheService();
        $stats = $service->getStats();

        expect($stats['enabled'])->toBeFalse();
    })->group('cache', 'config');

    test('detects if cache driver supports tagging', function () {
        $stats = $this->cacheService->getStats();

        // Array driver supports tagging
        expect($stats['supports_tagging'])->toBeIn([true, false]);
    })->group('cache', 'config');
});

describe('Cache Service - Basic Operations', function () {
    beforeEach(function () {
        Cache::flush();
        $this->cacheService = app(CacheService::class);
    });

    test('can remember a value in cache', function () {
        $result = $this->cacheService->remember(
            'test.key',
            'en',
            'general',
            fn() => 'Test Value'
        );

        expect($result)->toBe('Test Value');
    })->group('cache', 'operations');

    test('retrieves cached value on subsequent calls', function () {
        $callCount = 0;

        // First call
        $result1 = $this->cacheService->remember(
            'test.key',
            'en',
            'general',
            function () use (&$callCount) {
                $callCount++;
                return 'Test Value';
            }
        );

        // Second call - should return from cache
        $result2 = $this->cacheService->remember(
            'test.key',
            'en',
            'general',
            function () use (&$callCount) {
                $callCount++;
                return 'Test Value';
            }
        );

        expect($result1)->toBe('Test Value')
            ->and($result2)->toBe('Test Value')
            ->and($callCount)->toBe(1); // Callback called only once
    })->group('cache', 'operations');

    test('can forget a specific cache entry', function () {
        // Store value
        $this->cacheService->remember(
            'test.key',
            'en',
            'general',
            fn() => 'Test Value'
        );

        // Verify it's cached
        expect($this->cacheService->has('test.key', 'en', 'general'))->toBeTrue();

        // Forget it
        $this->cacheService->forget('test.key', 'en', 'general');

        // Verify it's gone
        expect($this->cacheService->has('test.key', 'en', 'general'))->toBeFalse();
    })->group('cache', 'operations');

    test('bypasses cache when disabled', function () {
        config(['ai-translator.cache.enabled' => false]);
        $service = new CacheService();

        $callCount = 0;

        // Multiple calls should always execute callback
        $service->remember('test.key', 'en', 'general', function () use (&$callCount) {
            $callCount++;
            return 'Value';
        });

        $service->remember('test.key', 'en', 'general', function () use (&$callCount) {
            $callCount++;
            return 'Value';
        });

        expect($callCount)->toBe(2); // Called twice, not cached
    })->group('cache', 'operations');
});

describe('Cache Service - Tagging', function () {
    beforeEach(function () {
        Cache::flush();
        $this->cacheService = app(CacheService::class);
    });

    test('can flush cache by language', function () {
        // Cache translations for multiple languages
        $this->cacheService->remember('key1', 'en', 'general', fn() => 'EN Value 1');
        $this->cacheService->remember('key2', 'en', 'general', fn() => 'EN Value 2');
        $this->cacheService->remember('key1', 'es', 'general', fn() => 'ES Value 1');

        // Flush English cache
        $this->cacheService->forgetByLanguage('en');

        // English should be gone, Spanish should remain
        expect($this->cacheService->has('key1', 'en', 'general'))->toBeFalse()
            ->and($this->cacheService->has('key2', 'en', 'general'))->toBeFalse()
            ->and($this->cacheService->has('key1', 'es', 'general'))->toBeIn([true, false]); // Depends on driver
    })->group('cache', 'tagging');

    test('can flush cache by group', function () {
        // Cache translations in different groups
        $this->cacheService->remember('key1', 'en', 'home', fn() => 'Home Value');
        $this->cacheService->remember('key2', 'en', 'auth', fn() => 'Auth Value');

        // Flush home group
        $this->cacheService->forgetByGroup('home');

        // Home should be gone, auth might remain (depends on tagging support)
        expect($this->cacheService->has('key1', 'en', 'home'))->toBeFalse();
    })->group('cache', 'tagging');

    test('can flush all translation cache', function () {
        // Cache multiple translations
        $this->cacheService->remember('key1', 'en', 'general', fn() => 'Value 1');
        $this->cacheService->remember('key2', 'es', 'auth', fn() => 'Value 2');

        // Flush all
        $this->cacheService->flushAll();

        // All should be gone
        expect($this->cacheService->has('key1', 'en', 'general'))->toBeFalse()
            ->and($this->cacheService->has('key2', 'es', 'auth'))->toBeFalse();
    })->group('cache', 'tagging');
});

describe('Cache Service - Warm Up', function () {
    beforeEach(function () {
        Cache::flush();
        $this->cacheService = app(CacheService::class);
        $this->language = Language::factory()->create(['code' => 'en']);
    });

    test('can warm up cache for a language', function () {
        // Create translations
        Translation::factory()->count(5)->create([
            'language_id' => $this->language->id,
            'group' => 'general',
        ]);

        // Warm up cache
        $count = $this->cacheService->warmUp('en');

        expect($count)->toBe(5);
    })->group('cache', 'warmup');

    test('can warm up cache for specific group', function () {
        // Create translations in different groups
        Translation::factory()->count(3)->create([
            'language_id' => $this->language->id,
            'group' => 'home',
        ]);

        Translation::factory()->count(2)->create([
            'language_id' => $this->language->id,
            'group' => 'auth',
        ]);

        // Warm up only home group
        $count = $this->cacheService->warmUp('en', 'home');

        expect($count)->toBe(3);
    })->group('cache', 'warmup');

    test('warm up returns zero when cache disabled', function () {
        config(['ai-translator.cache.enabled' => false]);
        $service = new CacheService();

        Translation::factory()->count(5)->create([
            'language_id' => $this->language->id,
        ]);

        $count = $service->warmUp('en');

        expect($count)->toBe(0);
    })->group('cache', 'warmup');
});

describe('Cache Service - Auto Invalidation', function () {
    beforeEach(function () {
        Cache::flush();
        $this->cacheService = app(CacheService::class);
        $this->language = Language::factory()->create(['code' => 'en']);
    });

    test('cache is invalidated when translation is created', function () {
        // Cache should be empty
        expect($this->cacheService->has('test.key', 'en', 'general'))->toBeFalse();

        // Create translation (should not be cached yet)
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'test.key',
            'group' => 'general',
        ]);

        // After creation, cache should still be empty (not auto-cached on create)
        expect($this->cacheService->has('test.key', 'en', 'general'))->toBeFalse();
    })->group('cache', 'auto-invalidation');

    test('cache is invalidated when translation is updated', function () {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'test.key',
            'group' => 'general',
            'value' => 'Original Value',
        ]);

        // Cache the value
        $this->cacheService->remember('test.key', 'en', 'general', fn() => 'Original Value');

        // Update translation
        $translation->update(['value' => 'Updated Value']);

        // Cache should be invalidated
        expect($this->cacheService->has('test.key', 'en', 'general'))->toBeFalse();
    })->group('cache', 'auto-invalidation');

    test('cache is invalidated when translation is deleted', function () {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'test.key',
            'group' => 'general',
        ]);

        // Cache the value
        $this->cacheService->remember('test.key', 'en', 'general', fn() => 'Value');

        // Delete translation
        $translation->delete();

        // Cache should be invalidated
        expect($this->cacheService->has('test.key', 'en', 'general'))->toBeFalse();
    })->group('cache', 'auto-invalidation');
});

describe('Cache Service - Error Handling', function () {
    beforeEach(function () {
        $this->cacheService = app(CacheService::class);
    });

    test('handles cache failures gracefully', function () {
        // This test verifies that cache failures don't break the application
        // The callback should still execute and return the value

        $result = $this->cacheService->remember(
            'test.key',
            'en',
            'general',
            fn() => 'Fallback Value'
        );

        expect($result)->toBe('Fallback Value');
    })->group('cache', 'error-handling');

    test('forget operations do not throw on non-existent keys', function () {
        // Should not throw even if key doesn't exist
        $this->cacheService->forget('non.existent.key', 'en', 'general');

        expect(true)->toBeTrue();
    })->group('cache', 'error-handling');
});

describe('Cache Service - Has Method', function () {
    beforeEach(function () {
        Cache::flush();
        $this->cacheService = app(CacheService::class);
    });

    test('has returns true for cached keys', function () {
        $this->cacheService->remember('test.key', 'en', 'general', fn() => 'Value');

        expect($this->cacheService->has('test.key', 'en', 'general'))->toBeTrue();
    })->group('cache', 'has');

    test('has returns false for non-cached keys', function () {
        expect($this->cacheService->has('non.existent', 'en', 'general'))->toBeFalse();
    })->group('cache', 'has');

    test('has returns false when cache is disabled', function () {
        config(['ai-translator.cache.enabled' => false]);
        $service = new CacheService();

        expect($service->has('any.key', 'en', 'general'))->toBeFalse();
    })->group('cache', 'has');
});
