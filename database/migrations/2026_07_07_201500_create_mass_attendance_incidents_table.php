<?php

use App\Globals\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_attendance_incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('weekend_id')->constrained('weekends')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('child_id')->constrained('children')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('incidence_type_id')->constrained('incidence_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->enum('status', [
                Status::ACTIVE,
                Status::INACTIVE,
            ])->default(Status::ACTIVE);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['weekend_id', 'child_id']);
            $table->index(['incidence_type_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->unique(['weekend_id', 'child_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_attendance_incidents');
    }
};
