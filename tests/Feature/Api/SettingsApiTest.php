<?php

use Masum\AiTranslator\Models\PackageSetting;
use function Pest\Laravel\{getJson, postJson};

describe('Settings API - GET /api/translator/settings', function () {
    test('can get all settings', function () {
        PackageSetting::create([
            'key' => 'test_setting',
            'value' => 'test_value',
            'type' => 'string',
        ]);

        $response = getJson('/api/translator/settings');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'key',
                        'value',
                        'type',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    })->group('api', 'settings');

    test('returns empty array when no settings exist', function () {
        $response = getJson('/api/translator/settings');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    })->group('api', 'settings');
});

describe('Settings API - GET /api/translator/settings/{key}', function () {
    test('can get a specific setting', function () {
        PackageSetting::create([
            'key' => 'cache_ttl',
            'value' => '3600',
            'type' => 'integer',
        ]);

        $response = getJson('/api/translator/settings/cache_ttl');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'key' => 'cache_ttl',
                    'value' => '3600',
                ],
            ]);
    })->group('api', 'settings');

    test('returns 404 for non-existent setting', function () {
        $response = getJson('/api/translator/settings/nonexistent');

        $response->assertNotFound();
    })->group('api', 'settings');
});

describe('Settings API - POST /api/translator/settings', function () {
    test('can create or update a setting', function () {
        $data = [
            'key' => 'new_setting',
            'value' => 'new_value',
            'type' => 'string',
        ];

        $response = postJson('/api/translator/settings', $data);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'key' => 'new_setting',
                    'value' => 'new_value',
                ],
            ]);

        expect(PackageSetting::where('key', 'new_setting')->exists())->toBeTrue();
    })->group('api', 'settings');

    test('updates existing setting', function () {
        PackageSetting::create([
            'key' => 'existing_setting',
            'value' => 'old_value',
            'type' => 'string',
        ]);

        $response = postJson('/api/translator/settings', [
            'key' => 'existing_setting',
            'value' => 'new_value',
            'type' => 'string',
        ]);

        $response->assertOk();

        $setting = PackageSetting::where('key', 'existing_setting')->first();
        expect($setting->value)->toBe('new_value');
    })->group('api', 'settings');

    test('validates required fields', function () {
        $response = postJson('/api/translator/settings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['key', 'value']);
    })->group('api', 'settings');

    test('validates type field when provided', function () {
        $response = postJson('/api/translator/settings', [
            'key' => 'test',
            'value' => 'test',
            'type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    })->group('api', 'settings');

    test('accepts valid type values', function () {
        $validTypes = ['string', 'integer', 'boolean', 'json', 'array'];

        foreach ($validTypes as $type) {
            $response = postJson('/api/translator/settings', [
                'key' => "test_{$type}",
                'value' => 'test',
                'type' => $type,
            ]);

            $response->assertOk();
        }
    })->group('api', 'settings');
});

describe('Settings API - Typed Values', function () {
    test('handles boolean values correctly', function () {
        postJson('/api/translator/settings', [
            'key' => 'is_enabled',
            'value' => 'true',
            'type' => 'boolean',
        ]);

        $setting = PackageSetting::where('key', 'is_enabled')->first();
        expect($setting->value)->toBe('true');
    })->group('api', 'settings');

    test('handles integer values correctly', function () {
        postJson('/api/translator/settings', [
            'key' => 'max_retries',
            'value' => '5',
            'type' => 'integer',
        ]);

        $setting = PackageSetting::where('key', 'max_retries')->first();
        expect($setting->value)->toBe('5');
    })->group('api', 'settings');

    test('handles json values correctly', function () {
        postJson('/api/translator/settings', [
            'key' => 'config',
            'value' => json_encode(['foo' => 'bar']),
            'type' => 'json',
        ]);

        $setting = PackageSetting::where('key', 'config')->first();
        $decoded = json_decode($setting->value, true);

        expect($decoded)->toBe(['foo' => 'bar']);
    })->group('api', 'settings');
});
