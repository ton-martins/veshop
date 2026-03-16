<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShopAuthenticatedSessionController extends Controller
{
    public function create(string $slug): Response|RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        /** @var ShopCustomer|null $shopCustomer */
        $shopCustomer = Auth::guard('shop')->user();

        if ($shopCustomer && (int) $shopCustomer->contractor_id === (int) $contractor->id) {
            if ($contractor->requiresEmailVerification() && ! $shopCustomer->hasVerifiedEmail()) {
                return redirect()->route('shop.verification.notice', ['slug' => $contractor->slug]);
            }

            return redirect()->route('shop.account', ['slug' => $contractor->slug]);
        }

        if ($shopCustomer) {
            Auth::guard('shop')->logout();
        }

        return Inertia::render('Public/ShopAuthLogin', [
            'contractor' => $this->toContractorPayload($contractor),
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $credentials = [
            'email' => strtolower(trim((string) $validated['email'])),
            'password' => (string) $validated['password'],
            'contractor_id' => $contractor->id,
            'is_active' => true,
        ];

        if (! Auth::guard('shop')->attempt($credentials, (bool) ($validated['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha inválidos para esta loja.',
            ]);
        }

        $request->session()->regenerate();

        /** @var ShopCustomer|null $customer */
        $customer = Auth::guard('shop')->user();
        if ($customer) {
            $customer->forceFill([
                'last_login_at' => now(),
            ])->save();
        }

        if ($customer && $contractor->requiresEmailVerification() && ! $customer->hasVerifiedEmail()) {
            return redirect()
                ->route('shop.verification.notice', ['slug' => $contractor->slug])
                ->with('status', 'Confirme seu e-mail para continuar.');
        }

        return redirect()->intended(route('shop.account', ['slug' => $contractor->slug]));
    }

    public function destroy(Request $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        Auth::guard('shop')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('shop.show', ['slug' => $contractor->slug]);
    }

    private function resolveActiveContractorBySlug(string $slug): Contractor
    {
        return Contractor::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function toContractorPayload(Contractor $contractor): array
    {
        return [
            'id' => $contractor->id,
            'slug' => $contractor->slug,
            'name' => $contractor->name,
            'brand_name' => $contractor->brand_name,
            'primary_color' => $contractor->brand_primary_color,
            'logo_url' => $contractor->brand_logo_url,
            'avatar_url' => $contractor->brand_avatar_url,
        ];
    }
}
