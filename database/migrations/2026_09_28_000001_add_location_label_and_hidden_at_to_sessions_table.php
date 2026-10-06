<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->text('location_label')->nullable()->after('location_accuracy');
            $table->timestamp('location_updated_at')->nullable()->after('location_label');
            // Hides the session from the live table while keeping it in the PDF history.
            $table->timestamp('hidden_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropColumn(['location_label', 'location_updated_at', 'hidden_at']);
        });
    }
};
