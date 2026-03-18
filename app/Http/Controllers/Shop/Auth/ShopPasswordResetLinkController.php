<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use App\Notifications\Shop\ResetShopCustomerPasswordNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShopPasswordResetLinkController extends Controller
{
    public function create(string $slug): Response
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        return Inertia::render('Public/ShopAuthForgotPassword', [
            'contractor' => $this->toContractorPayload($contractor),
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
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = strtolower(trim((string) $validated['email']));

        $status = Password::broker('shop_customers')->sendResetLink(
            [
                'email' => $email,
                'contractor_id' => $contractor->id,
            ],
            function (ShopCustomer $customer, string $token) use ($contractor): void {
                $customer->notify(new ResetShopCustomerPasswordNotification($token, $contractor->slug));
            }
        );

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'Aguarde alguns instantes antes de solicitar um novo link.',
            ]);
        }

        return back()->with('status', 'Se o e-mail existir nesta loja, enviaremos um link de recuperação.');
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
