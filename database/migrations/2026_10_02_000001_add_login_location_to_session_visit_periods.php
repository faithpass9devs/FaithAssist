<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_visit_periods', function (Blueprint $table): void {
            $table->text('login_location')->nullable()->after('location');
            $table->unsignedInteger('login_location_accuracy')->nullable()->after('login_location');
        });
    }

    public function down(): void
    {
        Schema::table('session_visit_periods', function (Blueprint $table): void {
            $table->dropColumn(['login_location', 'login_location_accuracy']);
        });
    }
};
