<?php

namespace App\Repositories\Masses;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Event;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EventRepository
{
    public function queryWithScope(User $user, string $search): Builder
    {
        $scope = new UserScopeService($user);

        return $scope->applyEventScope(
            Event::query()
                ->with('church:id,name')
                ->when($search, function ($query) use ($search): void {
                    $query->where(function ($builder) use ($search): void {
                        $builder->where('name', 'like', "%{$search}%")
                            ->orWhereHas('church', fn ($church) => $church->where('name', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('starts_at')
        );
    }

    public function paginateQuery(Builder $query, int $perPage = 5, string $pageName = 'page'): LengthAwarePaginator
    {
        return $query->paginate($perPage, ['*'], $pageName)->withQueryString();
    }

    public function activeChurches(User $user)
    {
        $scope = new UserScopeService($user);

        return Church::query()
            ->when(! $scope->isGlobal(), fn ($query) => $query->whereIn('id', $scope->churchIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function create(array $data): Event
    {
        return Event::create($data);
    }

    public function update(Event $event, array $data): Event
    {
        $event->update($data);

        return $event->fresh();
    }

    public function delete(Event $event, ?int $userId = null): void
    {
        $event->forceFill(['deleted_by' => $userId])->save();
        $event->delete();
    }

    public function serializeEvent(Event $event, bool $forForm = false): array
    {
        return [
            'id' => $event->id,
            'church_id' => $event->church_id,
            'church' => $event->church?->name,
            'name' => $event->name,
            'starts_at' => $event->starts_at?->format($forForm ? 'Y-m-d' : 'Y-m-d h:i A'),
            'ends_at' => $event->ends_at?->format($forForm ? 'Y-m-d' : 'Y-m-d h:i A'),
            'status' => $event->status,
            'attendance_check_in_status' => $event->attendance_check_in_status,
            'attendance_check_out_status' => $event->attendance_check_out_status,
            'notes' => $event->notes,
        ];
    }
}
