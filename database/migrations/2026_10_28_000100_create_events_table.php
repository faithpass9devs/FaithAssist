<?php

use App\Globals\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 150);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', [
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])->default(Status::UPCOMING);
            $table->enum('attendance_check_in_status', [
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])->default(Status::UPCOMING);
            $table->enum('attendance_check_out_status', [
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])->default(Status::UPCOMING);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'status']);
            $table->index(['attendance_check_in_status', 'starts_at']);
            $table->index(['attendance_check_out_status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
