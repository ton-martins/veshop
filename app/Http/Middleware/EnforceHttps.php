<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceHttps
{
    /**
     * Redirects HTTP requests to HTTPS when configured.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.force_https', false)) {
            return $next($request);
        }

        $isForwardedHttps = strtolower((string) $request->headers->get('x-forwarded-proto')) === 'https';
        if ($request->isSecure() || $isForwardedHttps) {
            return $next($request);
        }

        $targetUrl = preg_replace('/^http:/i', 'https:', $request->fullUrl()) ?: $request->fullUrl();
        $statusCode = in_array(strtoupper($request->method()), ['GET', 'HEAD'], true) ? 301 : 307;

        return redirect()->away($targetUrl, $statusCode);
    }
}

