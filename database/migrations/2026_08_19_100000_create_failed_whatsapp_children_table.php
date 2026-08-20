<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_whatsapp_children', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('whatsapp_mass_batches')->nullOnDelete();
            $table->string('error_message');
            $table->timestamps();

            $table->index(['child_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_whatsapp_children');
    }
};
