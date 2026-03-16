<?php

namespace App\Notifications;

use App\Models\Sale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Sale $sale,
        private readonly string $title,
        private readonly string $message,
        private readonly string $targetUrl,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'order_id' => (int) $this->sale->id,
            'order_code' => (string) $this->sale->code,
            'order_status' => (string) $this->sale->status,
            'target_url' => $this->targetUrl,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
