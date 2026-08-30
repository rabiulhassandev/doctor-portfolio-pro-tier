<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         | Payment gateways post back from their own domain and have no CSRF
         | token to send.
         |
         | This is safe here ONLY because the callback trusts nothing in the
         | request body: it looks the payment up by our own transaction id and
         | then re-validates the whole thing against the gateway's API before
         | marking anything as paid. Without that verification step, exempting
         | these routes would let anyone confirm their own appointment by
         | posting a form. See SslCommerzGateway::handleCallback().
         |
         | Laravel 13 has no VerifyCsrfToken class to edit — the exemption list
         | lives here.
         */
        $middleware->validateCsrfTokens(except: [
            'payments/callback/*',
            'payments/ipn/*',
        ]);

        /*
         | Where an unauthenticated visitor is sent.
         |
         | Two guards, two login screens. Without this, a patient whose session
         | has expired is bounced to the STAFF login at /admin/login — a page
         | they have no account for and no way to get past.
         */
        $middleware->redirectGuestsTo(fn (Request $request): string => $request->is('admin', 'admin/*')
            ? route('filament.admin.auth.login')
            : route('patient.login'));

        // And where an already-signed-in patient is sent if they ask for the
        // login page again.
        $middleware->redirectUsersTo(fn (Request $request): string => $request->is('patient', 'patient/*')
            ? route('patient.dashboard')
            : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
