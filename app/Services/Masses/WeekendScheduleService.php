<?php

namespace App\Services\Masses;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\WeekendMassSchedule;
use App\Models\User;
use App\Repositories\Masses\WeekendMassScheduleRepository;
use App\Repositories\Masses\WeekendRepository;
use App\Services\UserScopeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WeekendScheduleService
{
    private const MONTHS = [
        1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN',
        7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
    ];

    private const MONTH_NAMES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public function __construct(
        private readonly WeekendMassScheduleRepository $schedules,
        private readonly WeekendRepository $weekends,
    ) {}

    public function lockedChurchId(User $user): ?int
    {
        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return null;
        }

        return $user->church_id !== null && $user->chapel_id === null ? (int) $user->church_id : null;
    }

    public function canManageChurch(User $user, int $churchId): bool
    {
        if ($user->chapel_id !== null) {
            return false;
        }

        return $this->churchOptions($user)->contains('id', $churchId);
    }

    public function canAssignChapels(User $user): bool
    {
        $scope = new UserScopeService($user);

        return $scope->isGlobal() || $user->can('masses.scope.all');
    }

    public function churchOptions(User $user): Collection
    {
        $lockedChurchId = $this->lockedChurchId($user);

        return $this->weekends->activeChurches($user)
            ->when($lockedChurchId !== null, fn (Collection $churches) => $churches->where('id', $lockedChurchId)->values());
    }

    public function resolveChurchId(User $user, ?int $requestedChurchId): ?int
    {
        $lockedChurchId = $this->lockedChurchId($user);

        if ($lockedChurchId !== null) {
            return $lockedChurchId;
        }

        $churches = $this->churchOptions($user);

        if ($requestedChurchId && $churches->contains('id', $requestedChurchId)) {
            return $requestedChurchId;
        }

        return $churches->count() === 1 ? (int) $churches->first()->id : null;
    }

    public function indexData(User $user): array
    {
        $churches = $this->churchOptions($user);

        return [
            'lockedChurchId' => $this->lockedChurchId($user),
            'scheduledChurchIds' => $this->schedules->churchIdsWithSchedules($churches->pluck('id')),
        ];
    }

    public function scheduleFormData(User $user, ?int $requestedChurchId): array
    {
        $churchId = $this->resolveChurchId($user, $requestedChurchId);
        $today = now();

        return [
            'churches' => $this->churchOptions($user),
            'lockedChurchId' => $this->lockedChurchId($user),
            'selectedChurchId' => $churchId,
            'chapels' => $churchId && $this->canAssignChapels($user)
                ? $this->schedules->activeChapelsForChurch($churchId)
                : [],
            'schedules' => $churchId
                ? $this->schedules->forChurch($churchId)
                    ->filter(fn (WeekendMassSchedule $schedule): bool => $this->canAssignChapels($user) || $schedule->chapel_id === null)
                    ->map(fn (WeekendMassSchedule $schedule) => $this->schedules->serializeSchedule($schedule))
                    ->values()
                : [],
            'monthLabel' => self::MONTH_NAMES[$today->month].' de '.$today->year,
        ];
    }

    public function saveSchedule(User $user, int $churchId, array $items): void
    {
        DB::transaction(function () use ($user, $churchId, $items): void {
            Church::query()->whereKey($churchId)->lockForUpdate()->firstOrFail();

            $existing = WeekendMassSchedule::query()
                ->where('church_id', $churchId)
                ->when(! $this->canAssignChapels($user), fn ($query) => $query->whereNull('chapel_id'))
                ->get()
                ->keyBy('id');
            $keptIds = [];

            foreach ($items as $item) {
                $data = [
                    'church_id' => $churchId,
                    'chapel_id' => $item['chapel_id'] ?? null,
                    'day_of_week' => $item['day_of_week'],
                    'name' => $item['name'],
                    'starts_time' => $item['starts_time'],
                    'ends_time' => $item['ends_time'],
                    'updated_by' => $user->id,
                ];
                $id = isset($item['id']) ? (int) $item['id'] : null;

                if ($id && $existing->has($id)) {
                    $this->schedules->update($existing->get($id), $data);
                    $keptIds[] = $id;

                    continue;
                }

                $this->schedules->create($data + ['created_by' => $user->id]);
            }

            $existing->except($keptIds)
                ->each(fn (WeekendMassSchedule $schedule) => $this->schedules->delete($schedule, $user->id));
        });
    }

    public function previewForMonth(int $churchId, ?Carbon $now = null): array
    {
        $now ??= now();
        $today = $now->copy()->startOfDay();
        $schedules = $this->schedules->forChurch($churchId);
        $weekends = [];

        if ($schedules->isNotEmpty()) {
            $saturday = $today->copy()->startOfMonth();

            if (! $saturday->isSaturday()) {
                $saturday->next(Carbon::SATURDAY);
            }

            while ($saturday->month === $today->month) {
                $startsAt = $saturday->copy()->startOfDay();
                $endsAt = $saturday->copy()->addDay()->setTime(23, 59);

                if ($endsAt->greaterThanOrEqualTo($today)
                    && ! $this->schedules->weekendOverlaps($churchId, $startsAt, $endsAt)) {
                    $weekends[] = [
                        'name' => $this->weekendName($startsAt, $endsAt),
                        'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                        'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                        'masses' => $schedules
                            ->map(fn (WeekendMassSchedule $schedule) => $this->massFromSchedule($schedule, $startsAt))
                            ->values()
                            ->all(),
                    ];
                }

                $saturday->addWeek();
            }
        }

        return [
            'church_id' => $churchId,
            'schedules_count' => $schedules->count(),
            'month_label' => self::MONTH_NAMES[$today->month].' de '.$today->year,
            'weekends' => $weekends,
        ];
    }

    public function generateForMonth(User $user, int $churchId): int
    {
        return DB::transaction(function () use ($user, $churchId): int {
            Church::query()->whereKey($churchId)->lockForUpdate()->firstOrFail();
            $preview = $this->previewForMonth($churchId);

            foreach ($preview['weekends'] as $item) {
                $weekend = $this->schedules->createWeekend([
                    'church_id' => $churchId,
                    'name' => $item['name'],
                    'starts_at' => $item['starts_at'],
                    'ends_at' => $item['ends_at'],
                    'status' => Status::UPCOMING,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);

                foreach ($item['masses'] as $mass) {
                    $this->schedules->createMass([
                        'weekend_id' => $weekend->id,
                        'church_id' => $churchId,
                        'chapel_id' => $mass['chapel_id'],
                        'name' => $mass['name'],
                        'starts_at' => Carbon::parse($mass['starts_at'])->format('Y-m-d H:i:s'),
                        'ends_at' => Carbon::parse($mass['ends_at'])->format('Y-m-d H:i:s'),
                        'status' => Status::UPCOMING,
                        'attendance_check_in_status' => Status::UPCOMING,
                        'attendance_check_out_status' => Status::UPCOMING,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            return count($preview['weekends']);
        });
    }

    private function massFromSchedule(WeekendMassSchedule $schedule, Carbon $saturday): array
    {
        $date = $schedule->day_of_week === WeekendMassSchedule::SUNDAY
            ? $saturday->copy()->addDay()
            : $saturday->copy();
        $startsAt = $date->copy()->setTimeFromTimeString((string) $schedule->starts_time);
        $endsAt = $date->copy()->setTimeFromTimeString((string) $schedule->ends_time);

        return [
            'day_of_week' => $schedule->day_of_week,
            'name' => $schedule->name,
            'chapel_id' => $schedule->chapel_id,
            'chapel' => $schedule->chapel?->name,
            'starts_at' => $startsAt->format('Y-m-d H:i'),
            'ends_at' => $endsAt->format('Y-m-d H:i'),
        ];
    }

    private function weekendName(Carbon $startsAt, Carbon $endsAt): string
    {
        if ($startsAt->month === $endsAt->month) {
            return sprintf(
                'FIN DE SEMANA %s-%s %s %d',
                $startsAt->format('d'),
                $endsAt->format('d'),
                self::MONTHS[$startsAt->month],
                $startsAt->year,
            );
        }

        return sprintf(
            'FIN DE SEMANA %s %s - %s %s %d',
            $startsAt->format('d'),
            self::MONTHS[$startsAt->month],
            $endsAt->format('d'),
            self::MONTHS[$endsAt->month],
            $endsAt->year,
        );
    }
}
