# TASK 03: Testing Infrastructure

**Phase:** 1 - Foundation & Security
**Priority:** P1 - Critical
**Estimated Time:** 40-50 hours
**Dependencies:** None (can run parallel with other tasks)
**Complexity:** Medium

---

## Overview

Establish comprehensive testing infrastructure with unit tests, feature tests, and integration tests to achieve 80%+ code coverage.

---

## Tasks

### P1-T03-S01: Set Up Testing Environment ⭐⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 6-8 hours
**Assigned To:** -

#### Context

Configure Pest PHP, set up test database, and create test helpers.

#### Implementation

**Install Dependencies:**

```bash
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-laravel --dev
composer require mockery/mockery --dev
```

**Configure Pest:**

```php
// tests/Pest.php
<?php

uses(Tests\TestCase::class)->in('Feature');
uses(Tests\TestCase::class)->in('Unit');

// Custom expectations
expect()->extend('toBeTranslation', function () {
    return $this->toBeInstanceOf(\Masum\AiTranslator\Models\Translation::class);
});

expect()->extend('toBeLanguage', function () {
    return $this->toBeInstanceOf(\Masum\AiTranslator\Models\Language::class);
});

// Helper functions
function createLanguage(array $attributes = []): \Masum\AiTranslator\Models\Language
{
    return \Masum\AiTranslator\Models\Language::factory()->create($attributes);
}

function createTranslation(array $attributes = []): \Masum\AiTranslator\Models\Translation
{
    return \Masum\AiTranslator\Models\Translation::factory()->create($attributes);
}
```

**Create Base Test Case:**

```php
// tests/TestCase.php
<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Masum\AiTranslator\AiTranslatorServiceProvider;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            AiTranslatorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('ai-translator.gemini.api_key', 'test-key');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--database' => 'testing']);
    }
}
```

**Acceptance Criteria:**
- [ ] Pest PHP installed and configured
- [ ] Test database configured (SQLite in memory)
- [ ] Base test case with helpers
- [ ] Custom expectations defined
- [ ] Factories created for models

---

### P1-T03-S02: Create Model Factories ⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 4-5 hours
**Assigned To:** -

#### Implementation

```php
// database/factories/LanguageFactory.php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masum\AiTranslator\Models\Language;

class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->languageCode,
            'name' => $this->faker->country,
            'native_name' => $this->faker->country,
            'direction' => $this->faker->randomElement(['ltr', 'rtl']),
            'is_active' => true,
            'is_default' => false,
            'country_code' => $this->faker->countryCode,
            'region' => $this->faker->randomElement(['Asia', 'Europe', 'Africa', 'North America']),
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function rtl(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'rtl',
            'code' => 'ar',
            'name' => 'Arabic',
        ]);
    }
}
```

```php
// database/factories/TranslationFactory.php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Models\Language;

class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    public function definition(): array
    {
        return [
            'language_id' => Language::factory(),
            'group' => $this->faker->randomElement(['common', 'home', 'auth', null]),
            'key' => $this->faker->words(3, true),
            'value' => $this->faker->sentence,
            'is_active' => true,
            'is_auto_translated' => false,
        ];
    }

    public function autoTranslated(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_auto_translated' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forLanguage(string $code): static
    {
        return $this->state(function (array $attributes) use ($code) {
            return [
                'language_id' => Language::where('code', $code)->first()->id
                    ?? Language::factory()->create(['code' => $code])->id,
            ];
        });
    }
}
```

**Acceptance Criteria:**
- [ ] Factory for Language model
- [ ] Factory for Translation model
- [ ] Factory for PackageSetting model
- [ ] Factory for TranslationHistory model
- [ ] State methods for common scenarios
- [ ] Relationships handled properly

---

### P1-T03-S03: Write Unit Tests ⭐⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 16-20 hours
**Assigned To:** -

#### Test Files to Create

**Model Tests:**

```php
// tests/Unit/Models/LanguageTest.php
<?php

use Masum\AiTranslator\Models\Language;

test('can create language', function () {
    $language = createLanguage([
        'code' => 'en',
        'name' => 'English',
    ]);

    expect($language)->toBeLanguage()
        ->and($language->code)->toBe('en')
        ->and($language->name)->toBe('English');
});

test('can set as default language', function () {
    $en = createLanguage(['code' => 'en', 'is_default' => true]);
    $bn = createLanguage(['code' => 'bn']);

    $bn->setAsDefault();

    expect($bn->fresh()->is_default)->toBeTrue()
        ->and($en->fresh()->is_default)->toBeFalse();
});

test('cannot deactivate default language', function () {
    $language = createLanguage(['is_default' => true]);

    $result = $language->deactivate();

    expect($result)->toBeFalse()
        ->and($language->fresh()->is_active)->toBeTrue();
});

test('is rtl when direction is rtl', function () {
    $language = createLanguage(['direction' => 'rtl']);

    expect($language->is_rtl)->toBeTrue();
});

test('get country info returns correct data', function () {
    $language = createLanguage([
        'code' => 'bn',
        'name' => 'Bengali',
        'country_code' => 'BD',
        'region' => 'Asia',
    ]);

    $info = $language->getCountryInfo();

    expect($info)->toHaveKeys(['language_code', 'language_name', 'country', 'country_code', 'region'])
        ->and($info['language_code'])->toBe('bn')
        ->and($info['country'])->toBe('Bangladesh');
});
```

```php
// tests/Unit/Models/TranslationTest.php
<?php

use Masum\AiTranslator\Models\Translation;

test('can create translation', function () {
    $translation = createTranslation([
        'key' => 'welcome.message',
        'value' => 'Welcome',
    ]);

    expect($translation)->toBeTranslation()
        ->and($translation->key)->toBe('welcome.message');
});

test('caches translation on creation', function () {
    $translation = createTranslation([
        'key' => 'test.key',
        'value' => 'Test Value',
    ]);

    $cached = Cache::get("ai_translator.{$translation->group}.{$translation->key}.{$translation->language->code}");

    expect($cached)->toBe('Test Value');
});

test('clears cache on update', function () {
    $translation = createTranslation();
    $cacheKey = "ai_translator.{$translation->group}.{$translation->key}.{$translation->language->code}";

    // Ensure cached
    Cache::get($cacheKey);

    $translation->update(['value' => 'New Value']);

    $cached = Cache::get($cacheKey);

    expect($cached)->toBeNull();
});
```

**Service Tests:**

```php
// tests/Unit/Services/TranslationServiceTest.php
<?php

use Masum\AiTranslator\Services\TranslationService;

test('retrieves translation from cache first', function () {
    $service = app(TranslationService::class);
    $language = createLanguage(['code' => 'en']);

    Cache::shouldReceive('get')
        ->once()
        ->andReturn('Cached Value');

    $result = $service->get('test.key', 'en', 'home', 'Default');

    expect($result)->toBe('Cached Value');
});

test('retrieves from database if not cached', function () {
    $service = app(TranslationService::class);
    $language = createLanguage(['code' => 'en']);
    $translation = createTranslation([
        'key' => 'test.key',
        'value' => 'DB Value',
        'language_id' => $language->id,
    ]);

    Cache::flush();

    $result = $service->get('test.key', 'en', null, 'Default');

    expect($result)->toBe('DB Value');
});

test('returns default if translation not found', function () {
    $service = app(TranslationService::class);
    createLanguage(['code' => 'en']);

    $result = $service->get('missing.key', 'en', null, 'Default Value');

    expect($result)->toBe('Default Value');
});
```

**Acceptance Criteria:**
- [ ] 80%+ code coverage on models
- [ ] All model methods tested
- [ ] All service methods tested
- [ ] Edge cases covered
- [ ] Tests run fast (< 10s total)

---

### P1-T03-S04: Write Feature Tests ⭐⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 14-17 hours
**Assigned To:** -

#### Test Files to Create

```php
// tests/Feature/LanguageApiTest.php
<?php

use Masum\AiTranslator\Models\Language;

test('can list all languages', function () {
    createLanguage(['code' => 'en']);
    createLanguage(['code' => 'bn']);

    $response = $this->getJson('/api/translator/languages');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'code', 'name', 'native_name'],
            ],
        ])
        ->assertJsonCount(2, 'data');
});

test('can create language', function () {
    $response = $this->postJson('/api/translator/languages', [
        'code' => 'es',
        'name' => 'Spanish',
        'native_name' => 'Español',
        'direction' => 'ltr',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'success' => true,
            'data' => [
                'code' => 'es',
                'name' => 'Spanish',
            ],
        ]);

    $this->assertDatabaseHas('languages', [
        'code' => 'es',
        'name' => 'Spanish',
    ]);
});

test('validates language creation', function () {
    $response = $this->postJson('/api/translator/languages', [
        'code' => '', // Invalid
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code', 'name', 'native_name', 'direction']);
});

test('can update language', function () {
    $language = createLanguage(['code' => 'en']);

    $response = $this->putJson("/api/translator/languages/{$language->code}", [
        'name' => 'Updated English',
    ]);

    $response->assertStatus(200);

    expect($language->fresh()->name)->toBe('Updated English');
});

test('can delete language', function () {
    $language = createLanguage();

    $response = $this->deleteJson("/api/translator/languages/{$language->code}");

    $response->assertStatus(200);

    $this->assertDatabaseMissing('languages', [
        'id' => $language->id,
    ]);
});

test('cannot delete default language', function () {
    $language = createLanguage(['is_default' => true]);

    $response = $this->deleteJson("/api/translator/languages/{$language->code}");

    $response->assertStatus(422);

    $this->assertDatabaseHas('languages', [
        'id' => $language->id,
    ]);
});
```

**Translation API Tests:**

```php
// tests/Feature/TranslationApiTest.php
<?php

test('can create translation', function () {
    $language = createLanguage(['code' => 'en']);

    $response = $this->postJson('/api/translator/translations', [
        'key' => 'welcome.title',
        'value' => 'Welcome',
        'language_code' => 'en',
        'group' => 'home',
    ]);

    $response->assertStatus(201);

    $this->assertDatabaseHas('translations', [
        'key' => 'welcome.title',
        'value' => 'Welcome',
    ]);
});

test('can filter translations by language', function () {
    $en = createLanguage(['code' => 'en']);
    $bn = createLanguage(['code' => 'bn']);

    createTranslation(['language_id' => $en->id]);
    createTranslation(['language_id' => $en->id]);
    createTranslation(['language_id' => $bn->id]);

    $response = $this->getJson('/api/translator/translations?language=en');

    $response->assertStatus(200)
        ->assertJsonCount(2, 'data');
});
```

**Acceptance Criteria:**
- [ ] All API endpoints tested
- [ ] Success and failure cases covered
- [ ] Validation tested
- [ ] Authorization tested
- [ ] Response structure validated

---

### P1-T03-S05: Add Code Coverage Reporting ⭐

**Status:** 🔴 Not Started
**Time Estimate:** 2-3 hours
**Assigned To:** -

#### Implementation

**Configure PHPUnit:**

```xml
<!-- phpunit.xml -->
<coverage processUncoveredFiles="true">
    <include>
        <directory suffix=".php">./src</directory>
    </include>
    <exclude>
        <directory>./src/Console/Commands/stubs</directory>
        <file>./src/AiTranslatorServiceProvider.php</file>
    </exclude>
    <report>
        <html outputDirectory="coverage-html"/>
        <clover outputFile="coverage-clover.xml"/>
        <text outputFile="php://stdout" showUncoveredFiles="true"/>
    </report>
</coverage>
```

**Add Scripts:**

```json
// composer.json
{
    "scripts": {
        "test": "pest",
        "test:coverage": "pest --coverage --min=80",
        "test:coverage-html": "pest --coverage-html=coverage-html",
        "test:unit": "pest --testsuite=Unit",
        "test:feature": "pest --testsuite=Feature"
    }
}
```

**Acceptance Criteria:**
- [ ] Coverage reports generated
- [ ] Minimum 80% coverage enforced
- [ ] HTML coverage report available
- [ ] CI integration ready

---

## Summary

**Total Subtasks:** 5
**Estimated Time:** 40-50 hours
**Priority:** P1 - Critical

**Completion Checklist:**
- [ ] Testing environment set up
- [ ] All factories created
- [ ] Unit tests written (80%+ coverage)
- [ ] Feature tests written
- [ ] Code coverage reporting configured
- [ ] All tests passing
- [ ] CI/CD integration ready
