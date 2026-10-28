<?php

use App\Globals\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnUpdate()->restrictOnDelete();
            $table->morphs('attendee');
            $table->string('attendee_code', 50);
            $table->foreignId('church_id')->constrained('churches')->cascadeOnUpdate()->restrictOnDelete();
            $table->dateTime('check_in_at')->nullable();
            $table->foreignId('check_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('check_out_at')->nullable();
            $table->foreignId('check_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                Status::PENDING,
                Status::CHECK_IN,
                Status::CHECK_OUT,
                Status::FAILED,
            ])->default(Status::PENDING);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'attendee_type', 'attendee_id'], 'event_attendance_unique_attendee_per_event');
            $table->index(['event_id', 'attendee_code']);
            $table->index(['status', 'check_in_at', 'check_out_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendance');
    }
};
