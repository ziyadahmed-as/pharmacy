<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureActingRoleChosen;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureRoleAccess;
use App\Http\Middleware\EnsureSessionNotExpired;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'account.active' => EnsureAccountIsActive::class,
            'password.changed' => EnsurePasswordIsChanged::class,
            'acting.role' => EnsureActingRoleChosen::class,
            'role' => EnsureRoleAccess::class,
            'session.activity' => EnsureSessionNotExpired::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            EnsureSessionNotExpired::class,
            EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
