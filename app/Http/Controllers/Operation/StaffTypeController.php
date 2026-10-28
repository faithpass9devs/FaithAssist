<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\StaffTypeRequest;
use App\Models\Operation\StaffType;
use App\Services\Operation\StaffTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffTypeController extends Controller
{
    public function __construct(private readonly StaffTypeService $staffTypes)
    {
        $this->authorizeResource(StaffType::class, 'tipo_personal');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Operation/StaffTypes/Index', $this->staffTypes->indexData($search));
    }

    public function store(StaffTypeRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->staffTypes->createStaffType($request->validated()),
            'message' => 'Tipo de personal creado correctamente.',
        ], 201);
    }

    public function update(StaffTypeRequest $request, StaffType $tipo_personal): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->staffTypes->updateStaffType($tipo_personal, $request->validated()),
            'message' => 'Tipo de personal actualizado correctamente.',
        ]);
    }

    public function destroy(StaffType $tipo_personal): JsonResponse
    {
        $this->staffTypes->deleteStaffType($tipo_personal);

        return response()->json([
            'success' => true,
            'message' => 'Tipo de personal eliminado correctamente.',
        ]);
    }
}
