<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('translation_id')->constrained('translations')->onDelete('cascade');
            $table->text('old_value')->nullable()->comment('Previous translation value');
            $table->text('new_value')->comment('New translation value');
            $table->unsignedBigInteger('changed_by_user_id')->nullable()->comment('User who made the change');
            $table->enum('change_type', ['created', 'updated', 'deleted'])->comment('Type of change');
            $table->string('ip_address', 45)->nullable()->comment('IP address of the user');
            $table->text('user_agent')->nullable()->comment('Browser user agent');
            $table->timestamp('created_at')->useCurrent();

            // Index for querying history
            $table->index(['translation_id', 'created_at']);
            $table->index('changed_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_histories');
    }
};
