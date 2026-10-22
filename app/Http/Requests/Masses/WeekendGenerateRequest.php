<?php

namespace App\Http\Requests\Masses;

use App\Models\Masses\Mass;
use App\Models\Masses\Weekend;
use App\Services\Masses\WeekendScheduleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WeekendGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('create', Weekend::class)) {
            return false;
        }

        return $this->isMethod('GET') || $user->can('create', Mass::class);
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'integer', Rule::exists('churches', 'id')->whereNull('deleted_at')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! app(WeekendScheduleService::class)->canManageChurch($this->user(), $this->integer('church_id'))) {
                $validator->errors()->add('church_id', 'Solo la parroquia puede generar sus fines de semana.');
            }
        });
    }
}
