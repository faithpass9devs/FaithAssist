<?php

namespace App\Http\Requests\Masses;

use App\Globals\Status;
use App\Http\Requests\Concerns\UppercasesFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidenceTypeRequest extends FormRequest
{
    use UppercasesFields;

    protected function textFields(): array
    {
        return ['name', 'description'];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $incidenceTypeId = $this->route('incidenceType')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('incidence_types', 'name')
                    ->ignore($incidenceTypeId)
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([Status::ACTIVE, Status::INACTIVE])],
        ];
    }
}
