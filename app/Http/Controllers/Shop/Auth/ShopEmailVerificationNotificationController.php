<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        $this->logVerificationDispatch(
            'shop_verification.dispatch_requested',
            $contractor,
            $customer,
            ['origin' => 'shop_verification_resend']
        );

        try {
            $customer->sendEmailVerificationNotification();

            $this->logVerificationDispatch(
                'shop_verification.dispatch_enqueued',
                $contractor,
                $customer,
                ['origin' => 'shop_verification_resend']
            );
        } catch (Throwable $exception) {
            $this->logVerificationDispatch(
                'shop_verification.dispatch_failed',
                $contractor,
                $customer,
                [
                    'origin' => 'shop_verification_resend',
                    'error_class' => $exception::class,
                    'error_message' => substr($exception->getMessage(), 0, 200),
                ]
            );

            throw $exception;
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

    /**
     * @param array<string, mixed> $extra
     */
    private function logVerificationDispatch(string $event, Contractor $contractor, ShopCustomer $customer, array $extra = []): void
    {
        if (! (bool) config('logging.shop_verification_debug', false)) {
            return;
        }

        $channel = (string) config('logging.shop_verification_channel', config('logging.default', 'stack'));
        $email = strtolower(trim((string) ($customer->email ?? '')));

        Log::channel($channel)->info($event, array_merge([
            'contractor_id' => (int) $contractor->id,
            'shop_customer_id' => (int) $customer->id,
            'shop_customer_email_hash' => $email !== '' ? hash('sha256', $email) : null,
            'mail_queue_connection' => (string) config('queue.workloads.mail.connection', config('queue.default')),
            'mail_queue_name' => (string) config('queue.workloads.mail.queue', 'emails'),
            'mail_mailer' => (string) config('mail.default'),
            'mail_host' => (string) config('mail.mailers.smtp.host', ''),
            'mail_port' => (int) config('mail.mailers.smtp.port', 0),
            'mail_scheme' => (string) config('mail.mailers.smtp.scheme', ''),
        ], $extra));
    }
}
