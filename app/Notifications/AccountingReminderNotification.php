<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(private readonly array $payload)
    {
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

        $email = method_exists($notifiable, 'routeNotificationFor')
            ? (string) ($notifiable->routeNotificationFor('mail') ?? '')
            : '';

        if (trim($email) !== '') {
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
            'title' => (string) ($this->payload['title'] ?? 'Lembrete contábil'),
            'message' => (string) ($this->payload['message'] ?? ''),
            'target_url' => (string) ($this->payload['target_url'] ?? '/app/services/accounting'),
            'contractor_id' => (int) ($this->payload['contractor_id'] ?? 0),
            'context_type' => (string) ($this->payload['context_type'] ?? ''),
            'context_id' => (int) ($this->payload['context_id'] ?? 0),
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $title = (string) ($this->payload['title'] ?? 'Lembrete contábil');
        $message = (string) ($this->payload['message'] ?? '');
        $targetUrl = (string) ($this->payload['target_url'] ?? '/app/services/accounting');

        return (new MailMessage)
            ->subject($title)
            ->greeting('Olá!')
            ->line($message)
            ->action('Abrir gestão contábil', url($targetUrl))
            ->line('Mensagem automática do Veshop.');
    }
}
