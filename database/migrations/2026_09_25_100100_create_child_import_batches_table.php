<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('period_movement_id')->constrained('period_movements')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('original_filename')->nullable();
            $table->string('storage_path');
            $table->string('queue_batch_id')->nullable()->index();
            $table->unsignedInteger('total_jobs')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('error_report_path')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_import_batches');
    }
};
