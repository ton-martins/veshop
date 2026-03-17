<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

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

    public function confirm(Request $request): RedirectResponse|HttpResponse
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

        $targetUrl = (string) $request->session()->pull('url.intended', route('home', absolute: false));

        return $this->redirectForInertia($request, $targetUrl);
    }

    public function regenerate(Request $request): RedirectResponse|HttpResponse
    {
        $user = $request->user();

        $plainSecret = $this->twoFactorService->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $this->twoFactorService->encryptSecret($plainSecret),
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('two_factor_passed');

        return $this->redirectForInertia(
            $request,
            route('two-factor.setup'),
            'Nova chave gerada. Confirme o código do app autenticador para concluir.'
        );
    }

    public function destroy(Request $request): RedirectResponse|HttpResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $request->session()->forget('two_factor_passed');

        return $this->redirectForInertia(
            $request,
            route('two-factor.setup'),
            'Autenticação em dois fatores desativada.'
        );
    }

    private function redirectForInertia(
        Request $request,
        string $targetUrl,
        ?string $statusMessage = null
    ): RedirectResponse|HttpResponse {
        if ($request->headers->has('X-Inertia')) {
            if ($statusMessage !== null) {
                $request->session()->flash('status', $statusMessage);
            }

            return Inertia::location($targetUrl);
        }

        $redirect = redirect()->to($targetUrl);

        if ($statusMessage !== null) {
            $redirect->with('status', $statusMessage);
        }

        return $redirect;
    }
}
