<?php

namespace App\Services;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Operation\PeriodMovement;
use Illuminate\Validation\ValidationException;

class CatechismPeriodMovementService
{
    public const INSCRIPTIONS = 'INSCRIPCIONES';

    public const REINSCRIPTIONS = 'REINSCRIPCIONES';

    public function activeMovementForChurch(int|Church $church, string $movementTypeName): ?PeriodMovement
    {
        $churchId = $church instanceof Church ? $church->id : $church;

        $today = now()->toDateString();

        return PeriodMovement::query()
            ->with(['period:id,diocese_id,name,years,status', 'periodMovementType:id,name,status'])
            ->where('church_id', $churchId)
            ->where('status', Status::IN_PROGRESS)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereHas('period', fn ($query) => $query->where('status', Status::IN_PROGRESS))
            ->whereHas('periodMovementType', fn ($query) => $query
                ->where('name', $movementTypeName)
                ->where('status', Status::ACTIVE))
            ->orderByDesc('start_date')
            ->first();
    }

    public function requireActiveMovementForChurch(int|Church $church, string $movementTypeName, string $field = 'church_id'): PeriodMovement
    {
        $movement = $this->activeMovementForChurch($church, $movementTypeName);

        if ($movement) {
            return $movement;
        }

        throw ValidationException::withMessages([
            $field => $movementTypeName === self::REINSCRIPTIONS
                ? 'No hay un movimiento de reinscripciones activo para la parroquia del niño.'
                : 'No hay un movimiento de inscripciones activo para la parroquia seleccionada.',
        ]);
    }
}
