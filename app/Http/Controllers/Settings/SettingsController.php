<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Ecclesiastes\Church;
use App\Models\Settings\Setting;
use App\Models\User;
use App\Services\Settings\SettingResolver;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingResolver $resolver) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $this->authorize('viewAny', Setting::class);

        $church = $this->resolveTargetChurch($user, $request->integer('church_id') ?: null);

        if (! $church) {
            abort(404, 'No hay parroquias disponibles.');
        }

        return Inertia::render('Settings/Index', [
            'categories' => $this->resolver->groupedSettings($church->id, $church->diocese_id),
            'church' => ['id' => $church->id, 'name' => $church->name],
            'churchOptions' => $this->churchOptions($user),
            'canUpdate' => $user->can('ajustes.update'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->authorize('update', Setting::class);

        $church = $this->resolveTargetChurch($user, $request->integer('church_id') ?: null);

        abort_unless($church && $this->ensureAccess($user, $church), 403);

        $values = $request->input('values', []);

        if (! is_array($values)) {
            return response()->json(['message' => 'Formato de valores inválido.'], 422);
        }

        try {
            $this->resolver->saveChurchValues($church->id, $values, $user);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Datos inválidos.', 'errors' => $e->errors()], 422);
        }

        return response()->json(['message' => 'Ajustes guardados correctamente.']);
    }

    public function storeFile(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->authorize('update', Setting::class);

        $validated = $request->validate([
            'church_id' => ['required', 'integer'],
            'key' => ['required', 'string'],
            'file' => ['required', 'file', 'image', 'max:8192'],
        ]);

        $church = $this->resolveTargetChurch($user, $validated['church_id']);

        abort_unless($church && $this->ensureAccess($user, $church), 403);

        try {
            $path = $this->resolver->storeFile($church->id, $validated['key'], $request->file('file'));
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Archivo inválido.', 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'path' => $path,
            'file_url' => $this->resolver->resolveFileUrl($path),
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();

        $this->authorize('update', Setting::class);

        $validated = $request->validate([
            'church_id' => ['required', 'integer'],
            'keys' => ['required', 'array'],
            'keys.*' => ['required', 'string'],
        ]);

        $church = $this->resolveTargetChurch($user, $validated['church_id']);

        abort_unless($church && $this->ensureAccess($user, $church), 403);

        $this->resolver->resetChurchValues($church->id, $validated['keys']);

        return response()->json(['message' => 'Ajustes restablecidos.']);
    }

    private function resolveTargetChurch(User $user, ?int $requestedId): ?Church
    {
        if ($user->church_id !== null) {
            return Church::query()->find($user->church_id);
        }

        $scope = new UserScopeService($user);

        $query = Church::query();

        if (! $scope->isGlobal()) {
            $allowedIds = $scope->churchIds();
            $query->whereIn('id', $allowedIds);
        }

        if ($requestedId !== null) {
            $query->where('id', $requestedId);
        }

        return $query->orderBy('name')->first();
    }

    /**
     * @return array<int, array{id: int, name: string}>|null
     */
    private function churchOptions(User $user): ?array
    {
        if ($user->church_id !== null) {
            return null;
        }

        $scope = new UserScopeService($user);

        $query = Church::query();

        if (! $scope->isGlobal()) {
            $query->whereIn('id', $scope->churchIds());
        }

        return $query->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Church $church) => ['id' => $church->id, 'name' => $church->name])
            ->all();
    }

    private function ensureAccess(User $user, Church $church): bool
    {
        $scope = new UserScopeService($user);

        if ($scope->isGlobal()) {
            return true;
        }

        if ($user->church_id !== null) {
            return (int) $user->church_id === (int) $church->id;
        }

        return $scope->churchIds()->contains($church->id);
    }
}
