<?php

namespace App\Http\Controllers\Masses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\WeekendGenerateRequest;
use App\Http\Requests\Masses\WeekendScheduleRequest;
use App\Models\Masses\Weekend;
use App\Services\Masses\WeekendScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WeekendScheduleController extends Controller
{
    public function __construct(private readonly WeekendScheduleService $schedules) {}

    public function edit(Request $request): Response
    {
        $this->authorize('create', Weekend::class);

        return Inertia::render(
            'Masses/Weekends/Schedule',
            $this->schedules->scheduleFormData($request->user(), $request->integer('church_id') ?: null),
        );
    }

    public function update(WeekendScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->schedules->saveSchedule($request->user(), (int) $data['church_id'], $data['masses']);

        return redirect()->route('fines-semana-misas.index')
            ->with('success', 'Misas de fin de semana programadas correctamente.');
    }

    public function preview(WeekendGenerateRequest $request): JsonResponse
    {
        return response()->json($this->schedules->previewForMonth($request->integer('church_id')));
    }

    public function generate(WeekendGenerateRequest $request): RedirectResponse
    {
        $created = $this->schedules->generateForMonth($request->user(), $request->integer('church_id'));

        if ($created === 0) {
            return redirect()->route('fines-semana-misas.index')
                ->with('error', 'No hay fines de semana pendientes por generar en el mes actual.');
        }

        return redirect()->route('fines-semana-misas.index', ['tab' => 'upcoming'])
            ->with('success', "Se generaron {$created} fines de semana con sus misas correctamente.");
    }
}
