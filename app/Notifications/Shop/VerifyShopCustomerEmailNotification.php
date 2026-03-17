<?php

namespace App\Notifications\Shop;

use App\Models\ShopCustomer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class VerifyShopCustomerEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->connection = (string) config('queue.workloads.mail.connection', config('queue.default'));
        $this->queue = (string) config('queue.workloads.mail.queue', 'emails');
        $this->afterCommit = true;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->logMailCompose($notifiable);

        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage())
            ->subject('Confirme seu e-mail para acessar sua conta')
            ->greeting('Olá!')
            ->line('Para continuar seu cadastro e finalizar pedidos, confirme seu endereço de e-mail.')
            ->action('Confirmar e-mail', $verificationUrl)
            ->line('Se você não criou esta conta, ignore esta mensagem.')
            ->salutation("Atenciosamente,\nVeshop");
    }

    public function failed(Throwable $exception): void
    {
        if (! (bool) config('logging.shop_verification_debug', false)) {
            return;
        }

        $channel = (string) config('logging.shop_verification_channel', config('logging.default', 'stack'));

        Log::channel($channel)->error('shop_verification.notification_failed', [
            'error_class' => $exception::class,
            'error_message' => substr($exception->getMessage(), 0, 200),
            'mail_queue_connection' => (string) config('queue.workloads.mail.connection', config('queue.default')),
            'mail_queue_name' => (string) config('queue.workloads.mail.queue', 'emails'),
            'mail_mailer' => (string) config('mail.default'),
        ]);
    }

    private function verificationUrl(object $notifiable): string
    {
        if (! $notifiable instanceof ShopCustomer) {
            return url('/');
        }

        $notifiable->loadMissing('contractor');
        $slug = (string) ($notifiable->contractor?->slug ?? '');

        if ($slug === '') {
            return url('/');
        }

        return URL::temporarySignedRoute(
            'shop.verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'slug' => $slug,
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    private function logMailCompose(object $notifiable): void
    {
        if (! (bool) config('logging.shop_verification_debug', false)) {
            return;
        }

        if (! $notifiable instanceof ShopCustomer) {
            return;
        }

        $notifiable->loadMissing('contractor:id');
        $channel = (string) config('logging.shop_verification_channel', config('logging.default', 'stack'));
        $email = strtolower(trim((string) ($notifiable->email ?? '')));

        Log::channel($channel)->info('shop_verification.mail_composing', [
            'contractor_id' => (int) ($notifiable->contractor?->id ?? 0),
            'shop_customer_id' => (int) $notifiable->id,
            'shop_customer_email_hash' => $email !== '' ? hash('sha256', $email) : null,
            'mail_mailer' => (string) config('mail.default'),
            'mail_host' => (string) config('mail.mailers.smtp.host', ''),
            'mail_port' => (int) config('mail.mailers.smtp.port', 0),
            'mail_scheme' => (string) config('mail.mailers.smtp.scheme', ''),
        ]);
    }
}
