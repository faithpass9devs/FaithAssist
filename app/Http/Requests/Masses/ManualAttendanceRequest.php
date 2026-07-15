<?php

namespace App\Http\Requests\Masses;

use Illuminate\Foundation\Http\FormRequest;

class ManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'child_id' => ['required', 'integer', 'exists:children,id'],
            'weekend_id' => ['required', 'integer', 'exists:weekends,id'],
            'mass_ids' => ['required', 'array', 'min:1'],
            'mass_ids.*' => ['integer', 'distinct', 'exists:masses,id'],
        ];
    }
}
