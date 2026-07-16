<?php

namespace App\Http\Controllers\Masses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\IncidenceTypeRequest;
use App\Models\Masses\IncidenceType;
use App\Services\Masses\IncidenceTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncidenceTypeController extends Controller
{
    public function __construct(private readonly IncidenceTypeService $incidenceTypes)
    {
        $this->authorizeResource(IncidenceType::class, 'incidenceType');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Masses/IncidenceTypes/Index', $this->incidenceTypes->indexData($search));
    }

    public function store(IncidenceTypeRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->incidenceTypes->createIncidenceType($request->validated()),
            'message' => 'Tipo de incidencia creado correctamente.',
        ], 201);
    }

    public function update(IncidenceTypeRequest $request, IncidenceType $incidenceType): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->incidenceTypes->updateIncidenceType($incidenceType, $request->validated()),
            'message' => 'Tipo de incidencia actualizado correctamente.',
        ]);
    }

    public function destroy(IncidenceType $incidenceType): JsonResponse
    {
        $this->incidenceTypes->deleteIncidenceType($incidenceType);

        return response()->json([
            'success' => true,
            'message' => 'Tipo de incidencia eliminado correctamente.',
        ]);
    }
}
