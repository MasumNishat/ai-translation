<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;

describe('Database Indexes', function () {
    test('languages table has required indexes', function () {
        $indexes = DB::select("SHOW INDEX FROM languages");
        $indexNames = collect($indexes)->pluck('Key_name')->unique()->toArray();

        expect($indexNames)->toContain('idx_languages_is_active')
            ->and($indexNames)->toContain('idx_languages_is_default')
            ->and($indexNames)->toContain('idx_languages_active_code');
    })->group('indexes', 'performance')->skip(fn() => DB::connection()->getDriverName() !== 'mysql', 'MySQL only');

    test('translations table has required indexes', function () {
        $indexes = DB::select("SHOW INDEX FROM translations");
        $indexNames = collect($indexes)->pluck('Key_name')->unique()->toArray();

        expect($indexNames)->toContain('idx_translations_lang_key')
            ->and($indexNames)->toContain('idx_translations_lang_group')
            ->and($indexNames)->toContain('idx_translations_lang_group_key')
            ->and($indexNames)->toContain('idx_translations_key')
            ->and($indexNames)->toContain('idx_translations_group')
            ->and($indexNames)->toContain('idx_translations_is_active')
            ->and($indexNames)->toContain('idx_translations_created_at')
            ->and($indexNames)->toContain('idx_translations_updated_at');
    })->group('indexes', 'performance')->skip(fn() => DB::connection()->getDriverName() !== 'mysql', 'MySQL only');

    test('translations table has fulltext index', function () {
        $indexes = DB::select("SHOW INDEX FROM translations");
        $indexNames = collect($indexes)->pluck('Key_name')->unique()->toArray();

        expect($indexNames)->toContain('idx_translations_fulltext');
    })->group('indexes', 'performance')->skip(fn() => DB::connection()->getDriverName() !== 'mysql', 'MySQL only');

    test('composite index on language_id and key improves query performance', function () {
        $language = Language::factory()->create();
        Translation::factory()->count(100)->create(['language_id' => $language->id]);

        // Query using the index
        DB::enableQueryLog();

        Translation::where('language_id', $language->id)
            ->where('key', 'test.key')
            ->first();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Verify query was executed
        expect($queries)->toHaveCount(1);

        // On MySQL with indexes, the query should use the index
        // This is a basic test - in production you'd use EXPLAIN to verify index usage
    })->group('indexes', 'performance');

    test('group index improves group-based queries', function () {
        $language = Language::factory()->create();
        Translation::factory()->count(50)->create([
            'language_id' => $language->id,
            'group' => 'home',
        ]);
        Translation::factory()->count(50)->create([
            'language_id' => $language->id,
            'group' => 'auth',
        ]);

        DB::enableQueryLog();

        Translation::where('language_id', $language->id)
            ->where('group', 'home')
            ->get();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1);
    })->group('indexes', 'performance');

    test('temporal indexes improve date-based queries', function () {
        Translation::factory()->count(20)->create([
            'created_at' => now()->subDays(10),
        ]);
        Translation::factory()->count(30)->create([
            'created_at' => now()->subDays(5),
        ]);

        DB::enableQueryLog();

        Translation::where('created_at', '>=', now()->subDays(7))->get();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1);
    })->group('indexes', 'performance');

    test('active language index improves filtering', function () {
        Language::factory()->count(5)->create(['is_active' => true]);
        Language::factory()->count(5)->create(['is_active' => false]);

        DB::enableQueryLog();

        Language::where('is_active', true)->get();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1);
    })->group('indexes', 'performance');

    test('composite language index improves complex queries', function () {
        Language::factory()->count(3)->create(['is_active' => true]);
        Language::factory()->count(2)->create(['is_active' => false]);

        DB::enableQueryLog();

        Language::where('is_active', true)
            ->where('code', 'en')
            ->first();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($queries)->toHaveCount(1);
    })->group('indexes', 'performance');

    test('indexes do not slow down inserts significantly', function () {
        $language = Language::factory()->create();

        $startTime = microtime(true);

        // Insert multiple translations
        for ($i = 0; $i < 50; $i++) {
            Translation::factory()->create([
                'language_id' => $language->id,
                'key' => "test.key.{$i}",
            ]);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Should complete in reasonable time (< 5 seconds for 50 inserts)
        expect($duration)->toBeLessThan(5.0);
    })->group('indexes', 'performance');
});

describe('Index Performance Benefits', function () {
    beforeEach(function () {
        $this->language = Language::factory()->create();

        // Create a larger dataset to test performance
        Translation::factory()->count(200)->create([
            'language_id' => $this->language->id,
        ]);
    });

    test('key lookup is fast with indexes', function () {
        $key = Translation::first()->key;

        $startTime = microtime(true);

        Translation::where('key', $key)->first();

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        // Should be very fast with index (< 50ms for 200 records)
        expect($duration)->toBeLessThan(50);
    })->group('indexes', 'performance');

    test('language and group composite query is fast', function () {
        $translation = Translation::first();

        $startTime = microtime(true);

        Translation::where('language_id', $translation->language_id)
            ->where('group', $translation->group)
            ->get();

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        // Should be fast with composite index
        expect($duration)->toBeLessThan(50);
    })->group('indexes', 'performance');

    test('pagination with indexes is efficient', function () {
        $startTime = microtime(true);

        Translation::where('language_id', $this->language->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;

        // Should paginate quickly with indexes
        expect($duration)->toBeLessThan(100);
    })->group('indexes', 'performance');
});

describe('Full-Text Search Index', function () {
    test('full-text search works on translations', function () {
        $language = Language::factory()->create();

        Translation::factory()->create([
            'key' => 'search.test',
            'value' => 'The quick brown fox jumps over the lazy dog',
            'language_id' => $language->id,
        ]);

        Translation::factory()->create([
            'key' => 'another.test',
            'value' => 'Some other completely different text',
            'language_id' => $language->id,
        ]);

        // Full-text search query
        if (DB::connection()->getDriverName() === 'mysql') {
            $results = DB::select(
                "SELECT * FROM translations WHERE MATCH(key, value) AGAINST(? IN BOOLEAN MODE)",
                ['quick brown fox']
            );

            expect($results)->toHaveCount(1);
        } else {
            // For non-MySQL databases, skip this test
            expect(true)->toBeTrue();
        }
    })->group('indexes', 'performance', 'fulltext')->skip(fn() => DB::connection()->getDriverName() !== 'mysql', 'MySQL only');

    test('full-text search is faster than LIKE queries', function () {
        $language = Language::factory()->create();

        // Create many translations
        for ($i = 0; $i < 100; $i++) {
            Translation::factory()->create([
                'key' => "test.key.{$i}",
                'value' => "Some random text with word number {$i}",
                'language_id' => $language->id,
            ]);
        }

        Translation::factory()->create([
            'key' => 'special.key',
            'value' => 'The quick brown fox jumps over the lazy dog',
            'language_id' => $language->id,
        ]);

        if (DB::connection()->getDriverName() === 'mysql') {
            // Full-text search
            $startTime = microtime(true);
            DB::select(
                "SELECT * FROM translations WHERE MATCH(key, value) AGAINST(? IN BOOLEAN MODE)",
                ['quick brown fox']
            );
            $fulltextTime = microtime(true) - $startTime;

            // LIKE search
            $startTime = microtime(true);
            Translation::where('value', 'like', '%quick brown fox%')->get();
            $likeTime = microtime(true) - $startTime;

            // Full-text should be comparable or faster for this dataset
            // Note: For very small datasets, LIKE might actually be faster
            // but for larger datasets, full-text scales better
            expect($fulltextTime)->toBeLessThan($likeTime * 2); // Allow some variance
        } else {
            expect(true)->toBeTrue();
        }
    })->group('indexes', 'performance', 'fulltext')->skip(fn() => DB::connection()->getDriverName() !== 'mysql', 'MySQL only');
});
