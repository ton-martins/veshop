<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShopVerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $slug, int $id, string $hash): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        $customer = ShopCustomer::query()
            ->where('contractor_id', $contractor->id)
            ->findOrFail($id);

        abort_unless($customer->is_active, 403);
        abort_unless(hash_equals((string) $hash, sha1($customer->getEmailForVerification())), 403);

        if (! $customer->hasVerifiedEmail()) {
            if ($customer->markEmailAsVerified()) {
                event(new Verified($customer));
            }
        }

        Auth::guard('shop')->login($customer, true);
        $request->session()->regenerate();

        return redirect()
            ->route('shop.account', ['slug' => $contractor->slug])
            ->with('status', 'E-mail verificado com sucesso. Agora você pode finalizar pedidos.');
    }

    private function resolveActiveContractorBySlug(string $slug): Contractor
    {
        return Contractor::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }
}
