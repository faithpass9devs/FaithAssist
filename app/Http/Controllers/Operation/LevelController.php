<?php

namespace App\Http\Controllers\Operation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operation\LevelRequest;
use App\Models\Operation\Level;
use App\Services\Operation\LevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LevelController extends Controller
{
    public function __construct(private readonly LevelService $levels)
    {
        $this->authorizeResource(Level::class, 'nivel');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Operation/Levels/Index', $this->levels->indexData($request->user(), $search));
    }

    public function store(LevelRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->levels->createLevel($request->validated()),
            'message' => 'Nivel creado exitosamente.',
        ], 201);
    }

    public function update(LevelRequest $request, Level $nivel): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->levels->updateLevel($nivel, $request->validated()),
            'message' => 'Nivel actualizado exitosamente.',
        ]);
    }

    public function destroy(Level $nivel): JsonResponse
    {
        $this->levels->deleteLevel($nivel);

        return response()->json([
            'success' => true,
            'message' => 'Nivel eliminado exitosamente.',
        ]);
    }
}
