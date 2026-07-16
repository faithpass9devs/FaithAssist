<?php

namespace App\Http\Controllers\Masses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\WeekendRequest;
use App\Models\Masses\Weekend;
use App\Services\Masses\WeekendService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeekendController extends Controller
{
    public function __construct(private readonly WeekendService $weekends)
    {
        $this->authorizeResource(Weekend::class, 'weekend');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Masses/Weekends/Index', $this->weekends->indexData($request->user(), $search));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Masses/Weekends/Form', $this->weekends->createFormData($request->user()));
    }

    public function store(WeekendRequest $request): RedirectResponse
    {
        $this->weekends->createWeekend($request->validated());

        return redirect()->route('fines-semana-misas.index')
            ->with('success', 'Fin de semana creado correctamente.');
    }

    public function edit(Request $request, Weekend $weekend): Response
    {
        return Inertia::render('Masses/Weekends/Form', $this->weekends->editFormData($request->user(), $weekend));
    }

    public function update(WeekendRequest $request, Weekend $weekend): RedirectResponse
    {
        $this->weekends->updateWeekend($weekend, $request->validated());

        return redirect()->route('fines-semana-misas.index')
            ->with('success', 'Fin de semana actualizado correctamente.');
    }

    public function destroy(Weekend $weekend): RedirectResponse
    {
        $this->weekends->deleteWeekend($weekend);

        return redirect()->route('fines-semana-misas.index')
            ->with('success', 'Fin de semana eliminado correctamente.');
    }
}
