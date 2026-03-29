<?php

namespace App\Http\Middleware;

use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\SecurityAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleAdminSession
{
    private const ACTIVE_SESSION_KEY = 'active_admin_session_id';
    private const ACTIVE_DEVICE_HASH_KEY = 'active_admin_device_hash';

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
        $activeDeviceHash = trim((string) ($preferences[self::ACTIVE_DEVICE_HASH_KEY] ?? ''));
        $currentDeviceHash = $this->resolveDeviceHash($request);

        if ($activeSessionId === '') {
            $this->syncActiveSessionOnPreferences($user, $preferences, $currentSessionId, $currentDeviceHash);

            return $next($request);
        }

        if (hash_equals($activeSessionId, $currentSessionId)) {
            if ($activeDeviceHash === '' && $currentDeviceHash !== '') {
                $this->syncActiveSessionOnPreferences($user, $preferences, $currentSessionId, $currentDeviceHash);
            }

            return $next($request);
        }

        if ($activeDeviceHash !== '' && $currentDeviceHash !== '' && hash_equals($activeDeviceHash, $currentDeviceHash)) {
            $this->syncActiveSessionOnPreferences($user, $preferences, $currentSessionId, $currentDeviceHash);

            return $next($request);
        }

        if ($this->isStaleActiveSession((int) $user->id, $activeSessionId)) {
            $this->syncActiveSessionOnPreferences($user, $preferences, $currentSessionId, $currentDeviceHash);

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

    /**
     * @param  array<string, mixed>  $preferences
     */
    private function syncActiveSessionOnPreferences(
        User $user,
        array $preferences,
        string $sessionId,
        string $deviceHash
    ): void {
        $preferences[self::ACTIVE_SESSION_KEY] = $sessionId;
        if ($deviceHash !== '') {
            $preferences[self::ACTIVE_DEVICE_HASH_KEY] = $deviceHash;
        }

        $user->forceFill(['preferences' => $preferences])->save();
    }

    private function resolveDeviceHash(Request $request): string
    {
        $userAgent = strtolower(trim((string) ($request->userAgent() ?? '')));
        if ($userAgent === '') {
            return '';
        }

        return hash('sha256', $userAgent);
    }

    private function isStaleActiveSession(int $userId, string $activeSessionId): bool
    {
        $activeSessionId = trim($activeSessionId);
        if ($userId <= 0 || $activeSessionId === '' || ! $this->supportsDatabaseSessions()) {
            return false;
        }

        return ! DB::table('sessions')
            ->where('id', $activeSessionId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function supportsDatabaseSessions(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable('sessions');
    }
}
