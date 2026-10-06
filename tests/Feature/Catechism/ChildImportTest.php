<?php

namespace Tests\Feature\Catechism;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Imports\Catechism\ChildrenImport;
use App\Models\Catechism\Child;
use App\Models\Catechism\ChildImportBatch;
use App\Models\Lada;
use App\Models\Operation\Level;
use App\Models\Operation\Period;
use App\Models\Operation\PeriodMovement;
use App\Models\Operation\PeriodMovementType;
use App\Models\User;
use App\Services\CatechismPeriodMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class ChildImportTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_import_endpoints_require_the_superadmin_role(): void
    {
        $chain = $this->createChain();
        $this->createLada();

        $user = $this->makeGlobalUser('children.read', 'children.create', 'children.export');

        $this->actingAs($user)->get('/children/import/template')->assertForbidden();
        $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx(),
        ])->assertForbidden();
        $this->actingAs($user)->get('/children/import/algo')->assertForbidden();
    }

    public function test_template_is_downloadable_as_xlsx(): void
    {
        $user = $this->makeSuperadmin();

        $response = $this->actingAs($user)->get('/children/import/template');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type') ?? ''
        );
    }

    public function test_import_creates_child_with_levels_in_the_current_period(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $first = $this->createLevel($chain, ['name' => 'NIVEL UNO']);
        $second = $this->createLevel($chain, ['name' => 'NIVEL DOS']);
        $movement = $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $response = $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([
                $this->row($chain, $first, ['levels' => "{$first->id},{$second->id}"]),
            ]),
        ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
        $this->assertSame(0, $response->json('rejected'));

        $this->assertDatabaseHas('children', [
            'church_id' => $chain['church']->id,
            'community_id' => $chain['community']->id,
            'name' => 'JUAN CARLOS',
            'paterno' => 'PÉREZ',
            'materno' => 'GÓMEZ',
            'origin' => 'imported',
            'status' => Status::ACTIVE,
            'sex' => Sex::MALE,
            'blood_type' => BloodType::O_NEGATIVE,
            'email' => 'juan@example.com',
            'phone_lada' => '521',
            'phone' => '5512345678',
            'emergency_phone_lada' => '521',
            'emergency_phone' => '5598765432',
            'privacy_terms' => false,
        ]);

        $this->assertDatabaseHas('child_level_assignments', [
            'level_id' => $first->id,
            'period_id' => $movement->period_id,
            'period_movement_id' => $movement->id,
            'status' => Status::ACTIVE,
        ]);
        $this->assertDatabaseHas('child_level_assignments', [
            'level_id' => $second->id,
            'period_id' => $movement->period_id,
            'period_movement_id' => $movement->id,
        ]);
        $this->assertDatabaseCount('children', 1);

        $child = Child::query()->where('name', 'JUAN CARLOS')->firstOrFail();
        $this->assertSame('2015-03-14', $child->birthdate->format('Y-m-d'));
    }

    public function test_import_maps_sex_and_blood_type_variants(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([
                $this->row($chain, $level, ['name' => 'ANA', 'sex' => 'F', 'blood_type' => 'AB+']),
                $this->row($chain, $level, [
                    'name' => 'LUIS',
                    'paterno' => 'OTRO',
                    'sex' => 'masculino',
                    'blood_type' => '',
                ]),
            ]),
        ])->assertOk();

        $this->assertDatabaseHas('children', [
            'name' => 'ANA',
            'sex' => Sex::FEMALE,
            'blood_type' => BloodType::AB_POSITIVE,
        ]);
        $this->assertDatabaseHas('children', [
            'name' => 'LUIS',
            'sex' => Sex::MALE,
            'blood_type' => BloodType::UNKNOWN,
        ]);
    }

    public function test_import_allows_missing_birthdate_and_falls_back_to_registration_date_in_the_code(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level, ['birthdate' => ''])]),
        ])->assertOk();

        $child = Child::query()->where('name', 'JUAN CARLOS')->firstOrFail();

        $this->assertNull($child->birthdate);
        $this->assertSame(
            sprintf('%s-JPG-%s-CH%d-0001', now()->format('Y'), now()->format('Ymd'), $chain['church']->id),
            $child->code
        );
    }

    public function test_import_accepts_alternative_date_formats(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level, ['birthdate' => '14/03/2015'])]),
        ])->assertOk();

        $this->assertDatabaseHas('children', [
            'name' => 'JUAN CARLOS',
        ]);

        $child = Child::query()->where('name', 'JUAN CARLOS')->firstOrFail();
        $this->assertSame('2015-03-14', $child->birthdate->format('Y-m-d'));
    }

    public function test_import_reads_birthdate_from_an_excel_date_cell(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        // Excel guarda la fecha como número serial (42562) con formato de fecha.
        $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsxDateCells([$this->row($chain, $level, ['birthdate' => 42562])]),
        ])->assertOk();

        $child = Child::query()->where('name', 'JUAN CARLOS')->firstOrFail();
        $this->assertSame('2016-07-11', $child->birthdate->format('Y-m-d'));
    }

    public function test_import_reads_birthdate_serial_without_a_date_number_format(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        // Formato "General": el serial llega sin formato de fecha.
        $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level, ['birthdate' => 42562])]),
        ])->assertOk();

        $child = Child::query()->where('name', 'JUAN CARLOS')->firstOrFail();
        $this->assertSame('2016-07-11', $child->birthdate->format('Y-m-d'));
    }

    public function test_import_keeps_rejecting_a_birthdate_that_is_not_a_date(): void
    {
        Storage::fake('local');

        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $batchId = $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level, ['birthdate' => 999999999])]),
        ])->json('batch_id');

        $this->assertDatabaseMissing('children', ['name' => 'JUAN CARLOS']);

        $batch = ChildImportBatch::query()->where('batch_id', $batchId)->firstOrFail();
        $errors = $this->errorReportRows($batch);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString(
            'fecha',
            $errors[0][1],
            'Un número fuera de rango debe seguir reportándose como fecha inválida.'
        );
    }

    public function test_import_reports_rows_with_invalid_community_or_level(): void
    {
        Storage::fake('local');

        $chain = $this->createChain();
        $other = $this->createChain();
        $other['community']->update(['name' => 'Comunidad Ajena']);
        $other['church']->update(['name' => 'Parroquia Ajena']);

        $this->createLada();
        $level = $this->createLevel($chain);
        $foreignLevel = $this->createLevel($other, ['name' => 'NIVEL AJENO']);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $response = $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([
                $this->row($chain, $level, ['comunidad' => (string) $other['community']->id]),
                $this->row($chain, $level, [
                    'name' => 'SIN SEXO',
                    'comunidad' => (string) $chain['community']->id,
                    'levels' => (string) $foreignLevel->id,
                    'sex' => 'X',
                ]),
            ]),
        ]);

        $response->assertOk();
        $this->assertSame(0, $response->json('total'));
        $this->assertSame(2, $response->json('rejected'));

        $this->assertSame(0, Child::query()->count());

        $batch = ChildImportBatch::query()->where('batch_id', $response->json('batch_id'))->firstOrFail();
        $this->assertSame(2, $batch->failed_count);
        $this->assertNotNull($batch->error_report_path);

        [$first, $second] = $this->errorReportRows($batch);

        $this->assertSame(2, $first[0]);
        $this->assertStringContainsString('no pertenece al municipio de la parroquia', $first[1]);

        $this->assertSame(3, $second[0]);
        $this->assertStringContainsString('no pertenece a la diócesis de la parroquia', $second[1]);
        $this->assertStringContainsString('sex no es válido', $second[1]);
    }

    public function test_import_still_processes_valid_rows_when_others_are_rejected(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $response = $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([
                $this->row($chain, $level),
                $this->row($chain, $level, ['name' => '', 'paterno' => 'SIN NOMBRE']),
            ]),
        ]);

        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
        $this->assertSame(1, $response->json('rejected'));
        $this->assertDatabaseHas('children', ['name' => 'JUAN CARLOS']);

        $batch = ChildImportBatch::query()->where('batch_id', $response->json('batch_id'))->firstOrFail();
        $this->assertSame(1, $batch->failed_count);
        $this->assertNotNull($batch->error_report_path);
    }

    public function test_import_requires_an_active_inscriptions_movement_for_the_selected_church(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $user = $this->makeSuperadmin();

        $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level)]),
        ])->assertSessionHasErrors('church_id');

        $this->assertSame(0, ChildImportBatch::query()->count());
    }

    public function test_import_rejects_files_without_the_required_headings(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level)], ['name', 'paterno', 'sex']),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, ChildImportBatch::query()->count());
    }

    public function test_import_rejects_a_parish_that_does_not_exist(): void
    {
        $this->createLada();
        $user = $this->makeSuperadmin();

        $this->actingAs($user)->post('/children/import', [
            'church_id' => 9999,
            'file' => $this->xlsx(),
        ])->assertSessionHasErrors('church_id');
    }

    public function test_status_endpoint_eager_loads_only_columns_that_exist(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $batchId = $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level)]),
        ])->json('batch_id');

        $queried = [];
        DB::listen(function (QueryExecuted $query) use (&$queried): void {
            if (preg_match('/from ["`]?(churches|periods)["`]?/i', $query->sql) === 1) {
                $queried[] = $query->sql;
            }
        });

        $this->actingAs($user)->get("/children/import/{$batchId}")->assertOk();

        $this->assertNotEmpty($queried, 'El estado debe cargar la parroquia y el periodo.');

        // SQLite acepta identificadores desconocidos como literales, por lo que una
        // columna inexistente en un SELECT no falla en las pruebas como sí en MySQL.
        foreach ($queried as $sql) {
            preg_match('/select (.+?) from/i', $sql, $select);
            preg_match('/from ["`]?(\w+)["`]?/i', $sql, $table);

            $this->assertNotEmpty($table, "No se pudo detectar la tabla en: {$sql}");
            $columns = Schema::getColumnListing($table[1]);

            foreach (explode(',', $select[1] ?? '') as $column) {
                $column = trim($column, " \"`\n\r\t");

                if ($column === '' || str_contains($column, '(') || str_ends_with($column, '.*')) {
                    continue;
                }

                $this->assertContains(
                    explode(' as ', $column)[0],
                    $columns,
                    "La consulta usa una columna inexistente: {$sql}"
                );
            }
        }
    }

    public function test_status_endpoint_returns_the_batch_progress(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $batchId = $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level)]),
        ])->json('batch_id');

        $response = $this->actingAs($user)->get("/children/import/{$batchId}");

        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
        $this->assertSame(1, $response->json('imported'));
        $this->assertSame(0, $response->json('failed'));
        $this->assertSame(100, $response->json('progress'));
        $this->assertTrue($response->json('finished'));
        $this->assertSame($chain['church']->name, $response->json('church'));
        $this->assertSame('PERIODO TEST', $response->json('period'));
    }

    public function test_status_endpoint_is_not_found_for_an_unknown_batch(): void
    {
        $this->makeSuperadmin();

        $this->actingAs($this->makeSuperadmin())
            ->get('/children/import/no-existe')
            ->assertNotFound();
    }

    public function test_index_exposes_the_latest_import_batch_only_for_superadmins(): void
    {
        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $superadmin = $this->makeSuperadmin();

        $batchId = $this->actingAs($superadmin)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level)]),
        ])->json('batch_id');

        $this->actingAs($superadmin)
            ->get('/children')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Catechism/Children/Index')
                ->where('latestImportBatch.batch_id', $batchId)
                ->where('latestImportBatch.imported', 1)
                ->where('latestImportBatch.finished', true));

        $this->actingAs($this->makeGlobalUser('children.read'))
            ->get('/children')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('latestImportBatch', null));
    }

    public function test_error_report_is_downloadable_when_rows_were_rejected(): void
    {
        Storage::fake('local');

        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $batchId = $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level, ['name' => ''])]),
        ])->json('batch_id');

        $batch = ChildImportBatch::query()->where('batch_id', $batchId)->firstOrFail();

        $this->assertSame(1, $batch->failed_count);
        $this->assertNotNull($batch->error_report_path);
        $this->assertTrue(
            $batch->errors()->doesntExist(),
            'Las filas de error se depuran al generar el reporte.'
        );

        $response = $this->actingAs($user)->get("/children/import/{$batchId}/errors");

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type') ?? ''
        );
    }

    public function test_error_report_is_not_available_when_every_row_was_imported(): void
    {
        Storage::fake('local');

        $chain = $this->createChain();
        $this->createLada();
        $level = $this->createLevel($chain);
        $this->createActiveMovement($chain, CatechismPeriodMovementService::INSCRIPTIONS);
        $user = $this->makeSuperadmin();

        $batchId = $this->actingAs($user)->post('/children/import', [
            'church_id' => $chain['church']->id,
            'file' => $this->xlsx([$this->row($chain, $level)]),
        ])->json('batch_id');

        $this->actingAs($user)
            ->get("/children/import/{$batchId}/errors")
            ->assertNotFound();
    }

    /**
     * Genera un XLSX donde la celda birthdate es un número serial con formato
     * de fecha, tal como lo guarda Excel al escribir una fecha.
     */
    private function xlsxDateCells(array $rows): UploadedFile
    {
        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray(array_merge([ChildrenImport::HEADINGS], $rows), null, 'A1');

        $column = Coordinate::stringFromColumnIndex(
            array_search('birthdate', ChildrenImport::HEADINGS, true) + 1
        );
        foreach ($rows as $index => $row) {
            $coordinate = $column.($index + 2);
            $sheet->getActiveSheet()->getStyle($coordinate)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $sheet->getActiveSheet()->getCell($coordinate)->setValueExplicit(
                (float) $row['birthdate'],
                DataType::TYPE_NUMERIC
            );
        }

        $sheet->setActiveSheetIndex(0);
        $writer = IOFactory::createWriter($sheet, 'Xlsx');
        ob_start();
        $writer->save('php://output');
        $contents = (string) ob_get_clean();
        $sheet->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent('ninos.xlsx', $contents);
    }

    /**
     * Lee el reporte de errores generado y devuelve sus filas como [fila, motivo].
     *
     * @return array<int, array{0: int, 1: string}>
     */
    private function errorReportRows(ChildImportBatch $batch): array
    {
        $sheet = IOFactory::createReaderForFile(
            Storage::disk('local')->path($batch->error_report_path)
        )->load(Storage::disk('local')->path($batch->error_report_path))->getActiveSheet();

        $rows = $sheet->rangeToArray('A2:B'.$sheet->getHighestRow());

        return array_map(
            fn (array $row): array => [(int) $row[0], (string) $row[1]],
            $rows
        );
    }

    private function makeSuperadmin(): User
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $user = $this->makeGlobalUser('children.read');
        $user->assignRole(Role::findOrCreate('Superadmin', 'web'));

        return $user->fresh();
    }

    private function createLada(): void
    {
        Lada::query()->create([
            'label' => 'MX (+521)',
            'country' => 'Mexico WhatsApp',
            'code' => '521',
            'status' => Status::ACTIVE,
        ]);
    }

    private function createLevel(array $chain, array $overrides = []): Level
    {
        return Level::query()->create(array_merge([
            'diocese_id' => $chain['diocese']->id,
            'name' => 'NIVEL TEST',
            'status' => Status::ACTIVE,
        ], $overrides));
    }

    private function createActiveMovement(array $chain, string $typeName): PeriodMovement
    {
        $type = PeriodMovementType::query()->create([
            'name' => $typeName,
            'status' => Status::ACTIVE,
        ]);

        $period = Period::query()->create([
            'diocese_id' => $chain['diocese']->id,
            'name' => 'PERIODO TEST',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => Status::IN_PROGRESS,
        ]);

        return PeriodMovement::query()->create([
            'period_id' => $period->id,
            'church_id' => $chain['church']->id,
            'period_movement_type_id' => $type->id,
            'status' => Status::IN_PROGRESS,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
        ]);
    }

    private function row(array $chain, Level $level, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Juan Carlos',
            'paterno' => 'Pérez',
            'materno' => 'Gómez',
            'birthdate' => '2015-03-14',
            'sex' => 'M',
            'blood_type' => 'O-',
            'comunidad' => (string) $chain['community']->id,
            'levels' => (string) $level->id,
            'email' => 'juan@example.com',
            'phone' => '55 1234 5678',
            'emergency_phone' => '55 9876 5432',
        ], $overrides);
    }

    private function xlsx(array $rows = [], ?array $headings = null): UploadedFile
    {
        $headings ??= ChildrenImport::HEADINGS;

        $contents = Excel::raw(new class($headings, $rows) implements FromArray
        {
            public function __construct(
                private readonly array $headings,
                private readonly array $rows,
            ) {}

            public function array(): array
            {
                return array_merge([$this->headings], $this->rows);
            }

            public function headings(): array
            {
                return $this->headings;
            }
        }, ExcelWriter::XLSX);

        return UploadedFile::fake()->createWithContent('ninos.xlsx', $contents);
    }
}
