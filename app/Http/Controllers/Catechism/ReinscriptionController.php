<?php

namespace App\Http\Controllers\Catechism;

use App\Exports\Catechism\ReinscriptionsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catechism\ReinscriptionRequest;
use App\Models\Catechism\Child;
use App\Services\Catechism\ReinscriptionService;
use App\Services\CatechismPeriodMovementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;

class ReinscriptionController extends Controller
{
    public function __construct(private readonly ReinscriptionService $reinscriptions) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('reinscripciones.read'), 403);

        $data = $this->reinscriptions->indexData(
            $request->user(),
            $request->input('search', ''),
            $request->integer('community_id') ?: null,
            $request->integer('level_id') ?: null,
        );

        return Inertia::render('Catechism/Reinscriptions/Index', $data);
    }

    public function create(Request $request, Child $child): Response
    {
        abort_unless($request->user()->can('reinscripciones.create'), 403);

        $child = $this->reinscriptions->getChildForReinscription($request->user(), $child);

        return Inertia::render('Catechism/Reinscriptions/Form', [
            'child' => $child,
            ...$this->reinscriptions->getFormData($request->user()),
        ]);
    }

    public function store(
        ReinscriptionRequest $request,
        CatechismPeriodMovementService $movementService
    ): RedirectResponse {
        $this->reinscriptions->createReinscription($request->user(), $request->validated(), $movementService);

        return redirect()->route('reinscripciones.index')
            ->with('success', 'Reinscripción registrada correctamente.');
    }

    public function export(Request $request)
    {
        abort_unless($request->user()->can('reinscripciones.export'), 403);

        $filters = [
            'search' => $request->input('search', ''),
            'community_id' => $request->integer('community_id') ?: null,
            'level_id' => $request->integer('level_id') ?: null,
        ];

        $fileName = 'reinscripciones_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new ReinscriptionsExport($request->user(), $filters),
            $fileName,
            ExcelWriter::XLSX
        );
    }
}
