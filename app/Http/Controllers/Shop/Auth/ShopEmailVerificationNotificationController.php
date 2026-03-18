<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use App\Services\ShopVerificationNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopEmailVerificationNotificationController extends Controller
{
    public function __construct(
        private readonly ShopVerificationNotificationService $verificationNotificationService
    ) {
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        /** @var ShopCustomer|null $customer */
        $customer = $request->user('shop');

        abort_unless($customer, 403);
        abort_unless((int) $customer->contractor_id === (int) $contractor->id, 403);

        if (! $contractor->requiresEmailVerification() || $customer->hasVerifiedEmail()) {
            return redirect()->route('shop.show', [
                'slug' => $contractor->slug,
                'conta' => 1,
            ]);
        }

        $dispatchResult = $this->verificationNotificationService->dispatch(
            $contractor,
            $customer,
            'shop_verification_resend'
        );

        if ($dispatchResult === ShopVerificationNotificationService::RESULT_FAILED) {
            return back()->with('status', 'Nao foi possivel enviar o e-mail agora. Tente novamente em instantes.');
        }

        return back()->with('status', 'verification-link-sent');
    }

    private function resolveActiveContractorBySlug(string $slug): Contractor
    {
        return Contractor::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

}
