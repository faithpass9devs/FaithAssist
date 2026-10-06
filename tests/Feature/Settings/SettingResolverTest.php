<?php

namespace Tests\Feature\Settings;

use App\Models\Settings\Setting;
use App\Models\Settings\SettingCategory;
use App\Models\Settings\SettingValue;
use App\Models\User;
use App\Services\Settings\SettingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\ControllerTestHelpers;
use Tests\TestCase;

class SettingResolverTest extends TestCase
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

    private function createSetting(string $key, string $type = 'string', mixed $default = null, array $meta = []): Setting
    {
        return Setting::query()->create([
            'category_id' => $this->category->id,
            'key' => $key,
            'name' => $key,
            'type' => $type,
            'meta' => $meta,
            'default_value' => $default,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function makeResolver(): SettingResolver
    {
        return app(SettingResolver::class);
    }

    private function makeUser(): User
    {
        return $this->makeGlobalUser('ajustes.read', 'ajustes.update');
    }

    public function test_resolves_global_default_when_no_overrides_exist(): void
    {
        $this->createSetting('badge.accent_color', 'color', '#d4af37');

        $value = $this->makeResolver()->resolve(
            'badge.accent_color',
            $this->chain['church']->id,
            $this->chain['diocese']->id
        );

        $this->assertSame('#d4af37', $value);
    }

    public function test_church_override_takes_precedence_over_diocese_and_global(): void
    {
        $setting = $this->createSetting('badge.accent_color', 'color', '#d4af37');
        $church = $this->chain['church'];
        $diocese = $this->chain['diocese'];

        SettingValue::query()->create([
            'setting_id' => $setting->id,
            'scope' => SettingValue::SCOPE_DIOCESE,
            'scope_id' => $diocese->id,
            'value' => '#0000ff',
        ]);

        SettingValue::query()->create([
            'setting_id' => $setting->id,
            'scope' => SettingValue::SCOPE_CHURCH,
            'scope_id' => $church->id,
            'value' => '#ff0000',
        ]);

        [$value, $origin] = array_values(
            $this->makeResolver()->resolveWithOrigin('badge.accent_color', $church->id, $diocese->id)
        );

        $this->assertSame('#ff0000', $value);
        $this->assertSame(SettingValue::SCOPE_CHURCH, $origin);
    }

    public function test_diocese_override_takes_precedence_over_global(): void
    {
        $setting = $this->createSetting('badge.accent_color', 'color', '#d4af37');
        $church = $this->chain['church'];
        $diocese = $this->chain['diocese'];

        SettingValue::query()->create([
            'setting_id' => $setting->id,
            'scope' => SettingValue::SCOPE_DIOCESE,
            'scope_id' => $diocese->id,
            'value' => '#0000ff',
        ]);

        $resolved = $this->makeResolver()->resolveWithOrigin('badge.accent_color', $church->id, $diocese->id);

        $this->assertSame('#0000ff', $resolved['value']);
        $this->assertSame(SettingValue::SCOPE_DIOCESE, $resolved['origin']);
    }

    public function test_boolean_values_are_normalized(): void
    {
        $setting = $this->createSetting('badge.mostrar_nombre', 'boolean', true);
        $church = $this->chain['church'];

        $this->makeResolver()->saveChurchValues($church->id, [
            'badge.mostrar_nombre' => 'false',
        ], $this->makeUser());

        $value = SettingValue::query()->where('setting_id', $setting->id)->firstOrFail();
        $this->assertFalse($value->value);
    }

    public function test_unknown_keys_are_ignored(): void
    {
        $church = $this->chain['church'];

        $this->makeResolver()->saveChurchValues($church->id, [
            'no_existe' => 'x',
        ], $this->makeUser());

        $this->assertDatabaseCount('setting_values', 0);
    }

    public function test_reset_only_removes_requested_keys(): void
    {
        $accent = $this->createSetting('badge.accent_color', 'color', '#d4af37');
        $title = $this->createSetting('badge.title_text', 'string', 'Gafete');
        $church = $this->chain['church'];
        $user = $this->makeUser();

        $this->makeResolver()->saveChurchValues($church->id, [
            'badge.accent_color' => '#ff0000',
            'badge.title_text' => 'Nuevo título',
        ], $user);

        $this->makeResolver()->resetChurchValues($church->id, ['badge.accent_color']);

        $this->assertDatabaseMissing('setting_values', ['setting_id' => $accent->id]);
        $this->assertDatabaseHas('setting_values', ['setting_id' => $title->id]);
    }
}
