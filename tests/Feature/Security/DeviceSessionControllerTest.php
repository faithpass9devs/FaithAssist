<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\InternalNotification;
use App\Models\ModerationWarning;
use App\Models\User;
use App\Services\SessionLocationResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class DeviceSessionControllerTest extends TestCase
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

    public function test_user_without_read_permission_gets_403_on_index(): void
    {
        $user = $this->makeGlobalUser();

        $this->actingAs($user)
            ->get('/dispositivos-sesiones')
            ->assertForbidden();
    }

    public function test_user_with_read_permission_can_view_index(): void
    {
        $user = $this->makeGlobalUser('dispositivos_sesiones.read');

        $this->actingAs($user)
            ->get('/dispositivos-sesiones')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Security/DeviceSessions/Index'));
    }

    public function test_device_session_list_and_direct_routes_are_limited_to_lower_roles_in_the_same_parish(): void
    {
        $viewerScope = $this->createChain();
        $otherScope = $this->createChain();
        $viewer = $this->makeChurchUser(
            $viewerScope['diocese'],
            $viewerScope['deanery'],
            $viewerScope['church'],
            'dispositivos_sesiones.read',
            'dispositivos_sesiones.delete',
            'moderacion_cuentas.create',
            'moderacion_cuentas.update',
        );
        $viewer->assignRole(Role::firstOrCreate(['name' => 'Coordinador de Parroquia', 'guard_name' => 'web']));
        $visibleUser = User::factory()->create([
            'diocese_id' => $viewerScope['diocese']->id,
            'deanery_id' => $viewerScope['deanery']->id,
            'church_id' => $viewerScope['church']->id,
        ]);
        $visibleUser->assignRole(Role::firstOrCreate(['name' => 'Capturista', 'guard_name' => 'web']));
        $hiddenUser = User::factory()->create([
            'diocese_id' => $otherScope['diocese']->id,
            'deanery_id' => $otherScope['deanery']->id,
            'church_id' => $otherScope['church']->id,
        ]);
        $hiddenUser->assignRole(Role::firstOrCreate(['name' => 'Capturista', 'guard_name' => 'web']));
        $sameRoleUser = User::factory()->create([
            'diocese_id' => $viewerScope['diocese']->id,
            'deanery_id' => $viewerScope['deanery']->id,
            'church_id' => $viewerScope['church']->id,
        ]);
        $sameRoleUser->assignRole(Role::firstOrCreate(['name' => 'Coordinador de Parroquia', 'guard_name' => 'web']));

        foreach ([[$visibleUser, 'visible-session'], [$hiddenUser, 'hidden-session']] as [$target, $sessionId]) {
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $target->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'payload' => base64_encode('payload'),
                'last_activity' => time(),
            ]);
        }

        $sessions = collect($this->actingAs($viewer)->get('/dispositivos-sesiones')
            ->assertOk()
            ->viewData('page')['props']['sessions']);
        $this->assertNotNull($sessions->firstWhere('user_id', $visibleUser->id));
        $this->assertNull($sessions->firstWhere('user_id', $hiddenUser->id));
        $this->assertNull($sessions->firstWhere('user_id', $sameRoleUser->id));

        $this->get("/dispositivos-sesiones/usuario/{$hiddenUser->id}")->assertNotFound();
        $this->get("/dispositivos-sesiones/usuario/{$hiddenUser->id}/historial?month=2026-09")->assertNotFound();
        $this->deleteJson('/dispositivos-sesiones/hidden-session')->assertNotFound();
        $this->deleteJson("/dispositivos-sesiones/usuario/{$hiddenUser->id}")->assertNotFound();
        $this->postJson("/dispositivos-sesiones/usuario/{$hiddenUser->id}/advertencia", [
            'level' => 'leve',
            'message' => 'Prueba fuera del alcance',
        ])->assertNotFound();
        $this->patchJson("/dispositivos-sesiones/usuario/{$hiddenUser->id}/estado", [
            'account_status' => 'blocked',
        ])->assertNotFound();
        $this->assertSame('active', DB::table('sessions')->where('id', 'hidden-session')->value('status'));
        $this->assertSame('active', $hiddenUser->fresh()->account_status);
    }

    public function test_session_details_use_fresh_gps_and_expose_the_detected_device_model(): void
    {
        config(['app.display_timezone' => 'America/Mexico_City']);
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response(['address' => [
                'road' => 'Carretera Hidalgo',
                'town' => 'Tenancingo',
                'postcode' => '52416',
                'state' => 'Estado de México',
                'country' => 'México',
            ]]),
        ]);
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'located-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'device_name' => 'Computadora',
            'device_model' => 'Surface Laptop 6',
            'latitude' => 18.9612000,
            'longitude' => -99.6320000,
            'location_accuracy' => 12,
            'location_label' => 'Chaplin Road, Bulawayo, Zimbabwe',
            'location_updated_at' => now(),
            'payload' => base64_encode('payload'),
            // 2026-09-29 01:05 UTC is 2026-09-28 07:05 PM in Mexico City.
            'last_activity' => Carbon::parse('2026-09-29 01:05:00', 'UTC')->timestamp,
        ]);
        DB::table('session_visit_periods')->insert([
            'user_id' => $target->id,
            'session_id' => 'located-session',
            'device' => 'Computadora',
            'device_model' => 'Surface Laptop 6',
            'browser' => 'Google Chrome',
            'ip_address' => '127.0.0.1',
            'location' => 'Carretera Hidalgo, localidad cercana',
            'login_location' => 'Calle Hidalgo 12, Valle de Guadalupe, Tenancingo, C.P. 52416, Estado de México, México',
            'login_location_accuracy' => 12,
            'started_at' => Carbon::parse('2026-09-28 23:00:00', 'UTC'),
            'ended_at' => null,
        ]);

        $this->actingAs($viewer)
            ->get("/dispositivos-sesiones/usuario/{$target->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sessions.0.last_activity', '28/09/2026 07:05 PM')
                ->where('sessions.0.location', 'Carretera Hidalgo, Tenancingo, C.P. 52416, Estado de México, México')
                ->where('sessions.0.device_model', 'Surface Laptop 6')
                ->where('sessions.0.location_accuracy', 'Precisión aproximada: 12 m')
                ->etc());
    }

    public function test_deleting_a_session_hides_it_from_the_view_but_keeps_excel_history(): void
    {
        $actor = $this->makeGlobalUser('dispositivos_sesiones.read', 'dispositivos_sesiones.delete');
        $target = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'removable-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);

        $this->actingAs($actor)
            ->deleteJson('/dispositivos-sesiones/removable-session')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull(DB::table('sessions')->where('id', 'removable-session')->value('hidden_at'));

        $this->actingAs($actor)
            ->get("/dispositivos-sesiones/usuario/{$target->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sessions', [])->etc());

        $this->actingAs($actor)
            ->get("/dispositivos-sesiones/usuario/{$target->id}/historial")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_index_partial_refresh_reflects_new_logins_in_device_and_session_counts(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();
        $headers = [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Security/DeviceSessions/Index',
            'X-Inertia-Partial-Data' => 'sessions,summary',
        ];

        $before = collect($this->actingAs($viewer)->withHeaders($headers)->get('/dispositivos-sesiones')
            ->assertOk()
            ->json('props.sessions'));

        $this->assertSame(0, $before->firstWhere('user_id', $target->id)['session_count']);

        DB::table('sessions')->insert([
            'id' => 'fresh-login-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);

        $sessions = collect($this->actingAs($viewer)->withHeaders($headers)->get('/dispositivos-sesiones')
            ->assertOk()
            ->json('props.sessions'));

        $row = $sessions->firstWhere('user_id', $target->id);
        $this->assertSame(1, $row['device_count']);
        $this->assertSame(1, $row['session_count']);
    }

    public function test_index_hides_sessions_removed_from_the_view(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'hidden-index-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
            'status' => 'closed',
            'hidden_at' => now(),
        ]);

        $sessions = collect($this->actingAs($viewer)->get('/dispositivos-sesiones')
            ->assertOk()
            ->viewData('page')['props']['sessions']);

        $row = $sessions->firstWhere('user_id', $target->id);
        $this->assertSame(0, $row['session_count']);
        $this->assertSame(0, $row['device_count']);
    }

    public function test_a_session_closed_by_the_user_stays_visible_in_the_detail_view(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'signed-out-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'browser' => 'Google Chrome',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
            'status' => 'closed',
        ]);

        $this->actingAs($viewer)
            ->get("/dispositivos-sesiones/usuario/{$target->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sessions.0.id', 'signed-out-session')
                ->where('sessions.0.status', 'closed')
                ->where('sessions.0.browser', 'Google Chrome')
                ->etc());
    }

    public function test_several_browsers_on_the_same_machine_count_as_one_device(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();

        foreach (['Google Chrome', 'Microsoft Edge'] as $index => $browser) {
            DB::table('sessions')->insert([
                'id' => 'same-machine-'.$index,
                'user_id' => $target->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0)',
                'device_name' => 'Computadora',
                'operating_system' => 'Windows',
                'browser' => $browser,
                'payload' => base64_encode('payload'),
                'last_activity' => time(),
            ]);
        }

        DB::table('sessions')->insert([
            'id' => 'phone-device',
            'user_id' => $target->id,
            'ip_address' => '192.168.1.44',
            'user_agent' => 'Mozilla/5.0 (iPhone; Mobile)',
            'device_name' => 'Dispositivo móvil',
            'operating_system' => 'iOS',
            'browser' => 'Safari',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);

        $sessions = collect($this->actingAs($viewer)->get('/dispositivos-sesiones')
            ->assertOk()
            ->viewData('page')['props']['sessions']);

        $row = $sessions->firstWhere('user_id', $target->id);
        $this->assertSame(2, $row['device_count']);
        $this->assertSame(3, $row['session_count']);
    }

    public function test_closed_sessions_are_not_counted_as_devices(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'closed-device',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'device_name' => 'Computadora',
            'operating_system' => 'Windows',
            'browser' => 'Google Chrome',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
            'status' => 'closed',
        ]);

        $sessions = collect($this->actingAs($viewer)->get('/dispositivos-sesiones')
            ->assertOk()
            ->viewData('page')['props']['sessions']);

        $row = $sessions->firstWhere('user_id', $target->id);
        $this->assertSame(0, $row['device_count']);
        $this->assertSame(0, $row['session_count']);
    }

    public function test_closing_all_sessions_signs_out_every_device_and_keeps_excel_history(): void
    {
        $actor = $this->makeGlobalUser('dispositivos_sesiones.read', 'dispositivos_sesiones.delete');
        $target = User::factory()->create();

        foreach (['phone-session', 'laptop-session'] as $index => $sessionId) {
            DB::table('sessions')->insert([
                'id' => $sessionId,
                'user_id' => $target->id,
                'ip_address' => '127.0.0.'.($index + 1),
                'user_agent' => 'Mozilla/5.0',
                'payload' => base64_encode('payload'),
                'last_activity' => time(),
            ]);
        }

        $this->actingAs($actor)
            ->deleteJson("/dispositivos-sesiones/usuario/{$target->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        foreach (['phone-session', 'laptop-session'] as $sessionId) {
            $session = DB::table('sessions')->where('id', $sessionId)->first();
            $this->assertSame('closed', $session->status);
            $this->assertNotNull($session->hidden_at);
        }

        $this->actingAs($actor)
            ->get("/dispositivos-sesiones/usuario/{$target->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sessions', [])->etc());

        $this->actingAs($actor)
            ->get("/dispositivos-sesiones/usuario/{$target->id}/historial")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_location_endpoint_rejects_guests(): void
    {
        $this->postJson('/mi-sesion/ubicacion', ['latitude' => 18.9612, 'longitude' => -99.632])
            ->assertUnauthorized();
    }

    public function test_location_endpoint_validates_coordinates(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/mi-sesion/ubicacion', ['latitude' => 200, 'longitude' => -99.632])
            ->assertStatus(422);
    }

    public function test_location_endpoint_stores_the_detailed_address(): void
    {
        config(['session.driver' => 'database']);
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => [
                    'road' => 'Calle Hidalgo',
                    'house_number' => '12',
                    'neighbourhood' => 'Valle de Guadalupe',
                    'town' => 'Tenancingo',
                    'postcode' => '52416',
                    'state' => 'Estado de México',
                    'country' => 'México',
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $browserId = '44444444-4444-4444-8444-444444444444';

        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/140.0'])
            ->withCookie('faithassist_browser_id', $browserId)
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
                'latitude' => 18.9612,
                'longitude' => -99.632,
                'location_accuracy' => 8,
            ])
            ->assertRedirect();

        $this->withServerVariables(['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/140.0'])
            ->withCookie('faithassist_browser_id', $browserId)
            ->postJson('/mi-sesion/ubicacion', [
                'latitude' => 19.0,
                'longitude' => -99.6,
                'accuracy' => 8,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(
            'Calle Hidalgo 12, Valle de Guadalupe, Tenancingo, C.P. 52416, Estado de México, México',
            app(SessionLocationResolver::class)->fromCoordinates(18.9612, -99.632)
        );
        $session = DB::table('sessions')->where('user_id', $user->id)->where('status', 'active')->first();
        $this->assertSame('Calle Hidalgo 12, Valle de Guadalupe, Tenancingo, C.P. 52416, Estado de México, México', $session->location_label);
        $locations = DB::table('session_location_history')->where('user_id', $user->id)->orderBy('recorded_at')->get();
        $this->assertSame(['login', 'change'], $locations->pluck('kind')->all());
    }

    public function test_session_details_partial_refresh_reflects_new_and_updated_sessions(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();
        $other = User::factory()->create();
        $url = "/dispositivos-sesiones/usuario/{$target->id}";
        $headers = [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Security/DeviceSessions/Show',
            'X-Inertia-Partial-Data' => 'sessions,user',
        ];

        DB::table('sessions')->insert([
            'id' => 'target-chrome',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'device_name' => 'Equipo inicial',
            'browser' => 'Google Chrome',
            'payload' => base64_encode('payload'),
            'last_activity' => time() - 60,
        ]);

        $this->actingAs($viewer)->withHeaders($headers)->get($url)
            ->assertOk()
            ->assertJsonCount(1, 'props.sessions')
            ->assertJsonPath('props.sessions.0.device', 'Equipo inicial');

        DB::table('sessions')->where('id', 'target-chrome')->update([
            'device_name' => 'Equipo actualizado',
            'browser' => 'Microsoft Edge',
            'ip_address' => '192.0.2.10',
            'status' => 'closed',
            'last_activity' => time(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'target-mobile',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.2',
            'user_agent' => 'Mozilla/5.0 (iPhone; Mobile)',
            'browser' => 'Safari',
            'payload' => base64_encode('payload'),
            'last_activity' => time() - 10,
        ]);
        DB::table('sessions')->insert([
            'id' => 'other-session',
            'user_id' => $other->id,
            'ip_address' => '127.0.0.3',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);

        $response = $this->actingAs($viewer)->withHeaders($headers)->get($url)
            ->assertOk()
            ->assertJsonCount(2, 'props.sessions')
            ->assertJsonPath('props.sessions.0.id', 'target-chrome')
            ->assertJsonPath('props.sessions.0.device', 'Equipo actualizado')
            ->assertJsonPath('props.sessions.0.browser', 'Microsoft Edge')
            ->assertJsonPath('props.sessions.0.ip_address', '192.0.2.10')
            ->assertJsonPath('props.sessions.0.status', 'closed')
            ->assertJsonPath('props.sessions.1.id', 'target-mobile')
            ->assertJsonPath('props.sessions.1.device_type', 'Teléfono');

        $this->assertArrayNotHasKey('auth', $response->json('props'));
    }

    public function test_session_details_partial_refresh_requires_read_permission(): void
    {
        $viewer = $this->makeGlobalUser();
        $target = User::factory()->create();

        $this->actingAs($viewer)->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Security/DeviceSessions/Show',
            'X-Inertia-Partial-Data' => 'sessions,user',
        ])->get("/dispositivos-sesiones/usuario/{$target->id}")->assertForbidden();
    }

    public function test_user_remains_visible_when_all_sessions_are_closed(): void
    {
        $viewer = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();

        $this->actingAs($viewer)
            ->get('/dispositivos-sesiones')
            ->assertInertia(fn ($page) => $page->where('sessions', fn ($sessions) => collect($sessions)->contains(fn ($session) => (int) $session['user_id'] === (int) $target->id && $session['session_count'] === 0)));
    }

    public function test_user_without_delete_permission_cannot_close_session(): void
    {
        $actor = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'session-123',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);

        $this->actingAs($actor)
            ->deleteJson('/dispositivos-sesiones/session-123')
            ->assertForbidden();
    }

    public function test_user_without_moderation_permission_cannot_send_warning(): void
    {
        $actor = $this->makeGlobalUser('dispositivos_sesiones.read');
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->postJson("/dispositivos-sesiones/usuario/{$target->id}/advertencia", [
                'level' => 'moderada',
                'reason' => 'Prueba',
                'message' => 'Mensaje de prueba',
            ])
            ->assertForbidden();
    }

    public function test_leve_warning_only_notifies_without_changing_status(): void
    {
        $actor = $this->makeGlobalUser('dispositivos_sesiones.read', 'moderacion_cuentas.create');
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->postJson("/dispositivos-sesiones/usuario/{$target->id}/advertencia", [
                'level' => 'leve',
                'reason' => 'Uso indebido',
                'message' => 'Debes detener esta conducta.',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'level' => 'leve', 'warning_count' => 1, 'account_status' => 'active']);

        $this->assertDatabaseHas('moderation_warnings', [
            'user_id' => $target->id,
            'issued_by' => $actor->id,
            'level' => 'leve',
        ]);
        $this->assertDatabaseHas('internal_notifications', [
            'user_id' => $target->id,
            'type' => 'moderation_warning',
        ]);
        $this->assertSame('active', $target->fresh()->account_status);
    }

    public function test_sending_a_warning_closes_all_target_sessions(): void
    {
        $actor = $this->makeGlobalUser('dispositivos_sesiones.read', 'moderacion_cuentas.create');
        $target = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'target-session-456',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);

        $this->actingAs($actor)
            ->postJson("/dispositivos-sesiones/usuario/{$target->id}/advertencia", [
                'level' => 'leve',
                'message' => 'Mensaje de prueba.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('sessions', [
            'id' => 'target-session-456',
            'status' => 'closed',
        ]);
    }

    public function test_moderada_warning_suspends_account_for_24_hours(): void
    {
        $actor = $this->makeGlobalUser('dispositivos_sesiones.read', 'moderacion_cuentas.create');
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->postJson("/dispositivos-sesiones/usuario/{$target->id}/advertencia", [
                'level' => 'moderada',
                'message' => 'Sigues incurriendo en mal uso.',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'level' => 'moderada', 'account_status' => 'suspended']);

        $fresh = $target->fresh();
        $this->assertSame('suspended', $fresh->account_status);
        $this->assertNotNull($fresh->suspended_until);
        $this->assertTrue($fresh->suspended_until->greaterThan(now()->addHours(23)));
    }

    public function test_grave_warning_blocks_account_indefinitely(): void
    {
        $actor = $this->makeGlobalUser('dispositivos_sesiones.read', 'moderacion_cuentas.create');
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->postJson("/dispositivos-sesiones/usuario/{$target->id}/advertencia", [
                'level' => 'grave',
                'message' => 'Se bloquea el acceso hasta nuevo aviso.',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'level' => 'grave', 'account_status' => 'blocked']);

        $fresh = $target->fresh();
        $this->assertSame('blocked', $fresh->account_status);
        $this->assertNull($fresh->suspended_until);
    }

    public function test_suspended_account_regains_access_after_24_hours(): void
    {
        $target = User::factory()->create([
            'account_status' => 'suspended',
            'suspended_until' => now()->subMinute(),
        ]);
        $this->givePermissions($target, 'dispositivos_sesiones.read');

        $this->actingAs($target)
            ->get('/dispositivos-sesiones')
            ->assertOk();

        $fresh = $target->fresh();
        $this->assertSame('active', $fresh->account_status);
        $this->assertNull($fresh->suspended_until);
    }

    public function test_moderator_can_remove_a_warning_and_status_recalculates(): void
    {
        $actor = $this->makeGlobalUser('dispositivos_sesiones.read', 'moderacion_cuentas.create', 'moderacion_cuentas.update');
        $target = User::factory()->create();

        $warning = ModerationWarning::create([
            'user_id' => $target->id,
            'issued_by' => $actor->id,
            'level' => 'grave',
            'reason' => 'Uso indebido',
            'message' => 'Mensaje de prueba',
        ]);
        $target->forceFill(['account_status' => 'blocked'])->save();
        $notification = InternalNotification::create([
            'user_id' => $target->id,
            'type' => 'moderation_warning',
            'title' => 'Advertencia de uso indebido',
            'message' => 'Mensaje de prueba',
            'data' => ['warning_id' => $warning->id, 'level' => 'grave'],
        ]);

        $this->actingAs($actor)
            ->deleteJson("/dispositivos-sesiones/usuario/{$target->id}/advertencia/{$warning->id}")
            ->assertOk()
            ->assertJson(['success' => true, 'warning_count' => 0, 'account_status' => 'active']);

        $this->assertDatabaseMissing('moderation_warnings', ['id' => $warning->id]);
        $this->assertDatabaseMissing('internal_notifications', ['id' => $notification->id]);
    }

    public function test_user_can_receive_and_acknowledge_a_first_warning(): void
    {
        $target = User::factory()->create();
        $notification = InternalNotification::create([
            'user_id' => $target->id,
            'type' => 'moderation_warning',
            'title' => 'Advertencia de uso indebido',
            'message' => 'Debes detener esta conducta.',
            'data' => ['level' => 'leve'],
        ]);

        $this->actingAs($target)
            ->getJson('/notificaciones/pendiente')
            ->assertOk()
            ->assertJsonPath('notification.id', $notification->id)
            ->assertJsonPath('notification.level', 'leve');

        $this->actingAs($target)
            ->patchJson("/notificaciones/{$notification->id}/confirmar")
            ->assertOk()
            ->assertJson(['ok' => true, 'restricted' => false]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_restricted_user_can_acknowledge_notification_without_bypassing_lock(): void
    {
        $target = User::factory()->create(['account_status' => 'blocked']);
        $notification = InternalNotification::create([
            'user_id' => $target->id,
            'type' => 'moderation_warning',
            'title' => 'Advertencia de uso indebido',
            'message' => 'Tu cuenta está bloqueada.',
            'data' => ['level' => 'grave'],
        ]);

        $this->actingAs($target)
            ->getJson('/notificaciones/pendiente')
            ->assertOk()
            ->assertJsonPath('notification.level', 'grave');

        $this->actingAs($target)
            ->patchJson("/notificaciones/{$notification->id}/confirmar")
            ->assertOk()
            ->assertJson(['ok' => true, 'restricted' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($target)
            ->get('/')
            ->assertRedirect('/cuenta/restringida');

        $this->actingAs($target)
            ->get('/cuenta/restringida')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Account/Restricted'));
    }
}
