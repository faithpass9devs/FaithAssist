<?php

namespace App\Http\Requests\Operation;

use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Operation\Period;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PeriodMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period_id' => ['required', 'integer', Rule::exists('periods', 'id')->whereNull('deleted_at')],
            'church_id' => ['required', 'integer', Rule::exists('churches', 'id')->whereNull('deleted_at')],
            'period_movement_type_id' => ['required', 'integer', Rule::exists('period_movement_types', 'id')->whereNull('deleted_at')],
            'status' => ['required', Rule::in([
                Status::PENDING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $period = Period::query()->find($this->input('period_id'));

            if (! $period) {
                return;
            }

            $church = Church::query()->with('deanery:id,diocese_id')->find($this->input('church_id'));

            if ($church && $church->deanery?->diocese_id !== $period->diocese_id) {
                $validator->errors()->add('church_id', 'La parroquia debe pertenecer a la diócesis del periodo seleccionado.');
            }

            $startDate = (string) $this->input('start_date');
            $endDate = (string) $this->input('end_date');
            $periodStartDate = $period->start_date?->format('Y-m-d');
            $periodEndDate = $period->end_date?->format('Y-m-d');

            if ($periodStartDate !== null && $startDate < $periodStartDate) {
                $validator->errors()->add('start_date', 'La fecha de inicio debe estar dentro del rango del periodo.');
            }

            if ($periodEndDate !== null && $endDate > $periodEndDate) {
                $validator->errors()->add('end_date', 'La fecha de fin debe estar dentro del rango del periodo.');
            }
        });
    }
}
