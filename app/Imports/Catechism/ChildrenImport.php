<?php

namespace App\Imports\Catechism;

use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChildrenImport
{
    /**
     * Fila (1-indexada) donde empiezan los datos.
     */
    public const HEADING_ROW = 1;

    /**
     * Encabezados aceptados, ya normalizados (minúsculas, sin acentos, con _).
     */
    public const HEADINGS = [
        'name',
        'paterno',
        'materno',
        'birthdate',
        'sex',
        'blood_type',
        'comunidad',
        'levels',
        'email',
        'phone',
        'emergency_phone',
    ];

    /**
     * Lee la hoja activa y devuelve una entrada por fila con datos, con el número
     * real de línea del Excel para poder reportar errores.
     *
     * @return Collection<int, array{line: int, raw: array<string, string>}>
     */
    public static function rows(string $path): Collection
    {
        $sheet = static::sheet($path);
        $columns = static::headingColumns($sheet);
        $rows = collect();

        $lastRow = $sheet->getHighestRow();

        for ($line = static::HEADING_ROW + 1; $line <= $lastRow; $line++) {
            $raw = [];

            foreach ($columns as $column => $heading) {
                if ($heading === '') {
                    continue;
                }

                $raw[$heading] = static::normalizeValue($sheet->getCell($column.$line)->getValue());
            }

            if (static::isBlank($raw)) {
                continue;
            }

            $rows->push(['line' => $line, 'raw' => $raw]);
        }

        return $rows;
    }

    /**
     * Lee la primera fila del archivo y devuelve los encabezados tal cual vienen.
     */
    public static function headings(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $row = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0] ?? [];

        return array_map(fn ($value): string => static::normalizeValue($value), $row);
    }

    /**
     * Verifica que el archivo tenga los 11 encabezados requeridos.
     *
     * @throws ValidationException
     */
    public static function assertHeadings(?array $headings): void
    {
        $present = collect($headings ?? [])
            ->map(fn ($heading): string => static::normalizeHeading((string) $heading))
            ->all();

        $missing = array_values(array_diff(self::HEADINGS, $present));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'El archivo debe contener los encabezados: '.implode(', ', $missing).'.',
            ]);
        }
    }

    public static function normalizeHeading(string $heading): string
    {
        return Str::of($heading)
            ->ascii()
            ->lower()
            ->replaceMatches('/[\s\-]+/u', '_')
            ->trim('_')
            ->toString();
    }

    public static function normalizeValue(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_float($value)) {
            $value = fmod($value, 1.0) === 0.0 ? (int) $value : $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return trim((string) $value);
    }

    private static function sheet(string $path): Worksheet
    {
        return IOFactory::createReaderForFile($path)->load($path)->getActiveSheet();
    }

    /**
     * @return array<string, string> columna (A, B, ...) => encabezado normalizado
     */
    private static function headingColumns(Worksheet $sheet): array
    {
        $row = $sheet->rangeToArray('A'.static::HEADING_ROW.':'.$sheet->getHighestColumn().static::HEADING_ROW)[0] ?? [];
        $columns = [];
        $index = 0;

        foreach ($row as $value) {
            $columns[Coordinate::stringFromColumnIndex(++$index)] = static::normalizeHeading(
                static::normalizeValue($value)
            );
        }

        return $columns;
    }

    private static function isBlank(array $raw): bool
    {
        return collect($raw)
            ->filter(fn (?string $value): bool => $value !== null && trim($value) !== '')
            ->isEmpty();
    }
}
