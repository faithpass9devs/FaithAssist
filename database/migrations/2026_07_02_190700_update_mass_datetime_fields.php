<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekends', function (Blueprint $table): void {
            $table->dateTime('starts_at')->change();
            $table->dateTime('ends_at')->change();
        });

        DB::table('weekends')
            ->orderBy('id')
            ->get(['id', 'starts_at', 'ends_at'])
            ->each(function (object $weekend): void {
                DB::table('weekends')
                    ->where('id', $weekend->id)
                    ->update([
                        'starts_at' => Carbon::parse($weekend->starts_at)->startOfDay()->format('Y-m-d H:i:s'),
                        'ends_at' => Carbon::parse($weekend->ends_at)->setTime(23, 59)->format('Y-m-d H:i:s'),
                    ]);
            });

        Schema::table('masses', function (Blueprint $table): void {
            $table->dateTime('starts_at')->nullable()->after('name');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
        });

        DB::table('masses')
            ->orderBy('id')
            ->get(['id', 'celebrated_at'])
            ->each(function (object $mass): void {
                DB::table('masses')
                    ->where('id', $mass->id)
                    ->update([
                        'starts_at' => $mass->celebrated_at,
                    ]);
            });

        Schema::table('masses', function (Blueprint $table): void {
            $table->dropIndex(['attendance_status', 'celebrated_at']);
            $table->dropColumn('celebrated_at');
            $table->index(['attendance_status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('masses', function (Blueprint $table): void {
            $table->dateTime('celebrated_at')->nullable()->after('name');
        });

        DB::table('masses')
            ->orderBy('id')
            ->get(['id', 'starts_at'])
            ->each(function (object $mass): void {
                DB::table('masses')
                    ->where('id', $mass->id)
                    ->update([
                        'celebrated_at' => $mass->starts_at,
                    ]);
            });

        Schema::table('masses', function (Blueprint $table): void {
            $table->dropIndex(['attendance_status', 'starts_at']);
            $table->dropColumn(['starts_at', 'ends_at']);
            $table->index(['attendance_status', 'celebrated_at']);
        });

        Schema::table('weekends', function (Blueprint $table): void {
            $table->date('starts_at')->change();
            $table->date('ends_at')->change();
        });
    }
};
