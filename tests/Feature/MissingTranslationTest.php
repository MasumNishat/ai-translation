<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Services\MissingTranslationService;
use function Pest\Laravel\{getJson, postJson};

describe('Missing Translation Service', function () {
    beforeEach(function () {
        $this->service = app(MissingTranslationService::class);

        $this->defaultLang = Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->targetLang = Language::factory()->create([
            'code' => 'es',
            'name' => 'Spanish',
            'is_default' => false,
            'is_active' => true,
        ]);
    });

    test('finds missing translations for a language', function () {
        Translation::factory()->create([
            'key' => 'home.title',
            'value' => 'Welcome Home',
            'language_id' => $this->defaultLang->id,
            'group' => 'pages',
        ]);

        Translation::factory()->create([
            'key' => 'home.subtitle',
            'value' => 'Your dashboard',
            'language_id' => $this->defaultLang->id,
            'group' => 'pages',
        ]);

        Translation::factory()->create([
            'key' => 'home.title',
            'value' => 'Bienvenido',
            'language_id' => $this->targetLang->id,
            'group' => 'pages',
        ]);

        $missing = $this->service->findMissing('es');

        expect($missing)->toHaveCount(1)
            ->and($missing->first()['key'])->toBe('home.subtitle')
            ->and($missing->first()['source_value'])->toBe('Your dashboard')
            ->and($missing->first()['target_language'])->toBe('es');
    })->group('missing-translations', 'service');

    test('finds missing translations for specific group', function () {
        Translation::factory()->create([
            'key' => 'home.title',
            'language_id' => $this->defaultLang->id,
            'group' => 'pages',
        ]);

        Translation::factory()->create([
            'key' => 'auth.login',
            'language_id' => $this->defaultLang->id,
            'group' => 'auth',
        ]);

        $missing = $this->service->findMissing('es', 'pages');

        expect($missing)->toHaveCount(1)
            ->and($missing->first()['group'])->toBe('pages')
            ->and($missing->first()['key'])->toBe('home.title');
    })->group('missing-translations', 'service');

    test('returns empty collection when no translations missing', function () {
        Translation::factory()->create([
            'key' => 'test.key',
            'language_id' => $this->defaultLang->id,
        ]);

        Translation::factory()->create([
            'key' => 'test.key',
            'language_id' => $this->targetLang->id,
        ]);

        $missing = $this->service->findMissing('es');

        expect($missing)->toBeEmpty();
    })->group('missing-translations', 'service');

    test('generates comprehensive report for all languages', function () {
        Translation::factory()->count(5)->create([
            'language_id' => $this->defaultLang->id,
        ]);

        Translation::factory()->count(3)->create([
            'language_id' => $this->targetLang->id,
        ]);

        $report = $this->service->generateReport();

        expect($report)->toHaveKeys(['generated_at', 'default_language', 'languages', 'summary'])
            ->and($report['default_language'])->toBe('en')
            ->and($report['languages'])->toHaveKey('es')
            ->and($report['summary'])->not->toBeEmpty();
    })->group('missing-translations', 'service');

    test('calculates completion percentage correctly', function () {
        Translation::factory()->count(10)->create([
            'language_id' => $this->defaultLang->id,
        ]);

        Translation::factory()->count(8)->create([
            'language_id' => $this->targetLang->id,
        ]);

        $stats = $this->service->getCompletionStats('es');

        expect($stats['total_translations'])->toBe(8)
            ->and($stats['expected_translations'])->toBe(10)
            ->and($stats['missing_count'])->toBe(2)
            ->and($stats['completion_percentage'])->toBe(80.0);
    })->group('missing-translations', 'service');

    test('returns correct status based on completion percentage', function () {
        Translation::factory()->count(10)->create([
            'language_id' => $this->defaultLang->id,
        ]);

        // 100% completion
        Translation::factory()->count(10)->create([
            'language_id' => $this->targetLang->id,
        ]);

        $stats = $this->service->getCompletionStats('es');
        expect($stats['status'])->toBe('complete');
    })->group('missing-translations', 'service');

    test('groups missing translations by group', function () {
        Translation::factory()->count(3)->create([
            'language_id' => $this->defaultLang->id,
            'group' => 'home',
        ]);

        Translation::factory()->count(2)->create([
            'language_id' => $this->defaultLang->id,
            'group' => 'auth',
        ]);

        Translation::factory()->create([
            'language_id' => $this->targetLang->id,
            'group' => 'home',
        ]);

        $missingByGroup = $this->service->getMissingByGroup('es');

        expect($missingByGroup)->toHaveKeys(['home', 'auth'])
            ->and($missingByGroup['home']['missing_count'])->toBe(2)
            ->and($missingByGroup['auth']['missing_count'])->toBe(2)
            ->and($missingByGroup['home']['total_expected'])->toBe(3)
            ->and($missingByGroup['auth']['total_expected'])->toBe(2);
    })->group('missing-translations', 'service');

    test('identifies languages needing most attention', function () {
        $lang1 = Language::factory()->create(['code' => 'fr', 'is_active' => true]);
        $lang2 = Language::factory()->create(['code' => 'de', 'is_active' => true]);

        Translation::factory()->count(10)->create([
            'language_id' => $this->defaultLang->id,
        ]);

        // French: 3/10 (30%)
        Translation::factory()->count(3)->create([
            'language_id' => $lang1->id,
        ]);

        // German: 7/10 (70%)
        Translation::factory()->count(7)->create([
            'language_id' => $lang2->id,
        ]);

        // Spanish: 5/10 (50%)
        Translation::factory()->count(5)->create([
            'language_id' => $this->targetLang->id,
        ]);

        $languages = $this->service->getLanguagesNeedingAttention(3);

        expect($languages)->toHaveCount(3)
            ->and($languages->first()['code'])->toBe('fr') // Most missing
            ->and($languages->first()['missing_count'])->toBe(7);
    })->group('missing-translations', 'service');

    test('checks if specific key is missing', function () {
        Translation::factory()->create([
            'key' => 'existing.key',
            'language_id' => $this->targetLang->id,
        ]);

        $isMissing = $this->service->isKeyMissing('existing.key', 'es');
        expect($isMissing)->toBeFalse();

        $isMissing = $this->service->isKeyMissing('missing.key', 'es');
        expect($isMissing)->toBeTrue();
    })->group('missing-translations', 'service');

    test('gets array of missing keys', function () {
        Translation::factory()->create([
            'key' => 'home.title',
            'language_id' => $this->defaultLang->id,
        ]);

        Translation::factory()->create([
            'key' => 'home.subtitle',
            'language_id' => $this->defaultLang->id,
        ]);

        $missingKeys = $this->service->getMissingKeys('es');

        expect($missingKeys)->toBeArray()
            ->and($missingKeys)->toContain('home.title')
            ->and($missingKeys)->toContain('home.subtitle');
    })->group('missing-translations', 'service');

    test('handles language without default language', function () {
        Language::query()->update(['is_default' => false]);

        $report = $this->service->generateReport();

        expect($report)->toHaveKey('error')
            ->and($report['error'])->toBe('No default language set');
    })->group('missing-translations', 'service');
});

describe('Missing Translation API', function () {
    beforeEach(function () {
        $this->defaultLang = Language::factory()->create([
            'code' => 'en',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->targetLang = Language::factory()->create([
            'code' => 'es',
            'is_active' => true,
        ]);
    });

    test('can get missing translations for a language via API', function () {
        Translation::factory()->count(3)->create([
            'language_id' => $this->defaultLang->id,
        ]);

        Translation::factory()->create([
            'language_id' => $this->targetLang->id,
        ]);

        $response = getJson('/api/translator/missing-translations/es');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'stats' => [
                        'language',
                        'total_translations',
                        'expected_translations',
                        'missing_count',
                        'completion_percentage',
                        'status',
                    ],
                    'missing_translations',
                ],
            ]);

        expect($response->json('data.stats.missing_count'))->toBe(2);
    })->group('missing-translations', 'api');

    test('can filter missing translations by group', function () {
        Translation::factory()->count(2)->create([
            'language_id' => $this->defaultLang->id,
            'group' => 'home',
        ]);

        Translation::factory()->create([
            'language_id' => $this->defaultLang->id,
            'group' => 'auth',
        ]);

        $response = getJson('/api/translator/missing-translations/es?group=home');

        $response->assertOk();
        $data = $response->json('data');

        expect($data['stats']['expected_translations'])->toBe(2);
    })->group('missing-translations', 'api');

    test('returns 404 for non-existent language', function () {
        $response = getJson('/api/translator/missing-translations/nonexistent');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => "Language 'nonexistent' not found.",
            ]);
    })->group('missing-translations', 'api');

    test('can get comprehensive report via API', function () {
        Translation::factory()->count(5)->create([
            'language_id' => $this->defaultLang->id,
        ]);

        $response = getJson('/api/translator/missing-translations/report');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'generated_at',
                    'default_language',
                    'languages',
                    'summary',
                ],
            ]);
    })->group('missing-translations', 'api');

    test('can get completion stats for a language', function () {
        Translation::factory()->count(10)->create([
            'language_id' => $this->defaultLang->id,
        ]);

        Translation::factory()->count(7)->create([
            'language_id' => $this->targetLang->id,
        ]);

        $response = getJson('/api/translator/missing-translations/es/stats');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'data' => [
                    'language',
                    'total_translations',
                    'expected_translations',
                    'missing_count',
                    'completion_percentage',
                    'status',
                ],
            ]);

        expect($response->json('data.completion_percentage'))->toBe(70.0);
    })->group('missing-translations', 'api');

    test('can get missing translations grouped by group', function () {
        Translation::factory()->count(2)->create([
            'language_id' => $this->defaultLang->id,
            'group' => 'home',
        ]);

        Translation::factory()->count(3)->create([
            'language_id' => $this->defaultLang->id,
            'group' => 'auth',
        ]);

        Translation::factory()->create([
            'language_id' => $this->targetLang->id,
            'group' => 'home',
        ]);

        $response = getJson('/api/translator/missing-translations/es/by-group');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'home' => [
                        'group',
                        'total_expected',
                        'total_existing',
                        'missing_count',
                        'completion_percentage',
                        'status',
                    ],
                    'auth',
                ],
            ]);

        $data = $response->json('data');
        expect($data['home']['missing_count'])->toBe(1)
            ->and($data['auth']['missing_count'])->toBe(3);
    })->group('missing-translations', 'api');

    test('can get languages needing attention', function () {
        Translation::factory()->count(10)->create([
            'language_id' => $this->defaultLang->id,
        ]);

        Translation::factory()->count(2)->create([
            'language_id' => $this->targetLang->id,
        ]);

        $response = getJson('/api/translator/missing-translations/attention');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'code',
                        'name',
                        'missing_count',
                        'completion_percentage',
                        'status',
                    ],
                ],
            ]);

        expect($response->json('data'))->not->toBeEmpty();
    })->group('missing-translations', 'api');

    test('can limit languages needing attention', function () {
        Language::factory()->count(5)->create(['is_active' => true]);

        $response = getJson('/api/translator/missing-translations/attention?limit=3');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(3);
    })->group('missing-translations', 'api');

    test('can check if specific key is missing', function () {
        Translation::factory()->create([
            'key' => 'test.key',
            'language_id' => $this->targetLang->id,
        ]);

        $response = postJson('/api/translator/missing-translations/check-key', [
            'key' => 'test.key',
            'language' => 'es',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'key' => 'test.key',
                    'language' => 'es',
                    'is_missing' => false,
                ],
            ]);
    })->group('missing-translations', 'api');

    test('validates check key request', function () {
        $response = postJson('/api/translator/missing-translations/check-key', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['key', 'language']);
    })->group('missing-translations', 'api');

    test('validates language exists when checking key', function () {
        $response = postJson('/api/translator/missing-translations/check-key', [
            'key' => 'test.key',
            'language' => 'nonexistent',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['language']);
    })->group('missing-translations', 'api');
});
