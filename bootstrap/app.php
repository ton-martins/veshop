<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

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
            \App\Http\Middleware\EnsureRouteModelContractorScope::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'shop/*/pagamentos/webhook/*',
        ]);

        $middleware->alias([
            '2fa' => \App\Http\Middleware\EnsureTwoFactorAuthenticated::class,
            'single.admin.session' => \App\Http\Middleware\EnsureSingleAdminSession::class,
            'admin.activity.timeout' => \App\Http\Middleware\EnsureAdminInactivityTimeout::class,
            'role' => \App\Http\Middleware\EnsureUserRole::class,
            'contractor.module' => \App\Http\Middleware\EnsureContractorModuleEnabled::class,
            'shop.auth' => \App\Http\Middleware\EnsureShopAuthenticated::class,
            'shop.contractor' => \App\Http\Middleware\EnsureShopCustomerMatchesContractor::class,
            'shop.verified' => \App\Http\Middleware\EnsureShopEmailIsVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->headers->has('X-Inertia') || ! $request->hasSession()) {
                return null;
            }

            $errorBag = trim((string) $request->header('X-Inertia-Error-Bag', 'default'));
            if ($errorBag === '') {
                $errorBag = 'default';
            }

            return redirect()
                ->back(status: 303)
                ->withErrors($exception->errors(), $errorBag)
                ->withInput($request->except([
                    'password',
                    'password_confirmation',
                    'current_password',
                ]));
        });

        $exceptions->respond(function ($response, $exception, Request $request) {
            if ($response->getStatusCode() !== 419 || $request->expectsJson()) {
                return $response;
            }

            $routeName = (string) optional($request->route())->getName();
            $shopSlug = trim((string) $request->route('slug', ''));
            $redirectUrl = Route::has('login') ? route('login') : '/login';

            if (($shopSlug !== '') && str_starts_with($routeName, 'shop.') && Route::has('shop.auth.login')) {
                $redirectUrl = route('shop.auth.login', ['slug' => $shopSlug]);
            }

            return redirect()
                ->guest($redirectUrl)
                ->setStatusCode(303)
                ->with('status', 'Sua sessão expirou. Faça login novamente.');
        });
    })->create();
