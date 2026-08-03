<?php

namespace App\Support;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;

final class ExternalDataMapper
{
    public static function sex(?string $sex): string
    {
        return match (mb_strtoupper(trim((string) $sex))) {
            'M' => Sex::FEMALE,
            default => Sex::MALE,
        };
    }

    public static function bloodType(?string $value): string
    {
        $value = trim((string) $value);

        if (in_array($value, BloodType::values(), true)) {
            return $value;
        }

        $normalized = str_replace('−', '-', $value);

        $map = [
            'A+' => BloodType::A_POSITIVE,
            'A-' => BloodType::A_NEGATIVE,
            'B+' => BloodType::B_POSITIVE,
            'B-' => BloodType::B_NEGATIVE,
            'AB+' => BloodType::AB_POSITIVE,
            'AB-' => BloodType::AB_NEGATIVE,
            'O+' => BloodType::O_POSITIVE,
            'O-' => BloodType::O_NEGATIVE,
        ];

        return $map[$normalized] ?? BloodType::UNKNOWN;
    }

    public static function status(?string $status): string
    {
        return Status::ACTIVE;
    }

    public static function phone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', '', $phone) ?? '';

        return $normalized === '' ? null : $normalized;
    }
}
