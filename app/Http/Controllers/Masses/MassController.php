<?php

namespace App\Http\Controllers\Masses;

use App\Globals\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\MassRequest;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MassController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Mass::class, 'misa');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $weekendId = $request->integer('weekend_id') ?: null;
        $scope = new UserScopeService($request->user());

        $masses = $scope->applyMassScope(
            Mass::query()
                ->with(['weekend:id,name,starts_at,ends_at', 'church:id,name', 'chapel:id,name'])
                ->when($weekendId, fn ($query) => $query->where('weekend_id', $weekendId))
                ->when($search, function ($query) use ($search): void {
                    $query->where(function ($builder) use ($search): void {
                        $builder->where('name', 'like', "%{$search}%")
                            ->orWhereHas('church', fn ($church) => $church->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('chapel', fn ($chapel) => $chapel->where('name', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('starts_at')
        )
            ->paginate(72)
            ->withQueryString()
            ->through(fn (Mass $mass): array => $this->serializeMass($mass));

        return Inertia::render('Masses/Masses/Index', [
            'masses' => $masses,
            'weekends' => $this->weekendOptions($request),
            'churches' => $this->churchOptions($request),
            'chapels' => $this->chapelOptions($request),
            'search' => $search,
            'filters' => [
                'weekend_id' => $weekendId,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Masses/Masses/Form', [
            'mass' => null,
            'weekends' => $this->weekendOptions($request),
            'churches' => $this->churchOptions($request),
            'chapels' => $this->chapelOptions($request),
        ]);
    }

    public function store(MassRequest $request): RedirectResponse
    {
        Mass::query()->create($request->validated());

        return redirect()->route('misas.index')
            ->with('success', 'Misa creada correctamente.');
    }

    public function edit(Request $request, Mass $misa): Response
    {
        $misa->loadMissing(['weekend:id,name,starts_at,ends_at', 'church:id,name', 'chapel:id,name']);

        return Inertia::render('Masses/Masses/Form', [
            'mass' => $this->serializeMass($misa, true),
            'weekends' => $this->weekendOptions($request),
            'churches' => $this->churchOptions($request),
            'chapels' => $this->chapelOptions($request),
        ]);
    }

    public function update(MassRequest $request, Mass $misa): RedirectResponse
    {
        $misa->update($request->validated());

        return redirect()->route('misas.index')
            ->with('success', 'Misa actualizada correctamente.');
    }

    public function destroy(Mass $misa): RedirectResponse
    {
        $misa->delete();

        return redirect()->route('misas.index')
            ->with('success', 'Misa eliminada correctamente.');
    }

    private function weekendOptions(Request $request): array
    {
        $scope = new UserScopeService($request->user());

        return $scope->applyWeekendScope(
            Weekend::query()
                ->with('church:id,name')
                ->orderByDesc('starts_at')
        )
            ->get(['id', 'church_id', 'name', 'starts_at', 'ends_at', 'status'])
            ->map(fn (Weekend $weekend): array => [
                'id' => $weekend->id,
                'church_id' => $weekend->church_id,
                'name' => $weekend->name ?: $weekend->starts_at?->format('Y-m-d'),
                'starts_at' => $weekend->starts_at?->format('Y-m-d h:i A'),
                'ends_at' => $weekend->ends_at?->format('Y-m-d h:i A'),
                'status' => $weekend->status,
                'church' => $weekend->church?->name,
            ])
            ->all();
    }

    private function churchOptions(Request $request): array
    {
        $scope = new UserScopeService($request->user());

        return Church::query()
            ->when(! $scope->isGlobal(), fn ($query) => $query->whereIn('id', $scope->churchIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->all();
    }

    private function chapelOptions(Request $request): array
    {
        $user = $request->user();
        $scope = new UserScopeService($request->user());

        if (! $scope->isGlobal() && ! $user->can('masses.scope.all') && $user->chapel_id === null) {
            return [];
        }

        return Chapel::query()
            ->when(! $scope->isGlobal(), fn ($query) => $query->whereIn('id', $scope->chapelIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'church_id', 'name'])
            ->all();
    }

    private function serializeMass(Mass $mass, bool $forForm = false): array
    {
        return [
            'id' => $mass->id,
            'weekend_id' => $mass->weekend_id,
            'church_id' => $mass->church_id,
            'chapel_id' => $mass->chapel_id,
            'name' => $mass->name,
            'starts_at' => $mass->starts_at?->format($forForm ? 'Y-m-d\TH:i' : 'Y-m-d h:i A'),
            'ends_at' => $mass->ends_at?->format($forForm ? 'Y-m-d\TH:i' : 'Y-m-d h:i A'),
            'status' => $mass->status,
            'attendance_status' => $mass->attendance_status,
            'notes' => $mass->notes,
            'weekend' => $mass->weekend?->name ?: $mass->weekend?->starts_at?->format('Y-m-d'),
            'church' => $mass->church?->name,
            'chapel' => $mass->chapel?->name,
            'location' => $mass->chapel?->name ?: $mass->church?->name,
        ];
    }
}
