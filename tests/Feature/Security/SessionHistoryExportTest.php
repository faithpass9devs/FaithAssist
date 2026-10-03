<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\DeviceSessionRegistrar;
use App\Services\SessionVisitRecorder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class SessionHistoryExportTest extends TestCase
{
    use ControllerTestHelpers {
        makeGlobalUser as makeGlobalUserWithPermissions;
    }
    use RefreshDatabase;

    protected function makeGlobalUser(string ...$permissions): User
    {
        $user = $this->makeGlobalUserWithPermissions(...$permissions);
        $user->assignRole(Role::firstOrCreate(['name' => 'Superadmin', 'guard_name' => 'web']));

        return $user;
    }

    public function test_relogin_in_same_browser_creates_a_new_period_without_losing_the_first_one(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 18:00:00', 'UTC'));
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();
        $agent = 'Mozilla/5.0 Chrome/140.0';
        DB::table('sessions')->insert([
            'id' => 'first-login', 'user_id' => $target->id, 'ip_address' => '127.0.0.1',
            'user_agent' => $agent, 'payload' => base64_encode('first'), 'last_activity' => now()->timestamp,
        ]);
        app(DeviceSessionRegistrar::class)->register('first-login', $target->id, $agent);
        $this->travelTo(Carbon::parse('2026-09-10 19:00:00', 'UTC'));
        app(SessionVisitRecorder::class)->close('first-login');
        DB::table('sessions')->where('id', 'first-login')->update(['status' => 'closed']);

        $this->travelTo(Carbon::parse('2026-09-15 18:00:00', 'UTC'));
        DB::table('sessions')->insert([
            'id' => 'second-login', 'user_id' => $target->id, 'ip_address' => '127.0.0.1',
            'user_agent' => $agent, 'payload' => base64_encode('second'), 'last_activity' => now()->timestamp,
        ]);
        app(DeviceSessionRegistrar::class)->register('second-login', $target->id, $agent);
        $response = $this->actingAs($viewer)->get("/dispositivos-sesiones/usuario/{$target->id}/historial?month=2026-09")->assertOk();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

        $this->assertSame(1, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame(2, DB::table('session_visit_periods')->where('user_id', $target->id)->count());
        $this->assertSame('Ubicación', $sheet->getCell('F8')->getValue());
        $this->assertSame('Ubicación no disponible', $sheet->getCell('F9')->getValue());
        $this->assertSame(10, $sheet->getHighestRow());
        $this->assertSame('F', $sheet->getHighestColumn());
    }

    public function test_real_login_logout_and_relogin_in_same_browser_are_each_recorded(): void
    {
        config(['session.driver' => 'database']);
        Http::fakeSequence()
            ->push(['address' => ['road' => 'Calle Uno', 'town' => 'Tenancingo', 'country' => 'México']], 200)
            ->push(['address' => ['road' => 'Calle Dos', 'town' => 'Malinalco', 'country' => 'México']], 200)
            ->push(['address' => ['road' => 'Calle Tres', 'town' => 'Toluca', 'country' => 'México']], 200);
        $user = User::factory()->create();
        $browserId = '33333333-3333-4333-8333-333333333333';
        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/140.0'])
            ->withCookie('faithassist_browser_id', $browserId)
            ->post('/login', ['email' => $user->email, 'password' => 'password', 'latitude' => 18.9, 'longitude' => -99.5, 'location_accuracy' => 18, 'device_model' => 'Pixel 8 Pro'])
            ->assertRedirect();

        $firstPeriod = DB::table('session_visit_periods')->where('user_id', $user->id)->first();
        $this->assertNotNull($firstPeriod);
        $this->assertSame('Calle Uno, Tenancingo, México', $firstPeriod->login_location);
        $this->assertSame(18, $firstPeriod->login_location_accuracy);
        $this->assertSame('Pixel 8 Pro', $firstPeriod->device_model);
        $this->assertSame('login', DB::table('session_location_history')->where('session_id', $firstPeriod->session_id)->value('kind'));

        $this->post('/logout')->assertRedirect('/login');
        $this->travel(5)->days();
        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/140.0'])
            ->withCookie('faithassist_browser_id', $browserId)
            ->post('/login', ['email' => $user->email, 'password' => 'password', 'latitude' => 19.2, 'longitude' => -99.4, 'location_accuracy' => 25, 'device_model' => 'Pixel 8 Pro'])
            ->assertRedirect();

        $periods = DB::table('session_visit_periods')->where('user_id', $user->id)->orderBy('started_at')->get();
        $this->assertCount(2, $periods);
        $this->assertNotNull($periods[0]->ended_at);
        $this->assertNull($periods[1]->ended_at);
        $this->assertSame('Calle Dos, Malinalco, México', $periods[1]->login_location);
        $this->assertSame(25, $periods[1]->login_location_accuracy);
        $this->assertSame('Pixel 8 Pro', $periods[1]->device_model);
        $activeSession = DB::table('sessions')->where('user_id', $user->id)->where('status', 'active')->first();
        $this->assertNotNull($activeSession);
        $this->assertSame($periods[1]->session_id, $activeSession->id);

        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/140.0'])
            ->withCookie('faithassist_browser_id', $browserId)
            ->postJson('/mi-sesion/ubicacion', ['latitude' => 19.5, 'longitude' => -99.2, 'accuracy' => 8, 'device_model' => 'Pixel 8 Pro'])
            ->assertOk();
        $this->assertSame('Calle Tres, Toluca, México', DB::table('sessions')->where('id', $activeSession->id)->value('location_label'));
        $this->assertSame('Calle Dos, Malinalco, México', DB::table('session_visit_periods')->where('session_id', $periods[1]->session_id)->value('login_location'));
        $this->assertSame('Calle Tres, Toluca, México', DB::table('session_visit_periods')->where('session_id', $periods[1]->session_id)->value('location'));
        $locationChanges = DB::table('session_location_history')->where('session_id', $periods[1]->session_id)->orderBy('recorded_at')->get();
        $this->assertSame(['login', 'change'], $locationChanges->pluck('kind')->all());
        $this->assertSame('Calle Tres, Toluca, México', $locationChanges->last()->location);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->where('status', 'active')->count());
    }

    public function test_reactivating_legacy_browser_keeps_an_explicit_unverified_snapshot(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 18:00:00', 'UTC'));
        $user = User::factory()->create();
        foreach (['old' => 'closed', 'new' => 'active'] as $id => $status) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 Chrome/140.0', 'payload' => base64_encode($id),
                'last_activity' => Carbon::parse('2026-09-01 18:00:00', 'UTC')->timestamp,
                'status' => $status,
            ]);
        }

        app(DeviceSessionRegistrar::class)->register('new', $user->id, 'Mozilla/5.0 Chrome/140.0');
        $rows = DB::table('session_visit_periods')->where('user_id', $user->id)->orderBy('started_at')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows->first()->legacy);
        $this->assertSame('old', $rows->first()->session_id);
    }

    public function test_excel_separates_two_logins_from_the_same_browser_and_filters_by_month(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create(['email' => 'historial@example.com']);
        $this->period($target, 'chrome-sept', '2026-09-10 18:00:00', '2026-09-10 19:00:00', 'Chrome');
        $this->period($target, 'chrome-oct', '2026-10-01 18:00:00', null, 'Chrome');
        $this->period($target, 'edge-sept', '2026-09-10 18:00:00', null, 'Edge');
        DB::table('session_location_history')->insert([
            'user_id' => $target->id,
            'session_id' => 'chrome-sept',
            'kind' => 'change',
            'location' => 'Malinalco, México',
            'latitude' => 19.0,
            'longitude' => -99.5,
            'accuracy' => 9,
            'recorded_at' => '2026-09-10 18:30:00',
        ]);
        $response = $this->actingAs($viewer)
            ->get("/dispositivos-sesiones/usuario/{$target->id}/historial?month=2026-09")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('Historial de actividad y sesiones', $sheet->getCell('A1')->getValue());
        $this->assertStringStartsWith('Historial - ', $sheet->getTitle());
        $this->assertLessThanOrEqual(31, mb_strlen($sheet->getTitle()));
        $this->assertStringContainsString(Str::slug($target->name), $response->headers->get('Content-Disposition'));
        $this->assertSame('164E63', $sheet->getStyle('A1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('334155', $sheet->getStyle('A8')->getFill()->getStartColor()->getRGB());
        $this->assertSame('landscape', $sheet->getPageSetup()->getOrientation());
        $this->assertSame('historial@example.com', $sheet->getCell('D2')->getValue());
        $this->assertSame('01/09/2026 - 30/09/2026', $sheet->getCell('B5')->getValue());
        $this->assertSame('Ubicación', $sheet->getCell('F8')->getValue());
        $this->assertSame('Chrome', $sheet->getCell('D9')->getValue());
        $this->assertSame('Computadora · Pixel 8 Pro', $sheet->getCell('C9')->getValue());
        $this->assertSame('Inicio: Tenancingo, México · Precisión aproximada: 18 m', $sheet->getCell('F9')->getValue());
        $this->assertStringNotContainsString('Cambios durante la sesión:', $sheet->getCell('F9')->getValue());
        $this->assertSame('Edge', $sheet->getCell('D10')->getValue());
        $this->assertSame(10, $sheet->getHighestRow());
        $this->assertSame('F', $sheet->getHighestColumn());
    }

    public function test_custom_range_includes_logins_started_in_range_and_rejects_invalid_dates(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();
        $this->period($target, 'early', '2026-09-01 18:00:00', '2026-09-01 19:00:00', 'Chrome');
        $this->period($target, 'selected', '2026-09-29 18:00:00', '2026-10-02 19:00:00', 'Edge');
        $this->period($target, 'started-before-range', '2026-09-28 18:00:00', '2026-09-29 19:00:00', 'Firefox');
        DB::table('sessions')->insert([
            'id' => 'started-before-range', 'user_id' => $target->id, 'ip_address' => '127.0.0.2',
            'user_agent' => 'Mozilla/5.0 Firefox/140.0', 'payload' => base64_encode('payload'),
            'last_activity' => Carbon::parse('2026-09-29 18:30:00', 'UTC')->timestamp,
        ]);

        $response = $this->actingAs($viewer)->get("/dispositivos-sesiones/usuario/{$target->id}/historial?from=2026-09-29&to=2026-09-30")
            ->assertOk();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('29/09/2026 - 30/09/2026', $sheet->getCell('B5')->getValue());
        $this->assertSame('Edge', $sheet->getCell('D9')->getValue());
        $this->assertSame(9, $sheet->getHighestRow());

        $this->actingAs($viewer)->getJson("/dispositivos-sesiones/usuario/{$target->id}/historial?from=2026-10-02&to=2026-09-29")
            ->assertUnprocessable();
        $this->actingAs($viewer)->getJson("/dispositivos-sesiones/usuario/{$target->id}/historial?month=2026-09&from=2026-09-01&to=2026-09-30")
            ->assertUnprocessable();
    }

    public function test_export_requires_read_permission(): void
    {
        $this->actingAs($this->makeGlobalUser())
            ->get('/dispositivos-sesiones/usuario/'.User::factory()->create()->id.'/historial?month=2026-09')
            ->assertForbidden();
    }

    public function test_export_rejects_future_months_and_date_ranges(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();
        $url = "/dispositivos-sesiones/usuario/{$target->id}/historial";
        $tomorrow = now()->setTimezone(config('app.display_timezone'))->addDay()->format('Y-m-d');
        $nextMonth = now()->setTimezone(config('app.display_timezone'))->addMonth()->format('Y-m');

        $this->actingAs($viewer)->getJson("{$url}?month={$nextMonth}")
            ->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->actingAs($viewer)->getJson("{$url}?from={$tomorrow}&to={$tomorrow}")
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_empty_month_has_an_explicit_message(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();

        $response = $this->actingAs($viewer)->get("/dispositivos-sesiones/usuario/{$target->id}/historial?month=2025-01")
            ->assertOk();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

        $this->assertSame('Sin accesos registrados en el periodo', $sheet->getCell('A9')->getValue());
    }

    public function test_excel_shows_only_the_device_type_when_model_is_unavailable(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();
        $this->period($target, 'unknown-model', '2026-09-10 18:00:00', '2026-09-10 19:00:00', 'Chrome', null);

        $response = $this->actingAs($viewer)->get("/dispositivos-sesiones/usuario/{$target->id}/historial?month=2026-09")->assertOk();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

        $this->assertSame('Computadora', $sheet->getCell('C9')->getValue());
    }

    public function test_legacy_ip_location_is_not_misreported_as_a_verified_country(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'old-no-gps',
            'user_id' => $target->id,
            'ip_address' => '203.0.113.25',
            'user_agent' => 'Mozilla/5.0',
            'device_name' => 'Computadora',
            'location_label' => 'Chaplin Road, Bulawayo, Zimbabwe',
            'payload' => base64_encode('payload'),
            'last_activity' => Carbon::parse('2026-10-01 18:00:00', 'UTC')->timestamp,
        ]);

        $response = $this->actingAs($viewer)->get("/dispositivos-sesiones/usuario/{$target->id}/historial?month=2026-10")
            ->assertOk();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

        $this->assertSame('Ubicación inicial no verificable: el acceso no tiene coordenadas GPS guardadas', $sheet->getCell('F9')->getValue());
        $this->assertStringNotContainsString('Zimbabwe', $sheet->getCell('F9')->getValue());
    }

    private function period(User $user, string $sessionId, string $start, ?string $end, string $browser, ?string $deviceModel = 'Pixel 8 Pro'): void
    {
        DB::table('session_visit_periods')->insert([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'device' => 'Computadora',
            'device_model' => $deviceModel,
            'browser' => $browser,
            'ip_address' => '127.0.0.1',
            'location' => 'Tenancingo, México',
            'login_location' => 'Tenancingo, México',
            'login_location_accuracy' => 18,
            'started_at' => $start,
            'ended_at' => $end,
        ]);
        DB::table('session_location_history')->insert([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'kind' => 'login',
            'location' => 'Tenancingo, México',
            'latitude' => 18.9,
            'longitude' => -99.5,
            'accuracy' => 18,
            'recorded_at' => $start,
        ]);
    }
}
