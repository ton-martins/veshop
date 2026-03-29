<?php

namespace App\Application\Identity\Services;

use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Services\SecurityAuditLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminAccessAuditService
{
    private const ACTIVE_SESSION_KEY = 'active_admin_session_id';

    /**
     * @var list<string>
     */
    private const TRACKED_EVENTS = [
        'auth.login.success',
        'auth.sessions.terminated_by_new_login',
        'auth.session.kicked_by_new_login',
        'auth.logout.manual',
        'auth.logout.disconnect_all',
    ];

    public function __construct(
        private readonly SecurityAuditLogger $securityAuditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);

        $currentSessionId = (string) $request->session()->getId();

        $activeSessions = $this->supportsDatabaseSessions()
            ? $this->resolveActiveSessions((int) $user->id, $currentSessionId)
            : [];

        $accessLogs = SecurityAuditLog::query()
            ->where('user_id', (int) $user->id)
            ->whereIn('event', self::TRACKED_EVENTS)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (SecurityAuditLog $log): array {
                $context = is_array($log->context) ? $log->context : [];
                $ipAddress = trim((string) ($context['ip_address'] ?? ''));
                $userAgent = trim((string) ($context['user_agent'] ?? $log->user_agent ?? ''));

                return [
                    'id' => (int) $log->id,
                    'event' => (string) $log->event,
                    'event_label' => $this->eventLabel((string) $log->event),
                    'severity' => (string) $log->severity,
                    'ip_address' => $ipAddress !== '' ? $ipAddress : '-',
                    'device_label' => $this->deviceLabel($userAgent),
                    'user_agent' => Str::limit($userAgent !== '' ? $userAgent : '-', 220, '...'),
                    'occurred_at' => optional($log->occurred_at)?->format('d/m/Y H:i:s'),
                    'session_id' => trim((string) ($context['session_id'] ?? '')),
                    'terminated_sessions' => (int) ($context['terminated_sessions'] ?? 0),
                ];
            });

        return Inertia::render('Admin/Audit/Accesses', [
            'sessionDriver' => (string) config('session.driver', 'database'),
            'activeSessions' => $activeSessions,
            'accessLogs' => $accessLogs,
        ]);
    }

    public function registerSuccessfulLogin(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return;
        }

        $terminatedSessions = 0;
        $sessionId = (string) $request->session()->getId();

        if ($this->supportsDatabaseSessions()) {
            $terminatedSessions = DB::table('sessions')
                ->where('user_id', (int) $user->id)
                ->where('id', '!=', $sessionId)
                ->delete();
        }

        $this->setActiveSessionOnUserPreferences($user, $sessionId);

        $context = $this->requestContext($request, [
            'session_id' => $sessionId,
            'terminated_sessions' => $terminatedSessions,
        ]);

        $this->securityAuditLogger->log(
            $request,
            'auth.login.success',
            SecurityAuditLog::SEVERITY_INFO,
            $this->resolveContractorId($request),
            $context,
        );

        if ($terminatedSessions > 0) {
            $this->securityAuditLogger->log(
                $request,
                'auth.sessions.terminated_by_new_login',
                SecurityAuditLog::SEVERITY_INFO,
                $this->resolveContractorId($request),
                $context,
            );
        }
    }

    public function registerManualLogout(Request $request): void
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return;
        }

        $this->clearActiveSessionFromUserPreferences($user, (string) $request->session()->getId());

        $this->securityAuditLogger->log(
            $request,
            'auth.logout.manual',
            SecurityAuditLog::SEVERITY_INFO,
            $this->resolveContractorId($request),
            $this->requestContext($request, [
                'session_id' => (string) $request->session()->getId(),
            ]),
        );
    }

    public function disconnectAll(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);

        $disconnectedSessions = 0;
        if ($this->supportsDatabaseSessions()) {
            $disconnectedSessions = DB::table('sessions')
                ->where('user_id', (int) $user->id)
                ->delete();
        }

        $this->clearActiveSessionFromUserPreferences($user);

        $this->securityAuditLogger->log(
            $request,
            'auth.logout.disconnect_all',
            SecurityAuditLog::SEVERITY_WARNING,
            $this->resolveContractorId($request),
            $this->requestContext($request, [
                'session_id' => (string) $request->session()->getId(),
                'terminated_sessions' => $disconnectedSessions,
            ]),
        );

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Todos os dispositivos foram desconectados com sucesso.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveActiveSessions(int $userId, string $currentSessionId): array
    {
        return DB::table('sessions')
            ->where('user_id', $userId)
            ->orderByDesc('last_activity')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(function (object $session) use ($currentSessionId): array {
                $userAgent = trim((string) ($session->user_agent ?? ''));

                return [
                    'session_id' => (string) $session->id,
                    'is_current' => (string) $session->id === $currentSessionId,
                    'ip_address' => trim((string) ($session->ip_address ?? '')) ?: '-',
                    'device_label' => $this->deviceLabel($userAgent),
                    'user_agent' => Str::limit($userAgent !== '' ? $userAgent : '-', 220, '...'),
                    'last_activity' => $this->formatLastActivity((int) ($session->last_activity ?? 0)),
                ];
            })
            ->values()
            ->all();
    }

    private function supportsDatabaseSessions(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable('sessions');
    }

    private function setActiveSessionOnUserPreferences(User $user, string $sessionId): void
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return;
        }

        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $preferences[self::ACTIVE_SESSION_KEY] = $sessionId;

        $user->forceFill(['preferences' => $preferences])->save();
    }

    private function clearActiveSessionFromUserPreferences(User $user, ?string $onlyIfMatchesSessionId = null): void
    {
        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $activeSessionId = trim((string) ($preferences[self::ACTIVE_SESSION_KEY] ?? ''));
        $matchSessionId = trim((string) ($onlyIfMatchesSessionId ?? ''));

        if ($matchSessionId !== '' && $activeSessionId !== '' && ! hash_equals($activeSessionId, $matchSessionId)) {
            return;
        }

        if (! array_key_exists(self::ACTIVE_SESSION_KEY, $preferences)) {
            return;
        }

        unset($preferences[self::ACTIVE_SESSION_KEY]);
        $user->forceFill(['preferences' => $preferences])->save();
    }

    private function resolveContractorId(Request $request): ?int
    {
        $contractorId = (int) $request->session()->get('current_contractor_id', 0);

        return $contractorId > 0 ? $contractorId : null;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function requestContext(Request $request, array $extra = []): array
    {
        return array_merge([
            'ip_address' => trim((string) ($request->ip() ?? '')),
            'user_agent' => trim((string) ($request->userAgent() ?? '')),
            'device_label' => $this->deviceLabel((string) ($request->userAgent() ?? '')),
        ], $extra);
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'auth.login.success' => 'Login realizado',
            'auth.sessions.terminated_by_new_login' => 'Dispositivo anterior encerrado',
            'auth.session.kicked_by_new_login' => 'Sessão encerrada por novo acesso',
            'auth.logout.manual' => 'Logout manual',
            'auth.logout.disconnect_all' => 'Desconexão de todos os dispositivos',
            default => $event,
        };
    }

    private function formatLastActivity(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '-';
        }

        return Carbon::createFromTimestamp($timestamp)
            ->setTimezone((string) config('app.timezone', 'UTC'))
            ->format('d/m/Y H:i:s');
    }

    private function deviceLabel(string $userAgent): string
    {
        $agent = strtolower(trim($userAgent));
        if ($agent === '') {
            return 'Dispositivo não identificado';
        }

        $platform = 'Sistema não identificado';
        if (str_contains($agent, 'windows')) {
            $platform = 'Windows';
        } elseif (str_contains($agent, 'android')) {
            $platform = 'Android';
        } elseif (str_contains($agent, 'iphone') || str_contains($agent, 'ipad') || str_contains($agent, 'ios')) {
            $platform = 'iOS';
        } elseif (str_contains($agent, 'mac os') || str_contains($agent, 'macintosh')) {
            $platform = 'macOS';
        } elseif (str_contains($agent, 'linux')) {
            $platform = 'Linux';
        }

        $browser = 'Navegador não identificado';
        if (str_contains($agent, 'edg/')) {
            $browser = 'Microsoft Edge';
        } elseif (str_contains($agent, 'opr/') || str_contains($agent, 'opera')) {
            $browser = 'Opera';
        } elseif (str_contains($agent, 'chrome/')) {
            $browser = 'Google Chrome';
        } elseif (str_contains($agent, 'firefox/')) {
            $browser = 'Mozilla Firefox';
        } elseif (str_contains($agent, 'safari/')) {
            $browser = 'Safari';
        }

        $deviceType = str_contains($agent, 'mobile') || str_contains($agent, 'android') || str_contains($agent, 'iphone')
            ? 'Mobile'
            : 'Desktop';

        return sprintf('%s em %s (%s)', $browser, $platform, $deviceType);
    }
}
