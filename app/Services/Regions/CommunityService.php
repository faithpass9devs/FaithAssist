<?php

namespace App\Services\Regions;

use App\Models\Regions\Community;
use App\Models\User;
use App\Repositories\Regions\CommunityRepository;

class CommunityService
{
    public function __construct(private readonly CommunityRepository $communities) {}

    public function indexData(User $user, string $search, ?int $municipalityId = null): array
    {
        return [
            'communities' => $this->communities->paginateWithSearch($user, $search, $municipalityId),
            'municipalities' => $this->communities->activeMunicipalities($user),
            'search' => $search,
            'filters' => [
                'municipality_id' => $municipalityId,
            ],
        ];
    }

    public function createCommunity(array $data): array
    {
        $community = $this->communities->create($data);
        return $this->communityData($community);
    }

    public function updateCommunity(Community $community, array $data): array
    {
        $community = $this->communities->update($community, $data);
        return $this->communityData($community);
    }

    public function deleteCommunity(Community $community): void
    {
        $this->communities->delete($community);
    }

    private function communityData(Community $community): array
    {
        return $community->only(['id', 'municipality_id', 'name', 'status']);
    }
}
