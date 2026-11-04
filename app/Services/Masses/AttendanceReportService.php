<?php

namespace App\Services\Masses;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildLevelAssignment;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Models\Operation\Level;
use App\Models\Regions\Community;
use App\Models\User;
use App\Services\Settings\SettingResolver;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Construye el "estado de cuenta" de asistencias por niño.
 *
 * Reglas:
 *  - Solo se consideran fines de semana ya concluidos (estatus concluido o cuya fecha
 *    de fin ya pasó) y que tengan al menos un registro de asistencia. Así nunca
 *    aparecen fines de semana futuros ni meses sin captura.
 *  - Por fin de semana, un niño queda como: Presente (registró entrada en al menos una
 *    misa de cualquiera de los dos días; asistir a varias misas sigue siendo una sola
 *    asistencia), Justificada (incidencia activa), Falta (no fue a ninguna misa en
 *    ningún día del fin de semana) o No aplica (sin misas para su comunidad o aún no
 *    estaba inscrito en el sistema).
 *  - Solo las faltas cuentan contra el límite de faltas de la parroquia
 *    (ajuste attendance.max_absences); las justificadas no.
 */
class AttendanceReportService
{
    public const MAX_ABSENCES_SETTING = 'attendance.max_absences';

    public const DEFAULT_MAX_ABSENCES = 3;

    /** Niños procesados por bloque; acota la memoria sin importar el tamaño de la parroquia. */
    public const CHUNK_SIZE = 500;

    public const CELL_PRESENT = 'P';

    public const CELL_ABSENT = 'F';

    public const CELL_JUSTIFIED = 'J';

    public const CELL_NOT_APPLICABLE = '-';

    public const STATUS_REGULAR = 'regular';

    public const STATUS_AT_RISK = 'at_risk';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_LABELS = [
        self::STATUS_REGULAR => 'Regular',
        self::STATUS_AT_RISK => 'En riesgo',
        self::STATUS_BLOCKED => 'Sin derecho',
    ];

    private const MONTH_NAMES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    private const MONTH_SHORT = [
        1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR', 5 => 'MAY', 6 => 'JUN',
        7 => 'JUL', 8 => 'AGO', 9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
    ];

    public function __construct(
        private readonly WeekendScheduleService $schedules,
        private readonly SettingResolver $settings,
    ) {}

    /**
     * Opciones para el modal: parroquias disponibles y, para la parroquia elegida,
     * comunidades, niveles, fines de semana con asistencia y meses derivados.
     */
    public function options(User $user, ?int $churchId): array
    {
        $churches = $this->schedules->churchOptions($user);
        $church = $this->resolveChurch($user, $churchId, $churches);

        $payload = [
            'churches' => $churches->values(),
            'church_id' => $church?->id,
            'communities' => [],
            'levels' => [],
            'weekends' => [],
            'months' => [],
            'range' => null,
            'absence_limit' => null,
        ];

        if (! $church) {
            return $payload;
        }

        $weekends = $this->reportableWeekends($church->id);

        return [
            ...$payload,
            'communities' => $this->communityOptions($church->id),
            'levels' => $this->levelOptions($church->id),
            'weekends' => $weekends->map(fn (Weekend $weekend): array => $this->serializeWeekend($weekend))->values(),
            'months' => $this->monthOptions($weekends),
            'range' => $weekends->isEmpty() ? null : [
                'min' => $weekends->first()->starts_at->toDateString(),
                'max' => $weekends->last()->starts_at->toDateString(),
            ],
            'absence_limit' => $this->absenceLimit($church),
        ];
    }

    /**
     * Arma el reporte listo para exportar. Las filas no se materializan: `rows` es un
     * generador que produce cada fila (niño) conforme se consume, y `summary` es un
     * cierre que debe invocarse después de agotar las filas, porque el resumen se
     * acumula mientras el generador las produce. Así la memoria no crece con el
     * número de niños de la parroquia.
     *
     * @param  array{church_id:int, community_id?:int|null, level_id?:int|null, month?:string|null, date_from?:string|null, date_to?:string|null, child_status?:string|null}  $filters
     */
    public function build(User $user, array $filters): array
    {
        $churches = $this->schedules->churchOptions($user);
        $church = $this->resolveChurch($user, (int) $filters['church_id'], $churches);

        if (! $church) {
            throw ValidationException::withMessages([
                'church_id' => 'La parroquia seleccionada no está disponible dentro de tu alcance.',
            ]);
        }

        [$dateFrom, $dateTo, $periodLabel, $periodSlug] = $this->resolvePeriod($filters);

        $weekends = $this->reportableWeekends($church->id)
            ->when($dateFrom, fn (Collection $items) => $items->filter(
                fn (Weekend $weekend): bool => $weekend->starts_at->toDateString() >= $dateFrom
            ))
            ->when($dateTo, fn (Collection $items) => $items->filter(
                fn (Weekend $weekend): bool => $weekend->starts_at->toDateString() <= $dateTo
            ))
            ->values();

        if ($weekends->isEmpty()) {
            throw ValidationException::withMessages([
                'period' => 'No hay fines de semana con asistencia registrada en el periodo seleccionado.',
            ]);
        }

        $limit = $this->absenceLimit($church);
        $context = $this->buildContext($weekends, $limit);
        $accumulator = $this->emptyAccumulator($weekends->count());

        $communityName = isset($filters['community_id']) && $filters['community_id']
            ? Community::query()->whereKey($filters['community_id'])->value('name')
            : null;
        $levelName = isset($filters['level_id']) && $filters['level_id']
            ? Level::query()->whereKey($filters['level_id'])->value('name')
            : null;

        return [
            'church' => ['id' => $church->id, 'name' => $church->name],
            'period_label' => $periodLabel,
            'period_slug' => $periodSlug,
            'filters' => [
                'community' => $communityName,
                'level' => $levelName,
                'child_status' => ($filters['child_status'] ?? 'active') === 'all' ? 'Todos' : 'Activos',
            ],
            'absence_limit' => $limit,
            'generated_at' => now(),
            'generated_by' => $this->userDisplayName($user),
            'weekends' => $weekends->map(fn (Weekend $weekend): array => $this->serializeWeekend($weekend))->values()->all(),
            'total_children' => $this->childrenQuery($church->id, $filters)->count(),
            'rows' => $this->streamRows($church->id, $filters, $context, $accumulator),
            'summary' => function () use (&$accumulator, $weekends): array {
                return $this->summarize($accumulator, $weekends);
            },
        ];
    }

    /**
     * Produce las filas por bloques: cada bloque trae solo filas agregadas
     * (niño × fin de semana) desde SQL, nunca modelos de asistencia individuales.
     * Cada fila se acumula en el resumen y se cede de inmediato, sin retenerse.
     *
     * @return Generator<int, array<string, mixed>>
     */
    private function streamRows(int $churchId, array $filters, array $context, array &$accumulator): Generator
    {
        $page = 1;

        do {
            $children = $this->childrenQuery($churchId, $filters)->forPage($page, self::CHUNK_SIZE)->get();

            if ($children->isEmpty()) {
                return;
            }

            $childIds = $children->pluck('id')->all();
            $attended = $this->attendedByChildAndWeekend($childIds, $context['weekend_ids'], $context['church_id']);
            $incidents = $this->incidentsByChildAndWeekend($childIds, $context['weekend_ids']);
            $levels = $this->levelNamesByChild($childIds);

            foreach ($children as $child) {
                $row = $this->buildRow($child, $context, $attended, $incidents, $levels[$child->id] ?? '');
                $this->accumulate($accumulator, $row);

                yield $row;
            }

            $page++;
        } while ($children->count() === self::CHUNK_SIZE);
    }

    private function userDisplayName(User $user): string
    {
        $user->loadMissing('profile');

        $profileName = trim(collect([
            $user->profile?->name,
            $user->profile?->paterno,
            $user->profile?->materno,
        ])->filter()->implode(' '));

        return $profileName !== '' ? $profileName : (string) $user->name;
    }

    public function absenceLimit(Church $church): int
    {
        $church->loadMissing('deanery:id,diocese_id');

        $value = $this->settings->resolve(
            self::MAX_ABSENCES_SETTING,
            $church->id,
            $church->deanery?->diocese_id,
        );

        return is_numeric($value) && (int) $value > 0 ? (int) $value : self::DEFAULT_MAX_ABSENCES;
    }

    /**
     * Fines de semana concluidos de la parroquia que ya tienen asistencia capturada,
     * ordenados cronológicamente y con sus misas (y capilla) cargadas.
     *
     * @return Collection<int, Weekend>
     */
    public function reportableWeekends(int $churchId): Collection
    {
        return Weekend::query()
            ->with(['masses' => fn ($query) => $query
                ->with('chapel:id,community_id')
                ->orderBy('starts_at')])
            ->where('church_id', $churchId)
            ->where(function (Builder $query): void {
                $query->where('status', Status::COMPLETED)
                    ->orWhere('ends_at', '<', now());
            })
            ->whereHas('masses.attendances')
            ->orderBy('starts_at')
            ->get();
    }

    private function resolveChurch(User $user, ?int $churchId, Collection $churches): ?Church
    {
        if ($churchId !== null && $churches->contains('id', $churchId)) {
            return $churches->firstWhere('id', $churchId);
        }

        if ($churchId === null && $churches->count() === 1) {
            return $churches->first();
        }

        return null;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: string, 3: string} [desde, hasta, etiqueta legible, slug para nombre de archivo]
     */
    private function resolvePeriod(array $filters): array
    {
        $month = $filters['month'] ?? null;

        if ($month) {
            $start = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();

            return [
                $start->toDateString(),
                $start->copy()->endOfMonth()->toDateString(),
                self::MONTH_NAMES[$start->month].' de '.$start->year,
                self::MONTH_NAMES[$start->month].'_'.$start->year,
            ];
        }

        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        if ($from && $to) {
            return [$from, $to, 'Del '.$this->humanDate($from).' al '.$this->humanDate($to), $from.'_al_'.$to];
        }

        if ($from) {
            return [$from, null, 'Desde el '.$this->humanDate($from), 'desde_'.$from];
        }

        if ($to) {
            return [null, $to, 'Hasta el '.$this->humanDate($to), 'hasta_'.$to];
        }

        return [null, null, 'Histórico completo', 'historico'];
    }

    private function humanDate(string $date): string
    {
        $carbon = Carbon::parse($date);

        return $carbon->day.' de '.mb_strtolower(self::MONTH_NAMES[$carbon->month]).' de '.$carbon->year;
    }

    private function childrenQuery(int $churchId, array $filters): Builder
    {
        $status = $filters['child_status'] ?? 'active';

        return Child::query()
            ->with('community:id,name')
            ->select(['id', 'code', 'name', 'paterno', 'materno', 'community_id', 'status', 'created_at'])
            ->where('church_id', $churchId)
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', Status::ACTIVE))
            ->when($filters['community_id'] ?? null, fn (Builder $query, $communityId) => $query->where('community_id', $communityId))
            ->when($filters['level_id'] ?? null, fn (Builder $query, $levelId) => $query->whereHas(
                'activeLevelAssignments',
                fn (Builder $assignment) => $assignment->where('level_id', $levelId)
            ))
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * Datos precalculados de los fines de semana que no cambian entre niños.
     *
     * @param  Collection<int, Weekend>  $weekends
     * @return array{church_id:int, limit:int, weekend_ids:list<int>, ends_at:list<Carbon>, open_mass:list<bool>, chapel_communities:list<list<int>>}
     */
    private function buildContext(Collection $weekends, int $limit): array
    {
        $context = [
            'church_id' => (int) $weekends->first()->church_id,
            'limit' => $limit,
            'weekend_ids' => [],
            'ends_at' => [],
            'open_mass' => [],
            'chapel_communities' => [],
        ];

        foreach ($weekends as $weekend) {
            $context['weekend_ids'][] = (int) $weekend->id;
            $context['ends_at'][] = $weekend->ends_at;
            // Una misa "abierta" (sin capilla o capilla sin comunidad) aplica a todos los niños.
            $context['open_mass'][] = $weekend->masses->contains(
                fn (Mass $mass): bool => $mass->chapel_id === null || $mass->chapel?->community_id === null
            );
            $context['chapel_communities'][] = $weekend->masses
                ->map(fn (Mass $mass) => $mass->chapel?->community_id)
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        return $context;
    }

    /**
     * Número de misas con entrada registrada por (niño, fin de semana), agregado en SQL.
     *
     * @param  list<int>  $childIds
     * @param  list<int>  $weekendIds
     * @return array<string, int> "childId:weekendId" => misas asistidas
     */
    private function attendedByChildAndWeekend(array $childIds, array $weekendIds, int $churchId): array
    {
        $rows = DB::table('mass_attendance as ma')
            ->join('masses as m', 'm.id', '=', 'ma.mass_id')
            ->whereNull('m.deleted_at')
            ->where('ma.church_id', $churchId)
            ->whereIn('ma.child_id', $childIds)
            ->whereIn('m.weekend_id', $weekendIds)
            ->whereNotNull('ma.check_in_at')
            ->groupBy('ma.child_id', 'm.weekend_id')
            ->get([
                'ma.child_id',
                'm.weekend_id',
                DB::raw('COUNT(*) as attended'),
            ]);

        $map = [];

        foreach ($rows as $row) {
            $map[$row->child_id.':'.$row->weekend_id] = (int) $row->attended;
        }

        return $map;
    }

    /**
     * @param  list<int>  $childIds
     * @param  list<int>  $weekendIds
     * @return array<string, string> "childId:weekendId" => nombre del tipo de incidencia
     */
    private function incidentsByChildAndWeekend(array $childIds, array $weekendIds): array
    {
        $rows = DB::table('mass_attendance_incidents as i')
            ->leftJoin('incidence_types as t', 't.id', '=', 'i.incidence_type_id')
            ->whereNull('i.deleted_at')
            ->where('i.status', Status::ACTIVE)
            ->whereIn('i.child_id', $childIds)
            ->whereIn('i.weekend_id', $weekendIds)
            ->get(['i.child_id', 'i.weekend_id', 't.name']);

        $map = [];

        foreach ($rows as $row) {
            $map[$row->child_id.':'.$row->weekend_id] = (string) ($row->name ?? 'Justificada');
        }

        return $map;
    }

    /**
     * @param  list<int>  $childIds
     * @return array<int, string> childId => "NIVEL A, NIVEL B"
     */
    private function levelNamesByChild(array $childIds): array
    {
        $rows = DB::table('child_level_assignments as a')
            ->join('levels as l', 'l.id', '=', 'a.level_id')
            ->whereNull('a.deleted_at')
            ->where('a.status', Status::ACTIVE)
            ->whereIn('a.child_id', $childIds)
            ->orderBy('l.name')
            ->get(['a.child_id', 'l.name']);

        $map = [];

        foreach ($rows as $row) {
            $map[$row->child_id] ??= [];
            $map[$row->child_id][$row->name] = true;
        }

        return array_map(fn (array $names): string => implode(', ', array_keys($names)), $map);
    }

    /**
     * Fila compacta: los códigos por fin de semana van en una cadena ('PPFJ-') y los
     * detalles solo se guardan para las celdas que los tienen.
     *
     * @param  array<string, int>  $attended
     * @param  array<string, string>  $incidents
     */
    private function buildRow(Child $child, array $context, array $attended, array $incidents, string $levelNames): array
    {
        $registeredAt = $child->created_at?->copy()->startOfDay();
        $communityId = (int) $child->community_id;
        $codes = '';
        $details = [];
        $present = 0;
        $absences = 0;
        $justified = 0;
        $notApplicable = 0;

        foreach ($context['weekend_ids'] as $index => $weekendId) {
            $key = $child->id.':'.$weekendId;

            if (isset($attended[$key])) {
                $codes .= self::CELL_PRESENT;
                $present++;

                if ($attended[$key] > 1) {
                    $details[$index] = $attended[$key].' misas';
                }

                continue;
            }

            if (isset($incidents[$key])) {
                $codes .= self::CELL_JUSTIFIED;
                $details[$index] = $incidents[$key];
                $justified++;

                continue;
            }

            if ($registeredAt && $context['ends_at'][$index]->lt($registeredAt)) {
                $codes .= self::CELL_NOT_APPLICABLE;
                $details[$index] = 'Aún no inscrito';
                $notApplicable++;

                continue;
            }

            $applies = $context['open_mass'][$index]
                || in_array($communityId, $context['chapel_communities'][$index], true);

            if ($applies) {
                $codes .= self::CELL_ABSENT;
                $absences++;
            } else {
                $codes .= self::CELL_NOT_APPLICABLE;
                $details[$index] = 'Sin misa para su comunidad';
                $notApplicable++;
            }
        }

        $applicable = count($context['weekend_ids']) - $notApplicable;

        return [
            'child_id' => $child->id,
            'code' => $child->code,
            'name' => trim(collect([$child->paterno, $child->materno, $child->name])->filter()->implode(' ')),
            'level' => $levelNames,
            'community' => $child->community?->name ?? '',
            'child_status' => $child->status,
            'codes' => $codes,
            'details' => $details,
            'present' => $present,
            'justified' => $justified,
            'absences' => $absences,
            'applicable' => $applicable,
            'attendance_rate' => $applicable > 0 ? round($present / $applicable * 100, 1) : null,
            'remaining' => max(0, $context['limit'] - $absences),
            'status' => $this->statusFor($absences, $context['limit']),
        ];
    }

    public function statusFor(int $absences, int $limit): string
    {
        if ($absences >= $limit) {
            return self::STATUS_BLOCKED;
        }

        if ($absences === $limit - 1) {
            return self::STATUS_AT_RISK;
        }

        return self::STATUS_REGULAR;
    }

    private function emptyAccumulator(int $weekendCount): array
    {
        return [
            'children' => 0,
            'present' => 0,
            'absences' => 0,
            'justified' => 0,
            'applicable' => 0,
            'status' => [self::STATUS_REGULAR => 0, self::STATUS_AT_RISK => 0, self::STATUS_BLOCKED => 0],
            'levels' => [],
            'weekends' => array_fill(0, $weekendCount, [
                self::CELL_PRESENT => 0,
                self::CELL_ABSENT => 0,
                self::CELL_JUSTIFIED => 0,
                self::CELL_NOT_APPLICABLE => 0,
            ]),
        ];
    }

    private function accumulate(array &$acc, array $row): void
    {
        $acc['children']++;
        $acc['present'] += $row['present'];
        $acc['absences'] += $row['absences'];
        $acc['justified'] += $row['justified'];
        $acc['applicable'] += $row['applicable'];
        $acc['status'][$row['status']]++;

        $level = $row['level'] !== '' ? $row['level'] : 'Sin nivel';
        $acc['levels'][$level] ??= ['children' => 0, 'present' => 0, 'applicable' => 0, self::STATUS_REGULAR => 0, self::STATUS_AT_RISK => 0, self::STATUS_BLOCKED => 0];
        $acc['levels'][$level]['children']++;
        $acc['levels'][$level]['present'] += $row['present'];
        $acc['levels'][$level]['applicable'] += $row['applicable'];
        $acc['levels'][$level][$row['status']]++;

        $length = strlen($row['codes']);

        for ($i = 0; $i < $length; $i++) {
            $acc['weekends'][$i][$row['codes'][$i]]++;
        }
    }

    /**
     * @param  Collection<int, Weekend>  $weekends
     */
    private function summarize(array $acc, Collection $weekends): array
    {
        ksort($acc['levels']);

        $byLevel = [];

        foreach ($acc['levels'] as $level => $data) {
            $byLevel[] = [
                'level' => $level,
                'children' => $data['children'],
                'regular' => $data[self::STATUS_REGULAR],
                'at_risk' => $data[self::STATUS_AT_RISK],
                'blocked' => $data[self::STATUS_BLOCKED],
                'attendance_rate' => $data['applicable'] > 0 ? round($data['present'] / $data['applicable'] * 100, 1) : null,
            ];
        }

        $byWeekend = [];

        foreach ($weekends->values() as $index => $weekend) {
            $counts = $acc['weekends'][$index];
            $applicable = $counts[self::CELL_PRESENT] + $counts[self::CELL_ABSENT] + $counts[self::CELL_JUSTIFIED];

            $byWeekend[] = [
                ...$this->serializeWeekend($weekend),
                'present' => $counts[self::CELL_PRESENT],
                'absent' => $counts[self::CELL_ABSENT],
                'justified' => $counts[self::CELL_JUSTIFIED],
                'applicable' => $applicable,
                'attendance_rate' => $applicable > 0 ? round($counts[self::CELL_PRESENT] / $applicable * 100, 1) : null,
            ];
        }

        return [
            'children' => $acc['children'],
            'weekends' => $weekends->count(),
            'regular' => $acc['status'][self::STATUS_REGULAR],
            'at_risk' => $acc['status'][self::STATUS_AT_RISK],
            'blocked' => $acc['status'][self::STATUS_BLOCKED],
            'present' => $acc['present'],
            'absences' => $acc['absences'],
            'justified' => $acc['justified'],
            'attendance_rate' => $acc['applicable'] > 0 ? round($acc['present'] / $acc['applicable'] * 100, 1) : null,
            'by_level' => $byLevel,
            'by_weekend' => $byWeekend,
        ];
    }

    private function serializeWeekend(Weekend $weekend): array
    {
        $start = $weekend->starts_at;
        $end = $weekend->ends_at;

        $dates = $start->month === $end->month
            ? sprintf('%02d-%02d %s', $start->day, $end->day, self::MONTH_SHORT[$start->month])
            : sprintf('%02d %s-%02d %s', $start->day, self::MONTH_SHORT[$start->month], $end->day, self::MONTH_SHORT[$end->month]);

        return [
            'id' => $weekend->id,
            'name' => $weekend->name,
            'starts_at' => $start->toDateString(),
            'ends_at' => $end->toDateString(),
            'month' => $start->format('Y-m'),
            'label' => $dates,
            'status' => $weekend->status,
        ];
    }

    /**
     * @param  Collection<int, Weekend>  $weekends
     */
    private function monthOptions(Collection $weekends): array
    {
        return $weekends
            ->groupBy(fn (Weekend $weekend): string => $weekend->starts_at->format('Y-m'))
            ->map(fn (Collection $group, string $month): array => [
                'value' => $month,
                'label' => self::MONTH_NAMES[(int) substr($month, 5, 2)].' '.substr($month, 0, 4),
                'weekends' => $group->count(),
            ])
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    private function communityOptions(int $churchId): array
    {
        return Community::query()
            ->whereIn('id', Child::query()->where('church_id', $churchId)->select('community_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Community $community): array => ['id' => $community->id, 'name' => $community->name])
            ->all();
    }

    private function levelOptions(int $churchId): array
    {
        return Level::query()
            ->whereIn('id', ChildLevelAssignment::query()
                ->where('status', Status::ACTIVE)
                ->whereIn('child_id', Child::query()->where('church_id', $churchId)->select('id'))
                ->select('level_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Level $level): array => ['id' => $level->id, 'name' => $level->name])
            ->all();
    }
}
