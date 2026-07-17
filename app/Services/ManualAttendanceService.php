<?php

namespace App\Services;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Masses\ManualAttendance;
use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualAttendanceService
{
    public function register(array $data, User $user): Collection
    {
        $scope = new UserScopeService($user);

        $child = ($scope->isGlobal() ? Child::query() : $scope->applyChildScope(Child::query()))
            ->where('status', Status::ACTIVE)
            ->whereKey($data['child_id'])
            ->first();

        if (! $child) {
            throw ValidationException::withMessages([
                'child_id' => 'No se encontró un niño válido dentro de tu alcance.',
            ]);
        }

        $weekend = $scope->applyWeekendScope(
            Weekend::query()
        )->whereKey($data['weekend_id'])->first();

        if (! $weekend) {
            throw ValidationException::withMessages([
                'weekend_id' => 'No se encontró un fin de semana válido dentro de tu alcance.',
            ]);
        }

        $masses = $scope->applyMassScope(
            Mass::query()->where('weekend_id', $weekend->id)
        )
            ->whereIn('id', $data['mass_ids'])
            ->with(['church:id,name', 'chapel:id,name'])
            ->get()
            ->keyBy('id');

        if ($masses->count() !== count(array_unique($data['mass_ids']))) {
            throw ValidationException::withMessages([
                'mass_ids' => 'Una o más misas no están disponibles dentro de tu alcance.',
            ]);
        }

        return DB::transaction(function () use ($masses, $child, $user): Collection {
            return $masses->values()->map(function (Mass $mass) use ($child, $user): ManualAttendance {
                $attendance = ManualAttendance::query()->firstOrNew([
                    'mass_id' => $mass->id,
                    'child_id' => $child->id,
                ]);

                $attendance->fill([
                    'child_code' => $child->code,
                    'church_id' => $mass->church_id,
                    'chapel_id' => $mass->chapel_id,
                    'check_in_at' => $mass->starts_at ?? now(),
                    'check_in_by' => $user->id,
                    'check_out_at' => $mass->ends_at ?? $mass->starts_at ?? now(),
                    'check_out_by' => $user->id,
                    'status' => Status::CHECK_OUT,
                    'notes' => 'Registro manual',
                ]);

                $attendance->save();

                return $attendance->fresh(['child:id,name,paterno,materno,code', 'church:id,name', 'chapel:id,name', 'mass:id,weekend_id']);
            });
        });
    }
}
