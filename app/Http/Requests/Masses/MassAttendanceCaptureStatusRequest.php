<?php

namespace App\Http\Requests\Masses;

use App\Globals\Status;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MassAttendanceCaptureStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'capture' => ['required', Rule::in([Status::CHECK_IN, Status::CHECK_OUT])],
            'status' => ['required', Rule::in([Status::IN_PROGRESS, Status::COMPLETED])],
        ];
    }
}
