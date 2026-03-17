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

        if (! $user || $roles === []) {
            $this->securityAuditLogger->log(
                $request,
                'auth.role_denied',
                SecurityAuditLog::SEVERITY_WARNING,
                (int) $request->session()->get('current_contractor_id', 0),
                [
                    'reason' => 'missing_user_or_role_constraint',
                    'expected_roles' => $roles,
                ],
            );
            abort(403);
        }

        if (! in_array($user->role, $roles, true)) {
            $this->securityAuditLogger->log(
                $request,
                'auth.role_denied',
                SecurityAuditLog::SEVERITY_WARNING,
                (int) $request->session()->get('current_contractor_id', 0),
                [
                    'reason' => 'role_mismatch',
                    'expected_roles' => $roles,
                    'actual_role' => (string) $user->role,
                ],
            );
            abort(403);
        }

        return $next($request);
    }
}
