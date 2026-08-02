<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    private const ALLOWED_ROUTE_NAMES = [
        'profile.password.edit',
        'profile.password.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        if (in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        return redirect()
            ->route('profile.password.edit')
            ->with('status', 'Debes actualizar tu contraseña para ingresar al sistema.');
    }
}
