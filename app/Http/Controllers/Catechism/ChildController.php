<?php

namespace App\Http\Controllers\Catechism;

use App\Exports\Catechism\ChildrenExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catechism\ChildRequest;
use App\Models\Catechism\Child;
use App\Services\Catechism\ChildService;
use App\Services\Catechism\ChildQrWhatsappService;
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

    public function sendQrWhatsapp(Child $child, ChildQrWhatsappService $qrService)
    {
        // El binding automático de {child} ya verifica que el modelo existe
        // No necesitamos autorización adicional aquí

        try {
            // Validar que el niño tiene teléfono configurado
            if (!$child->phone || !$child->phone_lada) {
                return response()->json([
                    'success' => false,
                    'message' => 'El niño no tiene un teléfono registrado. Por favor, completa los datos de contacto.',
                ], 422);
            }

            // Validar que WhatsApp está configurado
            if (!config('meta.whatsapp.token') || !config('meta.whatsapp.phone_number_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp no está configurado en el sistema.',
                ], 500);
            }

            $qrService->sendChildQrBadge($child);

            return response()->json([
                'success' => true,
                'message' => 'QR enviado exitosamente por WhatsApp',
            ]);
        } catch (\Exception $e) {
            \Log::error('Error enviando QR por WhatsApp', [
                'child_id' => $child->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al enviar el QR por WhatsApp. Por favor, intenta más tarde.',
            ], 500);
        }
    }
}
