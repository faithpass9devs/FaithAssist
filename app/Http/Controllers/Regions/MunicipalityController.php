<?php

namespace App\Http\Controllers\Regions;

use App\Exports\Regions\MunicipalitiesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regions\MunicipalityRequest;
use App\Models\Regions\Municipality;
use App\Services\Regions\MunicipalityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

class MunicipalityController extends Controller
{
    public function __construct(private readonly MunicipalityService $municipalities)
    {
        $this->authorizeResource(Municipality::class, 'municipio');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');

        return Inertia::render('Regions/Municipalities/Index', $this->municipalities->indexData($request->user(), $search));
    }

    public function store(MunicipalityRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->municipalities->createMunicipality($request->validated()),
            'message' => 'Municipio creado correctamente.',
        ], 201);
    }

    public function update(MunicipalityRequest $request, Municipality $municipio): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->municipalities->updateMunicipality($municipio, $request->validated()),
            'message' => 'Municipio actualizado correctamente.',
        ]);
    }

    public function destroy(Municipality $municipio): JsonResponse
    {
        $this->municipalities->deleteMunicipality($municipio);

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
