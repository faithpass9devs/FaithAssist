<?php

namespace App\Http\Requests\Ecclesiastes;

use App\Globals\Status;
use App\Http\Requests\Concerns\UppercasesFields;
use App\Services\UserScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChapelRequest extends FormRequest
{
    use UppercasesFields;

    protected function textFields(): array
    {
        return ['name', 'address'];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $chapelId = $this->route('capilla')?->id;

        return [
            'community_id' => ['required', 'integer', Rule::exists('communities', 'id')->whereNull('deleted_at')],
            'church_id' => ['nullable', 'integer', Rule::exists('churches', 'id')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255', Rule::unique('chapels', 'name')->ignore($chapelId)->whereNull('deleted_at')],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([Status::ACTIVE, Status::INACTIVE])],
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

                if ($scope->isGlobal() || ($user->church_id === null && $user->can('capillas.scope.all'))) {
                    return;
                }

                $communityId = $this->integer('community_id') ?: null;
                $churchId = $this->filled('church_id') ? $this->integer('church_id') : null;

                $communityOk = $communityId && $scope->communityIds()->contains($communityId);
                $churchOk = $churchId && $scope->churchIds()->contains($churchId);

                if ($user->church_id !== null) {
                    if (! $communityOk) {
                        $validator->errors()->add(
                            'community_id',
                            'La comunidad debe estar asociada a la parroquia asignada al usuario.'
                        );

                        return;
                    }

                    if ($churchId && ! $churchOk) {
                        $validator->errors()->add(
                            'church_id',
                            'La parroquia debe corresponder al usuario.'
                        );

                        return;
                    }

                    return;
                }

                if ($communityOk || $churchOk) {
                    return;
                }

                $validator->errors()->add(
                    'community_id',
                    'La capilla debe pertenecer a una comunidad o parroquia dentro del alcance del usuario.'
                );
            },
        ];
    }
}
