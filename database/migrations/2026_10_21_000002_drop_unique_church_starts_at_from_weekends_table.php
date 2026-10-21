<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekends', function (Blueprint $table): void {
            $table->dropUnique(['church_id', 'starts_at']);
            $table->index(['church_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('weekends', function (Blueprint $table): void {
            $table->dropIndex(['church_id', 'starts_at']);
        });
    }
};
