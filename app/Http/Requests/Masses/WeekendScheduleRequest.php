<?php

namespace App\Http\Requests\Masses;

use App\Models\Ecclesiastes\Chapel;
use App\Models\Masses\Weekend;
use App\Models\Masses\WeekendMassSchedule;
use App\Services\Masses\WeekendScheduleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WeekendScheduleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $masses = $this->input('masses');

        if (! is_array($masses)) {
            return;
        }

        $this->merge([
            'masses' => collect($masses)
                ->map(function ($mass) {
                    if (! is_array($mass)) {
                        return $mass;
                    }

                    if (isset($mass['name']) && is_string($mass['name'])) {
                        $mass['name'] = mb_strtoupper(trim($mass['name']));
                    }

                    if (($mass['chapel_id'] ?? null) === '') {
                        $mass['chapel_id'] = null;
                    }

                    return $mass;
                })
                ->all(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', Weekend::class);
    }

    public function rules(): array
    {
        return [
            'church_id' => ['required', 'integer', Rule::exists('churches', 'id')->whereNull('deleted_at')],
            'masses' => ['present', 'array', 'max:50'],
            'masses.*.id' => ['nullable', 'integer'],
            'masses.*.day_of_week' => ['required', Rule::in(WeekendMassSchedule::DAYS)],
            'masses.*.name' => ['required', 'string', 'max:150'],
            'masses.*.starts_time' => ['required', 'date_format:H:i'],
            'masses.*.ends_time' => ['required', 'date_format:H:i', 'after:masses.*.starts_time'],
            'masses.*.chapel_id' => ['nullable', 'integer', Rule::exists('chapels', 'id')->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return [
            'masses.*.name' => 'nombre',
            'masses.*.starts_time' => 'hora de inicio',
            'masses.*.ends_time' => 'hora de fin',
            'masses.*.chapel_id' => 'capilla',
            'masses.*.day_of_week' => 'día',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $service = app(WeekendScheduleService::class);
            $user = $this->user();
            $churchId = $this->integer('church_id');

            if (! $service->canManageChurch($user, $churchId)) {
                $validator->errors()->add('church_id', 'Solo la parroquia puede programar sus misas de fin de semana.');

                return;
            }

            $canAssignChapels = $service->canAssignChapels($user);

            foreach ($this->input('masses', []) as $index => $mass) {
                $chapelId = $mass['chapel_id'] ?? null;

                if ($chapelId === null) {
                    continue;
                }

                if (! $canAssignChapels) {
                    $validator->errors()->add("masses.{$index}.chapel_id", 'Solo puedes programar misas propias de tu parroquia.');

                    continue;
                }

                if ((int) Chapel::query()->whereKey($chapelId)->value('church_id') !== $churchId) {
                    $validator->errors()->add("masses.{$index}.chapel_id", 'La capilla seleccionada no pertenece a la parroquia.');
                }
            }
        });
    }
}
