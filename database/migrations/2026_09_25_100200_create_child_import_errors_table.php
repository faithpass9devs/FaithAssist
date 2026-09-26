<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('child_import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_import_batch_id')->constrained('child_import_batches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('line');
            $table->string('message', 500);
            $table->timestamps();

            $table->index('child_import_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('child_import_errors');
    }
};
