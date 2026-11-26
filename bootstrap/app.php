<?php

use App\Http\Middleware\EnsureStaffUsesScannerMode;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
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
        // Percayai reverse proxy lokal (Cloudflare Tunnel / Laragon) agar IP klien & HTTPS terbaca akurat
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->alias([
            'active'        => EnsureUserIsActive::class,
            'role'          => EnsureUserHasRole::class,
            'scanner.staff' => EnsureStaffUsesScannerMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();