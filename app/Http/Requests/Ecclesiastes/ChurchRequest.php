<?php

namespace App\Http\Requests\Ecclesiastes;

use App\Globals\Status;
use App\Http\Requests\Concerns\UppercasesFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChurchRequest extends FormRequest
{
    use UppercasesFields;

    protected function textFields(): array
    {
        return ['name', 'alias', 'address'];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $churchId = $this->route('parroquia')?->id;

        return [
            'municipality_id' => ['required', 'integer', Rule::exists('municipalities', 'id')->whereNull('deleted_at')],
            'deanery_id'      => ['nullable', 'integer', Rule::exists('deaneries', 'id')->whereNull('deleted_at')],
            'name'            => ['required', 'string', 'max:255'],
            'alias'           => ['nullable', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'address'         => ['nullable', 'string', 'max:255'],
            'status'          => ['required', Rule::in([Status::ACTIVE, Status::INACTIVE])],
        ];
    }

}
