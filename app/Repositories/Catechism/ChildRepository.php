<?php

namespace App\Repositories\Catechism;

use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Ecclesiastes\Church;
use App\Models\Lada;
use App\Models\Operation\Level;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Models\User;
use App\Services\UserScopeService;

class ChildRepository
{
    public function paginateWithFilters(
        User $user,
        string $search,
        ?int $churchId = null,
        ?int $municipalityId = null,
        ?int $communityId = null,
        ?int $levelId = null,
        ?string $status = null,
        ?string $origin = null
    ) {
        $scope = new UserScopeService($user);

        $query = Child::query()
            ->with(['church:id,name', 'community:id,name', 'activeLevelAssignments.level:id,name'])
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when($churchId, fn ($query) => $query->where('church_id', $churchId))
            ->when($communityId, fn ($query) => $query->where('community_id', $communityId))
            ->when($levelId, fn ($query) => $query->whereHas(
                'activeLevelAssignments',
                fn ($assignment) => $assignment->where('level_id', $levelId)
            ))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($origin, fn ($query) => $query->where('origin', $origin))
            ->when($municipalityId, function ($query) use ($municipalityId) {
                $query->where(function ($builder) use ($municipalityId) {
                    $builder->whereHas('church', fn ($church) => $church->where('municipality_id', $municipalityId))
                        ->orWhereHas('community', fn ($community) => $community->where('municipality_id', $municipalityId));
                });
            })
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('name');

        return ($scope->isGlobal() ? $query : $scope->applyChildScope($query))
            ->paginate(15)
            ->withQueryString();
    }

    public function findOrFail(int $id): Child
    {
        return Child::query()
            ->with(['church:id,name', 'community:id,name', 'activeLevelAssignments.level:id,name'])
            ->findOrFail($id);
    }

    public function create(array $data): Child
    {
        return Child::create($data);
    }

    public function update(Child $child, array $data): Child
    {
        $child->update($data);
        return $child->fresh();
    }

    public function delete(Child $child): void
    {
        $child->delete();
    }

    public function getFormOptions(User $user): array
    {
        return $this->filterOptions($user);
    }

    public function getFilterOptions(User $user): array
    {
        return $this->filterOptions($user);
    }

    private function filterOptions(User $user): array
    {
        $scope = new UserScopeService($user);

        return [
            'churches' => Church::query()
                ->with('deanery:id,diocese_id')
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->churchIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'deanery_id', 'municipality_id', 'name'])
                ->map(fn (Church $church): array => [
                    'id' => $church->id,
                    'municipality_id' => $church->municipality_id,
                    'diocese_id' => $church->deanery?->diocese_id,
                    'name' => $church->name,
                ])
                ->values(),
            'municipalities' => Municipality::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->municipalityIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']),
            'communities' => Community::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->communityIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'municipality_id', 'name']),
            'levels' => Level::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('diocese_id', $scope->dioceseIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'diocese_id', 'name']),
        ];
    }

    public function serializeChild(Child $child): array
    {
        return [
            'id' => $child->id,
            'church_id' => $child->church_id,
            'community_id' => $child->community_id,
            'name' => $child->name,
            'paterno' => $child->paterno,
            'materno' => $child->materno,
            'full_name' => trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' ')),
            'code' => $child->code,
            'birthdate' => $child->birthdate?->format('Y-m-d'),
            'sex' => $child->sex,
            'email' => $child->email,
            'phone_lada' => $child->phone_lada,
            'phone' => $child->phone,
            'emergency_phone_lada' => $child->emergency_phone_lada,
            'emergency_phone' => $child->emergency_phone,
            'blood_type' => $child->blood_type,
            'observations' => $child->observations,
            'privacy_terms' => $child->privacy_terms,
            'badge_pdf_downloaded_at' => $child->badge_pdf_downloaded_at?->format('Y-m-d H:i:s'),
            'badge_pdf_downloaded' => ! empty($child->badge_pdf_downloaded_at),
            'status' => $child->status,
            'church' => $child->church?->name,
            'community' => $child->community?->name,
            'levels' => $child->activeLevelAssignments
                ->map(fn ($assignment): array => [
                    'id' => $assignment->level?->id,
                    'name' => $assignment->level?->name,
                    'assignment_id' => $assignment->id,
                ])
                ->filter(fn (array $level): bool => $level['id'] !== null)
                ->values()
                ->all(),
            'created_at' => $child->created_at?->format('d/m/Y'),
        ];
    }

    public function getLadaOptions(): array
    {
        return Lada::options();
    }

    public function getDefaultLada(): string
    {
        return Lada::defaultCode();
    }
}
