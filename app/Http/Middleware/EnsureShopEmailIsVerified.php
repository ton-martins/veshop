<?php

namespace App\Http\Middleware;

use App\Models\Contractor;
use App\Models\ShopCustomer;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopEmailIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var ShopCustomer|null $customer */
        $customer = $request->user('shop');
        $slug = (string) $request->route('slug', '');

        if (! $customer || $slug === '') {
            abort(403);
        }

        $contractor = Contractor::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $contractor || (int) $customer->contractor_id !== (int) $contractor->id) {
            abort(403);
        }

        if (! $contractor->requiresEmailVerification() || $customer->hasVerifiedEmail()) {
            return $next($request);
        }

        /** @var RedirectResponse $redirect */
        $redirect = redirect()->route('shop.verification.notice', ['slug' => $contractor->slug]);

        return $redirect->with('status', 'Confirme seu e-mail para continuar.');
    }
}
