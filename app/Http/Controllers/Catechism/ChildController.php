<?php

namespace App\Http\Controllers\Catechism;

use App\Exports\Catechism\ChildrenExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catechism\ChildRequest;
use App\Models\Catechism\Child;
use App\Services\Catechism\ChildQrWhatsappService;
use App\Services\Catechism\ChildService;
use App\Services\Catechism\MassWhatsAppService;
use App\Services\CatechismPeriodMovementService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
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

    public function sendQrWhatsapp(Child $child, ChildQrWhatsappService $qrService)
    {
        $this->authorize('view', $child);

        try {
            if (! $child->phone || ! $child->phone_lada) {
                return response()->json([
                    'success' => false,
                    'message' => 'El niño no tiene un teléfono registrado. Por favor, completa los datos de contacto.',
                ], 422);
            }

            if (! config('baileys.enabled')) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp no está habilitado en el sistema.',
                ], 500);
            }

            $message = $qrService->sendChildQrBadge($child);

            if (! $message) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo generar el PDF del gafete para enviarlo.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Gafete enviado correctamente por WhatsApp.',
                'message_id' => $message?->id,
                'status' => $message?->status,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error enviando gafete PDF por WhatsApp', [
                'child_id' => $child->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el gafete PDF por WhatsApp. Por favor, intenta más tarde.',
            ], 500);
        }
    }

    public function badgePdf(Request $request, Child $child, ChildQrWhatsappService $qrService)
    {
        abort_unless($request->user()?->can('children.read'), 403);

        if (! $request->user()?->can('children.scope.all')) {
            $scope = new UserScopeService($request->user());

            if (! $scope->isGlobal()) {
                $hasChurchAccess = $scope->churchIds()->contains($child->church_id);
                $hasCommunityAccess = $scope->communityIds()->contains($child->community_id);

                abort_unless($hasChurchAccess || $hasCommunityAccess, 403);
            }
        }

        $pdfContent = $qrService->generateChildBadgePdf(
            $child,
            $request->query('qr_image')
        );
        $fileName = $this->buildBadgePdfFilename($child);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    private function buildBadgePdfFilename(Child $child): string
    {
        $firstName = $this->firstWord((string) $child->name, 'NINO');
        $firstLastName = $this->firstWord((string) $child->paterno, 'SIN_APELLIDO');
        $displayName = trim($firstName.' '.$firstLastName);
        $displayName = preg_replace('/[^\pL\pN\s\-]/u', '', $displayName) ?: 'NINO SIN_APELLIDO';

        return 'Gafete de Asistencia '.$displayName.'.pdf';
    }

    private function firstWord(string $value, string $fallback): string
    {
        $value = trim($value);

        if ($value === '') {
            return $fallback;
        }

        $parts = preg_split('/\s+/u', $value) ?: [];

        return $parts[0] ?? $fallback;
    }

    public function massWhatsApp(Request $request, MassWhatsAppService $massWhatsapp): JsonResponse
    {
        $this->authorize('massWhatsApp', Child::class);

        $result = $massWhatsapp->createBatch(
            $request->user(),
            $request->input('search', ''),
            $request->integer('church_id') ?: null,
            $request->integer('municipality_id') ?: null,
            $request->integer('community_id') ?: null,
            $request->integer('level_id') ?: null,
            $request->input('status'),
        );

        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], 409);
        }

        return response()->json($result);
    }

    public function massWhatsAppStatus(Request $request, string $batch, MassWhatsAppService $massWhatsapp): JsonResponse
    {
        $this->authorize('massWhatsApp', Child::class);

        $data = $massWhatsapp->batchStatus($request->user(), $batch);

        return $data
            ? response()->json($data)
            : response()->json(['message' => 'Batch no encontrado.'], 404);
    }
}
