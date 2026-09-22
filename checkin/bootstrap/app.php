<?php

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
        $middleware->alias([
            'role' => \App\Http\Middleware\RequireRole::class,
        ]);
        $middleware->prepend(\App\Http\Middleware\CheckPostSize::class);
        $middleware->validateCsrfTokens(except: [
            'webhooks/seam',
            'webhooks/channex',
            'webhooks/telnyx/sms',
            'webhooks/stripe',
        ]);
        // Trust the load balancer / reverse proxy in front of the app (if any) so
        // Laravel correctly detects HTTPS. Without this, session/remember-me cookies
        // can behave inconsistently behind a proxy that terminates SSL.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            return redirect()->guest(route('login'))
                ->with('status', 'Your session expired. Please sign in again.');
        });
    })->create();
