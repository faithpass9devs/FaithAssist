<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $request->user()->forceFill([
            'ui_theme' => $validated['theme'],
            'ui_palette' => $validated['palette'] ?? $request->user()->ui_palette,
            'ui_custom_color' => $validated['custom_color'] ?? $request->user()->ui_custom_color,
        ])->save();

        return response()->json([
            'theme' => $request->user()->ui_theme,
            'palette' => $request->user()->ui_palette,
            'custom_color' => $request->user()->ui_custom_color,
        ]);
    }
}
