<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Facades\DB;

describe('Eager Loading Optimization - Translation Controller', function () {
    beforeEach(function () {
        $this->language = Language::factory()->create(['code' => 'en']);
        $this->otherLanguage = Language::factory()->create(['code' => 'es']);

        // Create translations with relationships
        Translation::factory()->count(5)->create([
            'language_id' => $this->language->id,
            'group' => 'general',
        ]);

        Translation::factory()->count(3)->create([
            'language_id' => $this->otherLanguage->id,
            'group' => 'auth',
        ]);
    });

    test('index endpoint eager loads language relationship', function () {
        // Enable query logging
        DB::enableQueryLog();

        $response = $this->getJson('/api/translator/translations');

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should have:
        // 1. Main query for translations
        // 2. Eager load query for languages
        // 3. Eager load query for translatedBy users (if any)
        // Should NOT have N+1 queries (one query per translation)

        // With proper eager loading, we should have <= 3 queries
        // (1 for translations, 1 for languages, 1 for users)
        expect(count($queries))->toBeLessThanOrEqual(5);
    })->group('eager-loading', 'translations');

    test('show endpoint eager loads relationships', function () {
        $translation = Translation::first();

        DB::enableQueryLog();

        $response = $this->getJson("/api/translator/translations/{$translation->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'key', 'value'],
            ]);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should have 1 query with eager loading
        expect(count($queries))->toBeLessThanOrEqual(2);
    })->group('eager-loading', 'translations');

    test('search uses eager loading to prevent N+1 queries', function () {
        DB::enableQueryLog();

        $response = $this->getJson('/api/translator/translations?query=test');

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // With eager loading, query count should be minimal
        expect(count($queries))->toBeLessThanOrEqual(5);
    })->group('eager-loading', 'translations', 'search');

    test('translation has translatedBy relationship defined', function () {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
        ]);

        // Should be able to load translatedBy relationship without error
        $translation->load('translatedBy');
        expect($translation->relationLoaded('translatedBy'))->toBeTrue();

        // Should have translatedBy method defined
        expect(method_exists($translation, 'translatedBy'))->toBeTrue();
    })->group('eager-loading', 'relationships');
});

describe('Eager Loading Optimization - Language Controller', function () {
    beforeEach(function () {
        $this->english = Language::factory()->create(['code' => 'en', 'name' => 'English']);
        $this->spanish = Language::factory()->create(['code' => 'es', 'name' => 'Spanish']);

        // Create translations for statistics
        Translation::factory()->count(10)->create([
            'language_id' => $this->english->id,
            'is_active' => true,
            'is_auto_translated' => false,
        ]);

        Translation::factory()->count(5)->create([
            'language_id' => $this->english->id,
            'is_active' => true,
            'is_auto_translated' => true,
        ]);

        Translation::factory()->count(3)->create([
            'language_id' => $this->spanish->id,
            'is_active' => true,
        ]);
    });

    test('index endpoint can load translation counts with with_stats parameter', function () {
        $response = $this->getJson('/api/translator/languages?with_stats=true');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['code', 'name'],
                ],
            ]);

        // Verify no N+1 queries by checking query count
        DB::enableQueryLog();

        $this->getJson('/api/translator/languages?with_stats=true');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should have 1 query with withCount, not N queries
        expect(count($queries))->toBeLessThanOrEqual(2);
    })->group('eager-loading', 'languages', 'stats');

    test('show endpoint loads translation counts', function () {
        DB::enableQueryLog();

        $response = $this->getJson('/api/translator/languages/en');

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should have 1 query with withCount
        expect(count($queries))->toBeLessThanOrEqual(2);
    })->group('eager-loading', 'languages');

    test('language model has translations relationship', function () {
        $language = Language::first();

        // Should be able to load translations relationship without error
        $language->load('translations');
        expect($language->relationLoaded('translations'))->toBeTrue();

        // Should have translations method defined
        expect(method_exists($language, 'translations'))->toBeTrue();
    })->group('eager-loading', 'relationships');

    test('withCount correctly counts translations', function () {
        $language = Language::withCount('translations')
            ->where('code', 'en')
            ->first();

        expect($language->translations_count)->toBe(15); // 10 + 5
    })->group('eager-loading', 'stats');

    test('withCount correctly counts active translations', function () {
        $language = Language::withCount([
            'translations as active_translations_count' => function ($q) {
                $q->where('is_active', true);
            },
        ])->where('code', 'en')->first();

        expect($language->active_translations_count)->toBe(15);
    })->group('eager-loading', 'stats');

    test('withCount correctly counts auto-translated translations', function () {
        $language = Language::withCount([
            'translations as auto_translated_count' => function ($q) {
                $q->where('is_auto_translated', true);
            },
        ])->where('code', 'en')->first();

        expect($language->auto_translated_count)->toBe(5);
    })->group('eager-loading', 'stats');
});

describe('Eager Loading Optimization - Query Count Reduction', function () {
    beforeEach(function () {
        $this->language = Language::factory()->create(['code' => 'en']);

        // Create 20 translations to test pagination
        Translation::factory()->count(20)->create([
            'language_id' => $this->language->id,
        ]);
    });

    test('translation list reduces query count by using eager loading', function () {
        DB::enableQueryLog();

        $response = $this->getJson('/api/translator/translations?per_page=20');

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Without eager loading: 1 (translations) + 20 (languages) = 21 queries
        // With eager loading: 1 (translations) + 1 (languages) + 1 (users) = 3 queries
        // Query count should be dramatically reduced (>80% reduction)

        // 5 is generous - with perfect optimization it should be 2-3
        expect(count($queries))->toBeLessThanOrEqual(5);
    })->group('eager-loading', 'performance');

    test('language list with stats reduces query count', function () {
        // Create multiple languages
        Language::factory()->count(5)->create();

        DB::enableQueryLog();

        $this->getJson('/api/translator/languages?with_stats=true');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Without withCount: 1 (languages) + 5 (count queries per language) = 6+ queries
        // With withCount: 1 query with subqueries
        expect(count($queries))->toBeLessThanOrEqual(2);
    })->group('eager-loading', 'performance');
});

describe('Eager Loading Optimization - Edge Cases', function () {
    test('handles translations without translatedBy user gracefully', function () {
        $language = Language::factory()->create(['code' => 'en']);

        $translation = Translation::factory()->create([
            'language_id' => $language->id,
            'translated_by_user_id' => null, // No user
        ]);

        $response = $this->getJson("/api/translator/translations/{$translation->id}");

        $response->assertStatus(200);
    })->group('eager-loading', 'edge-cases');

    test('handles languages without translations gracefully', function () {
        $language = Language::factory()->create(['code' => 'fr']);

        $response = $this->getJson('/api/translator/languages/fr');

        $response->assertStatus(200);
    })->group('eager-loading', 'edge-cases');

    test('eager loading works with filtered queries', function () {
        $language = Language::factory()->create(['code' => 'en']);

        Translation::factory()->count(5)->create([
            'language_id' => $language->id,
            'is_active' => true,
        ]);

        Translation::factory()->count(3)->create([
            'language_id' => $language->id,
            'is_active' => false,
        ]);

        DB::enableQueryLog();

        $response = $this->getJson('/api/translator/translations?active_only=true');

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should still use eager loading even with filters
        expect(count($queries))->toBeLessThanOrEqual(5);
    })->group('eager-loading', 'filtering');
});
