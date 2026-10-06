<?php

namespace App\Models;

use App\Globals\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Lada extends Model
{
    protected $fillable = [
        'label',
        'country',
        'code',
        'status',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', Status::ACTIVE);
    }

    public static function options(): array
    {
        return static::query()
            ->active()
            ->orderBy('country')
            ->orderBy('code')
            ->get(['code', 'country', 'label'])
            ->map(fn (Lada $lada): array => [
                'value' => $lada->code,
                'label' => filled($lada->label)
                    ? $lada->label
                    : sprintf('%s (+%s)', $lada->country, $lada->code),
            ])
            ->values()
            ->all();
    }

    public static function codes(): array
    {
        return static::query()
            ->active()
            ->orderByRaw('LENGTH(code) DESC')
            ->pluck('code')
            ->all();
    }

    public static function defaultCode(): string
    {
        $configured = preg_replace('/\D/', '', (string) config('services.phone.default_country_code', '521')) ?: '521';

        $exists = static::query()
            ->active()
            ->where('code', $configured)
            ->exists();

        if ($exists) {
            return $configured;
        }

        return static::query()->active()->orderBy('country')->orderBy('code')->value('code') ?? '521';
    }

    public static function normalizeLocal(string $localPhone, string $countryCode): ?string
    {
        $local = preg_replace('/\D/', '', $localPhone) ?: '';
        $code = preg_replace('/\D/', '', $countryCode) ?: '';

        if ($local === '' || $code === '') {
            return null;
        }

        return '+'.$code.$local;
    }

    public static function detectCountryCode(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?: '';

        if ($digits === '') {
            return static::defaultCode();
        }

        foreach (static::codes() as $code) {
            if (str_starts_with($digits, $code)) {
                return $code;
            }
        }

        return static::defaultCode();
    }
}
