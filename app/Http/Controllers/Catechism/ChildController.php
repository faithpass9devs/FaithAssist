<?php

namespace App\Http\Controllers\Catechism;

use App\Exports\Catechism\ChildImportTemplateExport;
use App\Exports\Catechism\ChildrenExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catechism\ChildImportRequest;
use App\Http\Requests\Catechism\ChildRequest;
use App\Models\Catechism\Child;
use App\Models\Ecclesiastes\Church;
use App\Models\Masses\MassAttendance;
use App\Models\User;
use App\Services\Catechism\ChildBatchPdfService;
use App\Services\Catechism\ChildImportService;
use App\Services\Catechism\ChildQrWhatsappService;
use App\Services\Catechism\ChildService;
use App\Services\CatechismPeriodMovementService;
use App\Services\UserScopeService;
use Illuminate\Database\Eloquent\Builder;
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

    public function search(Request $request)
    {
        abort_unless($request->user()?->can('children.search.execute'), 403);

        $term = trim((string) $request->query('q', ''));

        if ($term === '') {
            return redirect()->back()->with('error', 'Ingresa un código o nombre para buscar.');
        }

        $scope = new UserScopeService($request->user());
        $tokens = array_values(array_filter(preg_split('/\s+/u', $term) ?: []));

        $query = Child::query()
            ->with(['church:id,name', 'community:id,name', 'activeLevelAssignments.level:id,name'])
            ->where(function (Builder $builder) use ($term, $tokens): void {
                $builder->where('code', 'like', "%{$term}%")
                    ->orWhere(function (Builder $nameQuery) use ($tokens, $term): void {
                        $nameTokens = $tokens === [] ? [$term] : $tokens;

                        foreach ($nameTokens as $nameToken) {
                            $nameQuery->where(function (Builder $fieldQuery) use ($nameToken): void {
                                $fieldQuery->where('name', 'like', "%{$nameToken}%")
                                    ->orWhere('paterno', 'like', "%{$nameToken}%")
                                    ->orWhere('materno', 'like', "%{$nameToken}%");
                            });
                        }
                    });
            })
            ->orderBy('paterno')
            ->orderBy('materno')
            ->orderBy('name')
            ->limit(50);

        $scope->applyChildScope($query);

        $results = $query->get();

        if ($results->isEmpty()) {
            return redirect()->back()->with('error', 'No se encontraron niños con ese código o nombre.');
        }

        if ($results->count() === 1) {
            return redirect()->route('children.panel', ['child' => $results->first()->id]);
        }

        return Inertia::render('Catechism/Children/SearchResults', [
            'query' => $term,
            'results' => $results->map(fn (Child $child): array => $this->serializeChildForPanel($child))->values()->all(),
        ]);
    }

    public function panel(Request $request, Child $child): Response
    {
        abort_unless($request->user()?->can('children.search.execute'), 403);
        abort_unless($this->childWithinScope($request->user(), $child), 403);

        $child->loadMissing(['church:id,name', 'community:id,name', 'activeLevelAssignments.level:id,name']);

        $attendances = MassAttendance::query()
            ->with([
                'mass:id,weekend_id,name,starts_at,ends_at',
                'mass.weekend:id,name,starts_at,ends_at',
                'church:id,name',
                'chapel:id,name',
            ])
            ->where('child_id', $child->id)
            ->latest('check_in_at')
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MassAttendance $attendance): array => [
                'id' => $attendance->id,
                'mass_name' => $attendance->mass?->name,
                'weekend_name' => $attendance->mass?->weekend?->name
                    ?: $attendance->mass?->weekend?->starts_at?->format('Y-m-d'),
                'location' => $attendance->chapel?->name ?: $attendance->church?->name,
                'check_in_at' => $attendance->check_in_at?->format('Y-m-d H:i:s'),
                'check_out_at' => $attendance->check_out_at?->format('Y-m-d H:i:s'),
                'status' => $attendance->status,
                'valid' => $attendance->isValidAttendance(),
            ]);

        return Inertia::render('Catechism/Children/Panel', [
            'child' => $this->serializeChildForPanel($child),
            'attendances' => $attendances,
        ]);
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
        $pendingOnly = $request->boolean('pending_only');

        try {
            $result = $service->createBatch(
                $request->user(),
                $search,
                $churchId,
                $municipalityId,
                $communityId,
                $levelId,
                $status,
                $origin,
                $pendingOnly
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

    private function childWithinScope(?User $user, Child $child): bool
    {
        if (! $user) {
            return false;
        }

        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        return $scope->churchIds()->contains($child->church_id)
            || $scope->communityIds()->contains($child->community_id);
    }

    private function serializeChildForPanel(Child $child): array
    {
        return [
            'id' => $child->id,
            'code' => $child->code,
            'name' => $child->name,
            'paterno' => $child->paterno,
            'materno' => $child->materno,
            'full_name' => trim(collect([$child->name, $child->paterno, $child->materno])->filter()->implode(' ')),
            'birthdate' => $child->birthdate?->format('Y-m-d'),
            'sex' => $child->sex,
            'blood_type' => $child->blood_type,
            'status' => $child->status,
            'email' => $child->email,
            'phone_lada' => $child->phone_lada,
            'phone' => $child->phone,
            'emergency_phone_lada' => $child->emergency_phone_lada,
            'emergency_phone' => $child->emergency_phone,
            'church' => $child->church?->name,
            'community' => $child->community?->name,
            'levels' => $child->activeLevelAssignments
                ->map(fn ($assignment): array => [
                    'id' => $assignment->level?->id,
                    'name' => $assignment->level?->name,
                ])
                ->filter(fn (array $level): bool => $level['id'] !== null)
                ->values()
                ->all(),
        ];
    }
}
