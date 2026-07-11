<?php

use App\Globals\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 150)->nullable();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->enum('status', [
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])->default(Status::UPCOMING);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'status']);
            $table->index(['starts_at', 'ends_at']);
            $table->unique(['church_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekends');
    }
};
