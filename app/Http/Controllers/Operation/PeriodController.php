<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\PeriodRequest;
use App\Models\Operation\Period;
use App\Services\Operation\PeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeriodController extends Controller
{
    public function __construct(private readonly PeriodService $periods)
    {
        $this->authorizeResource(Period::class, 'periodo');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Operation/Periods/Index', $this->periods->indexData($request->user(), $search));
    }

    public function store(PeriodRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->periods->createPeriod($request->validated()),
            'message' => 'Periodo creado correctamente.',
        ], 201);
    }

    public function update(PeriodRequest $request, Period $periodo): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->periods->updatePeriod($periodo, $request->validated()),
            'message' => 'Periodo actualizado correctamente.',
        ]);
    }

    public function destroy(Period $periodo): JsonResponse
    {
        $this->periods->deletePeriod($periodo);

        return response()->json([
            'success' => true,
            'message' => 'Periodo eliminado correctamente.',
        ]);
    }
}
