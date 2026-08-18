<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->text('message_body')->nullable()->after('message_type');
            $table->string('filename')->nullable()->after('pdf_path');
            $table->string('baileys_message_id')->nullable()->after('filename');
            $table->unsignedTinyInteger('retry_count')->default(0)->after('error_message');
            $table->unsignedTinyInteger('max_retries')->default(3)->after('retry_count');
            $table->string('batch_key')->nullable()->index()->after('max_retries');
            $table->text('legend_text')->nullable()->after('batch_key');
            $table->timestamp('sent_at')->nullable()->after('legend_text');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['batch_key']);
            $table->dropColumn([
                'message_body', 'filename', 'baileys_message_id', 'retry_count',
                'max_retries', 'batch_key', 'legend_text', 'sent_at',
            ]);
        });
    }
};
