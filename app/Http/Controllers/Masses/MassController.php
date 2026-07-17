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

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $weekendId = $request->integer('weekend_id') ?: null;

        return Inertia::render('Masses/Masses/Index', $this->masses->indexData($request->user(), $search, $weekendId));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Masses/Masses/Form', $this->masses->createFormData($request->user()));
    }

    public function store(MassRequest $request): RedirectResponse
    {
        $this->masses->createMass($request->validated());

        return redirect()->route('misas.index')
            ->with('success', 'Misa creada correctamente.');
    }

    public function edit(Request $request, Mass $misa): Response
    {
        return Inertia::render('Masses/Masses/Form', $this->masses->editFormData($request->user(), $misa));
    }

    public function update(MassRequest $request, Mass $misa): RedirectResponse
    {
        $this->masses->updateMass($misa, $request->validated());

        return redirect()->route('misas.index')
            ->with('success', 'Misa actualizada correctamente.');
    }

    public function destroy(Mass $misa): RedirectResponse
    {
        $this->masses->deleteMass($misa);

        return redirect()->route('misas.index')
            ->with('success', 'Misa eliminada correctamente.');
    }
}
