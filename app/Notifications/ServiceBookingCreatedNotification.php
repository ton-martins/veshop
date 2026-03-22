<?php

namespace App\Notifications;

use App\Models\ServiceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ServiceBookingCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ServiceOrder $serviceOrder,
        private readonly string $title,
        private readonly string $message,
        private readonly string $targetUrl,
        private readonly ?string $whatsappUrl = null,
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
            'contractor_id' => (int) ($this->serviceOrder->contractor_id ?? 0),
            'service_order_id' => (int) $this->serviceOrder->id,
            'service_order_code' => (string) $this->serviceOrder->code,
            'service_order_status' => (string) $this->serviceOrder->status,
            'target_url' => $this->targetUrl,
            'whatsapp_url' => $this->whatsappUrl,
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $orderCode = (string) $this->serviceOrder->code;
        $serviceName = trim((string) ($this->serviceOrder->service?->name ?? $this->serviceOrder->title));
        $clientName = trim((string) ($this->serviceOrder->client?->name ?? data_get($this->serviceOrder->metadata, 'customer_name', '')));
        $scheduledFor = $this->serviceOrder->scheduled_for?->format('d/m/Y H:i');

        $mail = (new MailMessage)
            ->subject("{$this->title} - {$orderCode}")
            ->greeting('Olá!')
            ->line($this->message)
            ->line("Código do agendamento: {$orderCode}");

        if ($serviceName !== '') {
            $mail->line("Serviço: {$serviceName}");
        }

        if ($clientName !== '') {
            $mail->line("Cliente: {$clientName}");
        }

        if ($scheduledFor !== null) {
            $mail->line("Data e hora: {$scheduledFor}");
        }

        $mail->action('Acessar agendamentos', url($this->targetUrl))
            ->line('Esta é uma notificação automática do Veshop.');

        if ($this->whatsappUrl) {
            $mail->line('Mensagem para WhatsApp:')
                ->action('Abrir WhatsApp', $this->whatsappUrl);
        }

        return $mail;
    }

    private function shouldSendMail(mixed $notifiable): bool
    {
        $email = method_exists($notifiable, 'routeNotificationFor')
            ? (string) ($notifiable->routeNotificationFor('mail') ?? '')
            : '';

        if (trim($email) === '') {
            return false;
        }

        $settings = is_array($this->serviceOrder->contractor?->settings)
            ? $this->serviceOrder->contractor->settings
            : [];

        return (bool) ($settings['email_notifications_enabled'] ?? true);
    }
}

