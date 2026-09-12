<?php

use App\Models\Ecclesiastes\Church;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('period_movements', function (Blueprint $table) {
            $table->foreignId('church_id')->nullable()->after('period_id')->constrained('churches')->cascadeOnDelete();
        });

        $coatepec = Church::query()
            ->whereHas('municipality', fn ($q) => $q->where('name', 'Coatepec Harinas'))
            ->orderBy('id')
            ->value('id') ?? Church::query()->orderBy('id')->value('id');

        DB::table('period_movements')->whereNull('church_id')->update(['church_id' => $coatepec]);

        $groups = DB::table('period_movements')
            ->get(['id', 'period_id', 'period_movement_type_id', 'church_id'])
            ->groupBy(fn ($row) => $row->period_id.'-'.$row->period_movement_type_id.'-'.$row->church_id)
            ->filter(fn ($group) => $group->count() > 1);

        foreach ($groups as $group) {
            $keep = $group->sortBy('id')->first();
            $ids = $group->pluck('id')->reject(fn ($id) => $id === $keep->id);

            DB::table('period_movements')->whereIn('id', $ids)->delete();
        }

        Schema::table('period_movements', function (Blueprint $table) {
            $table->foreignId('church_id')->nullable(false)->change();
            $table->index('church_id');
            $table->unique(
                ['period_id', 'period_movement_type_id', 'church_id'],
                'period_movements_period_type_church_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('period_movements', function (Blueprint $table) {
            $table->dropUnique('period_movements_period_type_church_unique');
            $table->dropIndex(['church_id']);
            $table->dropForeign(['church_id']);
            $table->dropColumn('church_id');
        });
    }
};