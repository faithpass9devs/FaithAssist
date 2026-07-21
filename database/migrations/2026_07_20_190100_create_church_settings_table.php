<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumn = Schema::getConnection()->getDriverName() === 'pgsql' ? 'jsonb' : 'json';

        Schema::create('church_settings', function (Blueprint $table) use ($jsonColumn) {
            $table->id();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('setting_definition_id')->constrained('setting_definitions')->cascadeOnUpdate()->restrictOnDelete();
            $table->{$jsonColumn}('value')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['church_id', 'setting_definition_id']);
            $table->index('setting_definition_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('church_settings');
    }
};
