<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_whatsapp_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('whatsapp_mass_batch_id')->nullable()->constrained('whatsapp_mass_batches')->nullOnDelete();
            $table->foreignId('whatsapp_message_id')->nullable()->constrained('whatsapp_messages')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['child_id', 'whatsapp_mass_batch_id']);
            $table->index(['whatsapp_mass_batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_whatsapp_deliveries');
    }
};
