<?php

use Masum\AiTranslator\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

expect()->extend('toBeLanguage', function () {
    return $this->toBeInstanceOf(\Masum\AiTranslator\Models\Language::class);
});

expect()->extend('toBeTranslation', function () {
    return $this->toBeInstanceOf(\Masum\AiTranslator\Models\Translation::class);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a Language instance for testing
 */
function createLanguage(array $attributes = []): \Masum\AiTranslator\Models\Language
{
    return \Masum\AiTranslator\Models\Language::factory()->create($attributes);
}

/**
 * Create a Translation instance for testing
 */
function createTranslation(array $attributes = []): \Masum\AiTranslator\Models\Translation
{
    // Ensure language exists
    if (!isset($attributes['language_id'])) {
        $language = createLanguage();
        $attributes['language_id'] = $language->id;
    }

    return \Masum\AiTranslator\Models\Translation::factory()->create($attributes);
}

/**
 * Create multiple languages for testing
 */
function createLanguages(int $count = 3): \Illuminate\Support\Collection
{
    return \Masum\AiTranslator\Models\Language::factory()->count($count)->create();
}

/**
 * Create multiple translations for testing
 */
function createTranslations(int $count = 3, array $attributes = []): \Illuminate\Support\Collection
{
    if (!isset($attributes['language_id'])) {
        $language = createLanguage();
        $attributes['language_id'] = $language->id;
    }

    return \Masum\AiTranslator\Models\Translation::factory()->count($count)->create($attributes);
}
