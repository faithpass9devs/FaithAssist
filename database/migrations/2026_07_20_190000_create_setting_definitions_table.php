<?php

use App\Globals\SettingType;
use App\Globals\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jsonColumn = Schema::getConnection()->getDriverName() === 'pgsql' ? 'jsonb' : 'json';

        Schema::create('setting_definitions', function (Blueprint $table) use ($jsonColumn) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->enum('status', [Status::ACTIVE, Status::INACTIVE])->default(Status::ACTIVE);
            $table->enum('type_data', SettingType::values());
            $table->{$jsonColumn}('meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('type_data');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_definitions');
    }
};
