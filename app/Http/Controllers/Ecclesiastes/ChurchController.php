<?php

namespace App\Http\Controllers\Ecclesiastes;

use App\Globals\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ecclesiastes\ChurchRequest;
use App\Models\Ecclesiastes\Church;
use App\Models\Ecclesiastes\Deanery;
use App\Models\Regions\Municipality;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChurchController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Church::class, 'parroquia');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $municipalityId = $request->integer('municipality_id') ?: null;
        $deaneryId = $request->integer('deanery_id') ?: null;
        $status = $request->input('status') ?: null;
        $scope = new UserScopeService($request->user());

        $churches = Church::query()
            ->with(['municipality:id,name', 'deanery:id,name'])
            ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->churchIds()))
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($municipalityId, fn ($q) => $q->where('municipality_id', $municipalityId))
            ->when($deaneryId, fn ($q) => $q->where('deanery_id', $deaneryId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Church $church): array => $this->serializeChurch($church));

        return Inertia::render('Ecclesiastes/Churches/Index', [
            'churches' => $churches,
            'search' => $search,
            'filters' => [
                'municipality_id' => $municipalityId,
                'deanery_id' => $deaneryId,
                'status' => $status,
            ],
            'municipalities' => Municipality::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->municipalityIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']),
            'deaneries' => Deanery::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->deaneryIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']),
            'statuses' => [
                ['value' => Status::ACTIVE, 'label' => 'Activo'],
                ['value' => Status::INACTIVE, 'label' => 'Inactivo'],
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Ecclesiastes/Churches/Form', [
            'church' => null,
            ...$this->formOptions($request),
        ]);
    }

    public function store(ChurchRequest $request): RedirectResponse
    {
        Church::create($request->validated());

        return redirect()->route('parroquias.index')
            ->with('success', 'Parroquia creada correctamente.');
    }

    public function edit(Request $request, Church $parroquia): Response
    {
        $parroquia->loadMissing(['municipality:id,name', 'deanery:id,name']);

        return Inertia::render('Ecclesiastes/Churches/Form', [
            'church' => $this->serializeChurch($parroquia),
            ...$this->formOptions($request),
        ]);
    }

    public function update(ChurchRequest $request, Church $parroquia): RedirectResponse
    {
        $parroquia->update($request->validated());

        return redirect()->route('parroquias.index')
            ->with('success', 'Parroquia actualizada correctamente.');
    }

    public function destroy(Church $parroquia): JsonResponse
    {
        $parroquia->delete();

        return response()->json([
            'success' => true,
            'message' => 'Parroquia eliminada correctamente.',
        ]);
    }

    private function formOptions(Request $request): array
    {
        $scope = new UserScopeService($request->user());

        return [
            'municipalities' => Municipality::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->municipalityIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']),
            'deaneries' => Deanery::query()
                ->when(! $scope->isGlobal(), fn ($q) => $q->whereIn('id', $scope->deaneryIds()))
                ->where('status', Status::ACTIVE)
                ->orderBy('name')
                ->get(['id', 'name']),
            'statuses' => [
                ['value' => Status::ACTIVE, 'label' => 'Activo'],
                ['value' => Status::INACTIVE, 'label' => 'Inactivo'],
            ],
        ];
    }

    private function serializeChurch(Church $church): array
    {
        return [
            'id' => $church->id,
            'municipality_id' => $church->municipality_id,
            'deanery_id' => $church->deanery_id,
            'name' => $church->name,
            'alias' => $church->alias,
            'email' => $church->email,
            'phone' => $church->phone,
            'address' => $church->address,
            'status' => $church->status,
            'municipality' => $church->municipality?->name,
            'deanery' => $church->deanery?->name,
        ];
    }
}
