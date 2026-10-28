<?php

namespace App\Http\Requests\Operation;

use App\Http\Requests\Concerns\UppercasesFields;
use App\Models\Ecclesiastes\Church;
use App\Models\Regions\Community;
use App\Services\UserScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    use UppercasesFields;

    protected function textFields(): array
    {
        return ['name', 'paterno', 'materno'];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_type_id' => ['required', 'integer', Rule::exists('staff_types', 'id')->whereNull('deleted_at')],
            'church_id' => ['required', 'integer', Rule::exists('churches', 'id')->whereNull('deleted_at')],
            'community_id' => ['required', 'integer', Rule::exists('communities', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'paterno' => ['required', 'string', 'max:150'],
            'materno' => ['nullable', 'string', 'max:150'],
            'phone_lada' => ['nullable', 'string', 'max:8', Rule::exists('ladas', 'code')->where('status', 'active')],
            'phone' => ['nullable', 'string', 'max:30'],
            'birthdate' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $user = $this->user();

                if (! $user) {
                    return;
                }

                $scope = new UserScopeService($user);
                $churchId = $this->integer('church_id') ?: null;
                $communityId = $this->integer('community_id') ?: null;

                if ($churchId && $communityId) {
                    $churchMunicipalityId = Church::whereKey($churchId)->value('municipality_id');
                    $communityMunicipalityId = Community::whereKey($communityId)->value('municipality_id');

                    if ($churchMunicipalityId && $communityMunicipalityId && $churchMunicipalityId !== $communityMunicipalityId) {
                        $validator->errors()->add(
                            'community_id',
                            'La comunidad seleccionada no pertenece al municipio de la iglesia.'
                        );

                        return;
                    }
                }

                if ($scope->isGlobal()) {
                    return;
                }

                $churchOk = $churchId && $scope->churchIds()->contains($churchId);
                $communityOk = $communityId && $scope->communityIds()->contains($communityId);

                if ($churchOk || $communityOk) {
                    return;
                }

                $validator->errors()->add(
                    'church_id',
                    'El personal debe pertenecer a una iglesia o comunidad dentro del alcance del usuario.'
                );
            },
        ];
    }
}
