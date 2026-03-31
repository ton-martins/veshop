<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactorService) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        if ($request->session()->get('two_factor_passed', false)) {
            return $this->redirectAfterTwoFactor($request);
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        $plainSecret = $this->twoFactorService->decryptSecret($user->two_factor_secret);

        if (! $plainSecret || ! $this->twoFactorService->verifyCode($plainSecret, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'Código inválido. Confira o app autenticador e tente novamente.',
            ]);
        }

        $request->session()->put('two_factor_passed', true);

        return $this->redirectAfterTwoFactor($request);
    }

    private function redirectAfterTwoFactor(Request $request): RedirectResponse
    {
        $this->sanitizeIntendedUrlForRole($request, $request->user());

        return redirect()->intended(route('home', absolute: false));
    }

    private function sanitizeIntendedUrlForRole(Request $request, ?User $user): void
    {
        if (! $user) {
            return;
        }

        $intended = $request->session()->get('url.intended');
        if (! is_string($intended) || trim($intended) === '') {
            return;
        }

        $path = parse_url($intended, PHP_URL_PATH);
        $normalizedPath = is_string($path) && $path !== '' ? $path : $intended;
        $normalizedPath = '/'.ltrim($normalizedPath, '/');

        $isAdminAreaPath = $normalizedPath === '/app' || str_starts_with($normalizedPath, '/app/');
        $isMasterAreaPath = $normalizedPath === '/master' || str_starts_with($normalizedPath, '/master/');

        if (($user->isMaster() && $isAdminAreaPath) || ($user->isAdmin() && $isMasterAreaPath)) {
            $request->session()->forget('url.intended');
        }
    }
}
