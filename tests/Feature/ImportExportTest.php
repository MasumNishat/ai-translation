<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Services\JsonImportExportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\{getJson, postJson};

describe('JSON Import/Export Service', function () {
    beforeEach(function () {
        $this->service = app(JsonImportExportService::class);
        $this->language = Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English',
            'direction' => 'ltr',
        ]);
    });

    test('can export translations to JSON format', function () {
        Translation::factory()->count(3)->create([
            'language_id' => $this->language->id,
            'group' => 'home',
        ]);

        $result = $this->service->export('en');

        expect($result)->toHaveKeys(['meta', 'translations'])
            ->and($result['meta'])->toHaveKeys(['version', 'language', 'exported_at', 'total_translations', 'groups'])
            ->and($result['meta']['language']['code'])->toBe('en')
            ->and($result['meta']['total_translations'])->toBe(3);
    })->group('import-export', 'service');

    test('can export translations for specific group', function () {
        Translation::factory()->count(2)->create([
            'language_id' => $this->language->id,
            'group' => 'home',
        ]);
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'group' => 'auth',
        ]);

        $result = $this->service->export('en', 'home');

        expect($result['meta']['total_translations'])->toBe(2)
            ->and($result['meta']['groups'])->toContain('home')
            ->and($result['meta']['groups'])->not->toContain('auth');
    })->group('import-export', 'service');

    test('formats nested translations correctly', function () {
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'home.title',
            'value' => 'Welcome Home',
            'group' => 'pages',
        ]);

        Translation::factory()->create([
            'language_id' => $this->language->id,
            'key' => 'home.subtitle',
            'value' => 'Your dashboard',
            'group' => 'pages',
        ]);

        $result = $this->service->export('en');

        expect($result['translations'])->toHaveKey('home')
            ->and($result['translations']['home'])->toHaveKey('title')
            ->and($result['translations']['home'])->toHaveKey('subtitle')
            ->and($result['translations']['home']['title']['value'])->toBe('Welcome Home');
    })->group('import-export', 'service');

    test('throws exception for non-existent language', function () {
        expect(fn() => $this->service->export('nonexistent'))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    })->group('import-export', 'service');

    test('can import translations from valid JSON', function () {
        $data = [
            'meta' => [
                'version' => '1.0',
                'language' => [
                    'code' => 'es',
                    'name' => 'Spanish',
                    'native_name' => 'Español',
                    'direction' => 'ltr',
                ],
            ],
            'translations' => [
                'home' => [
                    'title' => [
                        'value' => 'Bienvenido',
                        'group' => 'pages',
                    ],
                ],
            ],
        ];

        Language::factory()->create(['code' => 'es']);

        $stats = $this->service->import($data);

        expect($stats)->toHaveKeys(['created', 'updated', 'skipped', 'errors'])
            ->and($stats['created'])->toBe(1)
            ->and($stats['updated'])->toBe(0)
            ->and($stats['errors'])->toBeEmpty();

        expect(Translation::where('key', 'home.title')->where('value', 'Bienvenido')->exists())->toBeTrue();
    })->group('import-export', 'service');

    test('can create language during import if option enabled', function () {
        $data = [
            'meta' => [
                'version' => '1.0',
                'language' => [
                    'code' => 'fr',
                    'name' => 'French',
                    'native_name' => 'Français',
                    'direction' => 'ltr',
                ],
            ],
            'translations' => [
                'test' => [
                    'key' => [
                        'value' => 'Bonjour',
                        'group' => 'general',
                    ],
                ],
            ],
        ];

        expect(Language::where('code', 'fr')->exists())->toBeFalse();

        $stats = $this->service->import($data, ['create_language' => true]);

        expect(Language::where('code', 'fr')->exists())->toBeTrue()
            ->and($stats['created'])->toBe(1);
    })->group('import-export', 'service');

    test('throws exception if language does not exist and create_language is false', function () {
        $data = [
            'meta' => [
                'version' => '1.0',
                'language' => [
                    'code' => 'de',
                    'name' => 'German',
                    'native_name' => 'Deutsch',
                    'direction' => 'ltr',
                ],
            ],
            'translations' => [],
        ];

        expect(fn() => $this->service->import($data, ['create_language' => false]))
            ->toThrow(\InvalidArgumentException::class);
    })->group('import-export', 'service');

    test('can update existing translations when overwrite is true', function () {
        $language = Language::factory()->create(['code' => 'en']);

        Translation::factory()->create([
            'key' => 'greeting',
            'value' => 'Hello',
            'language_id' => $language->id,
        ]);

        $data = [
            'meta' => [
                'version' => '1.0',
                'language' => [
                    'code' => 'en',
                    'name' => 'English',
                    'native_name' => 'English',
                    'direction' => 'ltr',
                ],
            ],
            'translations' => [
                'greeting' => [
                    'value' => 'Hi',
                    'group' => 'general',
                ],
            ],
        ];

        $stats = $this->service->import($data, ['overwrite' => true]);

        expect($stats['updated'])->toBe(1)
            ->and($stats['created'])->toBe(0);

        $translation = Translation::where('key', 'greeting')->first();
        expect($translation->value)->toBe('Hi');
    })->group('import-export', 'service');

    test('skips existing translations when overwrite is false', function () {
        $language = Language::factory()->create(['code' => 'en']);

        Translation::factory()->create([
            'key' => 'greeting',
            'value' => 'Hello',
            'language_id' => $language->id,
        ]);

        $data = [
            'meta' => [
                'version' => '1.0',
                'language' => [
                    'code' => 'en',
                    'name' => 'English',
                    'native_name' => 'English',
                    'direction' => 'ltr',
                ],
            ],
            'translations' => [
                'greeting' => [
                    'value' => 'Hi',
                    'group' => 'general',
                ],
            ],
        ];

        $stats = $this->service->import($data, ['overwrite' => false]);

        expect($stats['skipped'])->toBe(1)
            ->and($stats['updated'])->toBe(0);

        $translation = Translation::where('key', 'greeting')->first();
        expect($translation->value)->toBe('Hello'); // Unchanged
    })->group('import-export', 'service');

    test('validates import data structure', function () {
        $invalidData = [
            'meta' => [
                'version' => '1.0',
                // Missing language info
            ],
            'translations' => [],
        ];

        expect(fn() => $this->service->import($invalidData))
            ->toThrow(\InvalidArgumentException::class);
    })->group('import-export', 'service');

    test('can export all active languages', function () {
        Language::factory()->count(3)->create(['is_active' => true]);
        Translation::factory()->count(2)->create();

        $result = $this->service->exportAll();

        expect($result)->toHaveKeys(['meta', 'data'])
            ->and($result['meta'])->toHaveKeys(['version', 'exported_at', 'languages'])
            ->and($result['data'])->toBeArray();
    })->group('import-export', 'service');

    test('can export specific languages only', function () {
        Language::factory()->create(['code' => 'en', 'is_active' => true]);
        Language::factory()->create(['code' => 'es', 'is_active' => true]);
        Language::factory()->create(['code' => 'fr', 'is_active' => true]);

        $result = $this->service->exportAll(['en', 'es']);

        expect($result['meta']['languages'])->toHaveCount(2)
            ->and($result['meta']['languages'])->toContain('en')
            ->and($result['meta']['languages'])->toContain('es')
            ->and($result['meta']['languages'])->not->toContain('fr');
    })->group('import-export', 'service');
});

describe('Import/Export API - Export Endpoints', function () {
    beforeEach(function () {
        $this->language = Language::factory()->create([
            'code' => 'en',
            'name' => 'English',
        ]);
    });

    test('can export translations via API', function () {
        Translation::factory()->count(3)->create([
            'language_id' => $this->language->id,
        ]);

        $response = getJson('/api/translator/import-export/export/en');

        $response->assertOk()
            ->assertJsonStructure([
                'meta' => [
                    'version',
                    'language' => ['code', 'name', 'native_name', 'direction'],
                    'exported_at',
                    'total_translations',
                    'groups',
                ],
                'translations',
            ])
            ->assertHeader('Content-Disposition');
    })->group('import-export', 'api');

    test('returns 404 for non-existent language', function () {
        $response = getJson('/api/translator/import-export/export/nonexistent');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => "Language 'nonexistent' not found.",
            ]);
    })->group('import-export', 'api');

    test('can export translations by group', function () {
        Translation::factory()->count(2)->create([
            'language_id' => $this->language->id,
            'group' => 'home',
        ]);
        Translation::factory()->create([
            'language_id' => $this->language->id,
            'group' => 'auth',
        ]);

        $response = getJson('/api/translator/import-export/export/en/home');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['total_translations'])->toBe(2)
            ->and($data['meta']['groups'])->toContain('home');
    })->group('import-export', 'api');

    test('can export all languages', function () {
        Language::factory()->count(2)->create(['is_active' => true]);

        $response = getJson('/api/translator/import-export/export/all');

        $response->assertOk()
            ->assertJsonStructure([
                'meta' => ['version', 'exported_at', 'languages'],
                'data',
            ]);
    })->group('import-export', 'api');

    test('can export specific languages only', function () {
        Language::factory()->create(['code' => 'en', 'is_active' => true]);
        Language::factory()->create(['code' => 'es', 'is_active' => true]);
        Language::factory()->create(['code' => 'fr', 'is_active' => true]);

        $response = getJson('/api/translator/import-export/export/all?languages[]=en&languages[]=es');

        $response->assertOk();
        $data = $response->json();

        expect($data['meta']['languages'])->toHaveCount(2)
            ->and($data['meta']['languages'])->toContain('en')
            ->and($data['meta']['languages'])->toContain('es');
    })->group('import-export', 'api');
});

describe('Import/Export API - Import Endpoints', function () {
    test('can import translations via API', function () {
        Language::factory()->create(['code' => 'es']);

        $jsonData = [
            'meta' => [
                'version' => '1.0',
                'language' => [
                    'code' => 'es',
                    'name' => 'Spanish',
                    'native_name' => 'Español',
                    'direction' => 'ltr',
                ],
            ],
            'translations' => [
                'home' => [
                    'title' => [
                        'value' => 'Bienvenido',
                        'group' => 'pages',
                    ],
                ],
            ],
        ];

        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent(
            'translations.json',
            json_encode($jsonData)
        );

        $response = postJson('/api/translator/import-export/import', [
            'file' => $file,
            'overwrite' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'message',
                'data' => ['created', 'updated', 'skipped', 'errors'],
            ]);

        expect($response->json('data.created'))->toBe(1);
        expect(Translation::where('key', 'home.title')->exists())->toBeTrue();
    })->group('import-export', 'api');

    test('validates JSON file format', function () {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent(
            'invalid.json',
            'invalid json content {'
        );

        $response = postJson('/api/translator/import-export/import', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonFragment(['message']);
    })->group('import-export', 'api');

    test('requires file parameter', function () {
        $response = postJson('/api/translator/import-export/import', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    })->group('import-export', 'api');

    test('can create language during import if option enabled', function () {
        $jsonData = [
            'meta' => [
                'version' => '1.0',
                'language' => [
                    'code' => 'fr',
                    'name' => 'French',
                    'native_name' => 'Français',
                    'direction' => 'ltr',
                ],
            ],
            'translations' => [
                'test' => [
                    'value' => 'Bonjour',
                    'group' => 'general',
                ],
            ],
        ];

        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent(
            'translations.json',
            json_encode($jsonData)
        );

        $response = postJson('/api/translator/import-export/import', [
            'file' => $file,
            'create_language' => true,
        ]);

        $response->assertOk();
        expect(Language::where('code', 'fr')->exists())->toBeTrue();
    })->group('import-export', 'api');

    test('fails if language does not exist and create_language is false', function () {
        $jsonData = [
            'meta' => [
                'version' => '1.0',
                'language' => [
                    'code' => 'de',
                    'name' => 'German',
                    'native_name' => 'Deutsch',
                    'direction' => 'ltr',
                ],
            ],
            'translations' => [],
        ];

        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent(
            'translations.json',
            json_encode($jsonData)
        );

        $response = postJson('/api/translator/import-export/import', [
            'file' => $file,
            'create_language' => false,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    })->group('import-export', 'api');

    test('returns statistics about import', function () {
        $language = Language::factory()->create(['code' => 'en']);

        Translation::factory()->create([
            'key' => 'existing',
            'value' => 'Old Value',
            'language_id' => $language->id,
        ]);

        $jsonData = [
            'meta' => [
                'version' => '1.0',
                'language' => [
                    'code' => 'en',
                    'name' => 'English',
                    'native_name' => 'English',
                    'direction' => 'ltr',
                ],
            ],
            'translations' => [
                'existing' => [
                    'value' => 'New Value',
                    'group' => 'general',
                ],
                'new' => [
                    'value' => 'Brand New',
                    'group' => 'general',
                ],
            ],
        ];

        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent(
            'translations.json',
            json_encode($jsonData)
        );

        $response = postJson('/api/translator/import-export/import', [
            'file' => $file,
            'overwrite' => true,
        ]);

        $response->assertOk();
        $data = $response->json('data');

        expect($data['created'])->toBe(1)
            ->and($data['updated'])->toBe(1)
            ->and($data['errors'])->toBeEmpty();
    })->group('import-export', 'api');
});
