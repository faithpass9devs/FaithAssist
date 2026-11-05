<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('staff_type_id')->constrained('staff_types')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('paterno', 150);
            $table->string('materno', 150)->nullable();
            $table->string('phone_lada', 8)->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('code', 50)->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'community_id']);
            $table->index(['staff_type_id']);
            $table->index(['paterno', 'materno', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
