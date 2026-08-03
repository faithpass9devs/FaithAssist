<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_child_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_child_id');
            $table->foreignId('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('external_child_id');
            $table->unique('child_id');
            $table->index('external_child_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_child_imports');
    }
};
