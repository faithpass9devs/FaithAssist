<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('failed_whatsapp_children');
        Schema::dropIfExists('whatsapp_mass_batches');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        //
    }
};
