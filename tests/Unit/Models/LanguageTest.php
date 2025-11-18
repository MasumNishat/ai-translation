<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Facades\Cache;

test('can create a language', function () {
    $language = createLanguage([
        'code' => 'en',
        'name' => 'English',
        'native_name' => 'English',
        'direction' => 'ltr',
    ]);

    expect($language)->toBeLanguage()
        ->and($language->code)->toBe('en')
        ->and($language->name)->toBe('English')
        ->and($language->native_name)->toBe('English')
        ->and($language->direction)->toBe('ltr')
        ->and($language->is_active)->toBeTrue()
        ->and($language->is_default)->toBeFalse();
})->group('unit', 'models', 'language');

test('can activate a language', function () {
    $language = createLanguage(['is_active' => false]);

    $result = $language->activate();

    expect($result)->toBeTrue()
        ->and($language->fresh()->is_active)->toBeTrue();
})->group('unit', 'models', 'language');

test('can deactivate a language', function () {
    $language = createLanguage(['is_active' => true, 'is_default' => false]);

    $result = $language->deactivate();

    expect($result)->toBeTrue()
        ->and($language->fresh()->is_active)->toBeFalse();
})->group('unit', 'models', 'language');

test('cannot deactivate default language', function () {
    $language = createLanguage(['is_default' => true, 'is_active' => true]);

    $result = $language->deactivate();

    expect($result)->toBeFalse()
        ->and($language->fresh()->is_active)->toBeTrue();
})->group('unit', 'models', 'language');

test('can set language as default', function () {
    $oldDefault = createLanguage(['is_default' => true]);
    $newLanguage = createLanguage(['is_default' => false]);

    $result = $newLanguage->setAsDefault();

    expect($result)->toBeTrue()
        ->and($newLanguage->fresh()->is_default)->toBeTrue()
        ->and($newLanguage->fresh()->is_active)->toBeTrue()
        ->and($oldDefault->fresh()->is_default)->toBeFalse();
})->group('unit', 'models', 'language');

test('is_rtl returns true for RTL languages', function () {
    $language = createLanguage(['direction' => 'rtl']);

    expect($language->isRtl())->toBeTrue();
})->group('unit', 'models', 'language');

test('is_rtl returns false for LTR languages', function () {
    $language = createLanguage(['direction' => 'ltr']);

    expect($language->isRtl())->toBeFalse();
})->group('unit', 'models', 'language');

test('get country info returns correct structure', function () {
    $language = createLanguage([
        'code' => 'en',
        'name' => 'English',
        'country_code' => 'US',
        'region' => 'North America',
    ]);

    $info = $language->getCountryInfo();

    expect($info)->toHaveKeys(['language_code', 'language_name', 'country', 'country_code', 'region'])
        ->and($info['language_code'])->toBe('en')
        ->and($info['language_name'])->toBe('English')
        ->and($info['country_code'])->toBe('US')
        ->and($info['region'])->toBe('North America');
})->group('unit', 'models', 'language');

test('active scope filters active languages', function () {
    createLanguage(['is_active' => true]);
    createLanguage(['is_active' => true]);
    createLanguage(['is_active' => false]);

    $activeLanguages = Language::active()->get();

    expect($activeLanguages)->toHaveCount(2);
})->group('unit', 'models', 'language');

test('get active languages from cache', function () {
    createLanguage(['is_active' => true, 'name' => 'English']);
    createLanguage(['is_active' => true, 'name' => 'Spanish']);
    createLanguage(['is_active' => false]);

    $languages = Language::getActive();

    expect($languages)->toHaveCount(2)
        ->and(Cache::has('ai_translator.languages.active'))->toBeTrue();
})->group('unit', 'models', 'language');

test('get default language', function () {
    createLanguage(['is_default' => false]);
    $default = createLanguage(['code' => 'en', 'is_default' => true]);

    $retrieved = Language::getDefault();

    expect($retrieved->id)->toBe($default->id)
        ->and($retrieved->code)->toBe('en');
})->group('unit', 'models', 'language');

test('get language by code', function () {
    $language = createLanguage(['code' => 'fr']);

    $retrieved = Language::getByCode('fr');

    expect($retrieved->id)->toBe($language->id)
        ->and($retrieved->code)->toBe('fr');
})->group('unit', 'models', 'language');

test('has translations relationship', function () {
    $language = createLanguage();
    createTranslation(['language_id' => $language->id]);
    createTranslation(['language_id' => $language->id]);

    expect($language->translations)->toHaveCount(2)
        ->each->toBeTranslation();
})->group('unit', 'models', 'language');

test('clears cache when language is saved', function () {
    Cache::put('ai_translator.languages.active', 'test_value');

    $language = createLanguage();
    $language->name = 'Updated Name';
    $language->save();

    expect(Cache::has('ai_translator.languages.active'))->toBeFalse();
})->group('unit', 'models', 'language');

test('clears cache when language is deleted', function () {
    Cache::put('ai_translator.languages.active', 'test_value');

    $language = createLanguage(['is_default' => false]);
    $language->delete();

    expect(Cache::has('ai_translator.languages.active'))->toBeFalse();
})->group('unit', 'models', 'language');

test('factory creates unique language codes', function () {
    $lang1 = createLanguage();
    $lang2 = createLanguage();

    expect($lang1->code)->not->toBe($lang2->code);
})->group('unit', 'models', 'language');

test('factory state: active', function () {
    $language = Language::factory()->active()->create();

    expect($language->is_active)->toBeTrue();
})->group('unit', 'models', 'language');

test('factory state: inactive', function () {
    $language = Language::factory()->inactive()->create();

    expect($language->is_active)->toBeFalse();
})->group('unit', 'models', 'language');

test('factory state: default', function () {
    $language = Language::factory()->default()->create();

    expect($language->is_default)->toBeTrue();
})->group('unit', 'models', 'language');

test('factory state: rtl', function () {
    $language = Language::factory()->rtl()->create();

    expect($language->direction)->toBe('rtl')
        ->and($language->code)->toBe('ar')
        ->and($language->isRtl())->toBeTrue();
})->group('unit', 'models', 'language');

test('factory state: english', function () {
    $language = Language::factory()->english()->create();

    expect($language->code)->toBe('en')
        ->and($language->name)->toBe('English');
})->group('unit', 'models', 'language');
