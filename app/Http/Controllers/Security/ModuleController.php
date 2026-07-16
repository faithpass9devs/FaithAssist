<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Http\Requests\Security\ModuleRequest;
use App\Models\Module;
use App\Services\Security\ModuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function __construct(private readonly ModuleService $modules)
    {
        $this->authorizeResource(Module::class, 'modulo');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Security/Modules/Index', $this->modules->indexData($search));
    }

    public function store(ModuleRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->modules->createModule($request->validated()),
            'message' => 'Modulo creado correctamente.',
        ], 201);
    }

    public function update(ModuleRequest $request, Module $modulo): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->modules->updateModule($modulo, $request->validated()),
            'message' => 'Modulo actualizado correctamente.',
        ]);
    }

    public function destroy(Module $modulo): JsonResponse
    {
        $this->modules->deleteModule($modulo);

        return response()->json([
            'success' => true,
            'message' => 'Modulo eliminado correctamente.',
        ]);
    }
}
