<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->string('device_model', 120)->nullable()->after('device_name');
        });

        Schema::table('session_visit_periods', function (Blueprint $table): void {
            $table->string('device_model', 120)->nullable()->after('device');
        });
    }

    public function down(): void
    {
        Schema::table('session_visit_periods', function (Blueprint $table): void {
            $table->dropColumn('device_model');
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn('device_model');
        });
    }
};
