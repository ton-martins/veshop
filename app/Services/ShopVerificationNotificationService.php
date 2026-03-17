<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\ShopCustomer;
use App\Notifications\Shop\VerifyShopCustomerEmailNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShopVerificationNotificationService
{
    public const RESULT_QUEUED = 'queued';

    public const RESULT_SENT_SYNC = 'sent_sync';

    public const RESULT_FAILED = 'failed';

    /**
     * @return self::RESULT_*
     */
    public function dispatch(Contractor $contractor, ShopCustomer $customer, string $origin): string
    {
        $this->logDebug('shop_verification.dispatch_requested', $contractor, $customer, [
            'origin' => $origin,
        ]);

        try {
            $customer->sendEmailVerificationNotification();

            $this->logDebug('shop_verification.dispatch_enqueued', $contractor, $customer, [
                'origin' => $origin,
            ]);

            return self::RESULT_QUEUED;
        } catch (Throwable $queueException) {
            $this->logFailure('shop_verification.dispatch_failed_queue', $contractor, $customer, $origin, $queueException);
        }

        try {
            // Fallback síncrono para evitar erro 500 quando a fila está indisponível.
            $customer->notifyNow(new VerifyShopCustomerEmailNotification());

            $this->logDebug('shop_verification.dispatch_sent_sync', $contractor, $customer, [
                'origin' => $origin,
            ]);

            return self::RESULT_SENT_SYNC;
        } catch (Throwable $syncException) {
            $this->logFailure('shop_verification.dispatch_failed_sync', $contractor, $customer, $origin, $syncException);
        }

        return self::RESULT_FAILED;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function logDebug(string $event, Contractor $contractor, ShopCustomer $customer, array $extra = []): void
    {
        if (! (bool) config('logging.shop_verification_debug', false)) {
            return;
        }

        $channel = (string) config('logging.shop_verification_channel', config('logging.default', 'stack'));

        Log::channel($channel)->info(
            $event,
            array_merge($this->context($contractor, $customer), $extra)
        );
    }

    private function logFailure(
        string $event,
        Contractor $contractor,
        ShopCustomer $customer,
        string $origin,
        Throwable $exception
    ): void {
        $channel = (string) config('logging.shop_verification_channel', config('logging.default', 'stack'));

        Log::channel($channel)->error(
            $event,
            array_merge($this->context($contractor, $customer), [
                'origin' => $origin,
                'error_class' => $exception::class,
                'error_message' => substr($exception->getMessage(), 0, 200),
            ])
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Contractor $contractor, ShopCustomer $customer): array
    {
        $email = strtolower(trim((string) ($customer->email ?? '')));

        return [
            'contractor_id' => (int) $contractor->id,
            'shop_customer_id' => (int) $customer->id,
            'shop_customer_email_hash' => $email !== '' ? hash('sha256', $email) : null,
            'mail_queue_connection' => (string) config('queue.workloads.mail.connection', config('queue.default')),
            'mail_queue_name' => (string) config('queue.workloads.mail.queue', 'emails'),
            'mail_mailer' => (string) config('mail.default'),
            'mail_host' => (string) config('mail.mailers.smtp.host', ''),
            'mail_port' => (int) config('mail.mailers.smtp.port', 0),
            'mail_scheme' => (string) config('mail.mailers.smtp.scheme', ''),
        ];
    }
}
