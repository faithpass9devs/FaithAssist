<?php

namespace App\Http\Requests\Operation;

use App\Globals\Status;
use App\Http\Requests\Concerns\UppercasesFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffTypeRequest extends FormRequest
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
        $staffTypeId = $this->route('tipo_personal')?->id;

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('staff_types', 'name')->ignore($staffTypeId)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([Status::ACTIVE, Status::INACTIVE])],
        ];
    }
}
