<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopEmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request, string $slug): Response|RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        /** @var ShopCustomer|null $customer */
        $customer = $request->user('shop');

        abort_unless($customer, 403);
        abort_unless((int) $customer->contractor_id === (int) $contractor->id, 403);

        if (! $contractor->requiresEmailVerification() || $customer->hasVerifiedEmail()) {
            return redirect()->route('shop.account', ['slug' => $contractor->slug]);
        }

        return Inertia::render('Public/ShopVerifyEmail', [
            'contractor' => $this->toContractorPayload($contractor),
            'customer' => [
                'name' => (string) $customer->name,
                'email' => (string) $customer->email,
            ],
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
            'logo_url' => $contractor->brand_logo_url,
            'avatar_url' => $contractor->brand_avatar_url,
        ];
    }
}
