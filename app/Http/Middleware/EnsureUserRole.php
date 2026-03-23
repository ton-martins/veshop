<?php

namespace App\Http\Middleware;

use App\Models\SecurityAuditLog;
use App\Services\SecurityAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    public function __construct(
        private readonly SecurityAuditLogger $securityAuditLogger,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $normalizedExpectedRoles = collect($roles)
            ->map(static fn (string $role): string => strtolower(trim($role)))
            ->filter()
            ->values()
            ->all();

        if (! $user || $normalizedExpectedRoles === []) {
            $this->securityAuditLogger->log(
                $request,
                'auth.role_denied',
                SecurityAuditLog::SEVERITY_WARNING,
                (int) $request->session()->get('current_contractor_id', 0),
                [
                    'reason' => 'missing_user_or_role_constraint',
                    'expected_roles' => $normalizedExpectedRoles,
                ],
            );
            abort(403);
        }

        $actualRole = strtolower(trim((string) $user->role));

        if (! in_array($actualRole, $normalizedExpectedRoles, true)) {
            $this->securityAuditLogger->log(
                $request,
                'auth.role_denied',
                SecurityAuditLog::SEVERITY_WARNING,
                (int) $request->session()->get('current_contractor_id', 0),
                [
                    'reason' => 'role_mismatch',
                    'expected_roles' => $normalizedExpectedRoles,
                    'actual_role' => (string) $user->role,
                ],
            );
            abort(403);
        }

        return $next($request);
    }
}
