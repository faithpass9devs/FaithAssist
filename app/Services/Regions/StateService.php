<?php

namespace App\Services\Regions;

use App\Models\Regions\State;
use App\Models\User;
use App\Repositories\Regions\StateRepository;

class StateService
{
    public function __construct(private readonly StateRepository $states) {}

    public function indexData(User $user, string $search): array
    {
        return [
            'states' => $this->states->paginateVisibleStates($user, $search),
            'search' => $search,
        ];
    }

    public function createState(array $data): array
    {
        return $this->stateData($this->states->create($data));
    }

    public function updateState(State $state, array $data): array
    {
        return $this->stateData($this->states->update($state, $data));
    }

    public function deleteState(State $state): void
    {
        $this->states->delete($state);
    }

    private function stateData(State $state): array
    {
        return $state->only(['id', 'name', 'short_name', 'status']);
    }
}
