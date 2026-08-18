<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropColumn(['media_id', 'meta_message_id', 'request_payload', 'response_payload']);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->string('media_id')->nullable();
            $table->string('meta_message_id')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
        });
    }
};
