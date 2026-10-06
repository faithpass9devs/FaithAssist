<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($request->route()?->getName(), ['notifications.pending', 'notifications.acknowledge', 'account.restricted', 'logout'], true)) {
            if ($request->route()?->getName() === 'account.restricted' && $user->account_status === 'suspended' && $user->suspended_until && now()->greaterThanOrEqualTo($user->suspended_until)) {
                return redirect()->route('home');
            }

            return $next($request);
        }

        if ($user && $user->account_status === 'suspended' && $user->suspended_until && now()->greaterThanOrEqualTo($user->suspended_until)) {
            $user->forceFill(['account_status' => 'active', 'suspended_until' => null])->save();
        }

        if ($user && in_array($user->account_status, ['suspended', 'blocked'], true)) {
            return redirect()->route('account.restricted');
        }

        return $next($request);
    }
}
