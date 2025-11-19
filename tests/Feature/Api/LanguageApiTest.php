<?php

use Masum\AiTranslator\Models\Language;
use function Pest\Laravel\{getJson, postJson, putJson, deleteJson};

describe('Language API - GET /api/translator/languages', function () {
    test('can list all languages', function () {
        Language::factory()->count(3)->create();

        $response = getJson('/api/translator/languages');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'native_name',
                        'direction',
                        'is_active',
                        'is_default',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        expect($response->json('data'))->toHaveCount(3);
    })->group('api', 'languages');

    test('can filter active languages only', function () {
        Language::factory()->count(2)->create(['is_active' => true]);
        Language::factory()->count(1)->create(['is_active' => false]);

        $response = getJson('/api/translator/languages?active_only=true');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(2);
    })->group('api', 'languages');

    test('returns empty array when no languages exist', function () {
        $response = getJson('/api/translator/languages');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    })->group('api', 'languages');
});

describe('Language API - GET /api/translator/languages/{id}', function () {
    test('can get a specific language', function () {
        $language = Language::factory()->english()->create();

        $response = getJson("/api/translator/languages/{$language->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $language->id,
                    'code' => 'en',
                    'name' => 'English',
                    'native_name' => 'English',
                ],
            ]);
    })->group('api', 'languages');

    test('returns 404 for non-existent language', function () {
        $response = getJson('/api/translator/languages/99999');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
            ]);
    })->group('api', 'languages');
});

describe('Language API - POST /api/translator/languages', function () {
    test('can create a new language', function () {
        $data = [
            'code' => 'de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
            'country_code' => 'DE',
            'region' => 'Europe',
        ];

        $response = postJson('/api/translator/languages', $data);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'de',
                    'name' => 'German',
                    'native_name' => 'Deutsch',
                ],
            ]);

        expect(Language::where('code', 'de')->exists())->toBeTrue();
    })->group('api', 'languages');

    test('validates required fields', function () {
        $response = postJson('/api/translator/languages', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name', 'native_name', 'direction']);
    })->group('api', 'languages');

    test('validates language code uniqueness', function () {
        Language::factory()->create(['code' => 'en']);

        $response = postJson('/api/translator/languages', [
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'direction' => 'ltr',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    })->group('api', 'languages');

    test('validates direction field', function () {
        $response = postJson('/api/translator/languages', [
            'code' => 'xx',
            'name' => 'Test',
            'native_name' => 'Test',
            'direction' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['direction']);
    })->group('api', 'languages');
});

describe('Language API - PUT /api/translator/languages/{id}', function () {
    test('can update a language', function () {
        $language = Language::factory()->create([
            'name' => 'Old Name',
        ]);

        $response = putJson("/api/translator/languages/{$language->id}", [
            'name' => 'New Name',
            'native_name' => 'Updated',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'New Name',
                    'native_name' => 'Updated',
                ],
            ]);

        expect($language->fresh()->name)->toBe('New Name');
    })->group('api', 'languages');

    test('cannot change language code', function () {
        $language = Language::factory()->create(['code' => 'en']);

        $response = putJson("/api/translator/languages/{$language->id}", [
            'code' => 'es',
        ]);

        // Code should not change
        expect($language->fresh()->code)->toBe('en');
    })->group('api', 'languages');

    test('validates updated data', function () {
        $language = Language::factory()->create();

        $response = putJson("/api/translator/languages/{$language->id}", [
            'direction' => 'invalid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['direction']);
    })->group('api', 'languages');
});

describe('Language API - DELETE /api/translator/languages/{id}', function () {
    test('can delete a language', function () {
        $language = Language::factory()->create(['is_default' => false]);

        $response = deleteJson("/api/translator/languages/{$language->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Language deleted successfully',
            ]);

        expect(Language::find($language->id))->toBeNull();
    })->group('api', 'languages');

    test('cannot delete default language', function () {
        $language = Language::factory()->create(['is_default' => true]);

        $response = deleteJson("/api/translator/languages/{$language->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        expect(Language::find($language->id))->not->toBeNull();
    })->group('api', 'languages');

    test('returns 404 when deleting non-existent language', function () {
        $response = deleteJson('/api/translator/languages/99999');

        $response->assertNotFound();
    })->group('api', 'languages');
});

describe('Language API - POST /api/translator/languages/{id}/activate', function () {
    test('can activate a language', function () {
        $language = Language::factory()->create(['is_active' => false]);

        $response = postJson("/api/translator/languages/{$language->id}/activate");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        expect($language->fresh()->is_active)->toBeTrue();
    })->group('api', 'languages');
});

describe('Language API - POST /api/translator/languages/{id}/deactivate', function () {
    test('can deactivate a language', function () {
        $language = Language::factory()->create([
            'is_active' => true,
            'is_default' => false,
        ]);

        $response = postJson("/api/translator/languages/{$language->id}/deactivate");

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        expect($language->fresh()->is_active)->toBeFalse();
    })->group('api', 'languages');

    test('cannot deactivate default language', function () {
        $language = Language::factory()->create([
            'is_active' => true,
            'is_default' => true,
        ]);

        $response = postJson("/api/translator/languages/{$language->id}/deactivate");

        $response->assertStatus(422);
        expect($language->fresh()->is_active)->toBeTrue();
    })->group('api', 'languages');
});
