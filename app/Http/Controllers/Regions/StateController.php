<?php

namespace App\Http\Controllers\Regions;

use App\Exports\Regions\StatesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regions\StateRequest;
use App\Models\Regions\State;
use App\Services\Regions\StateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class StateController extends Controller
{
    public function __construct(private readonly StateService $states)
    {
        $this->authorizeResource(State::class, 'estado');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Regions/States/Index', $this->states->indexData($request->user(), $search));
    }

    public function store(StateRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->states->createState($request->validated()),
            'message' => 'Estado creado correctamente.',
        ], 201);
    }

    public function update(StateRequest $request, State $estado): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->states->updateState($estado, $request->validated()),
            'message' => 'Estado actualizado correctamente.',
        ]);
    }

    public function destroy(State $estado): JsonResponse
    {
        $this->states->deleteState($estado);

        return response()->json([
            'success' => true,
            'message' => 'Estado eliminado correctamente.',
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('export', State::class);

        $search = $request->input('search', '');
        $fileName = 'estados_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new StatesExport($request->user(), $search),
            $fileName,
            ExcelWriter::XLSX
        );
    }
}
