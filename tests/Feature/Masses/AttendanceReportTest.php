<?php

namespace Tests\Feature\Masses;

use App\Globals\BloodType;
use App\Globals\Sex;
use App\Globals\Status;
use App\Models\Catechism\Child;
use App\Models\Ecclesiastes\Chapel;
use App\Models\Masses\IncidenceType;
use App\Models\Masses\Mass;
use App\Models\Masses\MassAttendance;
use App\Models\Masses\MassAttendanceIncident;
use App\Models\Masses\Weekend;
use App\Models\Regions\Community;
use App\Models\Settings\Setting;
use App\Models\Settings\SettingCategory;
use App\Models\Settings\SettingValue;
use App\Services\Masses\AttendanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;
use ZipArchive;

class AttendanceReportTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    private array $chain;

    private int $childSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-26 10:00:00');
        $this->chain = $this->createChain();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_options_only_expose_concluded_weekends_with_attendance(): void
    {
        $user = $this->makeGlobalUser('weekends.report');
        $child = $this->createChild();

        // Septiembre: concluido con asistencia -> visible.
        $september = $this->createWeekend('2026-09-05', Status::COMPLETED);
        $this->markPresent($this->createMass($september, '2026-09-06 10:00'), $child);

        // Octubre: en curso pero ya terminó y tiene asistencia -> visible.
        $october = $this->createWeekend('2026-10-17', Status::IN_PROGRESS);
        $this->markPresent($this->createMass($october, '2026-10-18 10:00'), $child);

        // Agosto: concluido pero sin asistencia -> oculto.
        $this->createMass($this->createWeekend('2026-08-01', Status::COMPLETED), '2026-08-02 10:00');

        // Noviembre: futuro -> oculto.
        $this->createMass($this->createWeekend('2026-11-07', Status::UPCOMING), '2026-11-08 10:00');

        $response = $this->actingAs($user)
            ->getJson('/fines-semana-misas/reporte-asistencias/opciones?church_id='.$this->chain['church']->id)
            ->assertOk()
            ->assertJsonPath('church_id', $this->chain['church']->id)
            ->assertJsonPath('absence_limit', AttendanceReportService::DEFAULT_MAX_ABSENCES)
            ->assertJsonCount(2, 'weekends')
            ->assertJsonCount(2, 'months')
            ->assertJsonPath('months.0.value', '2026-10')
            ->assertJsonPath('months.0.label', 'Octubre 2026')
            ->assertJsonPath('months.1.value', '2026-09')
            ->assertJsonPath('range.min', '2026-09-05')
            ->assertJsonPath('range.max', '2026-10-17');

        $this->assertSame([$september->id, $october->id], array_column($response->json('weekends'), 'id'));
        $this->assertSame([$this->chain['community']->id], array_column($response->json('communities'), 'id'));
    }

    public function test_report_classifies_children_against_the_parish_absence_limit(): void
    {
        $user = $this->makeGlobalUser('weekends.report');
        $regular = $this->createChild(['name' => 'ANA']);
        $atRisk = $this->createChild(['name' => 'BETO']);
        $blocked = $this->createChild(['name' => 'CARLA']);
        $justified = $this->createChild(['name' => 'DIEGO']);

        $weekends = collect(['2026-10-03', '2026-10-10', '2026-10-17'])
            ->map(fn (string $date) => $this->createWeekend($date, Status::COMPLETED));
        $masses = $weekends->map(fn (Weekend $weekend) => $this->createMass($weekend, $weekend->starts_at->copy()->addDay()->setTime(10, 0)->format('Y-m-d H:i')));
        // El primer fin de semana también tiene misa el sábado.
        $saturdayMass = $this->createMass($weekends[0], '2026-10-03 18:00');

        $type = IncidenceType::query()->create(['name' => 'ENFERMEDAD', 'status' => Status::ACTIVE]);

        foreach ($masses as $index => $mass) {
            // Ana: el primer fin de semana va a las dos misas (una sola asistencia),
            // el segundo solo registra entrada (sigue siendo asistencia) y el tercero completo.
            if ($index === 1) {
                $this->markCheckInOnly($mass, $regular);
            } else {
                $this->markPresent($mass, $regular);
            }

            // Diego falta siempre pero justifica todas -> regular.
            MassAttendanceIncident::query()->create([
                'weekend_id' => $mass->weekend_id,
                'child_id' => $justified->id,
                'incidence_type_id' => $type->id,
                'status' => Status::ACTIVE,
            ]);
        }

        $this->markPresent($saturdayMass, $regular);

        // Beto solo registra entrada el sábado del primer fin de semana: 1 asistencia, 2 faltas -> en riesgo.
        $this->markCheckInOnly($saturdayMass, $atRisk);

        // Carla nunca asiste ningún día: 3 faltas -> sin derecho.

        $report = app(AttendanceReportService::class)->build($user, [
            'church_id' => $this->chain['church']->id,
            'month' => '2026-10',
        ]);

        $rows = collect($report['rows'])->keyBy('child_id');

        $this->assertSame(3, $report['absence_limit']);
        $this->assertSame('Octubre de 2026', $report['period_label']);
        $this->assertSame('Octubre_2026', $report['period_slug']);
        $this->assertCount(3, $report['weekends']);

        $this->assertSame(['P', 'P', 'P'], str_split($rows[$regular->id]['codes']));
        $this->assertSame('2 misas', $rows[$regular->id]['details'][0]);
        $this->assertSame(3, $rows[$regular->id]['present']);
        $this->assertSame(AttendanceReportService::STATUS_REGULAR, $rows[$regular->id]['status']);
        $this->assertSame(3, $rows[$regular->id]['remaining']);

        $this->assertSame(['P', 'F', 'F'], str_split($rows[$atRisk->id]['codes']));
        $this->assertSame(2, $rows[$atRisk->id]['absences']);
        $this->assertSame(AttendanceReportService::STATUS_AT_RISK, $rows[$atRisk->id]['status']);
        $this->assertSame(1, $rows[$atRisk->id]['remaining']);

        $this->assertSame(['F', 'F', 'F'], str_split($rows[$blocked->id]['codes']));
        $this->assertSame(3, $rows[$blocked->id]['absences']);
        $this->assertSame(AttendanceReportService::STATUS_BLOCKED, $rows[$blocked->id]['status']);
        $this->assertSame(0, $rows[$blocked->id]['remaining']);

        $this->assertSame(['J', 'J', 'J'], str_split($rows[$justified->id]['codes']));
        $this->assertSame('ENFERMEDAD', $rows[$justified->id]['details'][0]);
        $this->assertSame(0, $rows[$justified->id]['absences']);
        $this->assertSame(3, $rows[$justified->id]['justified']);
        $this->assertSame(AttendanceReportService::STATUS_REGULAR, $rows[$justified->id]['status']);

        $this->assertSame(4, $report['summary']['children']);
        $this->assertSame(2, $report['summary']['regular']);
        $this->assertSame(1, $report['summary']['at_risk']);
        $this->assertSame(1, $report['summary']['blocked']);
        $this->assertSame(2, $report['summary']['by_weekend'][0]['present']);
        $this->assertSame(1, $report['summary']['by_weekend'][0]['absent']);
        $this->assertSame(1, $report['summary']['by_weekend'][0]['justified']);
    }

    public function test_church_level_setting_raises_the_absence_limit(): void
    {
        $this->seedAbsenceLimitSetting(churchValue: 4);
        $user = $this->makeGlobalUser('weekends.report');
        $child = $this->createChild();

        foreach (['2026-10-03', '2026-10-10', '2026-10-17'] as $date) {
            $this->createMass($this->createWeekend($date, Status::COMPLETED), $date.' 10:00');
        }

        // Alguien más asistió para que los fines de semana cuenten como capturados.
        $other = $this->createChild(['name' => 'OTRO']);
        Mass::query()->each(fn (Mass $mass) => $this->markPresent($mass, $other));

        $report = app(AttendanceReportService::class)->build($user, [
            'church_id' => $this->chain['church']->id,
            'month' => '2026-10',
        ]);

        $row = collect($report['rows'])->firstWhere('child_id', $child->id);

        $this->assertSame(4, $report['absence_limit']);
        $this->assertSame(3, $row['absences']);
        $this->assertSame(AttendanceReportService::STATUS_AT_RISK, $row['status']);
        $this->assertSame(1, $row['remaining']);
    }

    public function test_weekends_before_registration_or_without_community_mass_do_not_count(): void
    {
        $user = $this->makeGlobalUser('weekends.report');
        $otherCommunity = Community::query()->create([
            'name' => 'OTRA COMUNIDAD',
            'municipality_id' => $this->chain['municipality']->id,
            'status' => Status::ACTIVE,
        ]);
        $chapel = Chapel::query()->create([
            'name' => 'CAPILLA OTRA',
            'church_id' => $this->chain['church']->id,
            'community_id' => $otherCommunity->id,
            'status' => Status::ACTIVE,
        ]);

        $veteran = $this->createChild(['name' => 'VETERANO']);
        $newcomer = $this->createChild(['name' => 'NUEVO']);
        $newcomer->forceFill(['created_at' => '2026-10-08 09:00:00'])->saveQuietly();

        $early = $this->createWeekend('2026-10-03', Status::COMPLETED);
        $this->markPresent($this->createMass($early, '2026-10-04 10:00'), $veteran);

        // Fin de semana donde solo hubo misa en una capilla de otra comunidad.
        $chapelOnly = $this->createWeekend('2026-10-10', Status::COMPLETED);
        $chapelMass = $this->createMass($chapelOnly, '2026-10-11 10:00', $chapel->id);
        $this->markPresent($chapelMass, $this->createChild(['name' => 'LOCAL', 'community_id' => $otherCommunity->id]));

        $report = app(AttendanceReportService::class)->build($user, [
            'church_id' => $this->chain['church']->id,
            'community_id' => $this->chain['community']->id,
            'date_from' => '2026-10-01',
            'date_to' => '2026-10-31',
        ]);

        $rows = collect($report['rows'])->keyBy('child_id');

        $this->assertCount(2, $rows);
        $this->assertSame(['P', '-'], str_split($rows[$veteran->id]['codes']));
        $this->assertSame(['-', '-'], str_split($rows[$newcomer->id]['codes']));
        $this->assertSame('Aún no inscrito', $rows[$newcomer->id]['details'][0]);
        $this->assertSame(0, $rows[$newcomer->id]['absences']);
        $this->assertSame(0, $rows[$veteran->id]['absences']);
        $this->assertSame('Del 1 de octubre de 2026 al 31 de octubre de 2026', $report['period_label']);
    }

    public function test_download_returns_a_styled_workbook(): void
    {
        $user = $this->makeGlobalUser('weekends.report');
        $child = $this->createChild();
        $weekend = $this->createWeekend('2026-10-17', Status::COMPLETED, 'SEMANA 5');
        $this->markPresent($this->createMass($weekend, '2026-10-18 10:00'), $child);

        $response = $this->actingAs($user)
            ->get('/fines-semana-misas/reporte-asistencias?church_id='.$this->chain['church']->id.'&month=2026-10')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertStringContainsString('R-Asistencias_Octubre_2026_parroquia_test.xlsx', $response->headers->get('Content-Disposition'));

        $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());

        $this->assertSame(['Asistencias', 'Indicadores'], $workbook->getSheetNames());

        $detail = $workbook->getSheetByName('Asistencias');
        $this->assertSame('Reporte de asistencias — Parroquia Test', $detail->getCell('A1')->getFormattedValue());
        $this->assertSame('Niño', $detail->getCell('C3')->getFormattedValue());
        $this->assertSame("17-18 OCT\nSEMANA 5", $detail->getCell('F3')->getFormattedValue());
        $this->assertSame('Estatus', $detail->getCell('L3')->getFormattedValue());
        $this->assertSame($child->code, $detail->getCell('B4')->getFormattedValue());
        $this->assertSame('P', $detail->getCell('F4')->getFormattedValue());
        $this->assertSame('Regular', $detail->getCell('L4')->getFormattedValue());
        $this->assertFalse($detail->getStyle('C4')->getFont()->getBold());
        $this->assertSame('DCFCE7', $detail->getStyle('F4')->getFill()->getStartColor()->getRGB());
        $this->assertSame('F4', $detail->getFreezePane());
        $this->assertSame('A3:L4', $detail->getAutoFilter()->getRange());

        $summary = $workbook->getSheetByName('Indicadores');
        $this->assertSame('Reporte de asistencias — Parroquia Test', $summary->getCell('A1')->getFormattedValue());
        $this->assertSame($detail->getStyle('A1')->getFont()->getSize(), $summary->getStyle('A1')->getFont()->getSize());
        $this->assertSame($detail->getStyle('A1')->getFont()->getColor()->getRGB(), $summary->getStyle('A1')->getFont()->getColor()->getRGB());
        $this->assertSame('Octubre de 2026', $summary->getCell('B4')->getFormattedValue());
        $this->assertSame('Fines de semana evaluados', $summary->getCell('A9')->getFormattedValue());
        $this->assertSame('left', $summary->getStyle('B9')->getAlignment()->getHorizontal());
        $this->assertSame('Generado el', $summary->getCell('A10')->getFormattedValue());
        $this->assertMatchesRegularExpression('/^\d{2}\/\d{2}\/\d{4}$/', $summary->getCell('B10')->getFormattedValue());
        $this->assertLessThan(6, $detail->getColumnDimension('A')->getWidth());
        $this->assertGreaterThan(30, $detail->getColumnDimension('B')->getWidth());
        $this->assertGreaterThan(45, $detail->getColumnDimension('C')->getWidth());
        $this->assertEqualsWithDelta(38, $summary->getColumnDimension('A')->getWidth(), 0.01);
        $this->assertEqualsWithDelta(22, $summary->getColumnDimension('B')->getWidth(), 0.01);
    }

    public function test_download_requires_permission_period_and_scope(): void
    {
        $church = $this->chain['church'];
        $withoutPermission = $this->makeGlobalUser('weekends.read');

        $this->actingAs($withoutPermission)
            ->getJson('/fines-semana-misas/reporte-asistencias?church_id='.$church->id.'&month=2026-10')
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para descargar el reporte de asistencias.');

        $this->actingAs($withoutPermission)
            ->getJson('/fines-semana-misas/reporte-asistencias/opciones?church_id='.$church->id)
            ->assertForbidden()
            ->assertJsonPath('message', 'No tienes permiso para consultar el reporte de asistencias.');

        $user = $this->makeGlobalUser('weekends.report');

        $this->actingAs($user)
            ->getJson('/fines-semana-misas/reporte-asistencias?church_id='.$church->id)
            ->assertUnprocessable()
            ->assertJsonPath('errors.month.0', 'Selecciona un mes o un rango de fechas.');

        // Cualquier regla sin mensaje propio caería en inglés: se verifica que todas estén traducidas.
        $this->actingAs($user)
            ->getJson('/fines-semana-misas/reporte-asistencias?church_id=999999&community_id=abc&level_id=999999&child_status=x&month=10-2026')
            ->assertUnprocessable()
            ->assertJsonPath('errors.church_id.0', 'La parroquia seleccionada no existe o fue eliminada.')
            ->assertJsonPath('errors.community_id.0', 'La comunidad seleccionada no es válida.')
            ->assertJsonPath('errors.level_id.0', 'El nivel seleccionado no existe o fue eliminado.')
            ->assertJsonPath('errors.child_status.0', 'El filtro de niños debe ser «Solo activos» o «Todos».')
            ->assertJsonPath('errors.month.0', 'El mes debe tener el formato AAAA-MM, por ejemplo 2026-10.');

        $this->actingAs($user)
            ->getJson('/fines-semana-misas/reporte-asistencias?church_id='.$church->id.'&date_from=2026-10-20&date_to=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonPath('errors.date_to.0', 'La fecha final debe ser igual o posterior a la inicial.');

        $this->actingAs($user)
            ->getJson('/fines-semana-misas/reporte-asistencias?church_id='.$church->id.'&month=2026-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['period']);

        $foreign = $this->createChain();
        $churchUser = $this->makeChurchUser($this->chain['diocese'], $this->chain['deanery'], $church, 'weekends.report');

        $this->actingAs($churchUser)
            ->getJson('/fines-semana-misas/reporte-asistencias?church_id='.$foreign['church']->id.'&month=2026-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['church_id']);
    }

    public function test_large_report_streams_with_bounded_memory(): void
    {
        Carbon::setTestNow('2027-01-15 10:00:00');
        $user = $this->makeGlobalUser('weekends.report');
        $church = $this->chain['church'];
        $childrenCount = 1500;
        $weekendCount = 40;

        // Inserción masiva con el query builder para que el arranque sea rápido.
        $children = [];
        for ($i = 1; $i <= $childrenCount; $i++) {
            $children[] = [
                'church_id' => $church->id,
                'community_id' => $this->chain['community']->id,
                'name' => 'NINO '.$i,
                'paterno' => 'AP'.str_pad((string) ($i % 97), 3, '0', STR_PAD_LEFT),
                'materno' => 'AM',
                'code' => sprintf('2026-BULK-%05d', $i),
                'birthdate' => '2018-01-01',
                'sex' => Sex::MALE,
                'blood_type' => BloodType::O_POSITIVE,
                'privacy_terms' => true,
                'status' => Status::ACTIVE,
                'created_at' => '2026-01-01 00:00:00',
                'updated_at' => '2026-01-01 00:00:00',
            ];
        }
        foreach (array_chunk($children, 500) as $chunk) {
            DB::table('children')->insert($chunk);
        }
        $childIds = DB::table('children')->where('church_id', $church->id)->pluck('id')->all();

        $start = Carbon::parse('2026-03-07');
        for ($w = 0; $w < $weekendCount; $w++) {
            $weekend = $this->createWeekend($start->copy()->addWeeks($w)->toDateString(), Status::COMPLETED);
            $mass = $this->createMass($weekend, $start->copy()->addWeeks($w)->addDay()->setTime(10, 0)->format('Y-m-d H:i'));

            // Asisten 2 de cada 3 niños, alternando según el fin de semana.
            $attendance = [];
            foreach ($childIds as $index => $childId) {
                if (($index + $w) % 3 === 0) {
                    continue;
                }
                $attendance[] = [
                    'mass_id' => $mass->id,
                    'child_id' => $childId,
                    'child_code' => 'X',
                    'church_id' => $church->id,
                    'status' => Status::CHECK_IN,
                    'check_in_at' => $mass->starts_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach (array_chunk($attendance, 500) as $chunk) {
                DB::table('mass_attendance')->insert($chunk);
            }
        }
        unset($children, $attendance);
        gc_collect_cycles();

        memory_reset_peak_usage();
        $before = memory_get_usage(true);
        $started = microtime(true);

        $response = $this->actingAs($user)
            ->get('/fines-semana-misas/reporte-asistencias?church_id='.$church->id.'&date_from=2026-03-01&date_to=2026-12-31')
            ->assertOk();

        $elapsed = microtime(true) - $started;
        $peakGrowth = memory_get_peak_usage(true) - $before;
        $path = $response->baseResponse->getFile()->getPathname();

        $this->assertStringContainsString('R-Asistencias_2026-03-01_al_2026-12-31_', $response->headers->get('Content-Disposition'));
        $this->assertGreaterThan(50_000, filesize($path));
        $this->assertLessThan(64 * 1024 * 1024, $peakGrowth, sprintf('El reporte creció la memoria pico %.1f MB', $peakGrowth / 1048576));
        $this->assertLessThan(20, $elapsed, sprintf('El reporte tardó %.1f s', $elapsed));

        // PhpSpreadsheet cargaría ~60k celdas en memoria; se inspecciona el XML directamente.
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertSame($childrenCount + 3, preg_match_all('/<row /', $sheetXml));
        $this->assertStringContainsString('<c r="'.$this->columnLetter(5 + $weekendCount + 6).'3"', $sheetXml);
        $this->assertStringContainsString('Estatus', $sheetXml);
    }

    private function columnLetter(int $column): string
    {
        $letter = '';

        while ($column > 0) {
            $remainder = ($column - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $column = intdiv($column - 1, 26);
        }

        return $letter;
    }

    private function seedAbsenceLimitSetting(?int $churchValue = null): void
    {
        $category = SettingCategory::query()->create([
            'key' => 'attendance',
            'name' => 'Asistencias',
            'icon' => 'ClipboardCheck',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $setting = Setting::query()->create([
            'category_id' => $category->id,
            'key' => AttendanceReportService::MAX_ABSENCES_SETTING,
            'name' => 'Límite de faltas',
            'type' => 'number',
            'meta' => ['min' => 1, 'max' => 20],
            'default_value' => AttendanceReportService::DEFAULT_MAX_ABSENCES,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        if ($churchValue !== null) {
            SettingValue::query()->create([
                'setting_id' => $setting->id,
                'scope' => SettingValue::SCOPE_CHURCH,
                'scope_id' => $this->chain['church']->id,
                'value' => $churchValue,
            ]);
        }
    }

    private function createWeekend(string $date, string $status, ?string $name = null): Weekend
    {
        $start = Carbon::parse($date)->startOfDay();

        return Weekend::query()->create([
            'church_id' => $this->chain['church']->id,
            'name' => $name ?? 'FIN DE SEMANA '.$start->format('d/m'),
            'starts_at' => $start->format('Y-m-d H:i:s'),
            'ends_at' => $start->copy()->addDay()->setTime(23, 59)->format('Y-m-d H:i:s'),
            'status' => $status,
        ]);
    }

    private function createMass(Weekend $weekend, string $startsAt, ?int $chapelId = null): Mass
    {
        return Mass::query()->create([
            'weekend_id' => $weekend->id,
            'church_id' => $this->chain['church']->id,
            'chapel_id' => $chapelId,
            'name' => 'MISA',
            'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addHour()->format('Y-m-d H:i'),
            'status' => Status::COMPLETED,
            'attendance_check_in_status' => Status::COMPLETED,
            'attendance_check_out_status' => Status::COMPLETED,
        ]);
    }

    private function markPresent(Mass $mass, Child $child): MassAttendance
    {
        return MassAttendance::query()->create([
            'mass_id' => $mass->id,
            'child_id' => $child->id,
            'child_code' => $child->code,
            'church_id' => $mass->church_id,
            'chapel_id' => $mass->chapel_id,
            'status' => Status::CHECK_OUT,
            'check_in_at' => $mass->starts_at,
            'check_out_at' => $mass->ends_at,
        ]);
    }

    private function markCheckInOnly(Mass $mass, Child $child): MassAttendance
    {
        return MassAttendance::query()->create([
            'mass_id' => $mass->id,
            'child_id' => $child->id,
            'child_code' => $child->code,
            'church_id' => $mass->church_id,
            'chapel_id' => $mass->chapel_id,
            'status' => Status::CHECK_IN,
            'check_in_at' => $mass->starts_at,
        ]);
    }

    private function createChild(array $overrides = []): Child
    {
        $this->childSequence++;

        $child = Child::query()->create(array_merge([
            'church_id' => $this->chain['church']->id,
            'community_id' => $this->chain['community']->id,
            'name' => 'JUAN',
            'paterno' => 'PEREZ',
            'materno' => 'GOMEZ',
            'code' => sprintf('2026-JPG-20180314-CH%d-%04d', $this->chain['church']->id, $this->childSequence),
            'birthdate' => '2018-03-14',
            'sex' => Sex::MALE,
            'blood_type' => BloodType::O_POSITIVE,
            'privacy_terms' => true,
            'status' => Status::ACTIVE,
        ], $overrides));

        // Los niños existen desde antes de cualquier fin de semana evaluado.
        $child->forceFill(['created_at' => '2026-01-10 09:00:00'])->saveQuietly();

        return $child->fresh();
    }
}
