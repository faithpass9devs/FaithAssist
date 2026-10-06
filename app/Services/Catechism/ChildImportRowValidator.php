<?php

namespace App\Services\Catechism;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Models\Lada;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Convierte una fila del Excel en los datos de `children` + `level_ids`,
 * acumulando todos los errores de esa fila en vez de abortar en el primero.
 */
final class ChildImportRowValidator
{
    private const DATE_FORMATS = [
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'd/m/Y H:i:s',
        'd/m/Y',
        'd-m-Y',
        'Y/m/d',
        'd.m.Y',
    ];

    private const SEX_MAP = [
        'M' => Sex::MALE,
        'H' => Sex::MALE,
        'MASC' => Sex::MALE,
        'MASCULINO' => Sex::MALE,
        'MALE' => Sex::MALE,
        'F' => Sex::FEMALE,
        'FEM' => Sex::FEMALE,
        'FEMENINO' => Sex::FEMALE,
        'FEMALE' => Sex::FEMALE,
    ];

    private const BLOOD_TYPE_MAP = [
        'A+' => BloodType::A_POSITIVE,
        'A-' => BloodType::A_NEGATIVE,
        'B+' => BloodType::B_POSITIVE,
        'B-' => BloodType::B_NEGATIVE,
        'AB+' => BloodType::AB_POSITIVE,
        'AB-' => BloodType::AB_NEGATIVE,
        'O+' => BloodType::O_POSITIVE,
        'O-' => BloodType::O_NEGATIVE,
    ];

    /**
     * Lada de México configurada para WhatsApp, o null si no está en el catálogo activo.
     */
    public static function defaultLada(): ?string
    {
        $code = Lada::defaultCode();

        $exists = Lada::query()
            ->where('code', $code)
            ->where('status', Status::ACTIVE)
            ->exists();

        return $exists ? $code : null;
    }

    /**
     * @return array{data: array<string, mixed>, level_ids: array<int, int>, errors: array<int, string>}
     */
    public function validate(array $raw, ChildImportContext $context): array
    {
        $errors = [];

        $name = $this->text($raw['name'] ?? null);
        $paterno = $this->text($raw['paterno'] ?? null);
        $materno = $this->text($raw['materno'] ?? null);

        if ($name === null) {
            $errors[] = 'La columna name es obligatoria.';
        } else {
            $this->assertMaxLength($name, 150, 'name', $errors);
        }

        if ($paterno === null) {
            $errors[] = 'La columna paterno es obligatoria.';
        } else {
            $this->assertMaxLength($paterno, 150, 'paterno', $errors);
        }

        if ($materno !== null) {
            $this->assertMaxLength($materno, 150, 'materno', $errors);
        }

        $sex = $this->sex($raw['sex'] ?? null, $errors);
        $bloodType = $this->bloodType($raw['blood_type'] ?? null, $errors);
        $birthdate = $this->birthdate($raw['birthdate'] ?? null, $errors);
        $email = $this->email($raw['email'] ?? null, $errors);
        $phone = $this->phone($raw['phone'] ?? null, 'phone', $errors);
        $emergencyPhone = $this->phone($raw['emergency_phone'] ?? null, 'emergency_phone', $errors);
        $communityId = $this->communityId($raw['comunidad'] ?? null, $context, $errors);
        $levelIds = $this->levelIds($raw['levels'] ?? null, $context, $errors);

        if ($errors !== []) {
            return ['data' => [], 'level_ids' => [], 'errors' => $errors];
        }

        return [
            'data' => [
                'church_id' => $context->church->id,
                'community_id' => $communityId,
                'name' => $name,
                'paterno' => $paterno,
                'materno' => $materno,
                'origin' => 'imported',
                'birthdate' => $birthdate,
                'sex' => $sex,
                'email' => $email,
                'phone_lada' => $phone === null ? null : $context->lada,
                'phone' => $phone,
                'emergency_phone_lada' => $emergencyPhone === null ? null : $context->lada,
                'emergency_phone' => $emergencyPhone,
                'blood_type' => $bloodType,
                'observations' => null,
                'privacy_terms' => false,
                'status' => Status::ACTIVE,
            ],
            'level_ids' => $levelIds,
            'errors' => [],
        ];
    }

    private function text(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : mb_strtoupper($normalized);
    }

    private function assertMaxLength(string $value, int $max, string $key, array &$errors): void
    {
        if (mb_strlen($value) > $max) {
            $errors[] = sprintf('La columna %s excede %d caracteres.', $key, $max);
        }
    }

    private function sex(mixed $value, array &$errors): ?string
    {
        $normalized = (string) preg_replace('/\s+/u', '', mb_strtoupper(trim((string) $value)));

        if ($normalized === '') {
            $errors[] = 'La columna sex es obligatoria (M o F).';

            return null;
        }

        if (! isset(self::SEX_MAP[$normalized])) {
            $errors[] = sprintf('El valor "%s" en sex no es válido (usa M o F).', $normalized);

            return null;
        }

        return self::SEX_MAP[$normalized];
    }

    private function bloodType(mixed $value, array &$errors): ?string
    {
        $normalized = mb_strtoupper(trim((string) $value));

        if ($normalized === '') {
            return BloodType::UNKNOWN;
        }

        // Acepta los valores internos de BloodType (p. ej. "o_negative") para que una fila
        // ya validada pueda volver a pasar por el validador dentro del job.
        foreach (BloodType::values() as $bloodType) {
            if (mb_strtoupper($bloodType) === $normalized) {
                return $bloodType;
            }
        }

        $normalized = str_replace(['0+', '0-', ' '], ['O+', 'O-', ''], $normalized);

        if (isset(self::BLOOD_TYPE_MAP[$normalized])) {
            return self::BLOOD_TYPE_MAP[$normalized];
        }

        $errors[] = sprintf(
            'El valor "%s" en blood_type no es válido (usa O+, O-, A+, A-, B+, B-, AB+ o AB-).',
            $normalized
        );

        return null;
    }

    private function birthdate(mixed $value, array &$errors): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $normalized);
            } catch (Throwable) {
                continue;
            }

            if ($parsed === false || $parsed->format($format) !== $normalized) {
                continue;
            }

            if ($parsed->isFuture()) {
                $errors[] = 'La fecha de nacimiento no puede ser futura.';

                return null;
            }

            return $parsed->toDateString();
        }

        $errors[] = sprintf(
            'El valor "%s" en birthdate no es una fecha válida (usa YYYY-MM-DD o DD/MM/AAAA).',
            $normalized
        );

        return null;
    }

    private function email(mixed $value, array &$errors): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > 255) {
            $errors[] = 'La columna email excede 255 caracteres.';

            return null;
        }

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = sprintf('El correo "%s" no es válido.', $normalized);

            return null;
        }

        return $normalized;
    }

    private function phone(mixed $value, string $key, array &$errors): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $digits = (string) preg_replace('/\D+/', '', $normalized);

        if ($digits === '') {
            return null;
        }

        if (mb_strlen($digits) > 30) {
            $errors[] = sprintf('La columna %s excede 30 caracteres.', $key);

            return null;
        }

        return $digits;
    }

    private function communityId(mixed $value, ChildImportContext $context, array &$errors): ?int
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            $errors[] = 'La columna comunidad es obligatoria.';

            return null;
        }

        $id = (int) $normalized;

        if ((string) $id !== $normalized || $id <= 0) {
            $errors[] = sprintf('El valor "%s" en comunidad no es un id válido.', $normalized);

            return null;
        }

        if (! array_key_exists($id, $context->communities)) {
            $errors[] = $context->church->municipality_id
                ? sprintf('La comunidad %d no existe o no pertenece al municipio de la parroquia.', $id)
                : sprintf('La comunidad %d no existe o está inactiva.', $id);

            return null;
        }

        return $id;
    }

    /**
     * @return array<int, int>
     */
    private function levelIds(mixed $value, ChildImportContext $context, array &$errors): array
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            $errors[] = 'La columna levels es obligatoria.';

            return [];
        }

        $ids = [];

        foreach (explode(',', $normalized) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $id = (int) $part;

            if ((string) $id !== $part || $id <= 0) {
                $errors[] = sprintf('El nivel "%s" en levels no es un id válido.', $part);

                continue;
            }

            if (! array_key_exists($id, $context->levels)) {
                $errors[] = sprintf(
                    'El nivel %d no existe, está inactivo o no pertenece a la diócesis de la parroquia.',
                    $id
                );

                continue;
            }

            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }
}
