<?php

namespace App\Http\Controllers\Regions;

use App\Exports\Regions\StatesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regions\StateRequest;
use App\Models\Regions\State;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class StateController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(State::class, 'estado');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $scope = new UserScopeService($request->user());

        $states = State::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->stateIds()))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15, ['id', 'name', 'short_name', 'status'])
            ->withQueryString();

        return Inertia::render('Regions/States/Index', [
            'states' => $states,
            'search' => $search,
        ]);
    }

    public function store(StateRequest $request): JsonResponse
    {
        $state = State::create($request->validated());

        return response()->json([
            'success' => true,
            'data'    => $state->only(['id', 'name', 'short_name', 'status']),
            'message' => 'Estado creado correctamente.',
        ], 201);
    }

    public function update(StateRequest $request, State $estado): JsonResponse
    {
        $estado->update($request->validated());

        return response()->json([
            'success' => true,
            'data'    => $estado->fresh()->only(['id', 'name', 'short_name', 'status']),
            'message' => 'Estado actualizado correctamente.',
        ]);
    }

    public function destroy(State $estado): JsonResponse
    {
        $estado->delete();

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
