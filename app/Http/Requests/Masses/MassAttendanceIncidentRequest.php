<?php

namespace App\Http\Requests\Masses;

use App\Globals\Status;
use App\Http\Requests\Concerns\UppercasesFields;
use App\Models\Catechism\Child;
use App\Models\Masses\Weekend;
use App\Services\UserScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MassAttendanceIncidentRequest extends FormRequest
{
    use UppercasesFields {
        prepareForValidation as prepareTextFieldsForValidation;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTextFieldsForValidation();

        if (! $this->filled('status')) {
            $this->merge(['status' => Status::ACTIVE]);
        }
    }

    protected function textFields(): array
    {
        return ['description'];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weekend_id' => ['required', 'integer', Rule::exists('weekends', 'id')->whereNull('deleted_at')],
            'child_id' => ['required', 'integer', Rule::exists('children', 'id')->whereNull('deleted_at')],
            'incidence_type_id' => [
                'required',
                'integer',
                Rule::exists('incidence_types', 'id')
                    ->where('status', Status::ACTIVE)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['required', 'string', 'max:2000'],
            'status' => ['required', Rule::in([Status::ACTIVE, Status::INACTIVE])],
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
            $child = Child::query()->find($this->integer('child_id'));

            if (! $weekend || ! $child) {
                return;
            }

            if (! $scope->isGlobal() && ! $user->can('incidencias_asistencia.scope.all')) {
                if (! $scope->churchIds()->contains($weekend->church_id)) {
                    $validator->errors()->add('weekend_id', 'El fin de semana está fuera de tu alcance.');
                }

                $childInScope = $scope->churchIds()->contains($child->church_id)
                    || $scope->communityIds()->contains($child->community_id);

                if (! $childInScope) {
                    $validator->errors()->add('child_id', 'El niño está fuera de tu alcance.');
                }
            }

            if ((int) $child->church_id !== (int) $weekend->church_id) {
                $validator->errors()->add('child_id', 'El niño no pertenece a la parroquia del fin de semana.');
            }
        });
    }
}
