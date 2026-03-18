<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        $this->logVerificationEvent('shop_verification.marked_as_verified', $contractor, $customer);

        return redirect()
            ->route('shop.show', ['slug' => $contractor->slug, 'conta' => 1])
            ->with('status', 'E-mail verificado com sucesso. Agora você pode finalizar pedidos.');
    }

    private function resolveActiveContractorBySlug(string $slug): Contractor
    {
        return Contractor::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function logVerificationEvent(string $event, Contractor $contractor, ShopCustomer $customer): void
    {
        if (! (bool) config('logging.shop_verification_debug', false)) {
            return;
        }

        $channel = (string) config('logging.shop_verification_channel', config('logging.default', 'stack'));
        $email = strtolower(trim((string) ($customer->email ?? '')));

        Log::channel($channel)->info($event, [
            'contractor_id' => (int) $contractor->id,
            'shop_customer_id' => (int) $customer->id,
            'shop_customer_email_hash' => $email !== '' ? hash('sha256', $email) : null,
            'verified_at' => optional($customer->email_verified_at)->toIso8601String() ?? now()->toIso8601String(),
        ]);
    }
}
