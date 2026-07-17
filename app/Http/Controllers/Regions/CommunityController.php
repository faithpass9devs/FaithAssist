<?php

namespace App\Http\Controllers\Regions;

use App\Exports\Regions\CommunitiesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regions\CommunityRequest;
use App\Models\Regions\Community;
use App\Services\Regions\CommunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

class CommunityController extends Controller
{
    public function __construct(private readonly CommunityService $communities)
    {
        $this->authorizeResource(Community::class, 'comunidad');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $municipalityId = $request->integer('municipality_id') ?: null;

        return Inertia::render('Regions/Communities/Index', $this->communities->indexData($request->user(), $search, $municipalityId));
    }

    public function store(CommunityRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->communities->createCommunity($request->validated()),
            'message' => 'Comunidad creada correctamente.',
        ], 201);
    }

    public function update(CommunityRequest $request, Community $comunidad): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->communities->updateCommunity($comunidad, $request->validated()),
            'message' => 'Comunidad actualizada correctamente.',
        ]);
    }

    public function destroy(Community $comunidad): JsonResponse
    {
        $this->communities->deleteCommunity($comunidad);

        return response()->json([
            'success' => true,
            'message' => 'Comunidad eliminada correctamente.',
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('export', Community::class);

        $search = $request->input('search', '');
        $municipalityId = $request->integer('municipality_id') ?: null;

        $fileName = 'comunidades_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new CommunitiesExport($request->user(), $search, $municipalityId),
            $fileName,
            ExcelWriter::XLSX
        );
    }
}
