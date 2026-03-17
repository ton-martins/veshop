<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SecurityAuditLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(
        Request $request,
        string $event,
        string $severity = SecurityAuditLog::SEVERITY_WARNING,
        ?int $contractorId = null,
        array $context = [],
    ): void {
        $user = $request->user();

        try {
            SecurityAuditLog::query()->create([
                'contractor_id' => $contractorId ?: null,
                'user_id' => $user?->id,
                'actor_role' => $user?->role ? (string) $user->role : null,
                'event' => Str::limit(trim($event), 120, ''),
                'severity' => $this->normalizeSeverity($severity),
                'request_method' => Str::upper(Str::limit((string) $request->method(), 10, '')),
                'request_path' => Str::limit('/'.ltrim($request->path(), '/'), 255, ''),
                'route_name' => Str::limit((string) optional($request->route())->getName(), 120, ''),
                'ip_hash' => $this->hashIp($request->ip()),
                'user_agent' => Str::limit((string) ($request->userAgent() ?? ''), 512, ''),
                'context' => $this->sanitizeContext($context),
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('security_audit.persistence_failed', [
                'event' => Str::limit(trim($event), 120, ''),
                'error_class' => $exception::class,
                'error_message' => Str::limit($exception->getMessage(), 240),
            ]);
        }
    }

    private function normalizeSeverity(string $severity): string
    {
        $normalized = strtolower(trim($severity));

        if (in_array($normalized, [
            SecurityAuditLog::SEVERITY_INFO,
            SecurityAuditLog::SEVERITY_WARNING,
            SecurityAuditLog::SEVERITY_CRITICAL,
        ], true)) {
            return $normalized;
        }

        return SecurityAuditLog::SEVERITY_WARNING;
    }

    private function hashIp(?string $ip): ?string
    {
        $safeIp = trim((string) ($ip ?? ''));
        if ($safeIp === '') {
            return null;
        }

        $secret = (string) config('app.key', 'veshop-security-audit');

        return hash_hmac('sha256', $safeIp, $secret);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $safeKey = Str::limit((string) $key, 80, '');
            if ($safeKey === '') {
                continue;
            }

            if ($this->shouldRedact($safeKey)) {
                $sanitized[$safeKey] = '[redacted]';
                continue;
            }

            $sanitized[$safeKey] = $this->normalizeContextValue($value);
        }

        return Arr::sortRecursive($sanitized);
    }

    private function shouldRedact(string $key): bool
    {
        $normalized = strtolower($key);
        $sensitiveTokens = [
            'password',
            'token',
            'secret',
            'authorization',
            'cookie',
            'signature',
            'key',
        ];

        foreach ($sensitiveTokens as $token) {
            if (str_contains($normalized, $token)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeContextValue(mixed $value): mixed
    {
        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return Str::limit($value, 300);
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $safeKey = is_int($key) ? (string) $key : Str::limit((string) $key, 80, '');
                if ($safeKey === '') {
                    continue;
                }

                if ($this->shouldRedact($safeKey)) {
                    $normalized[$safeKey] = '[redacted]';
                    continue;
                }

                $normalized[$safeKey] = $this->normalizeContextValue($item);
            }

            return $normalized;
        }

        return Str::limit((string) get_debug_type($value), 120);
    }
}
