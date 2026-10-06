<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\PasswordRecoverySession;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureSessionIsOpen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            EnsureSessionIsOpen::class,
            EnsureAccountIsActive::class,
        ]);

        $middleware->alias([
            'password.recovery.session' => PasswordRecoverySession::class,
            'password.changed' => EnsurePasswordIsChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
