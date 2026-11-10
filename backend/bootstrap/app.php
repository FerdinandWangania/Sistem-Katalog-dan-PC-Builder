<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\EnsureOwner;
use App\Http\Middleware\FinanceAccess;
use App\Http\Middleware\OperationalRead;
use App\Http\Middleware\SuperadminOnly;
use App\Http\Middleware\TokenAuth;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => TokenAuth::class,
            'admin' => AdminOnly::class,
            'owner' => EnsureOwner::class,
            'superadmin' => SuperadminOnly::class,
            'finance' => FinanceAccess::class,
            'admin.read' => OperationalRead::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
