<?php

namespace App\Http\Requests\Catechism;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Http\Requests\Concerns\UppercasesFields;
use App\Models\Ecclesiastes\Church;
use App\Models\Operation\Level;
use App\Models\Regions\Community;
use App\Services\CatechismPeriodMovementService;
use App\Services\UserScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChildRequest extends FormRequest
{
    use UppercasesFields;

    protected function textFields(): array
    {
        return ['name', 'paterno', 'materno', 'observations'];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'integer', Rule::exists('churches', 'id')->whereNull('deleted_at')],
            'community_id' => ['required', 'integer', Rule::exists('communities', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:150'],
            'paterno' => ['required', 'string', 'max:150'],
            'materno' => ['nullable', 'string', 'max:150'],
            'birthdate' => ['required', 'date', 'before_or_equal:today'],
            'sex' => ['required', Rule::in(Sex::values())],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_lada' => ['nullable', 'string', 'max:8', Rule::exists('ladas', 'code')->where('status', 'active')],
            'phone' => ['nullable', 'string', 'max:30'],
            'emergency_phone_lada' => ['nullable', 'string', 'max:8', Rule::exists('ladas', 'code')->where('status', 'active')],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'blood_type' => ['required', Rule::in(BloodType::values())],
            'observations' => ['nullable', 'string', 'max:2000'],
            'privacy_terms' => ['accepted'],
            'status' => ['required', Rule::in([
                Status::ACTIVE,
                Status::INACTIVE,
                Status::COMPLETED,
                Status::WITHDRAW,
                Status::SUSPENDED,
            ])],
            'level_ids' => $this->isMethod('post')
                ? ['required', 'array', 'min:1']
                : ['nullable', 'array'],
            'level_ids.*' => ['integer', Rule::exists('levels', 'id')->whereNull('deleted_at')],
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
                    $this->validateLevels($validator, $churchId);

                    return;
                }

                $churchOk = $churchId && $scope->churchIds()->contains($churchId);
                $communityOk = $communityId && $scope->communityIds()->contains($communityId);

                if ($churchOk || $communityOk) {
                    $this->validateLevels($validator, $churchId);

                    return;
                }

                $validator->errors()->add(
                    'church_id',
                    'El niño debe pertenecer a una iglesia o comunidad dentro del alcance del usuario.'
                );
            },
        ];
    }

    private function validateLevels($validator, ?int $churchId): void
    {
        if (! $churchId || $validator->errors()->isNotEmpty()) {
            return;
        }

        $church = Church::query()->with('deanery:id,diocese_id')->find($churchId);
        $dioceseId = $church?->deanery?->diocese_id;
        $levelIds = collect($this->input('level_ids', []))->filter()->unique()->values();

        if (! $dioceseId || ($levelIds->isEmpty() && $this->isMethod('post'))) {
            return;
        }

        $validLevelCount = Level::query()
            ->whereIn('id', $levelIds)
            ->where('diocese_id', $dioceseId)
            ->where('status', Status::ACTIVE)
            ->count();

        if ($validLevelCount !== $levelIds->count()) {
            $validator->errors()->add('level_ids', 'Los niveles seleccionados deben pertenecer a la diócesis de la parroquia.');

            return;
        }

        if ($this->isMethod('post') && ! app(CatechismPeriodMovementService::class)->activeMovementForChurch($church, CatechismPeriodMovementService::INSCRIPTIONS)) {
            $validator->errors()->add('church_id', 'No hay un movimiento de inscripciones activo para la parroquia seleccionada.');
        }
    }
}
