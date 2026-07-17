<?php

namespace App\Http\Controllers\Ecclesiastes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ecclesiastes\DeaneryRequest;
use App\Models\Ecclesiastes\Deanery;
use App\Services\Ecclesiastes\DeaneryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeaneryController extends Controller
{
    public function __construct(private readonly DeaneryService $deaneries)
    {
        $this->authorizeResource(Deanery::class, 'decanato');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Ecclesiastes/Deaneries/Index', $this->deaneries->indexData($request->user(), $search));
    }

    public function store(DeaneryRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->deaneries->createDeanery($request->validated()),
            'message' => 'Decanato creado correctamente.',
        ], 201);
    }

    public function update(DeaneryRequest $request, Deanery $decanato): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->deaneries->updateDeanery($decanato, $request->validated()),
            'message' => 'Decanato actualizado correctamente.',
        ]);
    }

    public function destroy(Deanery $decanato): JsonResponse
    {
        $this->deaneries->deleteDeanery($decanato);

        return response()->json([
            'success' => true,
            'message' => 'Decanato eliminado correctamente.',
        ]);
    }
}
