<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Facades\Cache;

test('can create a translation', function () {
    $language = createLanguage();
    $translation = createTranslation([
        'key' => 'home.title',
        'value' => 'Welcome Home',
        'language_id' => $language->id,
        'group' => 'pages',
    ]);

    expect($translation)->toBeTranslation()
        ->and($translation->key)->toBe('home.title')
        ->and($translation->value)->toBe('Welcome Home')
        ->and($translation->group)->toBe('pages')
        ->and($translation->language_id)->toBe($language->id);
})->group('unit', 'models', 'translation');

test('belongs to a language', function () {
    $language = createLanguage();
    $translation = createTranslation(['language_id' => $language->id]);

    expect($translation->language)->toBeLanguage()
        ->and($translation->language->id)->toBe($language->id);
})->group('unit', 'models', 'translation');

test('clears cache when translation is saved', function () {
    $language = createLanguage(['code' => 'en']);
    $translation = createTranslation([
        'key' => 'test.key',
        'language_id' => $language->id,
        'group' => 'test',
    ]);

    $cacheKey = "ai_translator.test.test.key.en";
    Cache::put($cacheKey, 'cached_value', 3600);

    $translation->value = 'Updated Value';
    $translation->save();

    expect(Cache::has($cacheKey))->toBeFalse();
})->group('unit', 'models', 'translation');

test('is active by default', function () {
    $translation = createTranslation();

    expect($translation->is_active)->toBeTrue();
})->group('unit', 'models', 'translation');

test('can have different groups', function () {
    $translation1 = createTranslation(['group' => 'auth']);
    $translation2 = createTranslation(['group' => 'validation']);

    expect($translation1->group)->toBe('auth')
        ->and($translation2->group)->toBe('validation');
})->group('unit', 'models', 'translation');

test('factory creates valid translations', function () {
    $translation = Translation::factory()->create();

    expect($translation)->toBeTranslation()
        ->and($translation->key)->not->toBeEmpty()
        ->and($translation->group)->not->toBeEmpty()
        ->and($translation->language_id)->not->toBeNull();
})->group('unit', 'models', 'translation');

test('factory state: with key', function () {
    $translation = Translation::factory()->withKey('custom.key')->create();

    expect($translation->key)->toBe('custom.key');
})->group('unit', 'models', 'translation');

test('factory state: with value', function () {
    $translation = Translation::factory()->withValue('Custom Value')->create();

    expect($translation->value)->toBe('Custom Value');
})->group('unit', 'models', 'translation');

test('factory state: with group', function () {
    $translation = Translation::factory()->withGroup('custom')->create();

    expect($translation->group)->toBe('custom');
})->group('unit', 'models', 'translation');

test('factory state: for language', function () {
    $language = createLanguage();
    $translation = Translation::factory()->forLanguage($language)->create();

    expect($translation->language_id)->toBe($language->id);
})->group('unit', 'models', 'translation');

test('factory state: missing', function () {
    $translation = Translation::factory()->missing()->create();

    expect($translation->value)->toBeNull();
})->group('unit', 'models', 'translation');

test('factory state: auth', function () {
    $translation = Translation::factory()->auth()->create();

    expect($translation->group)->toBe('auth')
        ->and($translation->key)->toContain('auth.');
})->group('unit', 'models', 'translation');

test('factory state: validation', function () {
    $translation = Translation::factory()->validation()->create();

    expect($translation->group)->toBe('validation')
        ->and($translation->key)->toContain('validation.');
})->group('unit', 'models', 'translation');

test('factory state: common', function () {
    $translation = Translation::factory()->common()->create();

    expect($translation->group)->toBe('common')
        ->and($translation->key)->toContain('common.');
})->group('unit', 'models', 'translation');

test('can update translation value', function () {
    $translation = createTranslation(['value' => 'Original Value']);

    $translation->update(['value' => 'Updated Value']);

    expect($translation->fresh()->value)->toBe('Updated Value');
})->group('unit', 'models', 'translation');

test('can mark translation as auto-translated', function () {
    $translation = createTranslation(['is_auto_translated' => false]);

    $translation->update(['is_auto_translated' => true]);

    expect($translation->fresh()->is_auto_translated)->toBeTrue();
})->group('unit', 'models', 'translation');

test('can create multiple translations for same key in different languages', function () {
    $english = createLanguage(['code' => 'en']);
    $spanish = createLanguage(['code' => 'es']);

    $enTranslation = createTranslation([
        'key' => 'greeting',
        'value' => 'Hello',
        'language_id' => $english->id,
    ]);

    $esTranslation = createTranslation([
        'key' => 'greeting',
        'value' => 'Hola',
        'language_id' => $spanish->id,
    ]);

    expect($enTranslation->key)->toBe('greeting')
        ->and($esTranslation->key)->toBe('greeting')
        ->and($enTranslation->value)->toBe('Hello')
        ->and($esTranslation->value)->toBe('Hola');
})->group('unit', 'models', 'translation');
