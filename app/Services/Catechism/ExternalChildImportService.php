<?php

namespace App\Services\Catechism;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildLevelAssignment;
use App\Models\Ecclesiastes\Church;
use App\Models\External\ExternalChild;
use App\Models\ExternalChildImport;
use App\Models\Operation\Level;
use App\Models\Operation\PeriodMovement;
use App\Models\Regions\Community;
use App\Models\User;
use App\Repositories\Catechism\ChildRepository;
use App\Services\ChildCodeGenerator;
use App\Support\ExternalDataMapper;
use App\Support\SplitLastNames;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ExternalChildImportService
{
    public const LADA = '52';

    public function __construct(
        private readonly ChildRepository $children,
        private readonly ChildCodeGenerator $codeGenerator,
    ) {}

    /**
     * Datos por defecto para la importación masiva (mismos criterios que el formulario pre-cargado).
     *
     * @return array<string, mixed>
     */
    public function importDefaults(ExternalChild $externo): array
    {
        $lastNames = SplitLastNames::split((string) $externo->last_names);

        return [
            'name' => $externo->name,
            'paterno' => $lastNames['paterno'],
            'materno' => $lastNames['materno'],
            'blood_type' => ExternalDataMapper::bloodType($externo->blood_type),
            'email' => $externo->email,
            'phone' => $externo->phone,
            'emergency_phone' => $externo->emergency_phone,
            'privacy_terms' => true,
        ];
    }

    public function import(User $user, ExternalChild $externo, array $data): Child
    {
        $child = DB::transaction(function () use ($user, $externo, $data): Child {
            if (ExternalChildImport::where('external_child_id', $externo->id)->exists()) {
                abort(409, 'Este externo ya fue importado al módulo de niños.');
            }

            $church = Church::query()->with('deanery:id,diocese_id')->findOrFail($this->resolveLocalChurchId($externo));
            $community = Community::findOrFail($data['community_id'] ?? $externo->community_id);

            $childData = [
                'church_id' => $church->id,
                'community_id' => $community->id,
                'name' => $data['name'],
                'paterno' => $data['paterno'],
                'materno' => $data['materno'] ?? null,
                'origin' => 'imported',
                'birthdate' => $externo->birthdate?->format('Y-m-d'),
                'sex' => ExternalDataMapper::sex($externo->sex),
                'email' => $data['email'] ?? null,
                'phone_lada' => $data['phone_lada'] ?? self::LADA,
                'phone' => ExternalDataMapper::phone($data['phone'] ?? null),
                'emergency_phone_lada' => $data['emergency_phone_lada'] ?? self::LADA,
                'emergency_phone' => ExternalDataMapper::phone($data['emergency_phone'] ?? null),
                'blood_type' => ExternalDataMapper::bloodType($data['blood_type'] ?? $externo->blood_type),
                'observations' => $data['observations'] ?? $externo->notes,
                'privacy_terms' => true,
                'status' => ExternalDataMapper::status($externo->status),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];

            $child = $this->createChildWithCode($childData);

            $levelIds = collect($data['level_ids'] ?? [])->filter()->unique()->values();

            if ($levelIds->isEmpty()) {
                $levels = $this->resolveLevelsForExterno($externo);
                $targetLevelId = $levels['targetLevel']['id'] ?? null;

                if ($targetLevelId !== null) {
                    $levelIds = collect([$targetLevelId]);
                }
            }

            $movement = $this->lastPeriodMovementForChurch($externo);

            if ($levelIds->isNotEmpty() && $movement !== null) {
                foreach ($levelIds as $levelId) {
                    ChildLevelAssignment::create([
                        'child_id' => $child->id,
                        'level_id' => $levelId,
                        'period_id' => $movement->period_id,
                        'period_movement_id' => $movement->id,
                        'status' => Status::ACTIVE,
                        'assigned_at' => now()->toDateString(),
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            ExternalChildImport::create([
                'external_child_id' => $externo->id,
                'child_id' => $child->id,
                'imported_by' => $user->id,
            ]);

            return $child;
        });

        // Los datos de la BD remota de Hostinger no se modifican, por lo que la
        // caché del dataset completo (24h) sigue siendo válida tras importar.
        // El estado "importado" se calcula en vivo contra la tabla local
        // ExternalChildImport en cada petición.

        return $child;
    }

    /**
     * Resuelve el nivel primario actual (Hostinger) y el siguiente nivel local.
     *
     * @return array{currentLevel:?array{id:int,name:string}, targetLevel:?array{id:int,name:string}}
     */
    public function resolveLevelsForExterno(ExternalChild $externo): array
    {
        $dioceseId = Church::query()
            ->with('deanery:id,diocese_id')
            ->find($this->resolveLocalChurchId($externo))
            ?->deanery
            ?->diocese_id;

        if (! $dioceseId) {
            return ['currentLevel' => null, 'targetLevel' => null];
        }

        $localLevels = Level::query()
            ->where('diocese_id', $dioceseId)
            ->where('status', Status::ACTIVE)
            ->get(['id', 'name'])
            ->sortBy(fn (Level $level): array => [
                (int) preg_replace('/\D+/', '', $level->name) ?: PHP_INT_MAX,
                $level->name,
            ])
            ->values();

        if ($localLevels->isEmpty()) {
            return ['currentLevel' => null, 'targetLevel' => null];
        }

        $currentLevelId = $this->externalCurrentPrimaryLevelId($externo);
        $currentIndex = $currentLevelId !== null
            ? $localLevels->search(fn (Level $level): bool => $level->id === $currentLevelId)
            : false;

        $currentLevel = $currentIndex === false ? null : $localLevels[$currentIndex];
        $targetLevel = $currentIndex === false
            ? $localLevels->first()
            : $localLevels[$currentIndex + 1] ?? null;

        return [
            'currentLevel' => $currentLevel
                ? ['id' => $currentLevel->id, 'name' => $currentLevel->name]
                : null,
            'targetLevel' => $targetLevel
                ? ['id' => $targetLevel->id, 'name' => $targetLevel->name]
                : null,
        ];
    }

    private function externalCurrentPrimaryLevelId(ExternalChild $externo): ?int
    {
        $periods = $externo->childPeriods->sortByDesc('id');

        foreach ($periods as $period) {
            $primary = $period->levels->firstWhere('is_primary', true);

            if ($primary?->level_id) {
                return $primary->level_id;
            }
        }

        foreach ($periods as $period) {
            if ($period->primary_level_id) {
                return $period->primary_level_id;
            }
        }

        return null;
    }

    private function lastPeriodMovementForChurch(ExternalChild $externo): ?PeriodMovement
    {
        return PeriodMovement::query()
            ->where('church_id', $this->resolveLocalChurchId($externo))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    private function resolveLocalChurchId(ExternalChild $externo): int
    {
        return $externo->church_id ?? 1;
    }

    private function createChildWithCode(array $childData): Child
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $childData['code'] = $this->codeGenerator->generate($childData);

            try {
                return $this->children->create($childData);
            } catch (QueryException $e) {
                $sqlState = $e->errorInfo[0] ?? null;
                $message = $e->getMessage();
                $isUniqueViolation = in_array($sqlState, ['23000', '23505'], true)
                    && (str_contains($message, 'children.code') || str_contains($message, 'children_code_unique'));

                if (! $isUniqueViolation) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Unable to generate a unique child code.');
    }
}
