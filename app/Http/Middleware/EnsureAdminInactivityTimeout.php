<?php

namespace App\Http\Middleware;

use App\Models\Contractor;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminInactivityTimeout
{
    private const SETTING_KEY = 'admin_inactivity_timeout';

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->isAdmin()) {
            return $next($request);
        }

        $contractor = $this->resolveCurrentContractor($request, $user);
        if (! $contractor) {
            return $next($request);
        }

        $timeout = $this->resolveTimeoutValue($contractor);
        $sessionKey = sprintf('admin_last_activity_at_%d', (int) $contractor->id);

        if ($timeout !== 'keep_active') {
            $timeoutMinutes = (int) $timeout;
            $lastActivityAt = (int) $request->session()->get($sessionKey, 0);
            $nowTimestamp = now()->getTimestamp();

            if ($lastActivityAt > 0 && ($nowTimestamp - $lastActivityAt) > ($timeoutMinutes * 60)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->with('status', 'Sua sessão expirou por inatividade. Faça login novamente.');
            }
        }

        $request->session()->put($sessionKey, now()->getTimestamp());

        return $next($request);
    }

    private function resolveCurrentContractor(Request $request, User $user): ?Contractor
    {
        $user->loadMissing('contractors');
        $availableContractors = $user->contractors->values();

        if ($availableContractors->isEmpty()) {
            return null;
        }

        $sessionContractorId = (int) $request->session()->get('current_contractor_id', 0);
        if ($sessionContractorId > 0) {
            $selected = $availableContractors->firstWhere('id', $sessionContractorId);
            if ($selected instanceof Contractor) {
                return $selected;
            }
        }

        $fallback = $availableContractors->first();
        if ($fallback instanceof Contractor) {
            $request->session()->put('current_contractor_id', $fallback->id);
        }

        return $fallback instanceof Contractor ? $fallback : null;
    }

    private function resolveTimeoutValue(Contractor $contractor): string
    {
        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $value = trim((string) ($settings[self::SETTING_KEY] ?? '60'));

        return in_array($value, ['15', '30', '60', 'keep_active'], true)
            ? $value
            : '60';
    }
}
