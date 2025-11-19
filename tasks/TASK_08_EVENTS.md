# TASK 08: Events & Notifications System

**Priority:** P3 (Medium)
**Total Estimated Time:** 15-20 hours
**Dependencies:** TASK_03 (Testing)
**Status:** ⏳ Pending

---

## Overview

Implement comprehensive event system for translation lifecycle, notifications for translators, webhooks for external integrations, and real-time updates.

---

## Subtasks

### P3-T08-S01: Translation Lifecycle Events

**Estimated Time:** 4-5 hours
**Priority:** P3
**Dependencies:** None

#### Description
Create events for all translation lifecycle stages with proper listeners.

#### Implementation

**1. Create Events**

```php
<?php

namespace Masum\AiTranslator\Events;

use Masum\AiTranslator\Models\Translation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Translation $translation
    ) {}
}

class TranslationUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Translation $translation,
        public array $changes
    ) {}
}

class TranslationDeleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Translation $translation
    ) {}
}

class TranslationRestored
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Translation $translation
    ) {}
}

class TranslationsBatchCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public array $translations,
        public string $languageCode,
        public string $group
    ) {}
}

class TranslationValidationFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Translation $translation,
        public array $errors
    ) {}
}

class TranslationApproved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Translation $translation,
        public $approver
    ) {}
}

class TranslationRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Translation $translation,
        public $rejector,
        public string $reason
    ) {}
}
```

**2. Dispatch Events from Model**

```php
<?php

// Add to Translation model

use Masum\AiTranslator\Events;

protected static function booted()
{
    static::created(function ($translation) {
        event(new Events\TranslationCreated($translation));
    });

    static::updated(function ($translation) {
        event(new Events\TranslationUpdated(
            $translation,
            $translation->getChanges()
        ));
    });

    static::deleted(function ($translation) {
        event(new Events\TranslationDeleted($translation));
    });

    static::restored(function ($translation) {
        event(new Events\TranslationRestored($translation));
    });
}
```

**3. Create Example Listeners**

```php
<?php

namespace Masum\AiTranslator\Listeners;

use Masum\AiTranslator\Events\TranslationCreated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ClearTranslationCache
{
    public function handle(TranslationCreated|TranslationUpdated|TranslationDeleted $event): void
    {
        $translation = $event->translation;
        $cacheKey = "ai_translator.{$translation->group}.{$translation->key}.{$translation->language->code}";

        Cache::forget($cacheKey);
        Cache::tags(['translations', "lang:{$translation->language->code}"])->flush();
    }
}

class LogTranslationChange
{
    public function handle($event): void
    {
        if ($event instanceof TranslationCreated) {
            Log::info('Translation created', [
                'key' => $event->translation->key,
                'language' => $event->translation->language->code,
            ]);
        } elseif ($event instanceof TranslationUpdated) {
            Log::info('Translation updated', [
                'key' => $event->translation->key,
                'changes' => $event->changes,
            ]);
        }
    }
}

class NotifyTranslationTeam
{
    public function handle(TranslationCreated $event): void
    {
        // Notify team when new translation is created
        // Implementation depends on notification channel (email, Slack, etc.)
    }
}
```

**4. Register Event Listeners**

```php
<?php

// In EventServiceProvider or package service provider

use Masum\AiTranslator\Events;
use Masum\AiTranslator\Listeners;

protected $listen = [
    Events\TranslationCreated::class => [
        Listeners\ClearTranslationCache::class,
        Listeners\LogTranslationChange::class,
        Listeners\NotifyTranslationTeam::class,
    ],
    Events\TranslationUpdated::class => [
        Listeners\ClearTranslationCache::class,
        Listeners\LogTranslationChange::class,
    ],
    Events\TranslationDeleted::class => [
        Listeners\ClearTranslationCache::class,
    ],
];
```

#### Testing

```php
test('fires event when translation is created', function () {
    Event::fake();

    $language = createLanguage();
    $translation = createTranslation(['language_id' => $language->id]);

    Event::assertDispatched(TranslationCreated::class, function ($event) use ($translation) {
        return $event->translation->id === $translation->id;
    });
});

test('clears cache when translation is updated', function () {
    $translation = createTranslation();
    $cacheKey = "ai_translator.{$translation->group}.{$translation->key}.{$translation->language->code}";

    Cache::put($cacheKey, 'cached_value', 3600);

    $translation->update(['value' => 'New Value']);

    expect(Cache::has($cacheKey))->toBeFalse();
});
```

#### Acceptance Criteria
- [ ] All lifecycle events created
- [ ] Events dispatched at correct times
- [ ] Listeners handle events properly
- [ ] Cache clearing listener works
- [ ] Logging listener works
- [ ] Tests achieve 85%+ coverage

---

### P3-T08-S02: Notification System

**Estimated Time:** 5-6 hours
**Priority:** P3
**Dependencies:** P3-T08-S01

#### Description
Implement notification system for translators and admins.

#### Implementation

**1. Create Notifications**

```php
<?php

namespace Masum\AiTranslator\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Masum\AiTranslator\Models\Translation;

class TranslationNeedsReview extends Notification
{
    public function __construct(
        public Translation $translation
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Translation Needs Review')
            ->line("A new translation needs your review:")
            ->line("Key: {$this->translation->key}")
            ->line("Language: {$this->translation->language->name}")
            ->action('Review Translation', url("/translations/{$this->translation->id}"))
            ->line('Thank you!');
    }

    public function toArray($notifiable): array
    {
        return [
            'translation_id' => $this->translation->id,
            'key' => $this->translation->key,
            'language' => $this->translation->language->code,
        ];
    }
}

class MissingTranslationsDetected extends Notification
{
    public function __construct(
        public string $languageCode,
        public int $count
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'slack', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Missing Translations Detected")
            ->line("Found {$this->count} missing translations for {$this->languageCode}")
            ->action('View Report', url("/translations/missing/{$this->languageCode}"))
            ->line('Please review and translate.');
    }

    public function toSlack($notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->content("⚠️ Missing Translations Alert")
            ->attachment(function ($attachment) {
                $attachment->title("Language: {$this->languageCode}")
                    ->fields([
                        'Missing Count' => $this->count,
                        'Action Required' => 'Please review',
                    ]);
            });
    }
}

class TranslationImportCompleted extends Notification
{
    public function __construct(
        public array $stats
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Translation Import Completed')
            ->line('Your translation import has completed successfully.')
            ->line("Created: {$this->stats['created']}")
            ->line("Updated: {$this->stats['updated']}")
            ->line("Errors: " . count($this->stats['errors']));
    }
}
```

**2. Create Notification Service**

```php
<?php

namespace Masum\AiTranslator\Services;

use Illuminate\Support\Facades\Notification;

class TranslationNotificationService
{
    /**
     * Notify about missing translations
     */
    public function notifyMissingTranslations(string $languageCode, int $count): void
    {
        $recipients = $this->getTranslationTeam($languageCode);

        Notification::send(
            $recipients,
            new MissingTranslationsDetected($languageCode, $count)
        );
    }

    /**
     * Notify about translation needing review
     */
    public function notifyNeedsReview(Translation $translation): void
    {
        $reviewers = $this->getReviewers($translation->language->code);

        Notification::send(
            $reviewers,
            new TranslationNeedsReview($translation)
        );
    }

    /**
     * Get translation team for language
     */
    protected function getTranslationTeam(string $languageCode): Collection
    {
        // Implementation depends on your user/team structure
        return User::whereHas('roles', function ($q) use ($languageCode) {
            $q->where('name', 'translator')
                ->where('language_code', $languageCode);
        })->get();
    }

    /**
     * Get reviewers for language
     */
    protected function getReviewers(string $languageCode): Collection
    {
        return User::whereHas('roles', function ($q) use ($languageCode) {
            $q->where('name', 'translation_reviewer')
                ->where('language_code', $languageCode);
        })->get();
    }
}
```

#### Acceptance Criteria
- [ ] Mail notifications work
- [ ] Database notifications work
- [ ] Slack notifications work (optional)
- [ ] Notification preferences configurable
- [ ] Unsubscribe functionality
- [ ] Tests achieve 80%+ coverage

---

### P3-T08-S03: Webhook System

**Estimated Time:** 4-5 hours
**Priority:** P3
**Dependencies:** P3-T08-S01

#### Description
Implement webhook system for external integrations.

#### Implementation

**1. Create Webhook Model and Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->json('events'); // ['translation.created', 'translation.updated']
            $table->string('secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('headers')->nullable(); // Custom headers
            $table->integer('retry_attempts')->default(3);
            $table->integer('timeout')->default(30); // seconds
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('translation_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained('translation_webhooks')->onDelete('cascade');
            $table->string('event');
            $table->json('payload');
            $table->integer('status_code')->nullable();
            $table->text('response')->nullable();
            $table->text('error')->nullable();
            $table->integer('attempt')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_webhook_logs');
        Schema::dropIfExists('translation_webhooks');
    }
};
```

**2. Webhook Model**

```php
<?php

namespace Masum\AiTranslator\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $table = 'translation_webhooks';

    protected $fillable = [
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'headers',
        'retry_attempts',
        'timeout',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function logs()
    {
        return $this->hasMany(WebhookLog::class);
    }

    public function shouldTriggerFor(string $event): bool
    {
        return $this->is_active && in_array($event, $this->events);
    }
}
```

**3. Webhook Service**

```php
<?php

namespace Masum\AiTranslator\Services;

use Illuminate\Support\Facades\Http;
use Masum\AiTranslator\Models\Webhook;
use Masum\AiTranslator\Models\WebhookLog;

class WebhookService
{
    /**
     * Trigger webhooks for an event
     */
    public function trigger(string $event, array $payload): void
    {
        $webhooks = Webhook::where('is_active', true)->get();

        foreach ($webhooks as $webhook) {
            if ($webhook->shouldTriggerFor($event)) {
                $this->sendWebhook($webhook, $event, $payload);
            }
        }
    }

    /**
     * Send webhook request
     */
    protected function sendWebhook(Webhook $webhook, string $event, array $payload): void
    {
        $fullPayload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ];

        $signature = $this->generateSignature($fullPayload, $webhook->secret);

        $headers = array_merge(
            $webhook->headers ?? [],
            [
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event' => $event,
            ]
        );

        for ($attempt = 1; $attempt <= $webhook->retry_attempts; $attempt++) {
            try {
                $response = Http::withHeaders($headers)
                    ->timeout($webhook->timeout)
                    ->post($webhook->url, $fullPayload);

                $this->logWebhook($webhook, $event, $fullPayload, $response->status(), $response->body(), null, $attempt);

                if ($response->successful()) {
                    $webhook->update(['last_triggered_at' => now()]);
                    break;
                }

                if ($attempt < $webhook->retry_attempts) {
                    sleep(pow(2, $attempt)); // Exponential backoff
                }
            } catch (\Exception $e) {
                $this->logWebhook($webhook, $event, $fullPayload, null, null, $e->getMessage(), $attempt);

                if ($attempt < $webhook->retry_attempts) {
                    sleep(pow(2, $attempt));
                }
            }
        }
    }

    /**
     * Generate HMAC signature
     */
    protected function generateSignature(array $payload, ?string $secret): string
    {
        if (!$secret) {
            return '';
        }

        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Log webhook attempt
     */
    protected function logWebhook(
        Webhook $webhook,
        string $event,
        array $payload,
        ?int $statusCode,
        ?string $response,
        ?string $error,
        int $attempt
    ): void {
        WebhookLog::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $payload,
            'status_code' => $statusCode,
            'response' => $response,
            'error' => $error,
            'attempt' => $attempt,
        ]);
    }
}
```

**4. Integrate with Events**

```php
<?php

namespace Masum\AiTranslator\Listeners;

use Masum\AiTranslator\Services\WebhookService;

class TriggerWebhooks
{
    public function __construct(
        protected WebhookService $webhookService
    ) {}

    public function handle($event): void
    {
        $eventName = class_basename($event);
        $eventName = 'translation.' . Str::snake($eventName);

        $payload = $this->getPayload($event);

        $this->webhookService->trigger($eventName, $payload);
    }

    protected function getPayload($event): array
    {
        if (property_exists($event, 'translation')) {
            return [
                'translation' => [
                    'id' => $event->translation->id,
                    'key' => $event->translation->key,
                    'value' => $event->translation->value,
                    'language' => $event->translation->language->code,
                    'group' => $event->translation->group,
                ],
            ];
        }

        return [];
    }
}
```

#### Acceptance Criteria
- [ ] Webhooks can be registered
- [ ] Webhooks trigger on events
- [ ] HMAC signature validation works
- [ ] Retry logic with exponential backoff
- [ ] Webhook logs capture all attempts
- [ ] Tests achieve 80%+ coverage

---

### P3-T08-S04: Real-time Updates (Broadcasting)

**Estimated Time:** 2-3 hours
**Priority:** P4
**Dependencies:** P3-T08-S01

#### Description
Add real-time broadcasting for translation updates.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationUpdatedBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Translation $translation
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('translations.' . $this->translation->language->code);
    }

    public function broadcastAs(): string
    {
        return 'translation.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->translation->id,
            'key' => $this->translation->key,
            'value' => $this->translation->value,
            'language' => $this->translation->language->code,
        ];
    }
}
```

#### Acceptance Criteria
- [ ] Broadcasting works with Pusher/Ably
- [ ] Events broadcast to correct channels
- [ ] Client can subscribe to updates
- [ ] Real-time updates in UI

---

### P3-T08-S05: Event Subscribers

**Estimated Time:** 1-2 hours
**Priority:** P3
**Dependencies:** P3-T08-S01

#### Description
Create event subscribers for grouped event handling.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Listeners;

use Illuminate\Events\Dispatcher;

class TranslationEventSubscriber
{
    public function handleCreated($event): void
    {
        // Handle translation created
    }

    public function handleUpdated($event): void
    {
        // Handle translation updated
    }

    public function handleDeleted($event): void
    {
        // Handle translation deleted
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            'Masum\AiTranslator\Events\TranslationCreated' => 'handleCreated',
            'Masum\AiTranslator\Events\TranslationUpdated' => 'handleUpdated',
            'Masum\AiTranslator\Events\TranslationDeleted' => 'handleDeleted',
        ];
    }
}
```

#### Acceptance Criteria
- [ ] Subscribers handle multiple events
- [ ] Subscribers registered properly
- [ ] Clean organization of event logic

---

## Definition of Done

- [ ] All 5 subtasks completed
- [ ] All acceptance criteria met
- [ ] Events fire correctly
- [ ] Notifications sent properly
- [ ] Webhooks work reliably
- [ ] Tests achieve 80%+ coverage
- [ ] Documentation updated

---

## Notes

- Consider rate limiting for webhooks
- Add webhook security best practices
- Document event payload structures
- Consider event versioning for BC
