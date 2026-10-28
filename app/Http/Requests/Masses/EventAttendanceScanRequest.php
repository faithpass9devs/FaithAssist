<?php

namespace App\Http\Requests\Masses;

use App\Globals\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventAttendanceScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attendee_code' => ['required', 'string', 'max:50'],
            'action' => ['required', Rule::in([Status::CHECK_IN, Status::CHECK_OUT])],
        ];
    }
}
