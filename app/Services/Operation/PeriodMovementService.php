<?php

namespace App\Services\Operation;

use App\Globals\Status;
use App\Models\Operation\Period;
use App\Models\Operation\PeriodMovement;
use App\Models\Operation\PeriodMovementType;
use App\Models\User;
use App\Repositories\Operation\PeriodMovementRepository;

class PeriodMovementService
{
    public function __construct(private readonly PeriodMovementRepository $movements) {}

    public function indexData(User $user, string $search): array
    {
        $periods = $this->movements->activePeriods($user);
        $movementTypes = $this->movements->activeMovementTypes();

        return [
            'movements' => $this->movements->paginateWithSearch($user, $search),
            'periods' => $periods->map(fn (Period $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'years' => $period->years,
                'diocese_name' => $period->diocese?->name,
            ])->values(),
            'movementTypes' => $movementTypes->map(fn (PeriodMovementType $movementType): array => [
                'id' => $movementType->id,
                'name' => $movementType->name,
                'status' => $movementType->status,
                'description' => $movementType->description,
            ])->values(),
            'search' => $search,
            'statusOptions' => [
                ['value' => Status::PENDING, 'label' => 'Pendiente'],
                ['value' => Status::IN_PROGRESS, 'label' => 'En proceso'],
                ['value' => Status::COMPLETED, 'label' => 'Completado'],
            ],
        ];
    }

    public function createMovement(array $data): array
    {
        $movement = $this->movements->create($data);
        return $this->movementData($movement);
    }

    public function updateMovement(PeriodMovement $movement, array $data): array
    {
        $movement = $this->movements->update($movement, $data);
        return $this->movementData($movement);
    }

    public function deleteMovement(PeriodMovement $movement): void
    {
        $this->movements->delete($movement);
    }

    private function movementData(PeriodMovement $movement): array
    {
        return $movement->only(['id', 'period_id', 'period_movement_type_id', 'status', 'start_date', 'end_date', 'notes']);
    }
}
