<?php

use App\Globals\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('masses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('weekend_id')->constrained('weekends')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('chapel_id')->nullable()->constrained('chapels')->cascadeOnUpdate()->nullOnDelete();
            $table->string('name', 150);
            $table->dateTime('celebrated_at');
            $table->enum('status', [
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])->default(Status::UPCOMING);
            $table->enum('attendance_status', [
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])->default(Status::UPCOMING);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['weekend_id', 'status']);
            $table->index(['church_id', 'chapel_id']);
            $table->index(['attendance_status', 'celebrated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masses');
    }
};
