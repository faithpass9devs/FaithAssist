<?php

namespace App\Http\Requests\Catechism;

use App\Globals\BloodType;
use App\Http\Requests\Concerns\UppercasesFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportExternalChildRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'paterno' => ['required', 'string', 'max:150'],
            'materno' => ['nullable', 'string', 'max:150'],
            'blood_type' => ['required', Rule::in(BloodType::values())],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'emergency_phone' => ['nullable', 'string', 'max:30'],
            'level_ids' => ['nullable', 'array'],
            'level_ids.*' => ['integer', Rule::exists('levels', 'id')->whereNull('deleted_at')],
            'privacy_terms' => ['accepted'],
        ];
    }
}
