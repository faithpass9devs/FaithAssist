<?php

namespace App\Support;

final class SplitLastNames
{
    /**
     * @return array{paterno:string, materno:?string}
     */
    public static function split(string $lastNames): array
    {
        $parts = preg_split('/\s+/u', trim($lastNames)) ?: [];
        $parts = array_values(array_filter($parts));
        $paterno = $parts[0] ?? '';
        $materno = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : null;

        return ['paterno' => $paterno, 'materno' => $materno];
    }
}
