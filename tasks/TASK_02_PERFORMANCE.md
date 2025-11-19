# TASK 02: Performance Optimizations

**Phase:** 1 - Foundation & Security
**Priority:** P2 - High
**Estimated Time:** 30-40 hours
**Dependencies:** TASK_01 (Authorization)
**Complexity:** Medium-High

---

## Overview

Optimize database queries, caching strategies, and implement queue system for better performance and scalability.

---

## Tasks

### P2-T02-S01: Add Database Indexes ⭐⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 3-4 hours
**Assigned To:** -

#### Context

Missing indexes on frequently queried columns cause slow queries. Add composite and single-column indexes.

#### Implementation

```php
// database/migrations/xxxx_add_indexes_to_translations.php
public function up(): void
{
    Schema::table('translations', function (Blueprint $table) {
        // Composite indexes for common queries
        $table->index(['language_id', 'group'], 'translations_lang_group_idx');
        $table->index(['language_id', 'key'], 'translations_lang_key_idx');
        $table->index(['language_id', 'is_active'], 'translations_lang_active_idx');

        // Single column indexes
        $table->index('key', 'translations_key_idx');
        $table->index('group', 'translations_group_idx');
        $table->index('is_active', 'translations_active_idx');
        $table->index('is_auto_translated', 'translations_auto_translated_idx');

        // Full-text index for search
        $table->fullText(['key', 'value'], 'translations_search_idx');
    });

    Schema::table('languages', function (Blueprint $table) {
        $table->index('is_active', 'languages_active_idx');
        $table->index('is_default', 'languages_default_idx');
        $table->index(['is_active', 'is_default'], 'languages_active_default_idx');
    });

    Schema::table('translation_histories', function (Blueprint $table) {
        $table->index('translation_id', 'histories_translation_idx');
        $table->index('changed_by_user_id', 'histories_user_idx');
        $table->index('created_at', 'histories_created_idx');
    });
}
```

**Acceptance Criteria:**
- [ ] All frequently queried columns indexed
- [ ] Composite indexes for multi-column queries
- [ ] Full-text search index added
- [ ] Query performance tested before/after
- [ ] No duplicate indexes

---

### P2-T02-S02: Optimize Cache Strategy ⭐⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 8-10 hours
**Assigned To:** -

#### Context

Current caching is basic. Implement cache tagging, better invalidation, and multi-tier caching.

#### Implementation

```php
// src/Services/CacheService.php
namespace Masum\AiTranslator\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    protected int $ttl;
    protected string $prefix;
    protected bool $enabled;

    public function __construct()
    {
        $this->ttl = config('ai-translator.translation.cache_ttl', 3600);
        $this->prefix = config('ai-translator.translation.cache_prefix', 'ai_translator');
        $this->enabled = config('ai-translator.translation.cache_enabled', true);
    }

    public function remember(string $key, string $locale, ?string $group, callable $callback)
    {
        if (!$this->enabled) {
            return $callback();
        }

        $cacheKey = $this->getCacheKey($key, $locale, $group);
        $tags = $this->getTags($locale, $group);

        return Cache::tags($tags)->remember($cacheKey, $this->ttl, $callback);
    }

    public function forget(string $key, string $locale, ?string $group): void
    {
        $cacheKey = $this->getCacheKey($key, $locale, $group);
        $tags = $this->getTags($locale, $group);

        Cache::tags($tags)->forget($cacheKey);
    }

    public function forgetByLanguage(string $locale): void
    {
        Cache::tags(["locale:{$locale}"])->flush();
    }

    public function forgetByGroup(string $group): void
    {
        Cache::tags(["group:{$group}"])->flush();
    }

    public function flushAll(): void
    {
        Cache::tags(['translations'])->flush();
    }

    protected function getCacheKey(string $key, string $locale, ?string $group): string
    {
        $parts = [$this->prefix];

        if ($group) {
            $parts[] = $group;
        }

        $parts[] = $key;
        $parts[] = $locale;

        return implode('.', $parts);
    }

    protected function getTags(string $locale, ?string $group): array
    {
        $tags = ['translations', "locale:{$locale}"];

        if ($group) {
            $tags[] = "group:{$group}";
        }

        return $tags;
    }

    public function getStats(): array
    {
        return [
            'enabled' => $this->enabled,
            'ttl' => $this->ttl,
            'prefix' => $this->prefix,
            'driver' => config('cache.default'),
        ];
    }
}
```

**Update Translation Model:**

```php
// src/Models/Translation.php
protected static function boot()
{
    parent::boot();

    static::created(function ($translation) {
        app(CacheService::class)->forgetByLanguage($translation->language_code);
    });

    static::updated(function ($translation) {
        app(CacheService::class)->forget(
            $translation->key,
            $translation->language_code,
            $translation->group
        );
    });

    static::deleted(function ($translation) {
        app(CacheService::class)->forget(
            $translation->key,
            $translation->language_code,
            $translation->group
        );
    });
}
```

**Acceptance Criteria:**
- [ ] Cache tagging implemented
- [ ] Granular cache invalidation
- [ ] Language-level cache clearing
- [ ] Group-level cache clearing
- [ ] Cache statistics available
- [ ] Tests for all cache operations

---

### P2-T02-S03: Implement Queue System for AI Translation ⭐⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 12-15 hours
**Assigned To:** -

#### Context

Batch translations and auto-translations can be slow. Move to queue for better UX.

#### Implementation

```php
// src/Jobs/TranslateJob.php
namespace Masum\AiTranslator\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Masum\AiTranslator\Services\TranslationService;
use Masum\AiTranslator\Events\TranslationCompleted;
use Masum\AiTranslator\Events\TranslationFailed;

class TranslateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        protected string $key,
        protected string $value,
        protected string $sourceLang,
        protected array $targetLangs,
        protected ?string $group = null,
        protected ?int $userId = null
    ) {
        $this->onQueue(config('ai-translator.queue.name', 'translations'));
    }

    public function handle(TranslationService $service): void
    {
        try {
            $results = $service->autoTranslate(
                $this->key,
                $this->value,
                $this->sourceLang,
                $this->targetLangs,
                $this->group,
                $this->userId
            );

            event(new TranslationCompleted($this->key, $results, $this->userId));
        } catch (\Exception $e) {
            $this->fail($e);
            event(new TranslationFailed($this->key, $e->getMessage(), $this->userId));
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('Translation job failed', [
            'key' => $this->key,
            'source' => $this->sourceLang,
            'targets' => $this->targetLangs,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**Update Controller:**

```php
// src/Http/Controllers/TranslationController.php
public function autoTranslate(AutoTranslateRequest $request): JsonResponse
{
    $useQueue = config('ai-translator.queue.enabled', true)
        && !$request->boolean('sync');

    if ($useQueue) {
        $job = TranslateJob::dispatch(
            $request->input('key'),
            $request->input('value'),
            $request->input('source_language'),
            $request->input('target_languages'),
            $request->input('group'),
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Translation queued successfully.',
            'job_id' => $job->getJobId(),
            'status_url' => route('translator.job.status', $job->getJobId()),
        ], 202);
    }

    // Synchronous processing...
}
```

**Add Job Status Endpoint:**

```php
// src/Http/Controllers/JobController.php
namespace Masum\AiTranslator\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function status(string $jobId): JsonResponse
    {
        $job = \DB::table('jobs')->where('id', $jobId)->first();

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found or completed.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 'processing',
            'attempts' => $job->attempts,
            'created_at' => $job->created_at,
        ]);
    }
}
```

**Acceptance Criteria:**
- [ ] Queue system configured
- [ ] Jobs for auto-translate and batch-translate
- [ ] Job status endpoint
- [ ] Event system for completion/failure
- [ ] Retry logic with exponential backoff
- [ ] Queue monitoring dashboard (optional)

---

### P2-T02-S04: Optimize Eager Loading ⭐⭐

**Status:** 🔴 Not Started
**Time Estimate:** 4-5 hours
**Assigned To:** -

#### Context

N+1 queries in relationships. Add eager loading to controllers.

#### Implementation

```php
// src/Http/Controllers/TranslationController.php
public function index(Request $request): JsonResponse
{
    $query = Translation::with(['language:id,code,name', 'createdBy:id,name']);

    // Apply filters...

    $translations = $query->paginate(15);

    return response()->json([
        'success' => true,
        'data' => TranslationResource::collection($translations),
        'pagination' => [
            'current_page' => $translations->currentPage(),
            'per_page' => $translations->perPage(),
            'total' => $translations->total(),
            'last_page' => $translations->lastPage(),
        ],
    ]);
}
```

**Add Relationship Counting:**

```php
// For statistics
$languagesWithCounts = Language::withCount([
    'translations',
    'translations as active_translations_count' => function ($query) {
        $query->where('is_active', true);
    }
])->get();
```

**Acceptance Criteria:**
- [ ] All list endpoints use eager loading
- [ ] No N+1 queries detected
- [ ] Query count reduced by 80%+
- [ ] Relationship counting where needed

---

### P2-T02-S05: Add Query Scopes ⭐

**Status:** 🔴 Not Started
**Time Estimate:** 3-4 hours
**Assigned To:** -

#### Context

Reusable query scopes for common filters.

#### Implementation

```php
// src/Models/Translation.php
public function scopeActive($query)
{
    return $query->where('is_active', true);
}

public function scopeForLanguage($query, string $code)
{
    return $query->whereHas('language', function ($q) use ($code) {
        $q->where('code', $code);
    });
}

public function scopeInGroup($query, string $group)
{
    return $query->where('group', $group);
}

public function scopeAutoTranslated($query)
{
    return $query->where('is_auto_translated', true);
}

public function scopeSearch($query, string $search)
{
    return $query->where(function ($q) use ($search) {
        $q->where('key', 'like', "%{$search}%")
          ->orWhere('value', 'like', "%{$search}%");
    });
}

// Usage in controller
$translations = Translation::active()
    ->forLanguage('en')
    ->inGroup('home')
    ->search($request->input('q'))
    ->paginate(15);
```

**Acceptance Criteria:**
- [ ] Scopes for common filters
- [ ] Chainable scopes
- [ ] Consistent naming convention
- [ ] Documentation for each scope

---

## Summary

**Total Subtasks:** 5
**Estimated Time:** 30-40 hours
**Priority:** P2 - High

**Completion Checklist:**
- [ ] All database indexes added
- [ ] Cache tagging implemented
- [ ] Queue system working
- [ ] Eager loading optimized
- [ ] Query scopes added
- [ ] Performance benchmarks improved
- [ ] Documentation updated
