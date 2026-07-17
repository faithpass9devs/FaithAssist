<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\PeriodMovementRequest;
use App\Models\Operation\PeriodMovement;
use App\Services\Operation\PeriodMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PeriodMovementController extends Controller
{
    public function __construct(private readonly PeriodMovementService $movements)
    {
        $this->authorizeResource(PeriodMovement::class, 'movimiento');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Operation/PeriodMovements/Index', $this->movements->indexData($request->user(), $search));
    }

    public function store(PeriodMovementRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->movements->createMovement($request->validated()),
            'message' => 'Movimiento creado correctamente.',
        ], 201);
    }

    public function update(PeriodMovementRequest $request, PeriodMovement $movimiento): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->movements->updateMovement($movimiento, $request->validated()),
            'message' => 'Movimiento actualizado correctamente.',
        ]);
    }

    public function destroy(PeriodMovement $movimiento): JsonResponse
    {
        $this->movements->deleteMovement($movimiento);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento eliminado correctamente.',
        ]);
    }
}
