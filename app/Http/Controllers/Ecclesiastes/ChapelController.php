<?php

namespace App\Http\Controllers\Ecclesiastes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ecclesiastes\ChapelRequest;
use App\Models\Ecclesiastes\Chapel;
use App\Services\Ecclesiastes\ChapelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChapelController extends Controller
{
    public function __construct(private readonly ChapelService $chapels)
    {
        $this->authorizeResource(Chapel::class, 'capilla');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Ecclesiastes/Chapels/Index', $this->chapels->indexData($request->user(), $search));
    }

    public function store(ChapelRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->chapels->createChapel($request->validated()),
            'message' => 'Capilla creada correctamente.',
        ], 201);
    }

    public function update(ChapelRequest $request, Chapel $capilla): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->chapels->updateChapel($capilla, $request->validated()),
            'message' => 'Capilla actualizada correctamente.',
        ]);
    }

    public function destroy(Chapel $capilla): JsonResponse
    {
        $this->chapels->deleteChapel($capilla);

        return response()->json([
            'success' => true,
            'message' => 'Capilla eliminada correctamente.',
        ]);
    }
}
