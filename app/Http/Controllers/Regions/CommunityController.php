<?php

namespace App\Http\Controllers\Regions;

use App\Exports\Regions\CommunitiesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Regions\CommunityRequest;
use App\Models\Regions\Community;
use App\Models\Regions\Municipality;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;

class CommunityController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Community::class, 'comunidad');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $scope = new UserScopeService($request->user());

        $communities = Community::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('municipality_id', $scope->municipalityIds()))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15, ['id', 'municipality_id', 'name', 'status'])
            ->withQueryString();

        $municipalities = Municipality::query()
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->municipalityIds()))
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Regions/Communities/Index', [
            'communities' => $communities,
            'municipalities' => $municipalities,
            'search' => $search,
        ]);
    }

    public function store(CommunityRequest $request): JsonResponse
    {
        $community = Community::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $community->only(['id', 'municipality_id', 'name', 'status']),
            'message' => 'Comunidad creada correctamente.',
        ], 201);
    }

    public function update(CommunityRequest $request, Community $comunidad): JsonResponse
    {
        $comunidad->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $comunidad->fresh()->only(['id', 'municipality_id', 'name', 'status']),
            'message' => 'Comunidad actualizada correctamente.',
        ]);
    }

    public function destroy(Community $comunidad): JsonResponse
    {
        $comunidad->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comunidad eliminada correctamente.',
        ]);
    }

    public function export(Request $request)
    {
        $this->authorize('export', Community::class);

        $search = $request->input('search', '');

        $fileName = 'comunidades_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new CommunitiesExport($request->user(), $search),
            $fileName,
            ExcelWriter::XLSX
        );
    }
}
