<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Masum\AiTranslator\Jobs\TranslateJob;
use Masum\AiTranslator\Events\TranslationCompleted;
use Masum\AiTranslator\Events\TranslationFailed;
use Masum\AiTranslator\Models\Language;
use function Pest\Laravel\postJson;

describe('Queue System - Configuration', function () {
    test('queue configuration is loaded correctly', function () {
        expect(config('ai-translator.queue.enabled'))->toBeTrue()
            ->and(config('ai-translator.queue.name'))->toBe('translations')
            ->and(config('ai-translator.queue.bulk_name'))->toBe('translations-bulk')
            ->and(config('ai-translator.queue.timeout'))->toBe(120)
            ->and(config('ai-translator.queue.retries'))->toBe(3);
    })->group('queue', 'config');

    test('queue can be disabled via config', function () {
        config(['ai-translator.queue.enabled' => false]);

        expect(config('ai-translator.queue.enabled'))->toBeFalse();
    })->group('queue', 'config');
});

describe('Queue System - TranslateJob', function () {
    beforeEach(function () {
        Queue::fake();
        Event::fake();

        $this->sourceLanguage = Language::factory()->create(['code' => 'en']);
        $this->targetLanguage = Language::factory()->create(['code' => 'es']);
    });

    test('translate job can be dispatched', function () {
        TranslateJob::dispatch(
            'home.title',
            'Welcome Home',
            'en',
            ['es'],
            'pages',
            1
        );

        Queue::assertPushed(TranslateJob::class);
    })->group('queue', 'jobs');

    test('translate job is pushed to correct queue', function () {
        TranslateJob::dispatch(
            'home.title',
            'Welcome Home',
            'en',
            ['es'],
            'pages',
            1
        );

        Queue::assertPushedOn('translations', TranslateJob::class);
    })->group('queue', 'jobs');

    test('translate job has correct retry configuration', function () {
        $job = new TranslateJob(
            'home.title',
            'Welcome Home',
            'en',
            ['es'],
            'pages',
            1
        );

        expect($job->tries)->toBe(3)
            ->and($job->timeout)->toBe(120)
            ->and($job->maxExceptions)->toBe(3);
    })->group('queue', 'jobs');

    test('translate job has backoff strategy', function () {
        $job = new TranslateJob(
            'home.title',
            'Welcome Home',
            'en',
            ['es']
        );

        $backoff = $job->backoff();

        expect($backoff)->toBe([10, 30, 60]);
    })->group('queue', 'jobs');

    test('translate job has correct tags', function () {
        $job = new TranslateJob(
            'home.title',
            'Welcome Home',
            'en',
            ['es'],
            'pages',
            123
        );

        $tags = $job->tags();

        expect($tags)->toContain('translation')
            ->and($tags)->toContain('key:home.title')
            ->and($tags)->toContain('source:en')
            ->and($tags)->toContain('user:123');
    })->group('queue', 'jobs');
});


describe('Queue System - Auto-Translate API with Queue', function () {
    beforeEach(function () {
        Queue::fake();

        config(['ai-translator.queue.enabled' => true]);

        $this->sourceLanguage = Language::factory()->create(['code' => 'en']);
        $this->targetLanguage = Language::factory()->create(['code' => 'es']);
    });

    test('auto-translate queues job when queue is enabled', function () {
        $response = postJson('/api/translator/auto-translate', [
            'key' => 'home.title',
            'value' => 'Welcome Home',
            'source_language' => 'en',
            'target_languages' => ['es'],
            'group' => 'pages',
        ]);

        $response->assertStatus(202) // 202 Accepted
            ->assertJson([
                'success' => true,
                'message' => 'Translation queued successfully. Processing in background.',
                'data' => [
                    'status' => 'queued',
                ],
            ]);

        Queue::assertPushed(TranslateJob::class);
    })->group('queue', 'api');

    test('auto-translate processes synchronously when sync=true', function () {
        $response = postJson('/api/translator/auto-translate', [
            'key' => 'home.title',
            'value' => 'Welcome Home',
            'source_language' => 'en',
            'target_languages' => ['es'],
            'group' => 'pages',
            'sync' => true,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);

        Queue::assertNothingPushed();
    })->group('queue', 'api');

    test('auto-translate processes synchronously when queue is disabled', function () {
        config(['ai-translator.queue.enabled' => false]);

        $response = postJson('/api/translator/auto-translate', [
            'key' => 'home.title',
            'value' => 'Welcome Home',
            'source_language' => 'en',
            'target_languages' => ['es'],
            'group' => 'pages',
        ]);

        $response->assertOk();
        Queue::assertNothingPushed();
    })->group('queue', 'api');
});

describe('Queue System - Batch Translate API with Queue', function () {
    beforeEach(function () {
        Queue::fake();

        config(['ai-translator.queue.enabled' => true]);
        config(['ai-translator.queue.batch_enabled' => true]);

        Language::factory()->create(['code' => 'en']);
        Language::factory()->create(['code' => 'es']);
    });

    test('batch translate processes synchronously when sync=true', function () {
        $response = postJson('/api/translator/batch-translate', [
            'translations' => [
                ['key' => 'home.title', 'value' => 'Welcome'],
            ],
            'source_language' => 'en',
            'target_languages' => ['es'],
            'sync' => true,
        ]);

        $response->assertOk();
        Queue::assertNothingPushed();
    })->group('queue', 'api');

    test('batch translate handles single translation without queue', function () {
        config(['ai-translator.queue.batch_enabled' => false]);

        $response = postJson('/api/translator/batch-translate', [
            'translations' => [
                ['key' => 'home.title', 'value' => 'Welcome'],
            ],
            'source_language' => 'en',
            'target_languages' => ['es'],
        ]);

        $response->assertOk();
        Queue::assertNothingPushed();
    })->group('queue', 'api');
});

describe('Queue System - Events', function () {
    test('translation completed event exists', function () {
        expect(class_exists(\Masum\AiTranslator\Events\TranslationCompleted::class))->toBeTrue();
    })->group('queue', 'events');

    test('translation failed event exists', function () {
        expect(class_exists(\Masum\AiTranslator\Events\TranslationFailed::class))->toBeTrue();
    })->group('queue', 'events');

    test('translation completed event can be created', function () {
        $event = new TranslationCompleted(
            'home.title',
            ['es' => ['success' => true, 'value' => 'Bienvenido']],
            123,
            ['test' => true]
        );

        expect($event->key)->toBe('home.title')
            ->and($event->results)->toHaveKey('es')
            ->and($event->userId)->toBe(123)
            ->and($event->metadata)->toHaveKey('test');
    })->group('queue', 'events');

    test('translation failed event can be created', function () {
        $event = new TranslationFailed(
            'home.title',
            'Translation service unavailable',
            123,
            ['attempts' => 3]
        );

        expect($event->key)->toBe('home.title')
            ->and($event->error)->toBe('Translation service unavailable')
            ->and($event->userId)->toBe(123)
            ->and($event->metadata)->toHaveKey('attempts');
    })->group('queue', 'events');
});

describe('Queue System - Job Processing', function () {
    beforeEach(function () {
        Bus::fake();
        Event::fake();
    });

    test('translate job can be processed', function () {
        $sourceLanguage = Language::factory()->create(['code' => 'en']);
        $targetLanguage = Language::factory()->create(['code' => 'es']);

        TranslateJob::dispatch(
            'test.key',
            'Test Value',
            'en',
            ['es'],
            'general',
            1
        );

        Bus::assertDispatched(TranslateJob::class);
    })->group('queue', 'processing');
});
