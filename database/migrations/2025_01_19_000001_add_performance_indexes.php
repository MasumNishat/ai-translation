<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Languages table indexes
        Schema::table('languages', function (Blueprint $table) {
            // Index on is_active for filtering active languages
            $table->index('is_active', 'idx_languages_is_active');

            // Index on is_default for finding default language quickly
            $table->index('is_default', 'idx_languages_is_default');

            // Composite index for active language lookups
            $table->index(['is_active', 'code'], 'idx_languages_active_code');
        });

        // Translations table indexes
        Schema::table('translations', function (Blueprint $table) {
            // Most common query pattern: lookup by language and key
            $table->index(['language_id', 'key'], 'idx_translations_lang_key');

            // Group filtering
            $table->index(['language_id', 'group'], 'idx_translations_lang_group');

            // Full composite for complex queries
            $table->index(['language_id', 'group', 'key'], 'idx_translations_lang_group_key');

            // Key lookup across languages
            $table->index('key', 'idx_translations_key');

            // Group-based queries
            $table->index('group', 'idx_translations_group');

            // Temporal queries
            $table->index('created_at', 'idx_translations_created_at');
            $table->index('updated_at', 'idx_translations_updated_at');

            // Active translations
            $table->index('is_active', 'idx_translations_is_active');
        });

        // Full-text search index for MySQL
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE translations ADD FULLTEXT INDEX idx_translations_fulltext (key, value)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropIndex('idx_languages_is_active');
            $table->dropIndex('idx_languages_is_default');
            $table->dropIndex('idx_languages_active_code');
        });

        Schema::table('translations', function (Blueprint $table) {
            $table->dropIndex('idx_translations_lang_key');
            $table->dropIndex('idx_translations_lang_group');
            $table->dropIndex('idx_translations_lang_group_key');
            $table->dropIndex('idx_translations_key');
            $table->dropIndex('idx_translations_group');
            $table->dropIndex('idx_translations_created_at');
            $table->dropIndex('idx_translations_updated_at');
            $table->dropIndex('idx_translations_is_active');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE translations DROP INDEX idx_translations_fulltext');
        }
    }
};
