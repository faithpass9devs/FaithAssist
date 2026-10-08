<?php

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\DeviceSessionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeviceSessionRegistrarTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64) Chrome/140.0';

    private const EDGE = 'Mozilla/5.0 (Windows NT 10.0; Win64) Chrome/140.0 Edg/140.0';

    public function test_login_assigns_a_persistent_browser_identifier(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

        $cookie = Cookie::queued('faithassist_browser_id');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertNotEmpty($cookie->getValue());
    }

    public function test_a_first_login_records_the_browser_as_active(): void
    {
        $user = User::factory()->create();
        $this->makeRow('first-login', $user->id, self::CHROME);

        $this->registrar()->register('first-login', $user->id, self::CHROME);

        $session = $this->row('first-login');
        $this->assertSame('active', $session->status);
        $this->assertSame('Google Chrome', $session->browser);
        $this->assertSame('Computadora', $session->device_name);
        $this->assertNull($session->hidden_at);
    }

    public function test_signing_in_again_from_the_same_browser_reactivates_the_record(): void
    {
        $user = User::factory()->create();
        $this->makeRow('old-chrome', $user->id, self::CHROME, [
            'status' => 'closed',
            'first_seen_at' => now()->subDays(3),
            'location_label' => 'Calle Hidalgo 12, Tenancingo, México',
        ]);
        $this->makeRow('new-chrome', $user->id, self::CHROME);

        $this->registrar()->register('new-chrome', $user->id, self::CHROME);

        $reactivated = $this->row('new-chrome');
        $this->assertSame('active', $reactivated->status);
        $this->assertSame('Calle Hidalgo 12, Tenancingo, México', $reactivated->location_label);
        $this->assertStringStartsWith(now()->subDays(3)->format('Y-m-d'), (string) $reactivated->first_seen_at);

        // The browser keeps a single record instead of piling up duplicates.
        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_each_browser_keeps_its_own_record_in_the_detail_view(): void
    {
        $user = User::factory()->create();
        $this->makeRow('chrome-session', $user->id, self::CHROME);
        $this->registrar()->register('chrome-session', $user->id, self::CHROME);

        $this->makeRow('edge-session', $user->id, self::EDGE);
        $this->registrar()->register('edge-session', $user->id, self::EDGE);

        // Chrome signs out and signs back in; Edge must stay untouched.
        DB::table('sessions')->where('id', 'chrome-session')->update(['status' => 'closed']);
        $this->makeRow('chrome-again', $user->id, self::CHROME);
        $this->registrar()->register('chrome-again', $user->id, self::CHROME);

        $records = DB::table('sessions')->where('user_id', $user->id)->whereNull('hidden_at')->get();
        $this->assertCount(2, $records);
        $this->assertEqualsCanonicalizing(
            ['Google Chrome', 'Microsoft Edge'],
            $records->pluck('browser')->all()
        );
        $this->assertSame('active', $this->row('chrome-again')->status);
        $this->assertSame('active', $this->row('edge-session')->status);
    }

    public function test_a_different_browser_creates_its_own_record(): void
    {
        $user = User::factory()->create();
        $this->makeRow('chrome-session', $user->id, self::CHROME);
        $this->registrar()->register('chrome-session', $user->id, self::CHROME);

        $this->makeRow('edge-session', $user->id, self::EDGE);
        $this->registrar()->register('edge-session', $user->id, self::EDGE);

        $this->assertSame('active', $this->row('chrome-session')->status);
        $this->assertSame('active', $this->row('edge-session')->status);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->whereNull('hidden_at')->count());
    }

    public function test_identical_user_agents_on_distinct_browsers_keep_separate_access_periods(): void
    {
        $user = User::factory()->create();
        $firstBrowser = '11111111-1111-4111-8111-111111111111';
        $secondBrowser = '22222222-2222-4222-8222-222222222222';
        $this->makeRow('browser-one', $user->id, self::CHROME);
        $this->registrar()->register('browser-one', $user->id, self::CHROME, $firstBrowser);
        $this->makeRow('browser-two', $user->id, self::CHROME);
        $this->registrar()->register('browser-two', $user->id, self::CHROME, $secondBrowser);

        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(2, DB::table('session_visit_periods')->where('user_id', $user->id)->count());

        $this->makeRow('browser-one-again', $user->id, self::CHROME);
        $this->registrar()->register('browser-one-again', $user->id, self::CHROME, $firstBrowser);

        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame('active', $this->row('browser-two')->status);
        $this->assertSame(3, DB::table('session_visit_periods')->where('user_id', $user->id)->count());
    }

    public function test_new_identified_browser_does_not_claim_an_unidentified_legacy_access(): void
    {
        $user = User::factory()->create();
        $this->makeRow('legacy', $user->id, self::CHROME, ['status' => 'closed']);
        $this->makeRow('identified', $user->id, self::CHROME);

        $this->registrar()->register('identified', $user->id, self::CHROME, '11111111-1111-4111-8111-111111111111');

        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame('closed', $this->row('legacy')->status);
    }

    public function test_signing_in_again_without_signing_out_does_not_leave_a_stale_active_session(): void
    {
        $user = User::factory()->create();
        $this->makeRow('stale-chrome', $user->id, self::CHROME, ['status' => 'active']);
        $this->makeRow('fresh-chrome', $user->id, self::CHROME);

        $this->registrar()->register('fresh-chrome', $user->id, self::CHROME);

        $this->assertSame(1, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame('active', $this->row('fresh-chrome')->status);
    }

    public function test_a_session_removed_by_a_moderator_comes_back_when_the_browser_signs_in_again(): void
    {
        $user = User::factory()->create();
        $this->makeRow('removed-chrome', $user->id, self::CHROME, [
            'status' => 'closed',
            'hidden_at' => now()->subHour(),
        ]);
        $this->makeRow('returning-chrome', $user->id, self::CHROME);

        $this->registrar()->register('returning-chrome', $user->id, self::CHROME);

        $this->assertSame('active', $this->row('returning-chrome')->status);
        $this->assertNull($this->row('returning-chrome')->hidden_at);
    }

    private function registrar(): DeviceSessionRegistrar
    {
        return app(DeviceSessionRegistrar::class);
    }

    private function makeRow(string $id, int $userId, string $userAgent, array $overrides = []): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => $userAgent,
            'payload' => base64_encode('payload'),
            'last_activity' => time() - 60,
            ...$overrides,
        ]);
    }

    private function row(string $id): object
    {
        return DB::table('sessions')->where('id', $id)->first();
    }
}
