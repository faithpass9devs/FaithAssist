<?php

namespace App\Services\Catechism;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildLevelAssignment;
use App\Models\Catechism\ChildReinscription;
use App\Models\User;
use App\Repositories\Catechism\ChildRepository;
use App\Services\CatechismPeriodMovementService;
use App\Services\ChildCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ChildService
{
    public function __construct(
        private readonly ChildRepository $children,
        private readonly ChildCodeGenerator $codeGenerator,
        private readonly ChildBatchPdfService $pdfBatchService,
        private readonly ChildImportService $childImportService,
    ) {}

    public function indexData(
        User $user,
        string $search,
        ?int $churchId = null,
        ?int $municipalityId = null,
        ?int $communityId = null,
        ?int $levelId = null,
        ?string $status = null,
        ?string $origin = null
    ): array {
        $children = $this->children->paginateWithFilters(
            $user,
            $search,
            $churchId,
            $municipalityId,
            $communityId,
            $levelId,
            $status,
            $origin
        );

        $filterOptions = $this->children->getFilterOptions($user);

        $serialized = $children->through(fn (Child $child) => $this->children->serializeChild($child));

        return [
            'children' => $serialized,
            'search' => $search,
            'filters' => [
                'church_id' => $churchId,
                'municipality_id' => $municipalityId,
                'community_id' => $communityId,
                'level_id' => $levelId,
                'status' => $status,
                'origin' => $origin,
            ],
            'churches' => $filterOptions['churches'],
            'municipalities' => $filterOptions['municipalities'],
            'communities' => $filterOptions['communities'],
            'levels' => $filterOptions['levels'],
            'statuses' => $this->options($this->statusLabels()),
            'statusLabels' => $this->statusLabels(),
            'sexLabels' => $this->sexLabels(),
            'bloodTypeLabels' => $this->bloodTypeLabels(),
            'latestPdfExportBatch' => $this->pdfBatchService->latestBatch($user),
            'latestImportBatch' => $user->hasRole('Superadmin')
                ? $this->childImportService->latestBatch($user)
                : null,
        ];
    }

    public function getFormData(User $user): array
    {
        $filterOptions = $this->children->getFormOptions($user);

        return [
            'child' => null,
            'churches' => $filterOptions['churches'],
            'municipalities' => $filterOptions['municipalities'],
            'communities' => $filterOptions['communities'],
            'levels' => $filterOptions['levels'],
            'countryCodes' => $this->children->getLadaOptions(),
            'defaultCountryCode' => $this->children->getDefaultLada(),
            'statuses' => $this->options($this->statusLabels()),
            'sexes' => $this->options($this->sexLabels()),
            'bloodTypes' => $this->options($this->bloodTypeLabels()),
        ];
    }

    public function getEditData(User $user, Child $child): array
    {
        $filterOptions = $this->children->getFormOptions($user);

        return [
            'child' => $this->children->serializeChild($child),
            'churches' => $filterOptions['churches'],
            'municipalities' => $filterOptions['municipalities'],
            'communities' => $filterOptions['communities'],
            'levels' => $filterOptions['levels'],
            'countryCodes' => $this->children->getLadaOptions(),
            'defaultCountryCode' => $this->children->getDefaultLada(),
            'statuses' => $this->options($this->statusLabels()),
            'sexes' => $this->options($this->sexLabels()),
            'bloodTypes' => $this->options($this->bloodTypeLabels()),
        ];
    }

    public function createChild(
        array $data,
        User $user,
        CatechismPeriodMovementService $movementService
    ): Child {
        $levelIds = collect($data['level_ids'] ?? [])->unique()->values();
        $movement = $movementService->requireActiveMovementForChurch(
            (int) $data['church_id'],
            CatechismPeriodMovementService::INSCRIPTIONS
        );

        $child = DB::transaction(function () use ($data, $levelIds, $movement, $user): Child {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $data['code'] = $this->codeGenerator->generate($data);

                try {
                    $child = $this->children->create(Arr::except($data, ['level_ids']));

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

                    return $child;
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

            throw new \RuntimeException('Unable to generate a unique child code.');
        });

        return $child;
    }

    public function updateChild(
        Child $child,
        array $data,
        User $user,
        CatechismPeriodMovementService $movementService
    ): Child
    {
        $levelIds = collect($data['level_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values();
        $activeLevelIds = $child->activeLevelAssignments()->pluck('level_id')->map(fn ($id) => (int) $id)->sort()->values();
        $levelsChanged = $levelIds->isNotEmpty() && $levelIds->all() !== $activeLevelIds->all();

        return DB::transaction(function () use ($child, $data, $user, $movementService, $levelIds, $activeLevelIds, $levelsChanged): Child {
            $this->children->update($child, Arr::only($data, [
                'church_id',
                'community_id',
                'name',
                'paterno',
                'materno',
                'birthdate',
                'sex',
                'email',
                'phone_lada',
                'phone',
                'emergency_phone_lada',
                'emergency_phone',
                'blood_type',
                'observations',
                'privacy_terms',
                'status',
            ]));

            if (! $levelsChanged) {
                return $child;
            }

            $child->loadMissing('church:id,name,deanery_id');
            $movement = $movementService->requireActiveMovementForChurch(
                $child->church,
                CatechismPeriodMovementService::REINSCRIPTIONS,
                'level_ids'
            );

            ChildReinscription::create([
                'child_id' => $child->id,
                'period_id' => $movement->period_id,
                'period_movement_id' => $movement->id,
                'from_level_ids' => $activeLevelIds->all(),
                'to_level_ids' => $levelIds->all(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            ChildLevelAssignment::query()
                ->where('child_id', $child->id)
                ->where('status', Status::ACTIVE)
                ->update([
                    'status' => Status::COMPLETED,
                    'ended_at' => now()->toDateString(),
                    'updated_by' => $user->id,
                ]);

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

            return $child;
        });
    }

    public function deleteChild(Child $child): void
    {
        $this->children->delete($child);
    }

    private function statusLabels(): array
    {
        return [
            Status::ACTIVE => 'Activo',
            Status::INACTIVE => 'Inactivo',
            Status::COMPLETED => 'Completado',
            Status::WITHDRAW => 'Retirado',
            Status::SUSPENDED => 'Suspendido',
        ];
    }

    private function sexLabels(): array
    {
        return [
            Sex::MALE => 'Masculino',
            Sex::FEMALE => 'Femenino',
        ];
    }

    private function bloodTypeLabels(): array
    {
        return [
            BloodType::A_POSITIVE => 'A+',
            BloodType::A_NEGATIVE => 'A-',
            BloodType::B_POSITIVE => 'B+',
            BloodType::B_NEGATIVE => 'B-',
            BloodType::AB_POSITIVE => 'AB+',
            BloodType::AB_NEGATIVE => 'AB-',
            BloodType::O_POSITIVE => 'O+',
            BloodType::O_NEGATIVE => 'O-',
            BloodType::UNKNOWN => 'Desconocido',
        ];
    }

    private function options(array $labels): array
    {
        return collect($labels)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}
