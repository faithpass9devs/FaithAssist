<?php

namespace App\Http\Controllers\Ecclesiastes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ecclesiastes\ChurchRequest;
use App\Models\Ecclesiastes\Church;
use App\Services\Ecclesiastes\ChurchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChurchController extends Controller
{
    public function __construct(private readonly ChurchService $churches)
    {
        $this->authorizeResource(Church::class, 'parroquia');
    }

    public function index(Request $request): Response
    {
        $search = $request->input('search', '');
        $municipalityId = $request->integer('municipality_id') ?: null;
        $deaneryId = $request->integer('deanery_id') ?: null;
        $status = $request->input('status') ?: null;

        return Inertia::render(
            'Ecclesiastes/Churches/Index',
            $this->churches->indexData($request->user(), $search, $municipalityId, $deaneryId, $status)
        );
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Ecclesiastes/Churches/Form', $this->churches->createFormData($request->user()));
    }

    public function store(ChurchRequest $request): RedirectResponse
    {
        $this->churches->createChurch($request->validated());

        return redirect()->route('parroquias.index')
            ->with('success', 'Parroquia creada correctamente.');
    }

    public function edit(Request $request, Church $parroquia): Response
    {
        return Inertia::render('Ecclesiastes/Churches/Form', $this->churches->editFormData($request->user(), $parroquia));
    }

    public function update(ChurchRequest $request, Church $parroquia): RedirectResponse
    {
        $this->churches->updateChurch($parroquia, $request->validated());

        return redirect()->route('parroquias.index')
            ->with('success', 'Parroquia actualizada correctamente.');
    }

    public function destroy(Church $parroquia): JsonResponse
    {
        $this->churches->deleteChurch($parroquia);

        return response()->json([
            'success' => true,
            'message' => 'Parroquia eliminada correctamente.',
        ]);
    }
}
