<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Adds security headers to every web response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('security.headers.enabled', true)) {
            return $response;
        }

        $this->setHeaderIfMissing($response, 'X-Frame-Options', 'DENY');
        $this->setHeaderIfMissing($response, 'X-Content-Type-Options', 'nosniff');
        $this->setHeaderIfMissing($response, 'Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->setHeaderIfMissing($response, 'Permissions-Policy', "camera=(), microphone=(), geolocation=()");
        $this->setHeaderIfMissing($response, 'X-Permitted-Cross-Domain-Policies', 'none');
        $this->setHeaderIfMissing($response, 'Cross-Origin-Opener-Policy', 'same-origin');
        $this->setHeaderIfMissing($response, 'Cross-Origin-Resource-Policy', 'same-origin');
        $csp = $this->resolveContentSecurityPolicy();
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

    private function resolveContentSecurityPolicy(): string
    {
        $policy = (string) config('security.headers.csp');
        if ($policy === '') {
            return $policy;
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
