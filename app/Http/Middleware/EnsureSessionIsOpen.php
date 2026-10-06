<?php

namespace App\Http\Middleware;

use App\Services\SessionVisitRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsOpen
{
    public function __construct(private readonly SessionVisitRecorder $visits) {}

    /**
     * Sign the user out on every device whose session row was closed by a moderator.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->hasSession()) {
            return $next($request);
        }

        $sessionId = $request->session()->getId();
        $record = DB::table('sessions')->where('id', $sessionId)->first();

        if (($record->status ?? 'active') !== 'closed') {
            return $next($request);
        }

        $this->visits->close($sessionId);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Invalidating drops the row, so the device record is restored for the history.
        DB::table('sessions')->updateOrInsert(['id' => $sessionId], (array) $record);

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Tu sesión fue cerrada por un administrador.'], 401);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Tu sesión fue cerrada por un administrador.');
    }
}
