<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;

describe('Query Scopes - Translation Model', function () {
    beforeEach(function () {
        $this->language = Language::factory()->create(['code' => 'en']);

        // Create various types of translations for testing
        Translation::factory()->count(3)->create([
            'language_id' => $this->language->id,
            'is_active' => true,
            'is_auto_translated' => true,
            'group' => 'home',
        ]);

        Translation::factory()->count(2)->create([
            'language_id' => $this->language->id,
            'is_active' => true,
            'is_auto_translated' => false,
            'group' => 'auth',
        ]);

        Translation::factory()->count(2)->create([
            'language_id' => $this->language->id,
            'is_active' => false,
            'is_auto_translated' => false,
            'group' => 'general',
        ]);
    });

    test('scopeActive filters only active translations', function () {
        $active = Translation::active()->get();

        expect($active)->toHaveCount(5) // 3 + 2
            ->and($active->every(fn ($t) => $t->is_active))->toBeTrue();
    })->group('scopes', 'translation', 'active');

    test('scopeInactive filters only inactive translations', function () {
        $inactive = Translation::inactive()->get();

        expect($inactive)->toHaveCount(2)
            ->and($inactive->every(fn ($t) => !$t->is_active))->toBeTrue();
    })->group('scopes', 'translation', 'inactive');

    test('scopeAutoTranslated filters only auto-translated translations', function () {
        $autoTranslated = Translation::autoTranslated()->get();

        expect($autoTranslated)->toHaveCount(3)
            ->and($autoTranslated->every(fn ($t) => $t->is_auto_translated))->toBeTrue();
    })->group('scopes', 'translation', 'auto-translated');

    test('scopeManuallyTranslated filters only manually translated translations', function () {
        $manual = Translation::manuallyTranslated()->get();

        expect($manual)->toHaveCount(4) // 2 + 2
            ->and($manual->every(fn ($t) => !$t->is_auto_translated))->toBeTrue();
    })->group('scopes', 'translation', 'manually-translated');

    test('scopeByLanguage filters by language code', function () {
        $otherLanguage = Language::factory()->create(['code' => 'es']);
        Translation::factory()->count(2)->create(['language_id' => $otherLanguage->id]);

        $enTranslations = Translation::byLanguage('en')->get();

        expect($enTranslations)->toHaveCount(7); // 3 + 2 + 2
    })->group('scopes', 'translation', 'by-language');

    test('scopeByGroup filters by group', function () {
        $homeTranslations = Translation::byGroup('home')->get();
        $authTranslations = Translation::byGroup('auth')->get();

        expect($homeTranslations)->toHaveCount(3)
            ->and($authTranslations)->toHaveCount(2);
    })->group('scopes', 'translation', 'by-group');

    test('scopeByKey filters by exact key', function () {
        $translation = Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'unique.test.key',
        ]);

        $result = Translation::byKey('unique.test.key')->first();

        expect($result)->not->toBeNull()
            ->and($result->key)->toBe('unique.test.key');
    })->group('scopes', 'translation', 'by-key');

    test('scopeSearch finds translations by key or value', function () {
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'welcome.message',
            'value' => 'Hello World',
        ]);

        Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'goodbye.message',
            'value' => 'See you later',
        ]);

        $searchByKey = Translation::search('welcome')->get();
        $searchByValue = Translation::search('Hello')->get();

        expect($searchByKey)->toHaveCount(1)
            ->and($searchByValue)->toHaveCount(1);
    })->group('scopes', 'translation', 'search');

    test('scopeRecent filters translations created in recent days', function () {
        // Create old translation
        $old = Translation::factory()->create([
            'language_id' => $this->language->id,
            'created_at' => now()->subDays(10),
        ]);

        // Create recent translation
        $recent = Translation::factory()->create([
            'language_id' => $this->language->id,
            'created_at' => now()->subDays(3),
        ]);

        $recentTranslations = Translation::recent(7)->get();

        // Should include the recent one and all from beforeEach (created now)
        expect($recentTranslations->pluck('id'))->not->toContain($old->id)
            ->and($recentTranslations->pluck('id'))->toContain($recent->id);
    })->group('scopes', 'translation', 'recent');

    test('scopeUpdatedAfter filters translations updated after specific date', function () {
        $cutoffDate = now()->subDays(5);

        // Update one translation recently
        $translation = Translation::first();
        $translation->update(['value' => 'Updated value']);

        $updated = Translation::updatedAfter($cutoffDate)->get();

        expect($updated)->not->toBeEmpty();
    })->group('scopes', 'translation', 'updated-after');
});

describe('Query Scopes - Chaining', function () {
    beforeEach(function () {
        $this->english = Language::factory()->create(['code' => 'en']);
        $this->spanish = Language::factory()->create(['code' => 'es']);

        // Create diverse set of translations
        Translation::factory()->count(3)->create([
            'language_id' => $this->english->id,
            'is_active' => true,
            'is_auto_translated' => true,
            'group' => 'home',
        ]);

        Translation::factory()->count(2)->create([
            'language_id' => $this->english->id,
            'is_active' => true,
            'is_auto_translated' => false,
            'group' => 'home',
        ]);

        Translation::factory()->count(2)->create([
            'language_id' => $this->spanish->id,
            'is_active' => true,
            'is_auto_translated' => true,
            'group' => 'auth',
        ]);
    });

    test('scopes can be chained together', function () {
        $result = Translation::active()
            ->byLanguage('en')
            ->byGroup('home')
            ->autoTranslated()
            ->get();

        expect($result)->toHaveCount(3)
            ->and($result->every(fn ($t) => $t->is_active))->toBeTrue()
            ->and($result->every(fn ($t) => $t->is_auto_translated))->toBeTrue()
            ->and($result->every(fn ($t) => $t->group === 'home'))->toBeTrue();
    })->group('scopes', 'chaining');

    test('scopes work with pagination', function () {
        $result = Translation::active()
            ->byLanguage('en')
            ->paginate(3);

        expect($result)->toHaveCount(3)
            ->and($result->total())->toBe(5);
    })->group('scopes', 'chaining', 'pagination');

    test('scopes work with ordering', function () {
        $result = Translation::active()
            ->byLanguage('en')
            ->orderBy('created_at', 'desc')
            ->get();

        expect($result)->toHaveCount(5);
    })->group('scopes', 'chaining', 'ordering');
});

describe('Query Scopes - Language Model', function () {
    beforeEach(function () {
        Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'is_active' => true,
            'is_default' => true,
            'direction' => 'ltr',
            'region' => 'Americas',
        ]);

        Language::factory()->create([
            'code' => 'ar',
            'name' => 'Arabic',
            'is_active' => true,
            'is_default' => false,
            'direction' => 'rtl',
            'region' => 'Middle East',
        ]);

        Language::factory()->create([
            'code' => 'fr',
            'name' => 'French',
            'is_active' => false,
            'is_default' => false,
            'direction' => 'ltr',
            'region' => 'Europe',
        ]);
    });

    test('scopeActive filters only active languages', function () {
        $active = Language::active()->get();

        expect($active)->toHaveCount(2)
            ->and($active->every(fn ($l) => $l->is_active))->toBeTrue();
    })->group('scopes', 'language', 'active');

    test('scopeInactive filters only inactive languages', function () {
        $inactive = Language::inactive()->get();

        expect($inactive)->toHaveCount(1)
            ->and($inactive->first()->code)->toBe('fr');
    })->group('scopes', 'language', 'inactive');

    test('scopeDefault filters default language', function () {
        $default = Language::default()->first();

        expect($default)->not->toBeNull()
            ->and($default->code)->toBe('en')
            ->and($default->is_default)->toBeTrue();
    })->group('scopes', 'language', 'default');

    test('scopeByDirection filters by text direction', function () {
        $ltr = Language::byDirection('ltr')->get();
        $rtl = Language::byDirection('rtl')->get();

        expect($ltr)->toHaveCount(2)
            ->and($rtl)->toHaveCount(1)
            ->and($rtl->first()->code)->toBe('ar');
    })->group('scopes', 'language', 'direction');

    test('scopeByRegion filters by region', function () {
        $americas = Language::byRegion('Americas')->get();
        $europe = Language::byRegion('Europe')->get();
        $middleEast = Language::byRegion('Middle East')->get();

        expect($americas)->toHaveCount(1)
            ->and($europe)->toHaveCount(1)
            ->and($middleEast)->toHaveCount(1);
    })->group('scopes', 'language', 'region');

    test('language scopes can be chained', function () {
        $result = Language::active()
            ->byDirection('ltr')
            ->get();

        expect($result)->toHaveCount(1)
            ->and($result->first()->code)->toBe('en');
    })->group('scopes', 'language', 'chaining');
});

describe('Query Scopes - Edge Cases', function () {
    test('scopeByGroup handles null group correctly', function () {
        $language = Language::factory()->create(['code' => 'en']);

        Translation::factory()->create([
            'language_id' => $language->id,
            'group' => null,
        ]);

        Translation::factory()->create([
            'language_id' => $language->id,
            'group' => 'home',
        ]);

        $nullGroup = Translation::byGroup(null)->get();

        expect($nullGroup)->toHaveCount(1)
            ->and($nullGroup->first()->group)->toBeNull();
    })->group('scopes', 'edge-cases');

    test('scopeSearch handles special characters', function () {
        $language = Language::factory()->create(['code' => 'en']);

        Translation::factory()->create([
            'language_id' => $language->id,
            'key' => 'test.key',
            'value' => "Value with % wildcard",
        ]);

        // Should handle special SQL characters
        $result = Translation::search('%')->get();

        expect($result)->toHaveCount(1);
    })->group('scopes', 'edge-cases');

    test('scopes return empty collection when no matches', function () {
        $result = Translation::byLanguage('nonexistent')->get();

        expect($result)->toBeEmpty();
    })->group('scopes', 'edge-cases');
});
