<?php

use App\Http\Middleware\CheckPostSize;
use App\Http\Middleware\EnforceResourceAccess;
use App\Http\Middleware\EnsureBookingAccess;
use App\Http\Middleware\EnsurePropertyAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\RestrictPrivateFiles;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            require base_path('routes/public.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RequireRole::class,
            'property.access' => EnsurePropertyAccess::class,
            'booking.access' => EnsureBookingAccess::class,
            'private.files' => RestrictPrivateFiles::class,
            'active' => EnsureUserIsActive::class,
            'force-password-change' => ForcePasswordChange::class,
        ]);

        // Deny-by-default resource checks for {session}, {property}, {room} and {task} route parameters.
        $middleware->appendToGroup('web', EnforceResourceAccess::class);
        $middleware->prepend(CheckPostSize::class);
        $middleware->appendToGroup('web', [
            EnsureUserIsActive::class,
            ForcePasswordChange::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/seam',
            'webhooks/channex',
            'webhooks/telnyx/sms',
            'webhooks/stripe',
            'webhooks/stripe/connect',
            'webhooks/wheelhouse/*',
        ]);
        // Trust the load balancer / reverse proxy in front of the app (if any) so
        // Laravel correctly detects HTTPS. Without this, session/remember-me cookies
        // can behave inconsistently behind a proxy that terminates SSL.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, $request) {
            return redirect()->guest(route('login'))
                ->with('status', 'Your session expired. Please sign in again.');
        });
    })->create();
