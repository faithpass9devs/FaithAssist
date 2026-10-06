<?php

use App\Globals\Status;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('masses', function (Blueprint $table): void {
            $table->enum('attendance_check_in_status', [
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])->default(Status::UPCOMING);

            $table->enum('attendance_check_out_status', [
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])->default(Status::UPCOMING);
        });

        DB::table('masses')->update([
            'attendance_check_in_status' => DB::raw('attendance_status'),
            'attendance_check_out_status' => DB::raw('attendance_status'),
        ]);

        Schema::table('masses', function (Blueprint $table): void {
            $table->dropIndex(['attendance_status', 'starts_at']);
            $table->dropColumn('attendance_status');

            $table->index(['attendance_check_in_status', 'starts_at']);
            $table->index(['attendance_check_out_status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('masses', function (Blueprint $table): void {
            $table->dropIndex(['attendance_check_out_status', 'starts_at']);
            $table->dropIndex(['attendance_check_in_status', 'starts_at']);

            $table->enum('attendance_status', [
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])->default(Status::UPCOMING);
        });

        DB::table('masses')->update([
            'attendance_status' => DB::raw('attendance_check_in_status'),
        ]);

        Schema::table('masses', function (Blueprint $table): void {
            $table->dropColumn(['attendance_check_out_status', 'attendance_check_in_status']);
            $table->index(['attendance_status', 'starts_at']);
        });
    }
};
