# TASK 10: Analytics & Reporting

**Priority:** P3 (Medium)
**Total Estimated Time:** 12-16 hours
**Dependencies:** TASK_07 (Database), TASK_08 (Events)
**Status:** ⏳ Pending

---

## Overview

Implement comprehensive analytics and reporting system for translation usage, performance metrics, translator productivity, and system health monitoring.

---

## Subtasks

### P3-T10-S01: Translation Usage Analytics

**Estimated Time:** 4-5 hours
**Priority:** P3
**Dependencies:** None

#### Description
Track and analyze translation usage patterns, popular translations, and access frequency.

#### Implementation

**1. Create Analytics Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('translation_id')->constrained()->onDelete('cascade');
            $table->string('source')->nullable(); // 'cache', 'database', 'ai'
            $table->integer('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->date('date');
            $table->timestamps();

            // Indexes
            $table->index(['translation_id', 'date']);
            $table->index('date');
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_analytics');
    }
};
```

**2. Analytics Service**

```php
<?php

namespace Masum\AiTranslator\Services;

use Masum\AiTranslator\Models\Translation;
use Masum\AiTranslator\Models\TranslationAnalytics;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TranslationAnalyticsService
{
    /**
     * Track translation access
     */
    public function track(Translation $translation, string $source = 'database'): void
    {
        if (!config('ai-translator.analytics.enabled', true)) {
            return;
        }

        $today = Carbon::today();

        TranslationAnalytics::updateOrCreate(
            [
                'translation_id' => $translation->id,
                'date' => $today,
            ],
            [
                'source' => $source,
                'access_count' => DB::raw('access_count + 1'),
                'last_accessed_at' => now(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'url' => request()->url(),
            ]
        );
    }

    /**
     * Get most accessed translations
     */
    public function getMostAccessed(int $limit = 10, ?string $languageCode = null, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $query = TranslationAnalytics::with('translation.language')
            ->select('translation_id', DB::raw('SUM(access_count) as total_accesses'))
            ->groupBy('translation_id')
            ->orderByDesc('total_accesses')
            ->limit($limit);

        if ($languageCode) {
            $query->whereHas('translation.language', fn($q) => $q->where('code', $languageCode));
        }

        if ($from) {
            $query->where('date', '>=', $from);
        }

        if ($to) {
            $query->where('date', '<=', $to);
        }

        return $query->get();
    }

    /**
     * Get access statistics by source
     */
    public function getAccessBySource(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = TranslationAnalytics::select('source', DB::raw('SUM(access_count) as total'))
            ->groupBy('source');

        if ($from) {
            $query->where('date', '>=', $from);
        }

        if ($to) {
            $query->where('date', '<=', $to);
        }

        $results = $query->get();

        $total = $results->sum('total');

        return [
            'total' => $total,
            'by_source' => $results->mapWithKeys(function ($item) use ($total) {
                return [
                    $item->source => [
                        'count' => $item->total,
                        'percentage' => $total > 0 ? round(($item->total / $total) * 100, 2) : 0,
                    ],
                ];
            })->toArray(),
        ];
    }

    /**
     * Get daily access trend
     */
    public function getDailyTrend(int $days = 30, ?string $languageCode = null): Collection
    {
        $from = Carbon::today()->subDays($days);

        $query = TranslationAnalytics::select(
                'date',
                DB::raw('SUM(access_count) as total_accesses'),
                DB::raw('COUNT(DISTINCT translation_id) as unique_translations')
            )
            ->where('date', '>=', $from)
            ->groupBy('date')
            ->orderBy('date');

        if ($languageCode) {
            $query->whereHas('translation.language', fn($q) => $q->where('code', $languageCode));
        }

        return $query->get();
    }

    /**
     * Get language usage statistics
     */
    public function getLanguageStats(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $query = TranslationAnalytics::join('translations', 'translation_analytics.translation_id', '=', 'translations.id')
            ->join('languages', 'translations.language_id', '=', 'languages.id')
            ->select(
                'languages.code',
                'languages.name',
                DB::raw('SUM(translation_analytics.access_count) as total_accesses'),
                DB::raw('COUNT(DISTINCT translation_analytics.translation_id) as unique_translations')
            )
            ->groupBy('languages.id', 'languages.code', 'languages.name')
            ->orderByDesc('total_accesses');

        if ($from) {
            $query->where('translation_analytics.date', '>=', $from);
        }

        if ($to) {
            $query->where('translation_analytics.date', '<=', $to);
        }

        return $query->get();
    }
}
```

**3. Add to Translation Service**

```php
// Modify TranslationService::get() method

public function get(string $key, string $languageCode, array $replace = []): string
{
    $cacheKey = $this->getCacheKey($key, $languageCode);

    // Try cache first
    if (Cache::has($cacheKey)) {
        $value = Cache::get($cacheKey);
        $this->trackAccess($key, $languageCode, 'cache');
        return $this->replace($value, $replace);
    }

    // Try database
    $translation = Translation::where('key', $key)
        ->whereHas('language', fn($q) => $q->where('code', $languageCode))
        ->first();

    if ($translation) {
        Cache::put($cacheKey, $translation->value, $this->cacheTtl);
        $this->trackAccess($key, $languageCode, 'database');
        return $this->replace($translation->value, $replace);
    }

    // Fallback to AI or default
    $value = $this->getFromAiOrDefault($key, $languageCode);
    $this->trackAccess($key, $languageCode, 'ai');

    return $this->replace($value, $replace);
}

protected function trackAccess(string $key, string $languageCode, string $source): void
{
    if (!config('ai-translator.analytics.enabled', true)) {
        return;
    }

    $translation = Translation::where('key', $key)
        ->whereHas('language', fn($q) => $q->where('code', $languageCode))
        ->first();

    if ($translation) {
        app(TranslationAnalyticsService::class)->track($translation, $source);
    }
}
```

**4. API Endpoints**

```php
// Add to routes/api.php

Route::prefix('analytics')->group(function () {
    Route::get('most-accessed', [AnalyticsController::class, 'mostAccessed']);
    Route::get('by-source', [AnalyticsController::class, 'bySource']);
    Route::get('daily-trend', [AnalyticsController::class, 'dailyTrend']);
    Route::get('language-stats', [AnalyticsController::class, 'languageStats']);
    Route::get('dashboard', [AnalyticsController::class, 'dashboard']);
});
```

**5. Analytics Controller**

```php
<?php

namespace Masum\AiTranslator\Http\Controllers;

use Masum\AiTranslator\Services\TranslationAnalyticsService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TranslationAnalyticsService $analytics
    ) {}

    public function mostAccessed(Request $request)
    {
        $this->authorize('view-analytics');

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'language' => 'nullable|string|exists:languages,code',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $data = $this->analytics->getMostAccessed(
            $validated['limit'] ?? 10,
            $validated['language'] ?? null,
            isset($validated['from']) ? Carbon::parse($validated['from']) : null,
            isset($validated['to']) ? Carbon::parse($validated['to']) : null
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function dashboard(Request $request)
    {
        $this->authorize('view-analytics');

        $from = Carbon::today()->subDays(30);
        $to = Carbon::today();

        return response()->json([
            'success' => true,
            'data' => [
                'most_accessed' => $this->analytics->getMostAccessed(10, null, $from, $to),
                'by_source' => $this->analytics->getAccessBySource($from, $to),
                'daily_trend' => $this->analytics->getDailyTrend(30),
                'language_stats' => $this->analytics->getLanguageStats($from, $to),
            ],
        ]);
    }
}
```

#### Testing

```php
test('tracks translation access', function () {
    $translation = createTranslation();

    $service = app(TranslationAnalyticsService::class);
    $service->track($translation, 'cache');

    $this->assertDatabaseHas('translation_analytics', [
        'translation_id' => $translation->id,
        'source' => 'cache',
        'access_count' => 1,
    ]);
});

test('gets most accessed translations', function () {
    $translation = createTranslation();

    TranslationAnalytics::create([
        'translation_id' => $translation->id,
        'source' => 'cache',
        'access_count' => 100,
        'date' => today(),
    ]);

    $service = app(TranslationAnalyticsService::class);
    $most = $service->getMostAccessed(10);

    expect($most->first()->translation_id)->toBe($translation->id);
});
```

#### Acceptance Criteria
- [ ] Tracks translation access correctly
- [ ] Records source (cache/database/AI)
- [ ] Provides most accessed translations
- [ ] Shows access trends over time
- [ ] Language usage statistics available
- [ ] API endpoints work correctly
- [ ] Tests achieve 85%+ coverage

---

### P3-T10-S02: Performance Metrics

**Estimated Time:** 3-4 hours
**Priority:** P3
**Dependencies:** P3-T10-S01

#### Description
Track and report performance metrics for translation system.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceMetricsService
{
    /**
     * Get cache performance metrics
     */
    public function getCacheMetrics(int $days = 7): array
    {
        $from = Carbon::today()->subDays($days);

        $stats = TranslationAnalytics::where('date', '>=', $from)
            ->select('source', DB::raw('SUM(access_count) as count'))
            ->groupBy('source')
            ->get()
            ->keyBy('source');

        $total = $stats->sum('count');
        $cacheHits = $stats->get('cache')?->count ?? 0;
        $dbHits = $stats->get('database')?->count ?? 0;
        $aiHits = $stats->get('ai')?->count ?? 0;

        return [
            'total_requests' => $total,
            'cache_hits' => $cacheHits,
            'cache_hit_rate' => $total > 0 ? round(($cacheHits / $total) * 100, 2) : 0,
            'database_hits' => $dbHits,
            'ai_hits' => $aiHits,
            'breakdown' => [
                'cache' => ['count' => $cacheHits, 'percentage' => $total > 0 ? round(($cacheHits / $total) * 100, 2) : 0],
                'database' => ['count' => $dbHits, 'percentage' => $total > 0 ? round(($dbHits / $total) * 100, 2) : 0],
                'ai' => ['count' => $aiHits, 'percentage' => $total > 0 ? round(($aiHits / $total) * 100, 2) : 0],
            ],
        ];
    }

    /**
     * Get average response times
     */
    public function getResponseTimes(): array
    {
        // This would require additional instrumentation
        // For now, return estimated values based on source
        return [
            'cache' => ['avg' => 5, 'unit' => 'ms'],
            'database' => ['avg' => 20, 'unit' => 'ms'],
            'ai' => ['avg' => 500, 'unit' => 'ms'],
        ];
    }

    /**
     * Get database performance metrics
     */
    public function getDatabaseMetrics(): array
    {
        return [
            'total_translations' => Translation::count(),
            'total_languages' => Language::count(),
            'total_groups' => Translation::distinct('group')->count(),
            'database_size' => $this->getDatabaseSize(),
            'index_usage' => $this->getIndexUsage(),
        ];
    }

    protected function getDatabaseSize(): array
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $tables = ['languages', 'translations', 'translation_history', 'translation_analytics'];
            $totalSize = 0;

            foreach ($tables as $table) {
                $result = DB::select("SELECT
                    (data_length + index_length) as size
                    FROM information_schema.TABLES
                    WHERE table_schema = DATABASE()
                    AND table_name = '{$table}'");

                $totalSize += $result[0]->size ?? 0;
            }

            return [
                'bytes' => $totalSize,
                'formatted' => $this->formatBytes($totalSize),
            ];
        }

        return ['bytes' => 0, 'formatted' => '0 B'];
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

#### Acceptance Criteria
- [ ] Cache hit rate calculated
- [ ] Response time metrics available
- [ ] Database size tracked
- [ ] Performance trends visible
- [ ] API endpoints for metrics

---

### P3-T10-S03: Translator Productivity Dashboard

**Estimated Time:** 3-4 hours
**Priority:** P3
**Dependencies:** None

#### Description
Track and display translator productivity metrics.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Services;

class TranslatorProductivityService
{
    /**
     * Get productivity metrics for translator
     */
    public function getProductivity($userId, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from = $from ?? Carbon::today()->subDays(30);
        $to = $to ?? Carbon::today();

        $translationsCreated = Translation::where('created_by', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $translationsUpdated = TranslationHistory::where('user_id', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->distinct('translation_id')
            ->count();

        $languagesWorked = Translation::where('created_by', $userId)
            ->whereBetween('created_at', [$from, $to])
            ->distinct('language_id')
            ->count();

        $avgPerDay = $from->diffInDays($to) > 0
            ? round($translationsCreated / $from->diffInDays($to), 2)
            : 0;

        return [
            'translations_created' => $translationsCreated,
            'translations_updated' => $translationsUpdated,
            'languages_worked' => $languagesWorked,
            'avg_per_day' => $avgPerDay,
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $from->diffInDays($to),
            ],
        ];
    }

    /**
     * Get leaderboard
     */
    public function getLeaderboard(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from = $from ?? Carbon::today()->subDays(30);
        $to = $to ?? Carbon::today();

        return Translation::select('created_by', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('created_by')
            ->groupBy('created_by')
            ->orderByDesc('count')
            ->limit($limit)
            ->with('creator:id,name,email')
            ->get();
    }
}
```

#### Acceptance Criteria
- [ ] Tracks translations per translator
- [ ] Shows productivity trends
- [ ] Leaderboard available
- [ ] Supports date ranges
- [ ] API endpoints work

---

### P3-T10-S04: Export Reports

**Estimated Time:** 2-3 hours
**Priority:** P3
**Dependencies:** All previous subtasks

#### Description
Generate and export analytics reports in various formats.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Services;

use League\Csv\Writer;

class ReportExportService
{
    /**
     * Export analytics report to CSV
     */
    public function exportToCSV(array $data, string $type): string
    {
        $csv = Writer::createFromString();

        // Add headers based on report type
        $headers = match($type) {
            'usage' => ['Translation Key', 'Language', 'Access Count', 'Last Accessed'],
            'performance' => ['Date', 'Cache Hits', 'DB Hits', 'AI Hits', 'Total'],
            'productivity' => ['Translator', 'Created', 'Updated', 'Languages'],
            default => ['Data'],
        };

        $csv->insertOne($headers);
        $csv->insertAll($data);

        return $csv->toString();
    }

    /**
     * Export to PDF
     */
    public function exportToPDF(array $data, string $type): string
    {
        // Implementation using dompdf or similar
        // Return PDF content
    }
}
```

#### Acceptance Criteria
- [ ] Can export to CSV
- [ ] Can export to PDF
- [ ] All report types supported
- [ ] Proper formatting
- [ ] Download endpoints work

---

## Definition of Done

- [ ] All 4 subtasks completed
- [ ] All acceptance criteria met
- [ ] Analytics tracking works
- [ ] Performance metrics accurate
- [ ] Reports can be exported
- [ ] Dashboard API functional
- [ ] Tests achieve 80%+ coverage
- [ ] Documentation complete

---

## Notes

- Consider data retention policies
- Add scheduled cleanup of old analytics
- Consider aggregating data for better performance
- Add caching for frequently accessed reports
