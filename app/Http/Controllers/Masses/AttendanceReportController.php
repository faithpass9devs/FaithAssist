<?php

namespace App\Http\Controllers\Masses;

use App\Exports\Masses\AttendanceReportWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Masses\AttendanceReportRequest;
use App\Models\Masses\Weekend;
use App\Services\Masses\AttendanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class AttendanceReportController extends Controller
{
    public function __construct(
        private readonly AttendanceReportService $reports,
        private readonly AttendanceReportWriter $writer,
    ) {}

    /**
     * Opciones del modal (parroquias, comunidades, niveles, meses y fines de semana con asistencia).
     */
    public function options(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('report', Weekend::class),
            403,
            'No tienes permiso para consultar el reporte de asistencias.',
        );

        return response()->json(
            $this->reports->options($request->user(), $request->integer('church_id') ?: null)
        );
    }

    public function download(AttendanceReportRequest $request): BinaryFileResponse
    {
        $report = $this->reports->build($request->user(), $request->filters());

        // El libro se escribe por flujo dentro de storage/app (siempre escribible por la app,
        // a diferencia del temp del sistema) y se envía con streaming; nunca se carga en memoria.
        $path = $this->writer->createWorkingFile();

        try {
            $this->writer->write($report, $path);
        } catch (Throwable $e) {
            @unlink($path);

            throw $e;
        }

        return response()
            ->download($path, $this->writer->fileName($report), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend(true);
    }
}
