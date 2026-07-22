<?php

namespace App\Http\Requests\Settings;

use App\Globals\SettingType;
use App\Globals\Status;
use App\Models\Settings\SettingDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateChurchSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'settings' => ['required', 'array'],
        ];

        foreach ($this->definitions() as $definition) {
            $rules["settings.{$definition->key}"] = $this->rulesFor($definition);
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $knownKeys = $this->definitions()->pluck('key')->all();
            $submittedKeys = array_keys($this->input('settings', []) + $this->file('settings', []));

            foreach (array_diff($submittedKeys, $knownKeys) as $key) {
                $validator->errors()->add("settings.{$key}", 'La configuración enviada no existe.');
            }

            foreach ($this->definitions() as $definition) {
                $value = $this->input("settings.{$definition->key}");

                if (! in_array($definition->type_data, [SettingType::ARRAY, SettingType::JSON], true) || $value === null || $value === '') {
                    continue;
                }

                if (is_string($value) && json_decode($value, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                    $validator->errors()->add("settings.{$definition->key}", 'El valor debe ser JSON valido.');
                }
            }
        });
    }

    private function rulesFor(SettingDefinition $definition): array
    {
        return match ($definition->type_data) {
            SettingType::BOOLEAN => ['nullable', 'boolean'],
            SettingType::STRING => ['nullable', 'string', 'max:2000'],
            SettingType::NUMBER => ['nullable', 'numeric'],
            SettingType::ARRAY, SettingType::JSON => ['nullable'],
            SettingType::FILE => $this->fileRules($definition->meta ?? []),
            default => ['nullable'],
        };
    }

    private function fileRules(array $meta): array
    {
        $rules = ['nullable', 'file'];

        if (! empty($meta['mimes'])) {
            $rules[] = 'mimes:'.implode(',', $meta['mimes']);
        }

        if (! empty($meta['mimetypes'])) {
            $rules[] = 'mimetypes:'.implode(',', $meta['mimetypes']);
        }

        if (! empty($meta['max_kb'])) {
            $rules[] = 'max:'.(int) $meta['max_kb'];
        }

        return $rules;
    }

    private function definitions()
    {
        return SettingDefinition::query()
            ->where('status', Status::ACTIVE)
            ->get();
    }
}
