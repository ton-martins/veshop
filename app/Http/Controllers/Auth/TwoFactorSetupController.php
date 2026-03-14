<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorSetupController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactorService)
    {
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled() && ! $request->session()->get('two_factor_passed', false)) {
            return redirect()->route('two-factor.challenge');
        }

        $plainSecret = $this->twoFactorService->decryptSecret($user->two_factor_secret);

        if (! $plainSecret) {
            $plainSecret = $this->twoFactorService->generateSecret();

            $user->forceFill([
                'two_factor_secret' => $this->twoFactorService->encryptSecret($plainSecret),
                'two_factor_confirmed_at' => null,
            ])->save();
        }

        return Inertia::render('Auth/TwoFactorSetup', [
            'isEnabled' => $user->hasTwoFactorEnabled(),
            'secret' => $this->twoFactorService->maskSecret($plainSecret),
            'otpauthUrl' => $this->twoFactorService->getProvisioningUri($user, $plainSecret),
            'status' => session('status'),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();
        $plainSecret = $this->twoFactorService->decryptSecret($user->two_factor_secret);

        if (! $plainSecret || ! $this->twoFactorService->verifyCode($plainSecret, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'Código inválido. Tente novamente com o código atual do app autenticador.',
            ]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->put('two_factor_passed', true);

        return redirect()->intended(route('home', absolute: false));
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $user = $request->user();

        $plainSecret = $this->twoFactorService->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $this->twoFactorService->encryptSecret($plainSecret),
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('two_factor_passed');

        return redirect()
            ->route('two-factor.setup')
            ->with('status', 'Nova chave gerada. Confirme o código do app autenticador para concluir.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('two_factor_passed');

        return redirect()
            ->route('two-factor.setup')
            ->with('status', 'Autenticação em dois fatores desativada.');
    }
}
