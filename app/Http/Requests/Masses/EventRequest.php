<?php

namespace App\Http\Requests\Masses;

use App\Globals\Status;
use App\Http\Requests\Concerns\UppercasesFields;
use App\Models\Masses\Event;
use App\Services\UserScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class EventRequest extends FormRequest
{
    use UppercasesFields {
        prepareForValidation as prepareTextFieldsForValidation;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTextFieldsForValidation();

        foreach (['starts_at', 'ends_at'] as $field) {
            if (! $this->filled($field)) {
                continue;
            }

            try {
                $this->merge([
                    $field => Carbon::parse($this->input($field))->format('Y-m-d H:i:s'),
                ]);
            } catch (Throwable) {
                // Deja el valor original para que la validación reporte el error.
            }
        }
    }

    protected function textFields(): array
    {
        return ['name', 'notes'];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'integer', Rule::exists('churches', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in([Status::UPCOMING, Status::IN_PROGRESS, Status::COMPLETED])],
            'attendance_check_in_status' => ['sometimes', Rule::in([Status::UPCOMING, Status::IN_PROGRESS, Status::COMPLETED])],
            'attendance_check_out_status' => ['sometimes', Rule::in([Status::UPCOMING, Status::IN_PROGRESS, Status::COMPLETED])],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.required' => 'Ingresa la fecha y hora de inicio del evento.',
            'starts_at.date' => 'La fecha y hora de inicio no es válida.',
            'ends_at.required' => 'Ingresa la fecha y hora de fin del evento.',
            'ends_at.date' => 'La fecha y hora de fin no es válida.',
            'ends_at.after' => 'La fecha y hora de fin debe ser posterior a la fecha y hora de inicio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'starts_at' => 'fecha y hora de inicio',
            'ends_at' => 'fecha y hora de fin',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('church_id')) {
                return;
            }

            $user = $this->user();

            if (! $user) {
                return;
            }

            $scope = new UserScopeService($user);
            $churchId = (int) $this->input('church_id');

            if ($user->can('eventos.scope.all') || $scope->isGlobal()) {
                return;
            }

            if (! $scope->churchIds()->contains($churchId)) {
                $validator->errors()->add('church_id', 'No puedes asignar un evento fuera de tu alcance.');
            }
        });
    }

    public function payload(?Event $event = null): array
    {
        $data = [
            'church_id' => (int) $this->input('church_id'),
            'name' => (string) $this->input('name'),
            'starts_at' => (string) $this->input('starts_at'),
            'ends_at' => (string) $this->input('ends_at'),
            'status' => (string) $this->input('status', Status::UPCOMING),
            'notes' => $this->filled('notes') ? (string) $this->input('notes') : null,
        ];

        if ($event) {
            $data['attendance_check_in_status'] = (string) $this->input(
                'attendance_check_in_status',
                $event->attendance_check_in_status
            );
            $data['attendance_check_out_status'] = (string) $this->input(
                'attendance_check_out_status',
                $event->attendance_check_out_status
            );
        } else {
            $data['attendance_check_in_status'] = Status::UPCOMING;
            $data['attendance_check_out_status'] = Status::UPCOMING;
        }

        return $data;
    }
}
