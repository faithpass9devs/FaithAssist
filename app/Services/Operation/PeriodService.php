<?php

namespace App\Services\Operation;

use App\Models\Operation\Period;
use App\Models\User;
use App\Repositories\Operation\PeriodRepository;

class PeriodService
{
    public function __construct(private readonly PeriodRepository $periods) {}

    public function indexData(User $user, string $search): array
    {
        return [
            'periods' => $this->periods->paginateWithSearch($user, $search),
            'dioceses' => $this->periods->activeDioceses($user),
            'search' => $search,
        ];
    }

    public function createPeriod(array $data): array
    {
        $data['years'] = $this->resolveYears($data['start_date'], $data['end_date']);
        $period = $this->periods->create($data);
        return $this->periodData($period);
    }

    public function updatePeriod(Period $period, array $data): array
    {
        $data['years'] = $this->resolveYears($data['start_date'], $data['end_date']);
        $period = $this->periods->update($period, $data);
        return $this->periodData($period);
    }

    public function deletePeriod(Period $period): void
    {
        $this->periods->delete($period);
    }

    private function periodData(Period $period): array
    {
        return $period->only(['id', 'diocese_id', 'name', 'start_date', 'end_date', 'years', 'status']);
    }

    private function resolveYears(string $startDate, string $endDate): string
    {
        $startYear = (int) date('Y', strtotime($startDate));
        $endYear = (int) date('Y', strtotime($endDate));

        return $startYear === $endYear
            ? (string) $startYear
            : "{$startYear}-{$endYear}";
    }
}
