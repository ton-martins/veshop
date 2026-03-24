<?php

namespace App\Notifications\Shop;

use App\Models\ShopCustomer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class ResetShopCustomerPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly ?string $contractorSlug = null,
    ) {
        $this->connection = (string) config('queue.workloads.mail.connection', config('queue.default'));
        $this->queue = (string) config('queue.workloads.mail.queue', 'default');
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
        $resetUrl = $this->resetUrl($notifiable);

        return (new MailMessage())
            ->subject('Redefinição de senha da sua conta')
            ->greeting('Olá!')
            ->line('Recebemos uma solicitação para redefinir a senha da sua conta na loja.')
            ->action('Redefinir senha', $resetUrl)
            ->line('Se você não solicitou a alteração, ignore este e-mail.')
            ->salutation("Atenciosamente,\nVeshop");
    }

    private function resetUrl(object $notifiable): string
    {
        if (! $notifiable instanceof ShopCustomer) {
            return url('/');
        }

        $slug = trim((string) ($this->contractorSlug ?? ''));
        if ($slug === '') {
            $notifiable->loadMissing('contractor:id,slug');
            $slug = (string) ($notifiable->contractor?->slug ?? '');
        }

        if ($slug === '') {
            return url('/');
        }

        return URL::route('shop.password.reset', [
            'slug' => $slug,
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
