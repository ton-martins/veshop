<?php

namespace App\Http\Middleware;

use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\SecurityAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleAdminSession
{
    private const ACTIVE_SESSION_KEY = 'active_admin_session_id';

    public function __construct(
        private readonly SecurityAuditLogger $securityAuditLogger,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return $next($request);
        }

        $currentSessionId = (string) $request->session()->getId();
        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $activeSessionId = trim((string) ($preferences[self::ACTIVE_SESSION_KEY] ?? ''));

        if ($activeSessionId === '') {
            $preferences[self::ACTIVE_SESSION_KEY] = $currentSessionId;
            $user->forceFill(['preferences' => $preferences])->save();

            return $next($request);
        }

        if (hash_equals($activeSessionId, $currentSessionId)) {
            return $next($request);
        }

        $this->securityAuditLogger->log(
            $request,
            'auth.session.kicked_by_new_login',
            SecurityAuditLog::SEVERITY_WARNING,
            $this->resolveContractorId($request),
            [
                'current_session_id' => $currentSessionId,
                'active_session_id' => $activeSessionId,
                'ip_address' => (string) ($request->ip() ?? ''),
                'user_agent' => (string) ($request->userAgent() ?? ''),
            ],
        );

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Sua sessão anterior foi encerrada porque houve um novo acesso em outro dispositivo.');
    }

    private function resolveContractorId(Request $request): ?int
    {
        $contractorId = (int) $request->session()->get('current_contractor_id', 0);

        return $contractorId > 0 ? $contractorId : null;
    }
}

