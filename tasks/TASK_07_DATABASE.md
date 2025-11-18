# TASK 07: Database Optimization

**Priority:** P2 (High)
**Total Estimated Time:** 15-20 hours
**Dependencies:** TASK_02 (Performance), TASK_03 (Testing)
**Status:** ⏳ Pending

---

## Overview

Optimize database structure, add proper indexes, implement database-level features, and create migration utilities for better performance and maintainability.

---

## Subtasks

### P2-T07-S01: Database Indexes Optimization

**Estimated Time:** 4-6 hours
**Priority:** P1
**Dependencies:** None

#### Description
Add comprehensive database indexes for improved query performance.

#### Implementation

**1. Create Index Migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Languages table indexes
        Schema::table('languages', function (Blueprint $table) {
            // Already has unique index on 'code' from initial migration
            $table->index('is_active'); // For filtering active languages
            $table->index('is_default'); // For finding default language
            $table->index(['is_active', 'code']); // Composite for active language lookups
        });

        // Translations table indexes
        Schema::table('translations', function (Blueprint $table) {
            // Composite index for most common query pattern
            $table->index(['language_id', 'key']); // Main lookup pattern
            $table->index(['language_id', 'group']); // Group filtering
            $table->index(['language_id', 'group', 'key']); // Full composite
            $table->index('key'); // Key lookup across languages
            $table->index('group'); // Group-based queries
            $table->index('created_at'); // Temporal queries
            $table->index('updated_at'); // Recently updated
            $table->index(['deleted_at']); // Soft deletes (if using)

            // Full-text search (MySQL/PostgreSQL)
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE translations ADD FULLTEXT fulltext_search(key, value)');
            }
        });

        // Translation history table indexes
        Schema::table('translation_history', function (Blueprint $table) {
            $table->index('translation_id'); // For history lookups
            $table->index(['translation_id', 'created_at']); // Chronological history
            $table->index('user_id'); // User activity tracking
            $table->index('created_at'); // Temporal analysis
        });

        // Settings table indexes
        Schema::table('translator_settings', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['is_default']);
            $table->dropIndex(['is_active', 'code']);
        });

        Schema::table('translations', function (Blueprint $table) {
            $table->dropIndex(['language_id', 'key']);
            $table->dropIndex(['language_id', 'group']);
            $table->dropIndex(['language_id', 'group', 'key']);
            $table->dropIndex(['key']);
            $table->dropIndex(['group']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['updated_at']);
            $table->dropIndex(['deleted_at']);

            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE translations DROP INDEX fulltext_search');
            }
        });

        Schema::table('translation_history', function (Blueprint $table) {
            $table->dropIndex(['translation_id']);
            $table->dropIndex(['translation_id', 'created_at']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('translator_settings', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['updated_at']);
        });
    }
};
```

**2. Add Index Analysis Command**

```php
<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AnalyzeIndexesCommand extends Command
{
    protected $signature = 'translator:analyze-indexes';

    protected $description = 'Analyze database indexes and query performance';

    public function handle(): int
    {
        $this->info('📊 Analyzing Database Indexes...');
        $this->newLine();

        $tables = ['languages', 'translations', 'translation_history', 'translator_settings'];

        foreach ($tables as $table) {
            $this->analyzeTable($table);
            $this->newLine();
        }

        $this->info('✓ Analysis complete');

        return Command::SUCCESS;
    }

    protected function analyzeTable(string $table): void
    {
        $this->info("Table: {$table}");

        // Get table statistics
        $stats = DB::select("SHOW TABLE STATUS LIKE '{$table}'");

        if (!empty($stats)) {
            $stat = $stats[0];
            $this->line("  Rows: " . number_format($stat->Rows));
            $this->line("  Data Size: " . $this->formatBytes($stat->Data_length));
            $this->line("  Index Size: " . $this->formatBytes($stat->Index_length));
        }

        // Get indexes
        $indexes = DB::select("SHOW INDEXES FROM {$table}");

        $this->line("  Indexes:");
        foreach ($indexes as $index) {
            $this->line("    - {$index->Key_name} on {$index->Column_name}");
        }
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

#### Testing

```php
test('can analyze database indexes', function () {
    $this->artisan('translator:analyze-indexes')
        ->expectsOutput('📊 Analyzing Database Indexes...')
        ->assertExitCode(0);
});

test('queries use indexes efficiently', function () {
    // Enable query logging
    DB::enableQueryLog();

    $language = createLanguage(['code' => 'en']);
    createTranslation(['key' => 'test', 'language_id' => $language->id]);

    // Perform common query
    Translation::where('language_id', $language->id)
        ->where('key', 'test')
        ->first();

    $queries = DB::getQueryLog();

    // Verify query uses index
    expect($queries)->toHaveCount(1);
    // Note: Actual index usage would be verified with EXPLAIN in production
});
```

#### Acceptance Criteria
- [ ] All recommended indexes created
- [ ] No missing indexes for common queries
- [ ] Full-text search index added (MySQL)
- [ ] Index analysis command works
- [ ] Query performance improved by 50%+
- [ ] No over-indexing (too many indexes)

---

### P2-T07-S02: Database Partitioning

**Estimated Time:** 4-6 hours
**Priority:** P3
**Dependencies:** P2-T07-S01

#### Description
Implement table partitioning for large translation datasets.

#### Implementation

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only for MySQL 5.7+
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        // Partition translation_history by year
        DB::statement("
            ALTER TABLE translation_history
            PARTITION BY RANGE (YEAR(created_at)) (
                PARTITION p2023 VALUES LESS THAN (2024),
                PARTITION p2024 VALUES LESS THAN (2025),
                PARTITION p2025 VALUES LESS THAN (2026),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE translation_history REMOVE PARTITIONING");
    }
};
```

#### Acceptance Criteria
- [ ] History table partitioned by year
- [ ] Partition maintenance command created
- [ ] Old partition cleanup automated
- [ ] Performance tested with 100k+ records

---

### P2-T07-S03: Database Views

**Estimated Time:** 3-4 hours
**Priority:** P3
**Dependencies:** None

#### Description
Create database views for common queries and reporting.

#### Implementation

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // View for translation completion status
        DB::statement("
            CREATE OR REPLACE VIEW translation_completion_view AS
            SELECT
                l.id as language_id,
                l.code as language_code,
                l.name as language_name,
                COUNT(t.id) as total_translations,
                COUNT(CASE WHEN t.value IS NOT NULL AND t.value != '' THEN 1 END) as completed_translations,
                ROUND((COUNT(CASE WHEN t.value IS NOT NULL AND t.value != '' THEN 1 END) / COUNT(t.id)) * 100, 2) as completion_percentage
            FROM languages l
            LEFT JOIN translations t ON l.id = t.language_id
            WHERE l.is_active = 1
            GROUP BY l.id, l.code, l.name
        ");

        // View for missing translations
        DB::statement("
            CREATE OR REPLACE VIEW missing_translations_view AS
            SELECT
                l.code as language_code,
                l.name as language_name,
                dt.key as translation_key,
                dt.value as source_value,
                dt.group as translation_group
            FROM languages l
            CROSS JOIN (
                SELECT DISTINCT key, value, `group`
                FROM translations
                WHERE language_id = (SELECT id FROM languages WHERE is_default = 1 LIMIT 1)
            ) dt
            LEFT JOIN translations t ON l.id = t.language_id AND dt.key = t.key
            WHERE l.is_active = 1
                AND l.is_default = 0
                AND t.id IS NULL
        ");

        // View for translation activity
        DB::statement("
            CREATE OR REPLACE VIEW translation_activity_view AS
            SELECT
                DATE(th.created_at) as activity_date,
                l.code as language_code,
                COUNT(DISTINCT th.translation_id) as translations_modified,
                COUNT(th.id) as total_changes,
                COUNT(DISTINCT th.user_id) as active_users
            FROM translation_history th
            JOIN translations t ON th.translation_id = t.id
            JOIN languages l ON t.language_id = l.id
            GROUP BY DATE(th.created_at), l.code
            ORDER BY activity_date DESC
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS translation_completion_view");
        DB::statement("DROP VIEW IF EXISTS missing_translations_view");
        DB::statement("DROP VIEW IF EXISTS translation_activity_view");
    }
};
```

**2. Create View Models**

```php
<?php

namespace Masum\AiTranslator\Models\Views;

use Illuminate\Database\Eloquent\Model;

class TranslationCompletionView extends Model
{
    protected $table = 'translation_completion_view';
    public $timestamps = false;

    protected $casts = [
        'total_translations' => 'integer',
        'completed_translations' => 'integer',
        'completion_percentage' => 'decimal:2',
    ];
}

class MissingTranslationsView extends Model
{
    protected $table = 'missing_translations_view';
    public $timestamps = false;
}

class TranslationActivityView extends Model
{
    protected $table = 'translation_activity_view';
    public $timestamps = false;

    protected $casts = [
        'activity_date' => 'date',
        'translations_modified' => 'integer',
        'total_changes' => 'integer',
        'active_users' => 'integer',
    ];
}
```

#### Acceptance Criteria
- [ ] Completion status view created
- [ ] Missing translations view created
- [ ] Activity tracking view created
- [ ] View models work correctly
- [ ] Views perform well with large datasets

---

### P2-T07-S04: Database Maintenance Commands

**Estimated Time:** 2-3 hours
**Priority:** P2
**Dependencies:** None

#### Description
Create commands for database cleanup and maintenance.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;
use Masum\AiTranslator\Models\Translation;
use Carbon\Carbon;

class CleanupDatabaseCommand extends Command
{
    protected $signature = 'translator:cleanup
                          {--days=90 : Number of days to keep soft-deleted records}
                          {--history-days=365 : Number of days to keep history}
                          {--force : Skip confirmation}';

    protected $description = 'Clean up old translation data';

    public function handle(): int
    {
        $days = $this->option('days');
        $historyDays = $this->option('history-days');

        if (!$this->option('force')) {
            if (!$this->confirm('This will permanently delete old data. Continue?')) {
                return Command::FAILURE;
            }
        }

        // Clean up soft-deleted translations
        $deletedCount = Translation::onlyTrashed()
            ->where('deleted_at', '<', Carbon::now()->subDays($days))
            ->forceDelete();

        $this->info("✓ Permanently deleted {$deletedCount} soft-deleted translations");

        // Clean up old history
        $historyCount = DB::table('translation_history')
            ->where('created_at', '<', Carbon::now()->subDays($historyDays))
            ->delete();

        $this->info("✓ Deleted {$historyCount} old history records");

        // Optimize tables
        $this->info('Optimizing database tables...');
        DB::statement('OPTIMIZE TABLE languages, translations, translation_history, translator_settings');
        $this->info('✓ Tables optimized');

        return Command::SUCCESS;
    }
}
```

#### Acceptance Criteria
- [ ] Cleanup command removes old data
- [ ] Configurable retention periods
- [ ] Table optimization included
- [ ] Safe with confirmation prompt
- [ ] Logs cleanup statistics

---

### P2-T07-S05: Database Seeding & Fixtures

**Estimated Time:** 2-3 hours
**Priority:** P3
**Dependencies:** TASK_03

#### Description
Create comprehensive seeders for development and testing.

#### Implementation

```php
<?php

namespace Masum\AiTranslator\Database\Seeders;

use Illuminate\Database\Seeder;
use Masum\AiTranslator\Models\Language;
use Masum\AiTranslator\Models\Translation;

class TranslatorDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LanguageSeeder::class,
            TranslationSeeder::class,
            SettingSeeder::class,
        ]);
    }
}

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'direction' => 'ltr', 'is_default' => true],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español', 'direction' => 'ltr'],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr'],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch', 'direction' => 'ltr'],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'direction' => 'rtl'],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文', 'direction' => 'ltr'],
            ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語', 'direction' => 'ltr'],
            ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский', 'direction' => 'ltr'],
        ];

        foreach ($languages as $language) {
            Language::firstOrCreate(
                ['code' => $language['code']],
                $language
            );
        }
    }
}

class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $english = Language::where('code', 'en')->first();

        $commonTranslations = [
            // Authentication
            ['key' => 'auth.login', 'value' => 'Login', 'group' => 'auth'],
            ['key' => 'auth.register', 'value' => 'Register', 'group' => 'auth'],
            ['key' => 'auth.logout', 'value' => 'Logout', 'group' => 'auth'],
            ['key' => 'auth.forgot_password', 'value' => 'Forgot Password?', 'group' => 'auth'],

            // Common UI
            ['key' => 'common.save', 'value' => 'Save', 'group' => 'common'],
            ['key' => 'common.cancel', 'value' => 'Cancel', 'group' => 'common'],
            ['key' => 'common.delete', 'value' => 'Delete', 'group' => 'common'],
            ['key' => 'common.edit', 'value' => 'Edit', 'group' => 'common'],
            ['key' => 'common.create', 'value' => 'Create', 'group' => 'common'],

            // Validation
            ['key' => 'validation.required', 'value' => 'This field is required', 'group' => 'validation'],
            ['key' => 'validation.email', 'value' => 'Please enter a valid email', 'group' => 'validation'],
        ];

        foreach ($commonTranslations as $translation) {
            Translation::firstOrCreate(
                [
                    'key' => $translation['key'],
                    'language_id' => $english->id,
                ],
                $translation
            );
        }
    }
}
```

**Command to Run Seeder**

```php
<?php

namespace Masum\AiTranslator\Console\Commands;

use Illuminate\Console\Command;

class SeedTranslationsCommand extends Command
{
    protected $signature = 'translator:seed {--fresh : Clear existing data first}';

    protected $description = 'Seed translation database with sample data';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->call('translator:cleanup', ['--force' => true]);
        }

        $this->call('db:seed', ['--class' => 'Masum\\AiTranslator\\Database\\Seeders\\TranslatorDatabaseSeeder']);

        $this->info('✓ Translation database seeded');

        return Command::SUCCESS;
    }
}
```

#### Acceptance Criteria
- [ ] Seeders create sample languages
- [ ] Seeders create sample translations
- [ ] Fresh option clears existing data
- [ ] Seeders are idempotent
- [ ] Useful for development and testing

---

## Definition of Done

- [ ] All 5 subtasks completed
- [ ] All acceptance criteria met
- [ ] Indexes improve query performance
- [ ] Database views work correctly
- [ ] Maintenance commands tested
- [ ] Documentation updated
- [ ] No performance regressions

---

## Notes

- Monitor index usage in production
- Consider archiving old translations instead of deleting
- Plan for horizontal scaling if needed
- Regular VACUUM/OPTIMIZE for PostgreSQL/MySQL
