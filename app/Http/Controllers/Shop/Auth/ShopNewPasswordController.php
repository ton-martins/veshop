<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShopNewPasswordController extends Controller
{
    public function create(Request $request, string $slug, string $token): Response
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        return Inertia::render('Public/ShopAuthResetPassword', [
            'contractor' => $this->toContractorPayload($contractor),
            'email' => (string) $request->query('email', ''),
            'token' => $token,
            'status' => session('status'),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'password_confirmation' => ['required', 'string'],
        ]);

        $email = strtolower(trim((string) $validated['email']));

        $status = Password::broker('shop_customers')->reset(
            [
                'token' => (string) $validated['token'],
                'email' => $email,
                'password' => (string) $validated['password'],
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'contractor_id' => $contractor->id,
            ],
            function (ShopCustomer $customer) use ($validated): void {
                $customer->forceFill([
                    'password' => Hash::make((string) $validated['password']),
                    'remember_token' => Str::random(60),
                    'is_active' => true,
                ])->save();

                event(new PasswordReset($customer));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('shop.auth.login', ['slug' => $contractor->slug])
                ->with('status', 'Senha redefinida com sucesso. Faça login para continuar.');
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
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
            'logo_url' => $this->normalizePublicAssetUrl($contractor->brand_logo_url),
            'avatar_url' => $this->normalizePublicAssetUrl($contractor->brand_avatar_url),
        ];
    }

    private function normalizePublicAssetUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $normalized = is_string($path) && $path !== '' ? $path : $value;

        if (str_starts_with($normalized, '/storage/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/')) {
            return '/'.ltrim($normalized, '/');
        }

        return $value;
    }
}
