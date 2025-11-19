<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Facades\Cache;
use function Pest\Laravel\{getJson, postJson, putJson, deleteJson};

beforeEach(function () {
    $this->language = Language::factory()->english()->create();
    $this->spanish = Language::factory()->spanish()->create();
});

describe('Translation API - GET /api/translator/translations', function () {
    test('can list all translations', function () {
        Translation::factory()->count(5)->create([
            'language_id' => $this->language->id,
        ]);

        $response = getJson('/api/translator/translations');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'key',
                        'value',
                        'language_id',
                        'group',
                        'is_active',
                        'is_auto_translated',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        expect($response->json('data'))->toHaveCount(5);
    })->group('api', 'translations');

    test('can filter translations by language', function () {
        Translation::factory()->count(3)->create(['language_id' => $this->language->id]);
        Translation::factory()->count(2)->create(['language_id' => $this->spanish->id]);

        $response = getJson("/api/translator/translations?language={$this->language->code}");

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(3);
    })->group('api', 'translations');

    test('can filter translations by group', function () {
        Translation::factory()->count(2)->create([
            'language_id' => $this->language->id,
            'group' => 'auth',
        ]);

        Translation::factory()->count(3)->create([
            'language_id' => $this->language->id,
            'group' => 'common',
        ]);

        $response = getJson('/api/translator/translations?group=auth');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    })->group('api', 'translations');

    test('can search translations by key or value', function () {
        Translation::factory()->create([
            'key' => 'auth.login',
            'value' => 'Login',
            'language_id' => $this->language->id,
        ]);

        Translation::factory()->create([
            'key' => 'auth.logout',
            'value' => 'Logout',
            'language_id' => $this->language->id,
        ]);

        $response = getJson('/api/translator/translations?search=login');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    })->group('api', 'translations');

    test('supports pagination', function () {
        Translation::factory()->count(25)->create([
            'language_id' => $this->language->id,
        ]);

        $response = getJson('/api/translator/translations?per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links',
            ]);

        expect($response->json('data'))->toHaveCount(10);
        expect($response->json('meta.total'))->toBe(25);
    })->group('api', 'translations');
});

describe('Translation API - GET /api/translator/translations/{id}', function () {
    test('can get a specific translation', function () {
        $translation = Translation::factory()->create([
            'key' => 'test.key',
            'value' => 'Test Value',
            'language_id' => $this->language->id,
        ]);

        $response = getJson("/api/translator/translations/{$translation->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $translation->id,
                    'key' => 'test.key',
                    'value' => 'Test Value',
                ],
            ]);
    })->group('api', 'translations');

    test('returns 404 for non-existent translation', function () {
        $response = getJson('/api/translator/translations/99999');

        $response->assertNotFound();
    })->group('api', 'translations');
});

describe('Translation API - POST /api/translator/translations', function () {
    test('can create a new translation', function () {
        $data = [
            'key' => 'auth.login',
            'value' => 'Login',
            'language_code' => $this->language->code,
            'group' => 'auth',
        ];

        $response = postJson('/api/translator/translations', $data);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'key' => 'auth.login',
                    'value' => 'Login',
                ],
            ]);

        expect(Translation::where('key', 'auth.login')->exists())->toBeTrue();
    })->group('api', 'translations');

    test('validates required fields', function () {
        $response = postJson('/api/translator/translations', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['key', 'value', 'language_code', 'group']);
    })->group('api', 'translations');

    test('validates language_code exists', function () {
        $response = postJson('/api/translator/translations', [
            'key' => 'test.key',
            'value' => 'Test',
            'language_code' => 'invalid',
            'group' => 'test',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['language_code']);
    })->group('api', 'translations');

    test('prevents duplicate keys for same language', function () {
        Translation::factory()->create([
            'key' => 'auth.login',
            'language_id' => $this->language->id,
        ]);

        $response = postJson('/api/translator/translations', [
            'key' => 'auth.login',
            'value' => 'Login',
            'language_code' => $this->language->code,
            'group' => 'auth',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['key']);
    })->group('api', 'translations');

    test('clears cache after creating translation', function () {
        $cacheKey = "ai_translator.auth.login.{$this->language->code}";
        Cache::put($cacheKey, 'cached_value', 3600);

        postJson('/api/translator/translations', [
            'key' => 'auth.login',
            'value' => 'Login',
            'language_code' => $this->language->code,
            'group' => 'auth',
        ]);

        expect(Cache::has($cacheKey))->toBeFalse();
    })->group('api', 'translations', 'cache');
});

describe('Translation API - PUT /api/translator/translations/{id}', function () {
    test('can update a translation', function () {
        $translation = Translation::factory()->create([
            'value' => 'Old Value',
            'language_id' => $this->language->id,
        ]);

        $response = putJson("/api/translator/translations/{$translation->id}", [
            'value' => 'New Value',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'value' => 'New Value',
                ],
            ]);

        expect($translation->fresh()->value)->toBe('New Value');
    })->group('api', 'translations');

    test('can update is_active status', function () {
        $translation = Translation::factory()->create([
            'is_active' => false,
            'language_id' => $this->language->id,
        ]);

        $response = putJson("/api/translator/translations/{$translation->id}", [
            'is_active' => true,
        ]);

        $response->assertOk();
        expect($translation->fresh()->is_active)->toBeTrue();
    })->group('api', 'translations');

    test('clears cache after updating translation', function () {
        $translation = Translation::factory()->create([
            'key' => 'auth.login',
            'language_id' => $this->language->id,
            'group' => 'auth',
        ]);

        $cacheKey = "ai_translator.auth.auth.login.{$this->language->code}";
        Cache::put($cacheKey, 'cached_value', 3600);

        putJson("/api/translator/translations/{$translation->id}", [
            'value' => 'Updated Value',
        ]);

        expect(Cache::has($cacheKey))->toBeFalse();
    })->group('api', 'translations', 'cache');
});

describe('Translation API - DELETE /api/translator/translations/{id}', function () {
    test('can delete a translation', function () {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
        ]);

        $response = deleteJson("/api/translator/translations/{$translation->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Translation deleted successfully',
            ]);

        expect(Translation::find($translation->id))->toBeNull();
    })->group('api', 'translations');

    test('returns 404 when deleting non-existent translation', function () {
        $response = deleteJson('/api/translator/translations/99999');

        $response->assertNotFound();
    })->group('api', 'translations');
});

describe('Translation API - GET /api/translator/translations/get', function () {
    test('can get translation by key and language', function () {
        Translation::factory()->create([
            'key' => 'auth.login',
            'value' => 'Login',
            'language_id' => $this->language->id,
            'group' => 'auth',
        ]);

        $response = getJson("/api/translator/translations/get?key=auth.login&language={$this->language->code}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => 'Login',
            ]);
    })->group('api', 'translations');

    test('validates required parameters', function () {
        $response = getJson('/api/translator/translations/get');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['key', 'language']);
    })->group('api', 'translations');

    test('returns key when translation not found', function () {
        $response = getJson("/api/translator/translations/get?key=nonexistent.key&language={$this->language->code}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => 'nonexistent.key',
            ]);
    })->group('api', 'translations');
});

describe('Translation API - POST /api/translator/translations/auto-translate', function () {
    test('validates required fields for auto-translate', function () {
        $response = postJson('/api/translator/translations/auto-translate', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['key', 'value', 'source_language', 'target_languages']);
    })->group('api', 'translations', 'auto-translate');

    test('validates source language exists', function () {
        $response = postJson('/api/translator/translations/auto-translate', [
            'key' => 'test.key',
            'value' => 'Test',
            'source_language' => 'invalid',
            'target_languages' => ['es'],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['source_language']);
    })->group('api', 'translations', 'auto-translate');

    test('validates target languages are array', function () {
        $response = postJson('/api/translator/translations/auto-translate', [
            'key' => 'test.key',
            'value' => 'Test',
            'source_language' => $this->language->code,
            'target_languages' => 'not-an-array',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['target_languages']);
    })->group('api', 'translations', 'auto-translate');
});

describe('Translation API - GET /api/translator/translations/groups', function () {
    test('can get list of all translation groups', function () {
        Translation::factory()->create([
            'group' => 'auth',
            'language_id' => $this->language->id,
        ]);

        Translation::factory()->create([
            'group' => 'common',
            'language_id' => $this->language->id,
        ]);

        Translation::factory()->create([
            'group' => 'auth',
            'language_id' => $this->spanish->id,
        ]);

        $response = getJson('/api/translator/translations/groups');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        $groups = $response->json('data');
        expect($groups)->toContain('auth')
            ->and($groups)->toContain('common');
    })->group('api', 'translations');
});
