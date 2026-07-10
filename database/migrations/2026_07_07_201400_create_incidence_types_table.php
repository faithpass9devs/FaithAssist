<?php

use App\Globals\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidence_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->enum('status', [
                Status::ACTIVE,
                Status::INACTIVE,
            ])->default(Status::ACTIVE);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->unique(['name', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidence_types');
    }
};
