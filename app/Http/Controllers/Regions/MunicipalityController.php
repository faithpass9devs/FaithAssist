<?php

namespace App\Http\Controllers\Regions;

use App\Exports\Regions\MunicipalitiesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regions\MunicipalityRequest;
use App\Models\Ecclesiastes\Diocese;
use App\Models\Regions\Municipality;
use App\Models\Regions\State;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

class MunicipalityController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Municipality::class, 'municipio');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $scope = new UserScopeService($request->user());

        $municipalities = Municipality::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->municipalityIds()))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15, ['id', 'state_id', 'diocese_id', 'name', 'status'])
            ->withQueryString();

        $states = State::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->stateIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'short_name']);

        $dioceses = Diocese::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->dioceseIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Regions/Municipalities/Index', [
            'municipalities' => $municipalities,
            'states' => $states,
            'dioceses' => $dioceses,
            'search' => $search,
        ]);
    }

    public function store(MunicipalityRequest $request): JsonResponse
    {
        $municipality = Municipality::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $municipality->only(['id', 'state_id', 'diocese_id', 'name', 'status']),
            'message' => 'Municipio creado correctamente.',
        ], 201);
    }

    public function update(MunicipalityRequest $request, Municipality $municipio): JsonResponse
    {
        $municipio->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $municipio->fresh()->only(['id', 'state_id', 'diocese_id', 'name', 'status']),
            'message' => 'Municipio actualizado correctamente.',
        ]);
    }

    public function destroy(Municipality $municipio): JsonResponse
    {
        $municipio->delete();

        return response()->json([
            'success' => true,
            'message' => 'Municipio eliminado correctamente.',
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('export', Municipality::class);

        $search = $request->input('search', '');

        $fileName = 'municipios_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new MunicipalitiesExport($request->user(), $search),
            $fileName,
            ExcelWriter::XLSX
        );
    }
}
