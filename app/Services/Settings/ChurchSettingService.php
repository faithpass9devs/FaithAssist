<?php

namespace App\Services\Settings;

use App\Globals\SettingType;
use App\Globals\Status;
use App\Models\Ecclesiastes\Church;
use App\Models\Settings\ChurchSetting;
use App\Models\Settings\SettingDefinition;
use App\Models\User;
use App\Services\UserScopeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChurchSettingService
{
    public function indexData(User $user, ?int $churchId = null): array
    {
        $churches = $this->visibleChurches($user);
        $church = $this->resolveChurch($user, $churchId, $churches);

        return [
            'churches' => $churches,
            'selectedChurchId' => $church?->id,
            'settings' => $church ? $this->settingsForChurch($church) : [],
        ];
    }

    public function visibleChurches(User $user)
    {
        $scope = new UserScopeService($user);

        return Church::query()
            ->when(! $scope->isGlobal(), fn ($query) => $query->whereIn('id', $scope->churchIds()))
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function resolveChurch(User $user, ?int $churchId, $churches = null): ?Church
    {
        $churches ??= $this->visibleChurches($user);

        if ($churchId !== null) {
            return $churches->firstWhere('id', $churchId);
        }

        if ($user->church_id !== null) {
            return $churches->firstWhere('id', $user->church_id);
        }

        return $churches->first();
    }

    public function settingsForChurch(Church $church): array
    {
        $definitions = SettingDefinition::query()
            ->where('status', Status::ACTIVE)
            ->orderBy('name')
            ->get();

        $values = ChurchSetting::query()
            ->where('church_id', $church->id)
            ->whereIn('setting_definition_id', $definitions->pluck('id'))
            ->get()
            ->keyBy('setting_definition_id');

        return $definitions
            ->map(function (SettingDefinition $definition) use ($values): array {
                $setting = $values->get($definition->id);

                return [
                    'id' => $definition->id,
                    'key' => $definition->key,
                    'name' => $definition->name,
                    'description' => $definition->description,
                    'type_data' => $definition->type_data,
                    'meta' => $definition->meta ?? [],
                    'value' => $setting?->value['value'] ?? null,
                    'file' => $definition->type_data === SettingType::FILE ? ($setting?->value ?? null) : null,
                ];
            })
            ->values()
            ->all();
    }

    public function updateSettings(Church $church, array $validated, User $user): void
    {
        $definitions = SettingDefinition::query()
            ->where('status', Status::ACTIVE)
            ->get()
            ->keyBy('key');

        $submitted = $validated['settings'] ?? [];

        DB::transaction(function () use ($church, $definitions, $submitted, $user): void {
            foreach ($submitted as $key => $value) {
                $definition = $definitions->get($key);

                if (! $definition) {
                    continue;
                }

                if ($definition->type_data === SettingType::FILE && ! $value instanceof UploadedFile) {
                    continue;
                }

                $payload = $this->payloadFor($definition, $value, $church);

                ChurchSetting::query()->updateOrCreate(
                    [
                        'church_id' => $church->id,
                        'setting_definition_id' => $definition->id,
                    ],
                    [
                        'value' => $payload,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]
                );
            }
        });
    }

    private function payloadFor(SettingDefinition $definition, mixed $value, Church $church): array
    {
        if ($definition->type_data === SettingType::FILE) {
            $path = $value->store("settings/churches/{$church->id}");

            return [
                'path' => $path,
                'disk' => config('filesystems.default'),
                'name' => $value->getClientOriginalName(),
                'mime' => $value->getMimeType(),
                'size' => $value->getSize(),
                'url' => Storage::url($path),
            ];
        }

        return [
            'value' => match ($definition->type_data) {
                SettingType::BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                SettingType::NUMBER => is_numeric($value) ? $value + 0 : null,
                SettingType::ARRAY, SettingType::JSON => is_string($value) ? json_decode($value, true) : $value,
                default => $value,
            },
        ];
    }
}
