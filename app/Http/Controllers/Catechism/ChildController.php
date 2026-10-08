<?php

namespace App\Http\Controllers\Catechism;

use App\Exports\Catechism\ChildImportTemplateExport;
use App\Exports\Catechism\ChildrenExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catechism\ChildImportRequest;
use App\Http\Requests\Catechism\ChildRequest;
use App\Models\Catechism\Child;
use App\Models\Ecclesiastes\Church;
use App\Models\User;
use App\Services\Catechism\ChildBatchPdfService;
use App\Services\Catechism\ChildImportService;
use App\Services\Catechism\ChildQrWhatsappService;
use App\Services\Catechism\ChildService;
use App\Services\CatechismPeriodMovementService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
            $request->input('origin'),
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

    public function update(
        ChildRequest $request,
        Child $child,
        CatechismPeriodMovementService $movementService
    ): RedirectResponse {
        $this->children->updateChild($child, $request->validated(), $request->user(), $movementService);

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

    public function exportPdfBatch(Request $request, ChildBatchPdfService $service): JsonResponse
    {
        $this->authorize('export', Child::class);

        $search = $request->input('search', '');
        $churchId = $request->integer('church_id') ?: null;
        $municipalityId = $request->integer('municipality_id') ?: null;
        $communityId = $request->integer('community_id') ?: null;
        $levelId = $request->integer('level_id') ?: null;
        $status = $request->input('status');
        $origin = $request->input('origin');

        try {
            $result = $service->createBatch(
                $request->user(),
                $search,
                $churchId,
                $municipalityId,
                $communityId,
                $levelId,
                $status,
                $origin
            );

            return response()->json($result);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            \Log::error('Error creando export PDF masivo', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'No se pudo iniciar la exportación.'], 500);
        }
    }

    public function pdfBatchStatus(Request $request, string $batch, ChildBatchPdfService $service): JsonResponse
    {
        $this->authorize('export', Child::class);

        $data = $service->batchStatus($request->user(), $batch);

        if ($data === null) {
            return response()->json(['message' => 'Exportación no encontrada.'], 404);
        }

        return response()->json($data);
    }

    public function downloadPdfBatch(Request $request, string $batch, ChildBatchPdfService $service)
    {
        $this->authorize('export', Child::class);

        $path = $service->download($request->user(), $batch);

        $fileName = 'gafetes_'.now()->format('Ymd_His').'.pdf';

        return response()->streamDownload(function () use ($path): void {
            echo Storage::disk('local')->get($path);
        }, $fileName, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    public function importTemplate(Request $request)
    {
        $this->assertSuperadmin($request->user());

        $fileName = 'plantilla_importacion_ninos_'.now()->format('Ymd').'.xlsx';

        return Excel::download(new ChildImportTemplateExport, $fileName, ExcelWriter::XLSX);
    }

    public function importBatch(ChildImportRequest $request, ChildImportService $service): JsonResponse
    {
        $this->assertSuperadmin($request->user());

        try {
            $result = $service->createBatch(
                $request->user(),
                Church::query()->findOrFail($request->integer('church_id')),
                $request->file('file')
            );

            return response()->json($result);
        } catch (NotFoundHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (RuntimeException $e) {
            Log::error('Error creando importación de niños', ['error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function importBatchStatus(Request $request, string $batch, ChildImportService $service): JsonResponse
    {
        $this->assertSuperadmin($request->user());

        $data = $service->status($request->user(), $batch);

        if ($data === null) {
            return response()->json(['message' => 'Importación no encontrada.'], 404);
        }

        return response()->json($data);
    }

    public function importBatchErrors(Request $request, string $batch, ChildImportService $service)
    {
        $this->assertSuperadmin($request->user());

        $path = $service->errorReport($request->user(), $batch);

        $fileName = 'errores_importacion_ninos_'.now()->format('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($path): void {
            echo Storage::disk('local')->get($path);
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    private function assertSuperadmin(?User $user): void
    {
        abort_unless($user?->hasRole('Superadmin') === true, 403);
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

        $isDownload = $request->query('download') === '1';
        $disposition = $isDownload ? 'attachment' : 'inline';

        $child->withoutEvents(function () use ($child): void {
            if (empty($child->badge_pdf_downloaded_at)) {
                $child->forceFill(['badge_pdf_downloaded_at' => now()])->saveQuietly();
            }
        });

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
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
}