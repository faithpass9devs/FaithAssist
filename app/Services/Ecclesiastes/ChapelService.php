<?php

namespace App\Services\Ecclesiastes;

use App\Models\Ecclesiastes\Chapel;
use App\Models\User;
use App\Repositories\Ecclesiastes\ChapelRepository;

class ChapelService
{
    public function __construct(private readonly ChapelRepository $chapels) {}

    public function indexData(User $user, string $search): array
    {
        return [
            'chapels' => $this->chapels->paginateWithScope($user, $search),
            'communities' => $this->chapels->activeCommunities($user),
            'churches' => $this->chapels->activeChurches($user),
            'search' => $search,
        ];
    }

    public function createChapel(array $data): array
    {
        $chapel = $this->chapels->create($data);
        return $this->chapelData($chapel);
    }

    public function updateChapel(Chapel $chapel, array $data): array
    {
        $chapel = $this->chapels->update($chapel, $data);
        return $this->chapelData($chapel);
    }

    public function deleteChapel(Chapel $chapel): void
    {
        $this->chapels->delete($chapel);
    }

    private function chapelData(Chapel $chapel): array
    {
        return $chapel->only(['id', 'community_id', 'church_id', 'name', 'address', 'status']);
    }
}
