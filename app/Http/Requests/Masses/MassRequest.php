<?php

namespace App\Http\Requests\Masses;

use App\Globals\Status;
use App\Http\Requests\Concerns\UppercasesFields;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Masses\Weekend;
use App\Services\UserScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MassRequest extends FormRequest
{
    use UppercasesFields {
        prepareForValidation as prepareTextFieldsForValidation;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTextFieldsForValidation();
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
            'weekend_id' => ['required', 'integer', Rule::exists('weekends', 'id')->whereNull('deleted_at')],
            'church_id' => ['required', 'integer', Rule::exists('churches', 'id')->whereNull('deleted_at')],
            'chapel_id' => ['nullable', 'integer', Rule::exists('chapels', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in([
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])],
            'attendance_status' => ['required', Rule::in([
                Status::UPCOMING,
                Status::IN_PROGRESS,
                Status::COMPLETED,
            ])],
            'notes' => ['nullable', 'string', 'max:2000'],
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
            $weekend = Weekend::query()->find($this->integer('weekend_id'));
            $churchId = $this->integer('church_id');
            $chapelId = $this->integer('chapel_id') ?: null;

            if (! $weekend) {
                return;
            }

            if ((int) $weekend->church_id !== $churchId) {
                $validator->errors()->add('church_id', 'La misa debe pertenecer a la parroquia del fin de semana.');
            }

            if ($chapelId) {
                $chapelChurchId = Chapel::query()->whereKey($chapelId)->value('church_id');

                if ((int) $chapelChurchId !== $churchId) {
                    $validator->errors()->add('chapel_id', 'La capilla seleccionada no pertenece a la parroquia.');
                }
            }

            $massStartsAt = Carbon::parse($this->input('starts_at'));
            $massEndsAt = Carbon::parse($this->input('ends_at'));
            $weekendStartsAt = $weekend->starts_at->copy();
            $weekendEndsAt = $weekend->ends_at->copy();

            if ($massStartsAt->lt($weekendStartsAt) || $massStartsAt->gt($weekendEndsAt)) {
                $validator->errors()->add('starts_at', 'La misa debe iniciar dentro del fin de semana seleccionado.');
            }

            if ($massEndsAt->lt($weekendStartsAt) || $massEndsAt->gt($weekendEndsAt)) {
                $validator->errors()->add('ends_at', 'La misa debe terminar dentro del fin de semana seleccionado.');
            }

            if ($scope->isGlobal() || $user->can('masses.scope.all')) {
                return;
            }

            if ($user->chapel_id !== null) {
                if ((int) $user->chapel_id !== $chapelId) {
                    $validator->errors()->add('chapel_id', 'Solo puedes gestionar misas de tu capilla.');
                }

                return;
            }

            $isOwnChurchMass = $user->church_id !== null
                && (int) $user->church_id === $churchId
                && $chapelId === null;

            if (! $isOwnChurchMass) {
                $validator->errors()->add('church_id', 'Solo puedes gestionar misas propias de tu parroquia.');
            }
        });
    }
}
