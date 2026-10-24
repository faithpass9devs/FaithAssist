<?php

namespace App\Http\Controllers\Masses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\MassRequest;
use App\Models\Masses\Mass;
use App\Services\Masses\MassService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MassController extends Controller
{
    public function __construct(private readonly MassService $masses)
    {
        $this->authorizeResource(Mass::class, 'misa');
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $weekend = $this->masses->findVisibleWeekend($request->user(), $request->integer('weekend_id') ?: null);

        if (! $weekend) {
            return redirect()->route('fines-semana-misas.index')
                ->with('error', 'Selecciona un fin de semana para ver sus misas.');
        }

        return Inertia::render(
            'Masses/Masses/Index',
            $this->masses->indexData($request->user(), $weekend, (string) $request->input('search', ''))
        );
    }

    public function create(Request $request): Response
    {
        return Inertia::render(
            'Masses/Masses/Form',
            $this->masses->createFormData($request->user(), $request->integer('weekend_id') ?: null)
        );
    }

    public function store(MassRequest $request): RedirectResponse
    {
        $mass = $this->masses->createMass($request->validated());

        return redirect()->route('misas.index', ['weekend_id' => $mass->weekend_id])
            ->with('success', 'Misa creada correctamente.');
    }

    public function edit(Request $request, Mass $misa): Response
    {
        return Inertia::render('Masses/Masses/Form', $this->masses->editFormData($request->user(), $misa));
    }

    public function update(MassRequest $request, Mass $misa): RedirectResponse
    {
        $mass = $this->masses->updateMass($misa, $request->validated());

        return redirect()->route('misas.index', ['weekend_id' => $mass->weekend_id])
            ->with('success', 'Misa actualizada correctamente.');
    }

    public function destroy(Mass $misa): RedirectResponse
    {
        $weekendId = $misa->weekend_id;
        $this->masses->deleteMass($misa);

        return redirect()->route('misas.index', ['weekend_id' => $weekendId])
            ->with('success', 'Misa eliminada correctamente.');
    }
}
