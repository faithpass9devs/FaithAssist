<?php

namespace App\Http\Requests\Masses;

use App\Globals\Status;
use App\Http\Requests\Concerns\UppercasesFields;
use App\Models\Masses\Weekend;
use App\Services\UserScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

class WeekendRequest extends FormRequest
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
            'ends_at' => $startsAt->copy()->addDay()->setTime(23, 59)->format('Y-m-d H:i:s'),
        ]);
    }

    protected function textFields(): array
    {
        return ['name'];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $weekendId = $this->route('weekend')?->id;

        return [
            'church_id' => ['required', 'integer', Rule::exists('churches', 'id')->whereNull('deleted_at')],
            'name' => ['nullable', 'string', 'max:150'],
            'starts_at' => [
                'required',
                'date',
                Rule::unique('weekends', 'starts_at')
                    ->ignore($weekendId)
                    ->where(fn ($query) => $query
                        ->where('church_id', $this->input('church_id'))
                        ->whereNull('deleted_at')),
            ],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in([
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $scope = new UserScopeService($user);
            $churchId = (int) $this->input('church_id');
            $weekendId = $this->route('weekend')?->id;
            $startsAt = Carbon::parse($this->input('starts_at'));
            $endsAt = Carbon::parse($this->input('ends_at'));

            if (! $scope->isGlobal() && ! $user->can('weekends.scope.all')) {
                $isOwnChurchUser = $user->church_id !== null
                    && $user->chapel_id === null
                    && (int) $user->church_id === $churchId;

                if (! $isOwnChurchUser) {
                    $validator->errors()->add('church_id', 'Solo la parroquia puede gestionar sus fines de semana.');

                    return;
                }
            }

            if (! $startsAt->isSaturday()) {
                $validator->errors()->add('starts_at', 'El fin de semana debe iniciar en sábado.');
            }

            if (
                ! $endsAt->isSunday()
                || ! $endsAt->equalTo($startsAt->copy()->addDay()->setTime(23, 59))
            ) {
                $validator->errors()->add('ends_at', 'El fin de semana debe terminar el domingo inmediato a las 23:59.');
            }

            $overlapExists = Weekend::query()
                ->where('church_id', $churchId)
                ->whereNull('deleted_at')
                ->when($weekendId, fn ($query) => $query->whereKeyNot($weekendId))
                ->whereDate('starts_at', '<=', $endsAt)
                ->whereDate('ends_at', '>=', $startsAt)
                ->exists();

            if ($overlapExists) {
                $validator->errors()->add('starts_at', 'Ya existe un fin de semana de esta parroquia en ese rango.');
                $validator->errors()->add('ends_at', 'Ya existe un fin de semana de esta parroquia en ese rango.');
            }

            if ($this->input('status') !== Status::IN_PROGRESS) {
                return;
            }

            $activeWeekendExists = Weekend::query()
                ->where('church_id', $churchId)
                ->where('status', Status::IN_PROGRESS)
                ->whereNull('deleted_at')
                ->when($weekendId, fn ($query) => $query->whereKeyNot($weekendId))
                ->exists();

            if ($activeWeekendExists) {
                $validator->errors()->add('status', 'Solo puede existir un fin de semana en curso por parroquia.');
            }
        });
    }
}
