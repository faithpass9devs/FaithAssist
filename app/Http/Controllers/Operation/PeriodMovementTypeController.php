<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\PeriodMovementTypeRequest;
use App\Models\Operation\PeriodMovementType;
use App\Services\Operation\PeriodMovementTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeriodMovementTypeController extends Controller
{
    public function __construct(private readonly PeriodMovementTypeService $types)
    {
        $this->authorizeResource(PeriodMovementType::class, 'tipo_movimiento_periodo');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Operation/PeriodMovementTypes/Index', $this->types->indexData($search));
    }

    public function store(PeriodMovementTypeRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->types->createMovementType($request->validated()),
            'message' => 'Tipo de movimiento creado correctamente.',
        ], 201);
    }

    public function update(PeriodMovementTypeRequest $request, PeriodMovementType $tipoMovimientoPeriodo): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->types->updateMovementType($tipoMovimientoPeriodo, $request->validated()),
            'message' => 'Tipo de movimiento actualizado correctamente.',
        ]);
    }

    public function destroy(PeriodMovementType $tipoMovimientoPeriodo): JsonResponse
    {
        $this->types->deleteMovementType($tipoMovimientoPeriodo);

        return response()->json([
            'success' => true,
            'message' => 'Tipo de movimiento eliminado correctamente.',
        ]);
    }
}
