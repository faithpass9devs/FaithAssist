<?php

namespace App\Services\Catechism;

use App\Globals\Status;
use App\Models\External\ExternalChild;
use App\Models\User;
use App\Repositories\Catechism\ExternosRepository;

class ExternosService
{
    public function __construct(private readonly ExternosRepository $externos) {}

    public function indexData(
        User $user,
        string $search,
        ?int $levelId = null,
        ?int $communityId = null
    ): array {
        $externos = $this->externos->paginateWithFilters($user, $search, $levelId, $communityId);

        $filterOptions = $this->externos->getFilterOptions($user);

        return [
            'externos' => $externos->through(
                fn (ExternalChild $child) => $this->externos->serializeExternalChild($child)
            ),
            'search' => $search,
            'filters' => [
                'level_id' => $levelId,
                'community_id' => $communityId,
            ],
            'communities' => $filterOptions['communities'],
            'levels' => $filterOptions['levels'],
            'sexLabels' => $this->sexLabels(),
            'levelStatusLabels' => $this->levelStatusLabels(),
        ];
    }

    public function showData(User $user, ExternalChild $child): array
    {
        return [
            'externo' => $this->externos->serializeExternalChild($child),
        ];
    }

    private function sexLabels(): array
    {
        return [
            'H' => 'Hombre',
            'M' => 'Mujer',
        ];
    }

    private function levelStatusLabels(): array
    {
        return [
            Status::IN_PROGRESS => 'En progreso',
            Status::COMPLETED => 'Completado',
            Status::WITHDRAW => 'Retirado',
        ];
    }
}
