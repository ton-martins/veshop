<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Adds security headers to every web response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $this->ensureInertiaHeaders($request, $response);
        $this->setLocalNoCacheHeaders($response);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $allowSameOriginFrame = $this->shouldAllowSameOriginFrame($request);
        $this->setHeaderIfMissing($response, 'X-Frame-Options', $allowSameOriginFrame ? 'SAMEORIGIN' : 'DENY');
        $this->setHeaderIfMissing($response, 'X-Content-Type-Options', 'nosniff');
        $this->setHeaderIfMissing($response, 'Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setHeaderIfMissing($response, 'Permissions-Policy', "camera=(), microphone=(), geolocation=()");
        $this->setHeaderIfMissing($response, 'X-Permitted-Cross-Domain-Policies', 'none');
        $this->setHeaderIfMissing($response, 'Cross-Origin-Opener-Policy', 'same-origin');
        $this->setHeaderIfMissing($response, 'Cross-Origin-Resource-Policy', 'same-origin');
        $this->setHeaderIfMissing($response, 'Access-Control-Expose-Headers', 'X-Inertia, X-Inertia-Location, X-Inertia-Version');
        $csp = $this->resolveContentSecurityPolicy($allowSameOriginFrame);
        $this->setHeaderIfMissing(
            $response,
            'Content-Security-Policy',
            $csp,
        );

        $isForwardedHttps = strtolower((string) $request->headers->get('x-forwarded-proto')) === 'https';
        $hstsEnabled = (bool) config('security.hsts.enabled', true);
        if ($hstsEnabled && ($request->isSecure() || $isForwardedHttps)) {
            $maxAge = (int) config('security.hsts.max_age', 31536000);
            $includeSubdomains = (bool) config('security.hsts.include_subdomains', true);
            $preload = (bool) config('security.hsts.preload', false);

            $value = "max-age={$maxAge}";
            if ($includeSubdomains) {
                $value .= '; includeSubDomains';
            }
            if ($preload) {
                $value .= '; preload';
            }

            $this->setHeaderIfMissing($response, 'Strict-Transport-Security', $value);
        }

        return $response;
    }

    private function setLocalNoCacheHeaders(Response $response): void
    {
        if (! app()->isLocal()) {
            return;
        }

        // Local dev should always reflect current code, avoiding browser stale cache.
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
    }

    private function ensureInertiaHeaders(Request $request, Response $response): void
    {
        if (! $this->looksLikeInertiaResponse($response)) {
            return;
        }

        if (! $response->headers->has('X-Inertia')) {
            $response->headers->set('X-Inertia', 'true');
        }

        $varyHeader = (string) $response->headers->get('Vary', '');
        if (! str_contains(strtolower($varyHeader), 'x-inertia')) {
            $response->headers->set(
                'Vary',
                trim($varyHeader) !== '' ? $varyHeader.', X-Inertia' : 'X-Inertia'
            );
        }
    }

    private function looksLikeInertiaResponse(Response $response): bool
    {
        if (! $response instanceof JsonResponse) {
            return false;
        }

        $payload = $response->getData(true);
        if (! is_array($payload)) {
            return false;
        }

        return array_key_exists('component', $payload)
            && array_key_exists('props', $payload)
            && array_key_exists('url', $payload)
            && array_key_exists('version', $payload);
    }

    private function resolveContentSecurityPolicy(bool $allowSameOriginFrame = false): string
    {
        $policy = (string) config('security.headers.csp');
        if ($policy === '') {
            return $policy;
        }

        if ($allowSameOriginFrame) {
            $policy = $this->replaceCspDirective($policy, 'frame-ancestors', "'self'");
        }

        if (! app()->isLocal()) {
            return $policy;
        }

        $hotFile = public_path('hot');
        if (! is_file($hotFile)) {
            return $policy;
        }

        $hotUrl = trim((string) @file_get_contents($hotFile));
        if ($hotUrl === '') {
            return $policy;
        }

        $parts = parse_url($hotUrl);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $policy;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $this->appendCspSource($policy, 'script-src', $origin);
    }

    private function shouldAllowSameOriginFrame(Request $request): bool
    {
        if ($request->routeIs('admin.services.accounting.documents.versions.download')) {
            return ! $request->boolean('download');
        }

        if ($request->routeIs('admin.reports.exports.download')) {
            return $request->boolean('inline');
        }

        return false;
    }

    private function replaceCspDirective(string $policy, string $directive, string $value): string
    {
        $chunks = array_values(array_filter(array_map('trim', explode(';', $policy))));
        $directivePrefix = $directive.' ';
        $updated = false;

        foreach ($chunks as $index => $chunk) {
            if (! str_starts_with($chunk, $directivePrefix)) {
                continue;
            }

            $chunks[$index] = $directivePrefix.trim($value);
            $updated = true;
            break;
        }

        if (! $updated) {
            $chunks[] = $directivePrefix.trim($value);
        }

        return implode('; ', $chunks).';';
    }

    private function appendCspSource(string $policy, string $directive, string $source): string
    {
        $chunks = array_values(array_filter(array_map('trim', explode(';', $policy))));
        $directivePrefix = $directive.' ';
        $updated = false;

        foreach ($chunks as $index => $chunk) {
            if (! str_starts_with($chunk, $directivePrefix)) {
                continue;
            }

            $values = preg_split('/\s+/', trim(substr($chunk, strlen($directivePrefix)))) ?: [];
            if (! in_array($source, $values, true)) {
                $values[] = $source;
            }

            $chunks[$index] = $directivePrefix.implode(' ', array_values(array_filter($values)));
            $updated = true;

            break;
        }

        if (! $updated) {
            $chunks[] = $directivePrefix.$source;
        }

        return implode('; ', $chunks).';';
    }

    private function setHeaderIfMissing(Response $response, string $name, string $value): void
    {
        if ($value === '' || $response->headers->has($name)) {
            return;
        }

        $response->headers->set($name, $value);
    }
}
