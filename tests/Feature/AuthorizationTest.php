<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Tests\Models\User;

describe('Authorization - Public API Mode', function () {
    test('guests can create languages when public API is enabled', function () {
        config(['ai-translator.security.public_api' => true]);

        $response = $this->postJson('/api/translator/languages', [
            'code' => 'de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
        ]);

        $response->assertStatus(201);
    })->group('authorization', 'public-api');

    test('guests can auto-translate when public API is enabled', function () {
        config(['ai-translator.security.public_api' => true]);
        Language::factory()->create(['code' => 'en']);
        Language::factory()->create(['code' => 'es']);

        // Note: This will fail without Gemini API key, but we're testing authorization not the actual translation
        $response = $this->postJson('/api/translator/auto-translate', [
            'key' => 'test.message',
            'value' => 'Hello',
            'source_language' => 'en',
            'target_languages' => ['es'],
        ]);

        // Should not be authorization error (403)
        expect($response->status())->not->toBe(403);
    })->group('authorization', 'public-api');
});

describe('Authorization - Authentication Required Mode', function () {
    test('guests cannot create languages when auth is required', function () {
        config([
            'ai-translator.security.public_api' => false,
            'ai-translator.security.require_authentication' => true,
        ]);

        $response = $this->postJson('/api/translator/languages', [
            'code' => 'de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
        ]);

        $response->assertStatus(403);
    })->group('authorization', 'auth-required');

    test('guests cannot auto-translate when auth is required', function () {
        config([
            'ai-translator.security.public_api' => false,
            'ai-translator.security.require_authentication' => true,
        ]);

        Language::factory()->create(['code' => 'en']);
        Language::factory()->create(['code' => 'es']);

        $response = $this->postJson('/api/translator/auto-translate', [
            'key' => 'test.message',
            'value' => 'Hello',
            'source_language' => 'en',
            'target_languages' => ['es'],
        ]);

        $response->assertStatus(403);
    })->group('authorization', 'auth-required');

    test('guests cannot update translations when auth is required', function () {
        config([
            'ai-translator.security.public_api' => false,
            'ai-translator.security.require_authentication' => true,
        ]);

        $language = Language::factory()->create();
        $translation = \Masum\AiTranslator\Models\Translation::factory()->create([
            'language_id' => $language->id,
        ]);

        $response = $this->putJson("/api/translator/translations/{$translation->id}", [
            'value' => 'Updated value',
        ]);

        $response->assertStatus(403);
    })->group('authorization', 'auth-required');
});

describe('Authorization - Permissive Mode (Default)', function () {
    beforeEach(function () {
        config([
            'ai-translator.security.public_api' => false,
            'ai-translator.security.require_authentication' => false,
            'ai-translator.security.authorization_mode' => 'permissive',
        ]);
    });

    test('guests can create languages in permissive mode', function () {
        $response = $this->postJson('/api/translator/languages', [
            'code' => 'de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
        ]);

        $response->assertStatus(201);
    })->group('authorization', 'permissive');

    test('guests can create translations in permissive mode', function () {
        $language = Language::factory()->create(['code' => 'en']);

        $response = $this->postJson('/api/translator/translations', [
            'key' => 'test.key',
            'value' => 'Test Value',
            'language_code' => 'en',
        ]);

        $response->assertStatus(201);
    })->group('authorization', 'permissive');
});

describe('Authorization - Strict Mode', function () {
    beforeEach(function () {
        config([
            'ai-translator.security.public_api' => false,
            'ai-translator.security.require_authentication' => false,
            'ai-translator.security.authorization_mode' => 'strict',
        ]);
    });

    test('guests cannot create languages in strict mode', function () {
        $response = $this->postJson('/api/translator/languages', [
            'code' => 'de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
        ]);

        $response->assertStatus(403);
    })->group('authorization', 'strict');

    test('guests cannot create translations in strict mode', function () {
        Language::factory()->create(['code' => 'en']);

        $response = $this->postJson('/api/translator/translations', [
            'key' => 'test.key',
            'value' => 'Test Value',
            'language_code' => 'en',
        ]);

        $response->assertStatus(403);
    })->group('authorization', 'strict');
});

describe('Authorization - Authenticated Users with Permissions', function () {
    beforeEach(function () {
        config([
            'ai-translator.security.public_api' => false,
            'ai-translator.security.require_authentication' => true,
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    });

    test('authenticated user with permission can create languages', function () {
        // In test environment, gates return true by default
        $response = $this->actingAs($this->user)
            ->postJson('/api/translator/languages', [
                'code' => 'de',
                'name' => 'German',
                'native_name' => 'Deutsch',
                'direction' => 'ltr',
            ]);

        $response->assertStatus(201);
    })->group('authorization', 'authenticated');

    test('authenticated user can create translations', function () {
        Language::factory()->create(['code' => 'en']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/translator/translations', [
                'key' => 'test.key',
                'value' => 'Test Value',
                'language_code' => 'en',
            ]);

        $response->assertStatus(201);
    })->group('authorization', 'authenticated');

    test('authenticated user can update translations', function () {
        $language = Language::factory()->create();
        $translation = \Masum\AiTranslator\Models\Translation::factory()->create([
            'language_id' => $language->id,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/translator/translations/{$translation->id}", [
                'value' => 'Updated value',
            ]);

        $response->assertStatus(200);
    })->group('authorization', 'authenticated');
});

describe('Authorization - Superadmin Bypass', function () {
    beforeEach(function () {
        config([
            'ai-translator.security.public_api' => false,
            'ai-translator.security.require_authentication' => true,
            'ai-translator.security.superadmin_permission' => 'translator-superadmin',
        ]);

        $this->superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        // Define superadmin gate for testing
        \Illuminate\Support\Facades\Gate::define('translator-superadmin', function ($user = null) use (&$isSuperadmin) {
            return $isSuperadmin ?? false;
        });

        $this->isSuperadmin = false;
    });

    test('superadmin can bypass all permission checks', function () use (&$isSuperadmin) {
        $isSuperadmin = true;

        // Redefine gate to return true for superadmin
        \Illuminate\Support\Facades\Gate::define('translator-superadmin', fn() => true);

        $response = $this->actingAs($this->superadmin)
            ->postJson('/api/translator/languages', [
                'code' => 'de',
                'name' => 'German',
                'native_name' => 'Deutsch',
                'direction' => 'ltr',
            ]);

        $response->assertStatus(201);
    })->group('authorization', 'superadmin');
});

describe('Authorization - Edge Cases', function () {
    test('authorization respects configuration priority correctly', function () {
        // Public API takes precedence over everything
        config([
            'ai-translator.security.public_api' => true,
            'ai-translator.security.require_authentication' => true,
            'ai-translator.security.authorization_mode' => 'strict',
        ]);

        $response = $this->postJson('/api/translator/languages', [
            'code' => 'de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
        ]);

        // Should succeed because public_api is enabled
        $response->assertStatus(201);
    })->group('authorization', 'edge-cases');

    test('handles missing user gracefully in permissive mode', function () {
        config([
            'ai-translator.security.public_api' => false,
            'ai-translator.security.require_authentication' => false,
            'ai-translator.security.authorization_mode' => 'permissive',
        ]);

        // No actingAs() call - truly no user
        $response = $this->postJson('/api/translator/languages', [
            'code' => 'de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'direction' => 'ltr',
        ]);

        $response->assertStatus(201);
    })->group('authorization', 'edge-cases');
});
