<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Setting key');
            $table->text('value')->comment('Setting value');
            $table->enum('type', ['string', 'integer', 'boolean', 'json', 'array'])->default('string')->comment('Value type');
            $table->boolean('is_encrypted')->default(false)->comment('Whether the value is encrypted');
            $table->text('description')->nullable()->comment('Setting description');
            $table->timestamps();

            $table->index('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_settings');
    }
};
