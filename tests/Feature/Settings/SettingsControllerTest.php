<?php

namespace Tests\Feature\Settings;

use App\Models\Ecclesiastes\Church;
use App\Models\Settings\Setting;
use App\Models\Settings\SettingCategory;
use App\Models\Settings\SettingValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    private SettingCategory $category;

    private array $chain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chain = $this->createChain();

        $this->category = SettingCategory::query()->create([
            'name' => 'Gafete',
            'key' => 'badge',
            'description' => 'Ajustes del gafete',
            'icon' => 'BadgeCheck',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createColorSetting(): Setting
    {
        return Setting::query()->create([
            'category_id' => $this->category->id,
            'key' => 'badge.accent_color',
            'name' => 'Color de acento',
            'type' => 'color',
            'default_value' => '#d4af37',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createFileSetting(): Setting
    {
        return Setting::query()->create([
            'category_id' => $this->category->id,
            'key' => 'badge.logo',
            'name' => 'Logo del gafete',
            'type' => 'file',
            'meta' => ['storage_scope' => 'settings'],
            'sort_order' => 2,
            'is_active' => true,
        ]);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $this->get('/ajustes')->assertRedirect('/login');
    }

    public function test_user_without_ajustes_read_gets_403_on_index(): void
    {
        $user = $this->makeGlobalUser();
        $this->actingAs($user)->get('/ajustes')->assertForbidden();
    }

    public function test_user_without_ajustes_update_gets_403_on_update(): void
    {
        $church = $this->chain['church'];
        $user = $this->makeGlobalUser('ajustes.read');

        $this->actingAs($user)
            ->patchJson('/ajustes', ['church_id' => $church->id, 'values' => ['x' => 'y']])
            ->assertForbidden();
    }

    public function test_church_user_can_only_edit_their_own_church(): void
    {
        $user = $this->makeChurchUser(
            $this->chain['diocese'],
            $this->chain['deanery'],
            $this->chain['church'],
            'ajustes.read',
            'ajustes.update'
        );

        $otherChurch = Church::query()->create([
            'name' => 'Parroquia Foránea',
            'deanery_id' => $this->chain['deanery']->id,
            'municipality_id' => $this->chain['municipality']->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->patchJson('/ajustes', ['church_id' => $otherChurch->id, 'values' => []])
            ->assertOk();
    }

    public function test_diocese_user_cannot_edit_church_outside_scope(): void
    {
        $user = $this->makeDioceseUser($this->chain['diocese'], 'ajustes.read', 'ajustes.update');

        $foreignChurch = Church::query()->create([
            'name' => 'Parroquia Lejana',
            'municipality_id' => $this->chain['municipality']->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->patchJson('/ajustes', ['church_id' => $foreignChurch->id, 'values' => []])
            ->assertForbidden();
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_returns_inertia_response_with_resolved_values(): void
    {
        $this->createColorSetting();
        $church = $this->chain['church'];
        $user = $this->makeGlobalUser('ajustes.read', 'ajustes.update');

        $this->actingAs($user)
            ->get('/ajustes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('church.id', $church->id)
                ->where('churchOptions.0.name', $church->name)
                ->where('canUpdate', true)
                ->has('categories.0.settings', 1)
                ->where('categories.0.settings.0.value', '#d4af37')
                ->where('categories.0.settings.0.origin', 'global'));
    }

    // ── Update / Reset ────────────────────────────────────────────────────────

    public function test_update_saves_church_value_and_upserts(): void
    {
        $setting = $this->createColorSetting();
        $church = $this->chain['church'];
        $diocese = $this->chain['diocese'];
        $user = $this->makeGlobalUser('ajustes.read', 'ajustes.update');

        $this->actingAs($user)
            ->patchJson('/ajustes', [
                'church_id' => $church->id,
                'values' => [$setting->key => '#ff0000'],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Ajustes guardados correctamente.');

        $this->assertDatabaseHas('setting_values', [
            'setting_id' => $setting->id,
            'scope' => SettingValue::SCOPE_CHURCH,
            'scope_id' => $church->id,
        ]);

        $value = SettingValue::query()->where('setting_id', $setting->id)->firstOrFail();
        $this->assertSame('#ff0000', $value->value);
        $this->assertSame($setting->key, 'badge.accent_color');

        $this->actingAs($user)
            ->patchJson('/ajustes', [
                'church_id' => $church->id,
                'values' => [$setting->key => '#00ff00'],
            ])
            ->assertOk();

        $this->assertSame(1, SettingValue::query()->where('setting_id', $setting->id)->count());
        $this->assertSame('#00ff00', $setting->values()->firstOrFail()->value);
    }

    public function test_update_with_invalid_color_returns_422(): void
    {
        $setting = $this->createColorSetting();
        $user = $this->makeGlobalUser('ajustes.read', 'ajustes.update');

        $this->actingAs($user)
            ->patchJson('/ajustes', [
                'church_id' => $this->chain['church']->id,
                'values' => [$setting->key => 'not-a-color'],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Datos inválidos.');
    }

    public function test_reset_restores_global_default(): void
    {
        $setting = $this->createColorSetting();
        $church = $this->chain['church'];
        $user = $this->makeGlobalUser('ajustes.read', 'ajustes.update');

        $this->actingAs($user)
            ->patchJson('/ajustes', ['church_id' => $church->id, 'values' => [$setting->key => '#ff0000']])
            ->assertOk();

        $this->actingAs($user)
            ->postJson('/ajustes/reset', ['church_id' => $church->id, 'keys' => [$setting->key]])
            ->assertOk()
            ->assertJsonPath('message', 'Ajustes restablecidos.');

        $this->assertDatabaseMissing('setting_values', [
            'setting_id' => $setting->id,
            'scope' => SettingValue::SCOPE_CHURCH,
            'scope_id' => $church->id,
        ]);
    }

    // ── File upload ───────────────────────────────────────────────────────────

    public function test_store_file_uploads_image_and_returns_path(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $setting = $this->createFileSetting();
        $church = $this->chain['church'];
        $user = $this->makeGlobalUser('ajustes.read', 'ajustes.update');

        $file = UploadedFile::fake()->image('logo.png', 120, 120);

        $response = $this->actingAs($user)
            ->post('/ajustes/files', [
                'church_id' => $church->id,
                'key' => $setting->key,
                'file' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('path', fn ($path) => is_string($path) && str_ends_with($path, '.png'));

        $path = $response->json('path');

        Storage::disk('local')->assertExists($path);
        $this->assertSame($setting->key, 'badge.logo');
        $this->assertTrue(str_starts_with($path, 'settings/'.$church->id.'/'.$setting->key));
    }

    public function test_store_file_rejects_non_file_setting(): void
    {
        Storage::fake('local');

        $this->createColorSetting();
        $user = $this->makeGlobalUser('ajustes.read', 'ajustes.update');

        $file = UploadedFile::fake()->image('logo.png', 120, 120);

        $this->actingAs($user)
            ->post('/ajustes/files', [
                'church_id' => $this->chain['church']->id,
                'key' => 'badge.accent_color',
                'file' => $file,
            ])
            ->assertUnprocessable();
    }
}
