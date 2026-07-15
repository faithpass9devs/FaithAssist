<?php

namespace App\Http\Controllers\Masses;

use App\Globals\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\WeekendRequest;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\Weekend;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeekendController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Weekend::class, 'weekend');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $scope = new UserScopeService($request->user());

        $weekends = $scope->applyWeekendScope(
            Weekend::query()
                ->with('church:id,name')
                ->when($search, function ($query) use ($search): void {
                    $query->where(function ($builder) use ($search): void {
                        $builder->where('name', 'like', "%{$search}%")
                            ->orWhereHas('church', fn ($church) => $church->where('name', 'like', "%{$search}%"));
                    });
                })
                ->orderByDesc('starts_at')
        )
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Weekend $weekend): array => $this->serializeWeekend($weekend));

        return Inertia::render('Masses/Weekends/Index', [
            'weekends' => $weekends,
            'churches' => $this->churchOptions($request),
            'search' => $search,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Masses/Weekends/Form', [
            'weekend' => null,
            'churches' => $this->churchOptions($request),
        ]);
    }

    public function store(WeekendRequest $request): RedirectResponse
    {
        Weekend::query()->create($request->validated());

        return redirect()->route('fines-semana-misas.index')
            ->with('success', 'Fin de semana creado correctamente.');
    }

    public function edit(Request $request, Weekend $weekend): Response
    {
        return Inertia::render('Masses/Weekends/Form', [
            'weekend' => $this->serializeWeekend($weekend->load('church:id,name'), true),
            'churches' => $this->churchOptions($request),
        ]);
    }

    public function update(WeekendRequest $request, Weekend $weekend): RedirectResponse
    {
        $weekend->update($request->validated());

        return redirect()->route('fines-semana-misas.index')
            ->with('success', 'Fin de semana actualizado correctamente.');
    }

    public function destroy(Weekend $weekend): RedirectResponse
    {
        $weekend->delete();

        return redirect()->route('fines-semana-misas.index')
            ->with('success', 'Fin de semana eliminado correctamente.');
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

    private function serializeWeekend(Weekend $weekend, bool $forForm = false): array
    {
        return [
            'id' => $weekend->id,
            'church_id' => $weekend->church_id,
            'church' => $weekend->church?->name,
            'name' => $weekend->name,
            'starts_at' => $weekend->starts_at?->format($forForm ? 'Y-m-d' : 'Y-m-d h:i A'),
            'ends_at' => $weekend->ends_at?->format($forForm ? 'Y-m-d' : 'Y-m-d h:i A'),
            'status' => $weekend->status,
        ];
    }
}
