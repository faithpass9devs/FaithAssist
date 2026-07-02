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
        ]);

        $request->user()->forceFill([
            'ui_theme' => $validated['theme'],
        ])->save();

        return response()->json([
            'theme' => $request->user()->ui_theme,
        ]);
    }
}
