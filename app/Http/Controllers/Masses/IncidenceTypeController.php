<?php

namespace App\Http\Controllers\Masses;

use App\Globals\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\IncidenceTypeRequest;
use App\Models\Masses\IncidenceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncidenceTypeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(IncidenceType::class, 'incidenceType');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        $incidenceTypes = IncidenceType::query()
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15, ['id', 'name', 'description', 'status'])
            ->withQueryString();

        return Inertia::render('Masses/IncidenceTypes/Index', [
            'incidenceTypes' => $incidenceTypes,
            'search' => $search,
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(IncidenceTypeRequest $request): JsonResponse
    {
        $incidenceType = IncidenceType::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $incidenceType->only(['id', 'name', 'description', 'status']),
            'message' => 'Tipo de incidencia creado correctamente.',
        ], 201);
    }

    public function update(IncidenceTypeRequest $request, IncidenceType $incidenceType): JsonResponse
    {
        $incidenceType->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $incidenceType->fresh()->only(['id', 'name', 'description', 'status']),
            'message' => 'Tipo de incidencia actualizado correctamente.',
        ]);
    }

    public function destroy(IncidenceType $incidenceType): JsonResponse
    {
        $incidenceType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tipo de incidencia eliminado correctamente.',
        ]);
    }

    private function statusOptions(): array
    {
        return [
            ['value' => Status::ACTIVE, 'label' => 'Activo'],
            ['value' => Status::INACTIVE, 'label' => 'Inactivo'],
        ];
    }
}
