<?php

namespace App\Services\Masses;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Event;
use App\Models\User;
use App\Repositories\Masses\EventRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventService
{
    public function __construct(private readonly EventRepository $events) {}

    public function indexData(User $user, string $search, string $tab = 'current'): array
    {
        $query = $this->events->queryWithScope($user, $search);
        $events = match ($tab) {
            'history' => (clone $query)->completed()->reorder('starts_at', 'desc'),
            'upcoming' => (clone $query)->upcoming()->reorder('starts_at'),
            default => (clone $query)->progress(),
        };

        return [
            'events' => $this->events->paginateQuery($events, 5, 'events_page')
                ->through(fn (Event $event) => $this->events->serializeEvent($event)),
            'search' => $search,
            'tab' => $tab,
        ];
    }

    public function createFormData(User $user): array
    {
        return [
            'event' => null,
            'churches' => $this->events->activeChurches($user),
            'lockedChurchId' => $this->lockedChurchId($user),
        ];
    }

    public function editFormData(User $user, Event $event): array
    {
        $event->load('church:id,name');

        return [
            'event' => $this->events->serializeEvent($event, true),
            'churches' => $this->events->activeChurches($user),
            'lockedChurchId' => $this->lockedChurchId($user),
        ];
    }

    public function createEvent(array $data): Event
    {
        if (($data['status'] ?? null) !== Status::UPCOMING) {
            throw ValidationException::withMessages([
                'status' => 'Los eventos nuevos deben crearse como próximos.',
            ]);
        }

        return DB::transaction(function () use ($data): Event {
            Church::query()->whereKey($data['church_id'])->lockForUpdate()->firstOrFail();
            $this->ensureStatusAvailable((int) $data['church_id'], $data['status']);

            return $this->events->create($data);
        });
    }

    public function updateEvent(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data): Event {
            $churchIds = collect([$event->church_id, $data['church_id']])
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->sort()
                ->values();

            Church::query()
                ->whereIn('id', $churchIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);
            $updatedEvent = $this->events->update($lockedEvent, $data);

            $this->ensureStatusAvailable(
                (int) $updatedEvent->church_id,
                $updatedEvent->status,
                $updatedEvent->id,
            );

            return $updatedEvent;
        });
    }

    public function deleteEvent(Event $event, ?int $userId = null): void
    {
        DB::transaction(fn () => $this->events->delete($event, $userId));
    }

    private function ensureStatusAvailable(int $churchId, string $status, ?int $ignoreEventId = null): void
    {
        if ($status !== Status::IN_PROGRESS) {
            return;
        }

        $exists = Event::query()
            ->where('church_id', $churchId)
            ->where('status', $status)
            ->when($ignoreEventId, fn ($query) => $query->whereKeyNot($ignoreEventId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'status' => 'Solo puede existir un evento en curso por parroquia.',
            ]);
        }
    }

    private function lockedChurchId(User $user): ?int
    {
        return ! $user->can('eventos.scope.all') && $user->church_id !== null
            ? (int) $user->church_id
            : null;
    }
}
