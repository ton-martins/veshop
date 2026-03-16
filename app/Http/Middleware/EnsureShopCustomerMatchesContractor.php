<?php

namespace App\Http\Middleware;

use App\Models\Contractor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopCustomerMatchesContractor
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
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

        return $next($request);
    }
}
