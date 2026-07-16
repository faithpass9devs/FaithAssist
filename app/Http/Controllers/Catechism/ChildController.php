<?php

namespace App\Http\Controllers\Catechism;

use App\Exports\Catechism\ChildrenExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catechism\ChildRequest;
use App\Models\Catechism\Child;
use App\Services\Catechism\ChildService;
use App\Services\CatechismPeriodMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class ChildController extends Controller
{
    public function __construct(private readonly ChildService $children)
    {
        $this->authorizeResource(Child::class, 'child');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Catechism/Children/Index', $this->children->indexData(
            $request->user(),
            $request->input('search', ''),
            $request->integer('church_id') ?: null,
            $request->integer('municipality_id') ?: null,
            $request->integer('community_id') ?: null,
            $request->integer('level_id') ?: null,
            $request->input('status'),
        ));
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Catechism/Children/Form', $this->children->getFormData($request->user()));
    }

    public function store(
        ChildRequest $request,
        CatechismPeriodMovementService $movementService
    ): RedirectResponse {
        $this->children->createChild($request->validated(), $request->user(), $movementService);

        return redirect()->route('children.index')
            ->with('success', 'Niño creado correctamente.');
    }

    public function edit(Request $request, Child $child): Response
    {
        $child = $child->loadMissing(['church:id,name', 'community:id,name', 'activeLevelAssignments.level:id,name']);

        return Inertia::render('Catechism/Children/Form', $this->children->getEditData($request->user(), $child));
    }

    public function update(ChildRequest $request, Child $child): RedirectResponse
    {
        $this->children->updateChild($child, $request->validated());

        return redirect()->route('children.index')
            ->with('success', 'Niño actualizado correctamente.');
    }

    public function destroy(Child $child): RedirectResponse
    {
        $this->children->deleteChild($child);

        return redirect()->route('children.index')
            ->with('success', 'Niño eliminado correctamente.');
    }

    public function export(Request $request)
    {
        $this->authorize('export', Child::class);

        $filters = [
            'search' => $request->input('search', ''),
            'church_id' => $request->integer('church_id') ?: null,
            'municipality_id' => $request->integer('municipality_id') ?: null,
            'community_id' => $request->integer('community_id') ?: null,
            'level_id' => $request->integer('level_id') ?: null,
            'status' => $request->input('status'),
        ];

        $fileName = 'ninos_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new ChildrenExport($request->user(), $filters),
            $fileName,
            ExcelWriter::XLSX
        );
    }
}
