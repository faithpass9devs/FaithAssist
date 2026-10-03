<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->uuid('browser_id')->nullable()->after('browser');
            $table->index(['user_id', 'browser_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'browser_id']);
            $table->dropColumn('browser_id');
        });
    }
};
