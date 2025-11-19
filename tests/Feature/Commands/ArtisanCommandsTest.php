<?php

use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Create default language
    $this->defaultLanguage = Language::factory()->english()->default()->create();
    $this->spanish = Language::factory()->spanish()->create();
    $this->french = Language::factory()->french()->create();
});

describe('translator:clear-cache command', function () {
    test('clears all translation cache', function () {
        // Set up some cache
        Cache::put('ai_translator.auth.login.en', 'Login', 3600);
        Cache::put('ai_translator.auth.logout.en', 'Logout', 3600);

        $this->artisan('translator:clear-cache', ['--all' => true])
            ->assertExitCode(0)
            ->expectsOutput('🧹 Clearing translation cache...');

        expect(Cache::has('ai_translator.auth.login.en'))->toBeFalse();
    })->group('commands', 'cache');

    test('clears cache for specific language', function () {
        Cache::put('ai_translator.auth.login.en', 'Login', 3600);
        Cache::put('ai_translator.auth.login.es', 'Iniciar sesión', 3600);

        $this->artisan('translator:clear-cache', ['--language' => 'en'])
            ->assertExitCode(0)
            ->expectsOutputToContain("Cleared cache for language 'en'");
    })->group('commands', 'cache');

    test('fails with invalid language', function () {
        $this->artisan('translator:clear-cache', ['--language' => 'invalid'])
            ->assertExitCode(1)
            ->expectsOutputToContain('not found');
    })->group('commands', 'cache');
});

describe('translator:stats command', function () {
    test('displays overall statistics', function () {
        // Create some translations
        Translation::factory()->count(10)->create([
            'language_id' => $this->defaultLanguage->id,
        ]);

        $this->artisan('translator:stats')
            ->assertExitCode(0)
            ->expectsOutput('📊 Translation Statistics')
            ->expectsOutputToContain('Total Languages')
            ->expectsOutputToContain('Total Translations');
    })->group('commands', 'stats');

    test('displays language-specific statistics', function () {
        Translation::factory()->count(5)->create([
            'language_id' => $this->spanish->id,
            'group' => 'auth',
        ]);

        $this->artisan('translator:stats', ['--language' => 'es'])
            ->assertExitCode(0)
            ->expectsOutputToContain('Language: Spanish');
    })->group('commands', 'stats');

    test('shows detailed statistics with --detailed flag', function () {
        Translation::factory()->count(10)->create([
            'language_id' => $this->defaultLanguage->id,
        ]);

        $this->artisan('translator:stats', ['--detailed' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('Top Translation Groups');
    })->group('commands', 'stats');

    test('shows missing translations in detailed mode', function () {
        // Create translation in default language
        Translation::factory()->create([
            'key' => 'auth.login',
            'language_id' => $this->defaultLanguage->id,
        ]);

        $this->artisan('translator:stats', [
            '--language' => 'es',
            '--detailed' => true,
        ])
            ->assertExitCode(0);
    })->group('commands', 'stats');
});

describe('translator:sync command', function () {
    test('syncs missing translations from default language', function () {
        // Create translations in default language
        Translation::factory()->create([
            'key' => 'auth.login',
            'value' => 'Login',
            'language_id' => $this->defaultLanguage->id,
            'group' => 'auth',
        ]);

        Translation::factory()->create([
            'key' => 'auth.logout',
            'value' => 'Logout',
            'language_id' => $this->defaultLanguage->id,
            'group' => 'auth',
        ]);

        $this->artisan('translator:sync', ['--language' => 'es'])
            ->assertExitCode(0)
            ->expectsOutput('🔄 Syncing translations...');

        // Check if translations were created
        expect(Translation::where('language_id', $this->spanish->id)->count())->toBe(2);
    })->group('commands', 'sync');

    test('dry run mode does not create translations', function () {
        Translation::factory()->create([
            'key' => 'auth.login',
            'language_id' => $this->defaultLanguage->id,
        ]);

        $initialCount = Translation::where('language_id', $this->spanish->id)->count();

        $this->artisan('translator:sync', [
            '--language' => 'es',
            '--dry-run' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY RUN MODE');

        expect(Translation::where('language_id', $this->spanish->id)->count())->toBe($initialCount);
    })->group('commands', 'sync');

    test('syncs only specific group when --group is provided', function () {
        Translation::factory()->create([
            'key' => 'auth.login',
            'language_id' => $this->defaultLanguage->id,
            'group' => 'auth',
        ]);

        Translation::factory()->create([
            'key' => 'common.save',
            'language_id' => $this->defaultLanguage->id,
            'group' => 'common',
        ]);

        $this->artisan('translator:sync', [
            '--language' => 'es',
            '--group' => 'auth',
        ])
            ->assertExitCode(0);

        // Only auth group should be synced
        expect(Translation::where('language_id', $this->spanish->id)
            ->where('group', 'auth')
            ->count())->toBe(1);

        expect(Translation::where('language_id', $this->spanish->id)
            ->where('group', 'common')
            ->count())->toBe(0);
    })->group('commands', 'sync');
});

describe('translator:export command', function () {
    test('exports translations to JSON format', function () {
        Translation::factory()->count(3)->create([
            'language_id' => $this->defaultLanguage->id,
            'group' => 'auth',
        ]);

        $path = storage_path('test-export.json');

        $this->artisan('translator:export', [
            'path' => $path,
            '--format' => 'json',
        ])
            ->assertExitCode(0)
            ->expectsOutput('📤 Exporting translations...');

        expect(File::exists($path))->toBeTrue();

        $content = File::get($path);
        $data = json_decode($content, true);

        expect($data)->toHaveKey('meta')
            ->and($data)->toHaveKey('languages');

        File::delete($path);
    })->group('commands', 'export');

    test('exports translations to CSV format', function () {
        Translation::factory()->create([
            'language_id' => $this->defaultLanguage->id,
        ]);

        $path = storage_path('test-export.csv');

        $this->artisan('translator:export', [
            'path' => $path,
            '--format' => 'csv',
        ])
            ->assertExitCode(0);

        expect(File::exists($path))->toBeTrue();

        $content = File::get($path);
        expect($content)->toContain('Language Code');

        File::delete($path);
    })->group('commands', 'export');

    test('exports translations to PHP array format', function () {
        Translation::factory()->create([
            'language_id' => $this->defaultLanguage->id,
        ]);

        $path = storage_path('test-export.php');

        $this->artisan('translator:export', [
            'path' => $path,
            '--format' => 'php',
        ])
            ->assertExitCode(0);

        expect(File::exists($path))->toBeTrue();

        $content = File::get($path);
        expect($content)->toContain('<?php');

        File::delete($path);
    })->group('commands', 'export');

    test('exports only specific language', function () {
        Translation::factory()->create([
            'language_id' => $this->defaultLanguage->id,
        ]);

        Translation::factory()->create([
            'language_id' => $this->spanish->id,
        ]);

        $path = storage_path('test-export.json');

        $this->artisan('translator:export', [
            'path' => $path,
            '--language' => 'en',
            '--format' => 'json',
        ])
            ->assertExitCode(0);

        $data = json_decode(File::get($path), true);
        expect($data['languages'])->toHaveKey('en')
            ->and($data['languages'])->not->toHaveKey('es');

        File::delete($path);
    })->group('commands', 'export');
});

describe('translator:import command', function () {
    test('imports translations from JSON format', function () {
        $data = [
            'meta' => [
                'version' => '1.0.0',
            ],
            'languages' => [
                'en' => [
                    'info' => [
                        'code' => 'en',
                        'name' => 'English',
                    ],
                    'translations' => [
                        'auth' => [
                            'login' => [
                                'value' => 'Login',
                                'is_auto_translated' => false,
                                'is_active' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $path = storage_path('test-import.json');
        File::put($path, json_encode($data));

        $this->artisan('translator:import', [
            'path' => $path,
            '--format' => 'json',
        ])
            ->assertExitCode(0)
            ->expectsOutput('📥 Importing translations...');

        expect(Translation::where('key', 'auth.login')->count())->toBeGreaterThan(0);

        File::delete($path);
    })->group('commands', 'import');

    test('dry run mode does not import translations', function () {
        $data = [
            'languages' => [
                'en' => [
                    'translations' => [
                        'auth' => [
                            'test' => ['value' => 'Test'],
                        ],
                    ],
                ],
            ],
        ];

        $path = storage_path('test-import.json');
        File::put($path, json_encode($data));

        $initialCount = Translation::count();

        $this->artisan('translator:import', [
            'path' => $path,
            '--format' => 'json',
            '--dry-run' => true,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY RUN MODE');

        expect(Translation::count())->toBe($initialCount);

        File::delete($path);
    })->group('commands', 'import');

    test('updates existing translations when --update flag is provided', function () {
        $existing = Translation::factory()->create([
            'key' => 'auth.login',
            'value' => 'Old Value',
            'language_id' => $this->defaultLanguage->id,
            'group' => 'auth',
        ]);

        $data = [
            'languages' => [
                'en' => [
                    'translations' => [
                        'auth' => [
                            'login' => ['value' => 'New Value'],
                        ],
                    ],
                ],
            ],
        ];

        $path = storage_path('test-import.json');
        File::put($path, json_encode($data));

        $this->artisan('translator:import', [
            'path' => $path,
            '--format' => 'json',
            '--update' => true,
        ])
            ->assertExitCode(0);

        expect($existing->fresh()->value)->toBe('New Value');

        File::delete($path);
    })->group('commands', 'import');

    test('fails when file does not exist', function () {
        $this->artisan('translator:import', [
            'path' => 'nonexistent.json',
        ])
            ->assertExitCode(1)
            ->expectsOutputToContain('File not found');
    })->group('commands', 'import');
});
