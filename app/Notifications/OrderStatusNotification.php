<?php

namespace App\Notifications;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Sale $sale,
        private readonly string $title,
        private readonly string $message,
        private readonly string $targetUrl,
    ) {
        $this->connection = (string) config('queue.workloads.notifications.connection', config('queue.default'));
        $this->queue = (string) config('queue.workloads.notifications.queue', 'default');
        $this->afterCommit = true;
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['database'];

        if ($this->shouldSendMail($notifiable)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'contractor_id' => (int) ($this->sale->contractor_id ?? 0),
            'order_id' => (int) $this->sale->id,
            'order_code' => (string) $this->sale->code,
            'order_status' => (string) $this->sale->status,
            'target_url' => $this->targetUrl,
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $orderCode = (string) $this->sale->code;
        $orderStatus = (string) $this->sale->status;

        return (new MailMessage)
            ->subject("{$this->title} - {$orderCode}")
            ->greeting('Olá!')
            ->line($this->message)
            ->line("Pedido: {$orderCode}")
            ->line("Status atual: {$orderStatus}")
            ->action('Acessar pedido', url($this->targetUrl))
            ->line('Esta é uma notificação automática do Veshop.');
    }

    private function shouldSendMail(mixed $notifiable): bool
    {
        $email = method_exists($notifiable, 'routeNotificationFor')
            ? (string) ($notifiable->routeNotificationFor('mail') ?? '')
            : '';

        if (trim($email) === '') {
            return false;
        }

        $settings = is_array($this->sale->contractor?->settings)
            ? $this->sale->contractor->settings
            : [];

        return (bool) ($settings['email_notifications_enabled'] ?? true);
    }
}
