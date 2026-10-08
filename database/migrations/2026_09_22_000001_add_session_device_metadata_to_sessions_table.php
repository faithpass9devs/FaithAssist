<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->string('device_name')->nullable()->after('user_agent');
            $table->string('operating_system')->nullable()->after('device_name');
            $table->string('browser')->nullable()->after('operating_system');
            $table->timestamp('first_seen_at')->nullable()->after('browser');
            $table->timestamp('last_seen_at')->nullable()->after('first_seen_at');
            $table->string('status')->default('active')->after('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn(['device_name', 'operating_system', 'browser', 'first_seen_at', 'last_seen_at', 'status']);
        });
    }
};
