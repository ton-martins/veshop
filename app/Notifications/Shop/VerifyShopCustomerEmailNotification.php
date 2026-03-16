<?php

namespace App\Notifications\Shop;

use App\Models\ShopCustomer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyShopCustomerEmailNotification extends Notification
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage())
            ->subject('Confirme seu e-mail para acessar sua conta')
            ->greeting('Olá!')
            ->line('Para continuar seu cadastro e finalizar pedidos, confirme seu endereço de e-mail.')
            ->action('Confirmar e-mail', $verificationUrl)
            ->line('Se você não criou esta conta, ignore esta mensagem.');
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
}
