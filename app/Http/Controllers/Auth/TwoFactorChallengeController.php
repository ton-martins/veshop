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

class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactorService)
    {
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }

        if ($request->session()->get('two_factor_passed', false)) {
            return redirect()->intended(route('home', absolute: false));
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse|HttpResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return $this->redirectForInertia($request, route('two-factor.setup'));
        }

        $plainSecret = $this->twoFactorService->decryptSecret($user->two_factor_secret);

        if (! $plainSecret || ! $this->twoFactorService->verifyCode($plainSecret, $request->string('code')->toString())) {
            throw ValidationException::withMessages([
                'code' => 'Código inválido. Confira o app autenticador e tente novamente.',
            ]);
        }

        $request->session()->put('two_factor_passed', true);

        $targetUrl = (string) $request->session()->pull('url.intended', route('home', absolute: false));

        return $this->redirectForInertia($request, $targetUrl);
    }

    private function redirectForInertia(Request $request, string $targetUrl): RedirectResponse|HttpResponse
    {
        if ($request->headers->has('X-Inertia')) {
            return Inertia::location($targetUrl);
        }

        return redirect()->to($targetUrl);
    }
}
