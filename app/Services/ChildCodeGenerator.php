<?php

namespace App\Services;

use App\Models\Catechism\Child;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ChildCodeGenerator
{
    /**
     * @param  array{name:string,paterno:string,materno?:string|null,birthdate?:string|null,church_id:int}  $data
     */
    public function generate(array $data, ?CarbonInterface $registeredAt = null): string
    {
        $registeredAt ??= now();
        $birthdate = filled($data['birthdate'] ?? null)
            ? Carbon::parse($data['birthdate'])->format('Ymd')
            : $registeredAt->format('Ymd');
        $base = sprintf(
            '%s-%s-%s-CH%s',
            $registeredAt->format('Y'),
            $this->initials($data['name'], $data['paterno'], $data['materno'] ?? null),
            $birthdate,
            $data['church_id'],
        );

        $sequence = Child::withTrashed()
            ->where('code', 'like', "{$base}-%")
            ->count() + 1;

        do {
            $code = sprintf('%s-%04d', $base, $sequence);
            $sequence++;
        } while (Child::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private function initials(string $name, string $paterno, ?string $materno): string
    {
        $parts = [$name, $paterno, $materno];

        return collect($parts)
            ->map(fn (?string $part) => $this->firstLetter($part))
            ->filter()
            ->implode('') ?: 'XXX';
    }

    private function firstLetter(?string $value): ?string
    {
        $normalized = Str::of((string) $value)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z]/', '')
            ->toString();

        return $normalized !== '' ? $normalized[0] : null;
    }
}
