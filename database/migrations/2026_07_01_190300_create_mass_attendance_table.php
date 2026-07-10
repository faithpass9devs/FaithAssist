<?php

use App\Globals\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_attendance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mass_id')->constrained('masses')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('child_id')->constrained('children')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('child_code', 40);
            $table->foreignId('church_id')->constrained('churches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('chapel_id')->nullable()->constrained('chapels')->cascadeOnUpdate()->nullOnDelete();
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

            $table->unique(['mass_id', 'child_id']);
            $table->index('child_code');
            $table->index(['church_id', 'chapel_id']);
            $table->index(['status', 'check_in_at', 'check_out_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_attendance');
    }
};
