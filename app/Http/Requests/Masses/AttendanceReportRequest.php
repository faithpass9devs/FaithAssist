<?php

namespace App\Http\Requests\Masses;

use App\Models\Masses\Weekend;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('report', Weekend::class);
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'integer', Rule::exists('churches', 'id')->whereNull('deleted_at')],
            'community_id' => ['nullable', 'integer', Rule::exists('communities', 'id')->whereNull('deleted_at')],
            'level_id' => ['nullable', 'integer', Rule::exists('levels', 'id')->whereNull('deleted_at')],
            'child_status' => ['nullable', Rule::in(['active', 'all'])],
            'month' => ['nullable', 'date_format:Y-m', 'required_without_all:date_from,date_to'],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'prohibited_unless:month,null'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from', 'prohibited_unless:month,null'],
        ];
    }

    public function messages(): array
    {
        return [
            'church_id.required' => 'Selecciona una parroquia.',
            'church_id.integer' => 'La parroquia seleccionada no es válida.',
            'church_id.exists' => 'La parroquia seleccionada no existe o fue eliminada.',
            'community_id.integer' => 'La comunidad seleccionada no es válida.',
            'community_id.exists' => 'La comunidad seleccionada no existe o fue eliminada.',
            'level_id.integer' => 'El nivel seleccionado no es válido.',
            'level_id.exists' => 'El nivel seleccionado no existe o fue eliminado.',
            'child_status.in' => 'El filtro de niños debe ser «Solo activos» o «Todos».',
            'month.date_format' => 'El mes debe tener el formato AAAA-MM, por ejemplo 2026-10.',
            'month.required_without_all' => 'Selecciona un mes o un rango de fechas.',
            'date_from.date_format' => 'La fecha inicial debe tener el formato AAAA-MM-DD.',
            'date_from.prohibited_unless' => 'Elige un mes o un rango de fechas, no ambos.',
            'date_to.date_format' => 'La fecha final debe tener el formato AAAA-MM-DD.',
            'date_to.prohibited_unless' => 'Elige un mes o un rango de fechas, no ambos.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
        ];
    }

    public function attributes(): array
    {
        return [
            'church_id' => 'parroquia',
            'community_id' => 'comunidad',
            'level_id' => 'nivel',
            'child_status' => 'filtro de niños',
            'month' => 'mes',
            'date_from' => 'fecha inicial',
            'date_to' => 'fecha final',
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('No tienes permiso para descargar el reporte de asistencias.');
    }

    /**
     * @return array{church_id:int, community_id:?int, level_id:?int, child_status:string, month:?string, date_from:?string, date_to:?string}
     */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'church_id' => (int) $data['church_id'],
            'community_id' => isset($data['community_id']) ? (int) $data['community_id'] : null,
            'level_id' => isset($data['level_id']) ? (int) $data['level_id'] : null,
            'child_status' => $data['child_status'] ?? 'active',
            'month' => $data['month'] ?? null,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
        ];
    }
}
