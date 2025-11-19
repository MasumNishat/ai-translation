# Testing Guide

This guide explains how to write and run tests for the Laravel AI Translator package.

---

## Quick Start

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test suite
composer test:unit
composer test:feature

# Run specific test file
./vendor/bin/pest tests/Unit/Models/LanguageTest.php

# Run tests matching pattern
./vendor/bin/pest --filter=language
```

---

## Test Structure

```
tests/
├── Feature/              # Integration/API tests
│   ├── LanguageApiTest.php
│   ├── TranslationApiTest.php
│   └── ...
├── Unit/                 # Unit tests
│   ├── Models/
│   │   ├── LanguageTest.php
│   │   └── TranslationTest.php
│   ├── Services/
│   │   └── TranslationServiceTest.php
│   └── ...
├── Pest.php             # Pest configuration
└── TestCase.php         # Base test case
```

---

## Writing Tests

### Unit Tests

Test individual methods and classes in isolation.

```php
<?php

use Masum\AiTranslator\Models\Language;

test('can activate language', function () {
    $language = createLanguage(['is_active' => false]);

    $language->activate();

    expect($language->fresh()->is_active)->toBeTrue();
});

test('get country info returns expected structure', function () {
    $language = createLanguage(['code' => 'bn']);

    $info = $language->getCountryInfo();

    expect($info)
        ->toHaveKeys(['language_code', 'country', 'country_code', 'region'])
        ->and($info['language_code'])->toBe('bn');
});
```

### Feature Tests

Test complete workflows and API endpoints.

```php
<?php

test('can create and retrieve translation', function () {
    $language = createLanguage(['code' => 'en']);

    // Create
    $response = $this->postJson('/api/translator/translations', [
        'key' => 'test.key',
        'value' => 'Test Value',
        'language_code' => 'en',
    ]);

    $response->assertStatus(201);

    // Retrieve
    $id = $response->json('data.id');
    $getResponse = $this->getJson("/api/translator/translations/{$id}");

    $getResponse->assertStatus(200)
        ->assertJson([
            'data' => [
                'key' => 'test.key',
                'value' => 'Test Value',
            ],
        ]);
});
```

### Testing with Factories

```php
// Create single instance
$language = createLanguage();

// Create with specific attributes
$language = createLanguage([
    'code' => 'en',
    'name' => 'English',
]);

// Create multiple instances
$languages = Language::factory()->count(3)->create();

// Use factory states
$rtlLanguage = Language::factory()->rtl()->create();
$defaultLanguage = Language::factory()->default()->create();
$inactiveLanguage = Language::factory()->inactive()->create();
```

### Testing Validation

```php
test('validates required fields', function () {
    $response = $this->postJson('/api/translator/languages', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'code',
            'name',
            'native_name',
            'direction',
        ]);
});

test('validates unique language code', function () {
    createLanguage(['code' => 'en']);

    $response = $this->postJson('/api/translator/languages', [
        'code' => 'en', // Duplicate
        'name' => 'English',
        'native_name' => 'English',
        'direction' => 'ltr',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationError('code');
});
```

### Testing Authorization

```php
test('requires authentication when configured', function () {
    config(['ai-translator.security.require_authentication' => true]);

    $response = $this->postJson('/api/translator/languages', [
        'code' => 'en',
        // ...
    ]);

    $response->assertStatus(403);
});

test('allows access with proper permissions', function () {
    $user = User::factory()->create();
    Gate::define('manage-languages', fn() => true);

    $response = $this->actingAs($user)
        ->postJson('/api/translator/languages', [
            'code' => 'en',
            // ...
        ]);

    $response->assertStatus(201);
});
```

### Testing Cache

```php
test('caches translation after retrieval', function () {
    $translation = createTranslation([
        'key' => 'test.key',
        'value' => 'Test Value',
    ]);

    $service = app(TranslationService::class);

    // First call - should cache
    $result = $service->get('test.key', $translation->language->code);

    // Verify cached
    $cacheKey = "ai_translator.{$translation->group}.test.key.{$translation->language->code}";
    $cached = Cache::get($cacheKey);

    expect($cached)->toBe('Test Value');
});

test('clears cache on translation update', function () {
    $translation = createTranslation();
    $cacheKey = "ai_translator.{$translation->group}.{$translation->key}.{$translation->language->code}";

    // Populate cache
    Cache::put($cacheKey, $translation->value, 3600);

    // Update translation
    $translation->update(['value' => 'New Value']);

    // Verify cache cleared
    expect(Cache::has($cacheKey))->toBeFalse();
});
```

### Testing Queue Jobs

```php
use Illuminate\Support\Facades\Queue;

test('dispatches translation job', function () {
    Queue::fake();

    $response = $this->postJson('/api/translator/auto-translate', [
        'key' => 'test.key',
        'value' => 'Test',
        'source_language' => 'en',
        'target_languages' => ['bn'],
    ]);

    Queue::assertPushed(TranslateJob::class);
});

test('job translates correctly', function () {
    $job = new TranslateJob('test.key', 'Hello', 'en', ['bn']);

    $job->handle(app(TranslationService::class));

    $this->assertDatabaseHas('translations', [
        'key' => 'test.key',
    ]);
});
```

### Testing Events

```php
use Illuminate\Support\Facades\Event;

test('fires event on translation created', function () {
    Event::fake();

    $translation = createTranslation();

    Event::assertDispatched(TranslationCreated::class, function ($event) use ($translation) {
        return $event->translation->id === $translation->id;
    });
});
```

---

## Best Practices

### 1. Test One Thing at a Time

```php
// Good
test('can create language', function () {
    $language = createLanguage();
    expect($language)->toBeLanguage();
});

test('validates language code is unique', function () {
    createLanguage(['code' => 'en']);
    // Test validation...
});

// Bad - testing multiple things
test('language creation and validation', function () {
    // Creates language
    // Tests validation
    // Tests updates
    // Too much in one test
});
```

### 2. Use Descriptive Test Names

```php
// Good
test('cannot delete default language', function () { });
test('clears cache when translation is updated', function () { });
test('returns 422 when language code already exists', function () { });

// Bad
test('language test', function () { });
test('it works', function () { });
```

### 3. Arrange-Act-Assert Pattern

```php
test('example', function () {
    // Arrange - Set up test data
    $language = createLanguage(['is_active' => false]);

    // Act - Perform the action
    $language->activate();

    // Assert - Verify the result
    expect($language->fresh()->is_active)->toBeTrue();
});
```

### 4. Clean Up After Tests

```php
// Use RefreshDatabase trait (automatically included in TestCase)
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;
}

// Or manually in specific tests
test('something', function () {
    // Test code...
})->beforeEach(function () {
    // Setup
})->afterEach(function () {
    // Cleanup
});
```

### 5. Mock External Services

```php
use Illuminate\Support\Facades\Http;

test('calls gemini api correctly', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => '{"bn":"হ্যালো"}']]]]
            ]
        ], 200)
    ]);

    $service = app(GeminiTranslationService::class);
    $result = $service->translate('Hello', 'en', ['bn']);

    expect($result)->toHaveKey('bn');
});
```

---

## Continuous Integration

### GitHub Actions

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
          extensions: mbstring, pdo_sqlite
          coverage: xdebug

      - name: Install Dependencies
        run: composer install

      - name: Run Tests
        run: composer test:coverage

      - name: Upload Coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage-clover.xml
```

---

## Troubleshooting

### Tests Running Slow

```bash
# Run tests in parallel
./vendor/bin/pest --parallel

# Run specific test suite
composer test:unit
```

### Database Issues

```bash
# Clear database
php artisan migrate:fresh --database=testing

# Check database connection
php artisan tinker
DB::connection('testing')->getPdo();
```

### Cache Issues

```php
// Clear cache in test
test('something', function () {
    Cache::flush();
    // Test code...
});
```

---

## Coverage Goals

- **Overall:** 80% minimum
- **Models:** 90% minimum
- **Services:** 85% minimum
- **Controllers:** 75% minimum

Check coverage:
```bash
composer test:coverage
open coverage-html/index.html
```
