<?php

namespace App\Http\Controllers\Catechism;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catechism\ImportExternalChildRequest;
use App\Models\External\ExternalChild;
use App\Services\Catechism\ExternosService;
use App\Services\Catechism\MassWhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExternosController extends Controller
{
    public function __construct(private readonly ExternosService $externos)
    {
        $this->authorizeResource(ExternalChild::class, 'externo');
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Catechism/Externos/Index', $this->externos->indexData(
            $request->user(),
            $request->input('search', ''),
            $request->integer('level_id') ?: null,
            $request->integer('community_id') ?: null,
        ));
    }

    public function show(Request $request, ExternalChild $externo): Response
    {
        $this->authorize('show', $externo);

        return Inertia::render('Catechism/Externos/Index', [
            ...$this->externos->indexData(
                $request->user(),
                $request->input('search', ''),
                $request->integer('level_id') ?: null,
                $request->integer('community_id') ?: null,
            ),
            'selectedExterno' => $this->externos->showData($request->user(), $externo)['externo'],
        ]);
    }

    public function register(Request $request, ExternalChild $externo): Response
    {
        $this->authorize('import', $externo);

        return Inertia::render('Catechism/Externos/Import', $this->externos->registerData(
            $request->user(),
            $externo
        ));
    }

    public function import(ImportExternalChildRequest $request, ExternalChild $externo): RedirectResponse
    {
        $this->authorize('import', $externo);

        $this->externos->import($request->user(), $externo, $request->validated());

        return redirect()->route('externos.index')->with('success', 'Niño importado correctamente.');
    }

    public function importBatch(Request $request): JsonResponse
    {
        $this->authorize('importAll', ExternalChild::class);

        $result = $this->externos->createImportBatch(
            $request->user(),
            $request->input('search', ''),
            $request->integer('level_id') ?: null,
            $request->integer('community_id') ?: null,
        );

        return response()->json($result);
    }

    public function batchStatus(Request $request, string $batch): JsonResponse
    {
        $this->authorize('importAll', ExternalChild::class);

        $data = $this->externos->batchStatus($request->user(), $batch);

        return $data
            ? response()->json($data)
            : response()->json(['message' => 'Batch no encontrado.'], 404);
    }

    public function massWhatsApp(Request $request, MassWhatsAppService $massWhatsapp): JsonResponse
    {
        $this->authorize('massWhatsApp', ExternalChild::class);

        $result = $massWhatsapp->createBatch($request->user());

        return response()->json($result);
    }

    public function massWhatsAppStatus(Request $request, string $batch, MassWhatsAppService $massWhatsapp): JsonResponse
    {
        $this->authorize('massWhatsApp', ExternalChild::class);

        $data = $massWhatsapp->batchStatus($request->user(), $batch);

        return $data
            ? response()->json($data)
            : response()->json(['message' => 'Batch no encontrado.'], 404);
    }
}
