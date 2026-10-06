<?php

namespace App\Services\Settings;

use App\Models\Ecclesiastes\Church;
use App\Models\Settings\Setting;
use App\Models\Settings\SettingCategory;
use App\Models\Settings\SettingValue;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingResolver
{
    /**
     * Cache por request/instancia: "key|churchId|dioceseId" => [value, origin].
     *
     * @var array<string, array{value: mixed, origin: string}>
     */
    protected array $resolved = [];

    /**
     * Resuelve el valor efectivo de un ajuste siguiendo la jerarquía
     * parish (church) → diocese → global (default_value).
     */
    public function resolve(string $key, ?int $churchId, ?int $dioceseId = null): mixed
    {
        return $this->resolveWithOrigin($key, $churchId, $dioceseId)['value'];
    }

    /**
     * Idem, pero a partir de un modelo Church.
     */
    public function resolveForChurch(string $key, Church $church): mixed
    {
        return $this->resolve($key, $church->id, $church->diocese_id);
    }

    /**
     * @return array{value: mixed, origin: string}
     */
    public function resolveWithOrigin(string $key, ?int $churchId, ?int $dioceseId = null): array
    {
        $cacheKey = "{$key}|{$churchId}|{$dioceseId}";

        if (isset($this->resolved[$cacheKey])) {
            return $this->resolved[$cacheKey];
        }

        $setting = $this->findActiveSetting($key);

        if (! $setting) {
            return $this->resolved[$cacheKey] = ['value' => null, 'origin' => 'global'];
        }

        $result = $this->resolveSettingWithOrigin($setting, $churchId, $dioceseId);

        return $this->resolved[$cacheKey] = $result;
    }

    /**
     * Agrupa los ajustes activos por categoría, con el valor resuelto y su origen.
     *
     * @return array<int, array<string, mixed>>
     */
    public function groupedSettings(int $churchId, ?int $dioceseId = null): array
    {
        $categories = SettingCategory::query()
            ->activas()
            ->orderBy('sort_order')
            ->with(['settings' => fn ($query) => $query->activos()->orderBy('sort_order')])
            ->get();

        return $categories->map(function (SettingCategory $category) use ($churchId, $dioceseId) {
            $settings = $category->settings->map(function (Setting $setting) use ($churchId, $dioceseId): array {
                $resolved = $this->resolveSettingWithOrigin($setting, $churchId, $dioceseId);

                return [
                    'key' => $setting->key,
                    'name' => $setting->name,
                    'description' => $setting->description,
                    'type' => $setting->type,
                    'meta' => $setting->meta,
                    'default_value' => $setting->default_value,
                    'value' => $resolved['value'],
                    'origin' => $resolved['origin'],
                ];
            })->all();

            return [
                'key' => $category->key,
                'name' => $category->name,
                'description' => $category->description,
                'icon' => $category->icon,
                'settings' => $settings,
            ];
        })->all();
    }

    /**
     * Guarda valores a nivel parroquia (upsert) validando dinámicamente cada ajuste.
     *
     * @param  array<string, mixed>  $values
     */
    public function saveChurchValues(int $churchId, array $values, User $user): void
    {
        $data = [];
        $rules = [];

        foreach ($values as $key => $rawValue) {
            $setting = $this->findActiveSetting($key);

            if (! $setting) {
                continue;
            }

            $normalized = $this->normalize($setting, $rawValue);
            $data[$key] = $normalized;
            $rules[$key] = $this->rulesFor($setting);
        }

        if ($data === []) {
            return;
        }

        // Las claves de ajustes usan puntos (badge.accent_color), lo que colisiona
        // con la notación anidada del validador; se mapean a espacio seguro y se
        // devuelven los errores con la clave original.
        $encoded = [];
        $encodedRules = [];

        foreach ($data as $key => $normalized) {
            $safe = str_replace('.', '::', $key);
            $encoded[$safe] = $normalized;
            $encodedRules[$safe] = $rules[$key];
        }

        $validator = Validator::make($encoded, $encodedRules);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->getMessages() as $safeKey => $messages) {
                $errors[str_replace('::', '.', $safeKey)] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        foreach ($data as $key => $normalized) {
            $setting = $this->findActiveSetting($key);

            SettingValue::query()->updateOrCreate(
                [
                    'setting_id' => $setting->id,
                    'scope' => SettingValue::SCOPE_CHURCH,
                    'scope_id' => $churchId,
                ],
                [
                    'value' => $normalized,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            $this->forgetResolved($key, $churchId);
        }
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function resetChurchValues(int $churchId, array $keys, ?User $user = null): void
    {
        $settingIds = Setting::query()
            ->whereIn('key', $keys)
            ->pluck('id');

        SettingValue::query()
            ->where('scope', SettingValue::SCOPE_CHURCH)
            ->where('scope_id', $churchId)
            ->whereIn('setting_id', $settingIds)
            ->delete();

        foreach ($keys as $key) {
            $this->forgetResolved($key, $churchId);
        }
    }

    /**
     * Guarda un archivo subido y devuelve la ruta relativa al disco.
     */
    public function storeFile(int $churchId, string $key, UploadedFile $file): string
    {
        $setting = $this->findActiveSetting($key);

        if (! $setting) {
            throw ValidationException::withMessages([$key => 'Ajuste no encontrado.']);
        }

        if ($setting->type !== 'file') {
            throw ValidationException::withMessages([$key => 'Este ajuste no acepta archivos.']);
        }

        $meta = (array) ($setting->meta ?? []);

        $validator = Validator::make(['file' => $file], [
            'file' => array_filter([
                'required',
                'file',
                'image',
                ...($meta['max_size_kb'] ?? null ? ['max:'.((int) $meta['max_size_kb']).'000'] : []),
            ]),
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $directory = trim((string) ($meta['storage_scope'] ?? 'settings'), '/');
        $path = $directory.'/'.$churchId.'/'.$setting->key;

        Storage::disk('local')->makeDirectory($path);

        $stored = Storage::disk('local')->putFileAs(
            $path,
            $file,
            $setting->key.'_'.time().'.'.$file->getClientOriginalExtension(),
        );

        if (! is_string($stored)) {
            throw ValidationException::withMessages([$key => 'No se pudo guardar el archivo.']);
        }

        $this->forgetResolved($key, $churchId);

        return $stored;
    }

    /**
     * Convierte el almacenamiento local del archivo a una URL file:// utilizable por dompdf.
     */
    public function resolveFileUrl(?string $storedPath): ?string
    {
        if (! $storedPath) {
            return null;
        }

        $absolutePath = Storage::disk('local')->path($storedPath);
        $realPath = realpath($absolutePath);

        if (! $realPath || ! file_exists($realPath)) {
            return null;
        }

        return 'file://'.str_replace('\\', '/', $realPath);
    }

    /**
     * @return array{value: mixed, origin: string}
     */
    private function resolveSettingWithOrigin(Setting $setting, ?int $churchId, ?int $dioceseId): array
    {
        $value = SettingValue::query()
            ->where('setting_id', $setting->id)
            ->where('scope', SettingValue::SCOPE_CHURCH)
            ->where('scope_id', $churchId)
            ->first();

        if ($value) {
            return ['value' => $value->value, 'origin' => SettingValue::SCOPE_CHURCH];
        }

        $value = SettingValue::query()
            ->where('setting_id', $setting->id)
            ->where('scope', SettingValue::SCOPE_DIOCESE)
            ->where('scope_id', $dioceseId)
            ->first();

        if ($value) {
            return ['value' => $value->value, 'origin' => SettingValue::SCOPE_DIOCESE];
        }

        return ['value' => $setting->default_value, 'origin' => 'global'];
    }

    private function findActiveSetting(string $key): ?Setting
    {
        return Setting::query()->activos()->where('key', $key)->first();
    }

    private function forgetResolved(string $key, int $churchId): void
    {
        foreach ($this->resolved as $cacheKey => $_) {
            if (str_starts_with($cacheKey, $key.'|')) {
                unset($this->resolved[$cacheKey]);
            }
        }
    }

    private function normalize(Setting $setting, mixed $rawValue): mixed
    {
        return match ($setting->type) {
            'boolean' => filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'number' => is_numeric($rawValue) ? (float) $rawValue : $rawValue,
            default => is_string($rawValue) ? trim($rawValue) : $rawValue,
        };
    }

    /**
     * @return array<int, mixed>
     */
    private function rulesFor(Setting $setting): array
    {
        $meta = (array) ($setting->meta ?? []);
        $rules = $meta['validation'] ?? [];

        if ($setting->type === 'boolean') {
            return ['boolean'];
        }

        if ($setting->type === 'number') {
            $numberRules = ['numeric'];

            foreach (['min', 'max'] as $bound) {
                if (isset($meta[$bound]) && is_numeric($meta[$bound])) {
                    $numberRules[] = $bound.':'.$meta[$bound];
                }
            }

            return $numberRules;
        }

        if ($setting->type === 'color') {
            if (! is_array($rules)) {
                $rules = ['string'];
            }

            $rules[] = 'regex:/^#[0-9a-fA-F]{6}$/';

            return $rules;
        }

        if ($setting->type === 'select') {
            $options = collect($meta['options'] ?? [])->pluck('value')->filter();

            if ($options->isNotEmpty()) {
                $rules[] = 'in:'.$options->implode(',');
            }
        }

        return is_array($rules) ? $rules : ['nullable'];
    }
}
