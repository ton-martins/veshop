<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopAuthenticated
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('shop')) {
            return $next($request);
        }

        $slug = (string) $request->route('slug', '');
        if ($slug !== '') {
            /** @var RedirectResponse $redirect */
            $redirect = redirect()->guest(route('shop.auth.login', ['slug' => $slug]));

            return $redirect->with('status', 'Faça login para continuar seu pedido.');
        }

        abort(401);
    }
}
