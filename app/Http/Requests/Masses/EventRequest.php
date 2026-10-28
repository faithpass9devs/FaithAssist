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

        if (! $this->filled('starts_at')) {
            return;
        }

        try {
            $startsAt = Carbon::parse($this->input('starts_at'))->startOfDay();
        } catch (Throwable) {
            return;
        }

        $this->merge([
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $startsAt->copy()->setTime(23, 59)->format('Y-m-d H:i:s'),
        ]);
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
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in([Status::UPCOMING, Status::IN_PROGRESS, Status::COMPLETED])],
            'attendance_check_in_status' => ['sometimes', Rule::in([Status::UPCOMING, Status::IN_PROGRESS, Status::COMPLETED])],
            'attendance_check_out_status' => ['sometimes', Rule::in([Status::UPCOMING, Status::IN_PROGRESS, Status::COMPLETED])],
            'notes' => ['nullable', 'string'],
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
