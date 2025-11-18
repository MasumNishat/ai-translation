<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique()->index()->comment('ISO 639-1 language code');
            $table->string('name')->comment('English name of the language');
            $table->string('native_name')->comment('Native name of the language');
            $table->enum('direction', ['ltr', 'rtl'])->default('ltr')->comment('Text direction');
            $table->boolean('is_active')->default(true)->index()->comment('Whether the language is active');
            $table->boolean('is_default')->default(false)->comment('Default language for the application');
            $table->string('country_code', 2)->nullable()->comment('ISO 3166-1 alpha-2 country code');
            $table->string('region')->nullable()->comment('Geographical region');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
