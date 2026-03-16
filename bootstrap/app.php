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
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->web(prepend: [
            \App\Http\Middleware\EnforceHttps::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'shop/*/pagamentos/webhook/*',
        ]);

        $middleware->alias([
            '2fa' => \App\Http\Middleware\EnsureTwoFactorAuthenticated::class,
            'role' => \App\Http\Middleware\EnsureUserRole::class,
            'contractor.module' => \App\Http\Middleware\EnsureContractorModuleEnabled::class,
            'shop.auth' => \App\Http\Middleware\EnsureShopAuthenticated::class,
            'shop.contractor' => \App\Http\Middleware\EnsureShopCustomerMatchesContractor::class,
            'shop.verified' => \App\Http\Middleware\EnsureShopEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
