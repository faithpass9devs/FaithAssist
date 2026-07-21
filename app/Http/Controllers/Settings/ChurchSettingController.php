<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateChurchSettingsRequest;
use App\Models\Ecclesiastes\Church;
use App\Models\Settings\ChurchSetting;
use App\Services\Settings\ChurchSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChurchSettingController extends Controller
{
    public function __construct(private readonly ChurchSettingService $settings) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ChurchSetting::class);

        return Inertia::render(
            'Settings/Index',
            $this->settings->indexData($request->user(), $request->integer('church_id') ?: null)
        );
    }

    public function update(UpdateChurchSettingsRequest $request, Church $church): RedirectResponse
    {
        $this->authorize('update', [ChurchSetting::class, $church]);

        $this->settings->updateSettings($church, $request->validated(), $request->user());

        return redirect()
            ->route('configuraciones.index', ['church_id' => $church->id])
            ->with('success', 'Configuraciones actualizadas correctamente.');
    }
}
