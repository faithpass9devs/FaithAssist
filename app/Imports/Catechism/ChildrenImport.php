<?php

namespace App\Imports\Catechism;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ChildrenImport
{
    /**
     * Fila (1-indexada) donde empiezan los datos.
     */
    public const HEADING_ROW = 1;

    /**
     * Serial del último día que Excel puede representar como fecha (31/12/9999).
     */
    private const MAX_SERIAL = 2958465.0;

    /**
     * Serial del día de hoy, para acotar las fechas de nacimiento.
     */
    private static function todaySerial(): float
    {
        return Date::PHPToExcel(new DateTimeImmutable('today'));
    }

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

                $raw[$heading] = static::cellValue($sheet, $column.$line, $heading);
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

    /**
     * Excel no guarda fechas como texto: las guarda como números seriales
     * (días desde 1899-12-30) con un formato de celda de fecha. Por eso hay que
     * mirar la celda completa y no sólo su valor.
     */
    private static function cellValue(Worksheet $sheet, string $coordinate, string $heading): string
    {
        $cell = $sheet->getCell($coordinate);
        $value = $cell->getValue();

        if ($heading === 'birthdate') {
            $date = static::excelDate($value, $cell);

            if ($date !== null) {
                return $date;
            }
        }

        return static::normalizeValue($value);
    }

    /**
     * Convierte un número serial de Excel a Y-m-d, o devuelve null si el valor
     * no es una fecha.
     */
    private static function excelDate(mixed $value, Cell $cell): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $serial = (float) $value;

        // Descarta temprano lo que no puede ser un serial, para no pagar la
        // búsqueda del formato de celda en valores que claramente no son fechas.
        if ($serial < 1 || $serial > static::MAX_SERIAL) {
            return null;
        }

        if (Date::isDateTime($cell, $value)) {
            return Date::excelToDateTimeObject($serial, 'UTC')->format('Y-m-d');
        }

        // Formato "General": hay exportadores que escriben el serial sin formato
        // de fecha. En la columna birthdate un entero nunca es otro dato que una
        // fecha de nacimiento, así que basta con acotarlo a 1900-hoy.
        if (floor($serial) !== $serial || $serial > static::todaySerial()) {
            return null;
        }

        return Date::excelToDateTimeObject($serial, 'UTC')->format('Y-m-d');
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
