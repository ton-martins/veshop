<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopEmailVerificationNotificationController extends Controller
{
    public function store(Request $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        /** @var ShopCustomer|null $customer */
        $customer = $request->user('shop');

        abort_unless($customer, 403);
        abort_unless((int) $customer->contractor_id === (int) $contractor->id, 403);

        if (! $contractor->requiresEmailVerification() || $customer->hasVerifiedEmail()) {
            return redirect()->route('shop.account', ['slug' => $contractor->slug]);
        }

        $customer->sendEmailVerificationNotification();

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
