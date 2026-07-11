<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UserThemeController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(['light', 'dark'])],
            'palette' => ['nullable', 'string', 'max:50'],
            'custom_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $columns = $this->availableUiColumns();

        $updates = [];

        if (in_array('ui_theme', $columns, true)) {
            $updates['ui_theme'] = $validated['theme'];
        }

        if (
            in_array('ui_palette', $columns, true)
            && array_key_exists('palette', $validated)
            && $validated['palette'] !== null
        ) {
            $updates['ui_palette'] = $validated['palette'];
        }

        if (
            in_array('ui_custom_color', $columns, true)
            && array_key_exists('custom_color', $validated)
            && $validated['custom_color'] !== null
        ) {
            $updates['ui_custom_color'] = $validated['custom_color'];
        }

        if ($updates !== []) {
            $request->user()->forceFill($updates)->save();
        }

        return response()->json([
            'theme' => $request->user()->ui_theme,
            'palette' => $request->user()->ui_palette,
            'custom_color' => $request->user()->ui_custom_color,
        ]);
    }

    private function availableUiColumns(): array
    {
        static $columns = null;

        if ($columns !== null) {
            return $columns;
        }

        $columns = [];

        foreach (['ui_theme', 'ui_palette', 'ui_custom_color'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }
}
