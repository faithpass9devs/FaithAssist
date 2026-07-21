<?php

namespace Tests\Feature\Settings;

use App\Globals\SettingType;
use App\Globals\Status;
use App\Models\Settings\ChurchSetting;
use App\Models\Settings\SettingDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class ChurchSettingControllerTest extends TestCase
{
    use ControllerTestHelpers, RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_from_index(): void
    {
        $this->get('/configuraciones')->assertRedirect('/login');
    }

    public function test_user_without_read_permission_gets_403_on_index(): void
    {
        $user = $this->makeGlobalUser();

        $this->actingAs($user)->get('/configuraciones')->assertForbidden();
    }

    public function test_index_returns_settings_for_visible_church(): void
    {
        $chain = $this->createChain();
        $this->booleanDefinition();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'configuraciones.read');

        $this->actingAs($user)->get('/configuraciones')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->where('selectedChurchId', $chain['church']->id)
                ->where('settings.0.key', 'show_apps'));
    }

    public function test_user_without_update_permission_gets_403_on_update(): void
    {
        $chain = $this->createChain();
        $this->booleanDefinition();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'configuraciones.read');

        $this->actingAs($user)
            ->put("/configuraciones/{$chain['church']->id}", [
                'settings' => ['show_apps' => true],
            ])
            ->assertForbidden();
    }

    public function test_church_scoped_user_can_update_own_church_settings(): void
    {
        $chain = $this->createChain();
        $definition = $this->booleanDefinition();
        $user = $this->makeChurchUser($chain['diocese'], $chain['deanery'], $chain['church'], 'configuraciones.update');

        $this->actingAs($user)
            ->put("/configuraciones/{$chain['church']->id}", [
                'settings' => ['show_apps' => false],
            ])
            ->assertRedirect("/configuraciones?church_id={$chain['church']->id}");

        $this->assertDatabaseHas('church_settings', [
            'church_id' => $chain['church']->id,
            'setting_definition_id' => $definition->id,
        ]);

        $this->assertFalse(ChurchSetting::query()->first()->value['value']);
    }

    public function test_church_scoped_user_cannot_update_another_church_settings(): void
    {
        $chain1 = $this->createChain();
        $chain2 = $this->createChain();
        $chain2['church']->update(['name' => 'Parroquia Foranea']);
        $this->booleanDefinition();
        $user = $this->makeChurchUser($chain1['diocese'], $chain1['deanery'], $chain1['church'], 'configuraciones.update');

        $this->actingAs($user)
            ->put("/configuraciones/{$chain2['church']->id}", [
                'settings' => ['show_apps' => true],
            ])
            ->assertForbidden();
    }

    public function test_scope_all_user_can_update_another_church_settings(): void
    {
        $chain = $this->createChain();
        $definition = $this->booleanDefinition();
        $user = $this->makeGlobalUser('configuraciones.update', 'configuraciones.scope.all');

        $this->actingAs($user)
            ->put("/configuraciones/{$chain['church']->id}", [
                'settings' => ['show_apps' => true],
            ])
            ->assertRedirect("/configuraciones?church_id={$chain['church']->id}");

        $this->assertDatabaseHas('church_settings', [
            'church_id' => $chain['church']->id,
            'setting_definition_id' => $definition->id,
        ]);
    }

    public function test_json_setting_must_be_valid_json(): void
    {
        $chain = $this->createChain();
        SettingDefinition::query()->create([
            'key' => 'extra_payload',
            'name' => 'Payload',
            'status' => Status::ACTIVE,
            'type_data' => SettingType::JSON,
        ]);
        $user = $this->makeGlobalUser('configuraciones.update');

        $this->actingAs($user)
            ->put("/configuraciones/{$chain['church']->id}", [
                'settings' => ['extra_payload' => '{bad json'],
            ])
            ->assertInvalid('settings.extra_payload');
    }

    public function test_file_setting_enforces_meta_rules_and_persists_metadata(): void
    {
        Storage::fake('local');

        $chain = $this->createChain();
        $definition = SettingDefinition::query()->create([
            'key' => 'church_logo',
            'name' => 'Logo',
            'status' => Status::ACTIVE,
            'type_data' => SettingType::FILE,
            'meta' => [
                'mimes' => ['png'],
                'mimetypes' => ['image/png'],
                'max_kb' => 128,
            ],
        ]);
        $user = $this->makeGlobalUser('configuraciones.update');

        $this->actingAs($user)
            ->put("/configuraciones/{$chain['church']->id}", [
                'settings' => [
                    'church_logo' => UploadedFile::fake()->image('logo.png')->size(32),
                ],
            ])
            ->assertRedirect("/configuraciones?church_id={$chain['church']->id}");

        $setting = ChurchSetting::query()
            ->where('setting_definition_id', $definition->id)
            ->firstOrFail();

        Storage::disk('local')->assertExists($setting->value['path']);
        $this->assertSame('logo.png', $setting->value['name']);
    }

    private function booleanDefinition(): SettingDefinition
    {
        return SettingDefinition::query()->create([
            'key' => 'show_apps',
            'name' => 'Mostrar aplicaciones',
            'status' => Status::ACTIVE,
            'type_data' => SettingType::BOOLEAN,
        ]);
    }
}
