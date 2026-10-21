<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekend_mass_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('chapel_id')->nullable()->constrained('chapels')->cascadeOnUpdate()->nullOnDelete();
            $table->enum('day_of_week', ['saturday', 'sunday']);
            $table->string('name', 150);
            $table->time('starts_time');
            $table->time('ends_time');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekend_mass_schedules');
    }
};
