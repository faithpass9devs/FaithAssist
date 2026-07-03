<?php

namespace App\Http\Requests\Security;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('usuario')?->id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name' => ['required', 'string', 'max:120'],
            'paterno' => ['required', 'string', 'max:120'],
            'materno' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'whatsapp_country_code' => ['required', 'string', Rule::exists('ladas', 'code')->where('status', 'active')],
            'whatsapp_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9\s\-\(\)]{7,15}$/'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'diocese_id' => ['nullable', 'integer', Rule::exists('dioceses', 'id'), 'required_with:deanery_id,church_id'],
            'deanery_id' => [
                'nullable',
                'integer',
                'required_with:church_id',
                Rule::exists('deaneries', 'id'),
                Rule::when(
                    filled($this->input('deanery_id')) && filled($this->input('diocese_id')),
                    Rule::exists('deaneries', 'id')->where('diocese_id', $this->input('diocese_id'))
                ),
            ],
            'church_id' => [
                'nullable',
                'integer',
                Rule::exists('churches', 'id'),
                Rule::when(
                    filled($this->input('church_id')) && filled($this->input('deanery_id')),
                    Rule::exists('churches', 'id')->where('deanery_id', $this->input('deanery_id'))
                ),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
            'password' => $isUpdate
                ? ['nullable', 'string', 'min:8', 'regex:/[A-ZÁÉÍÓÚÑ]/u', 'regex:/[a-záéíóúñ]/u', 'regex:/[0-9]/', 'confirmed']
                : ['required', 'string', 'min:8', 'regex:/[A-ZÁÉÍÓÚÑ]/u', 'regex:/[a-záéíóúñ]/u', 'regex:/[0-9]/', 'confirmed'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $editor = $this->user();
                if (! $editor) {
                    return;
                }

                $editorPermissionIds = $editor->getAllPermissions()->pluck('id');
                $manageableExportIds = Permission::query()
                    ->whereIn('name', [
                        'estados.export',
                        'municipios.export',
                        'comunidades.export',
                    ])
                    ->pluck('id');
                $assignablePermissionIds = $editorPermissionIds
                    ->merge($manageableExportIds)
                    ->unique()
                    ->toArray();

                // Validate submitted permissions are within the editor's own set
                $submittedIds = array_filter((array) $this->input('permissions', []));
                foreach ($submittedIds as $permId) {
                    if (! in_array((int) $permId, $assignablePermissionIds)) {
                        $validator->errors()->add(
                            'permissions',
                            'No puedes asignar permisos que no posees.'
                        );

                        return;
                    }
                }

                // Validate the submitted role only has permissions the editor can delegate
                $roleId = $this->input('role_id');
                if ($roleId) {
                    $role = Role::with('permissions:id')->find($roleId);
                    if ($role) {
                        $hasUnallowed = $role->permissions->contains(
                            fn ($p) => ! in_array($p->id, $assignablePermissionIds)
                        );

                        if ($hasUnallowed) {
                            $validator->errors()->add(
                                'role_id',
                                'No puedes asignar un rol que contiene permisos que no posees.'
                            );
                        }
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'deanery_id.exists' => 'El decanato seleccionado no pertenece a la diócesis asignada.',
            'church_id.exists' => 'La parroquia seleccionada no pertenece al decanato asignado.',
            'password.required' => 'Ingresa una contraseña.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe incluir mayúscula, minúscula y número.',
            'password.confirmed' => 'La confirmación de contraseña no coincide.',
        ];
    }
}
