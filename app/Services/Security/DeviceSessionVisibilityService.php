<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Collection;

class DeviceSessionVisibilityService
{
    private const ROLE_RANKS = [
        'Capturista' => 10,
        'Coordinador de Parroquia' => 20,
        'Superadmin' => 30,
    ];

    public function visibleUserIds(User $viewer): Collection
    {
        if ($viewer->hasRole('Superadmin')) {
            return User::query()->pluck('id');
        }

        $viewerRank = $this->rank($viewer);
        if ($viewerRank === 0) {
            return collect();
        }

        $lowerRoles = collect(self::ROLE_RANKS)
            ->filter(fn (int $rank): bool => $rank < $viewerRank)
            ->keys()
            ->values();

        $query = User::query()
            ->where('id', '!=', $viewer->id)
            ->where(function ($users) use ($lowerRoles): void {
                if ($lowerRoles->isNotEmpty()) {
                    $users->whereHas('roles', fn ($roles) => $roles->whereIn('name', $lowerRoles))
                        ->orWhereDoesntHave('roles');
                } else {
                    $users->whereDoesntHave('roles');
                }
            });

        if ($lowerRoles->isNotEmpty()) {
            $query->whereDoesntHave('roles', fn ($roles) => $roles->whereNotIn('name', $lowerRoles));
        }

        if ($viewer->chapel_id !== null) {
            $query->where('chapel_id', $viewer->chapel_id);
        } elseif ($viewer->church_id !== null) {
            $query->where('church_id', $viewer->church_id);
        } else {
            return collect();
        }

        return $query->pluck('id');
    }

    public function canView(User $viewer, User $target): bool
    {
        return $this->visibleUserIds($viewer)->contains($target->id);
    }

    private function rank(User $user): int
    {
        return $user->getRoleNames()
            ->map(fn (string $role): int => self::ROLE_RANKS[$role] ?? 0)
            ->max() ?? 0;
    }
}
