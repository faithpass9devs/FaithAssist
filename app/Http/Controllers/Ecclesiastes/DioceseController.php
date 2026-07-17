<?php

namespace App\Http\Controllers\Ecclesiastes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ecclesiastes\DioceseRequest;
use App\Models\Ecclesiastes\Diocese;
use App\Services\Ecclesiastes\DioceseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DioceseController extends Controller
{
    public function __construct(private readonly DioceseService $dioceses)
    {
        $this->authorizeResource(Diocese::class, 'diocesis');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Ecclesiastes/Dioceses/Index', $this->dioceses->indexData($request->user(), $search));
    }

    public function store(DioceseRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dioceses->createDiocese($request->validated()),
            'message' => 'Diocesis creada correctamente.',
        ], 201);
    }

    public function update(DioceseRequest $request, Diocese $diocesis): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->dioceses->updateDiocese($diocesis, $request->validated()),
            'message' => 'Diocesis actualizada correctamente.',
        ]);
    }

    public function destroy(Diocese $diocesis): JsonResponse
    {
        $this->dioceses->deleteDiocese($diocesis);

        return response()->json([
            'success' => true,
            'message' => 'Diocesis eliminada correctamente.',
        ]);
    }
}
