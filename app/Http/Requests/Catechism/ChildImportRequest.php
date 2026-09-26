<?php

namespace App\Http\Requests\Catechism;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChildImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Superadmin') === true;
    }

    public function rules(): array
    {
        return [
            'church_id' => [
                'required',
                'integer',
                Rule::exists('churches', 'id')
                    ->whereNull('deleted_at')
                    ->where('status', 'active'),
            ],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'El archivo debe ser un Excel (.xlsx o .xls).',
            'file.max' => 'El archivo no debe pesar más de 10 MB.',
            'church_id.required' => 'Debes seleccionar la parroquia a la que pertenecen los niños.',
        ];
    }
}
