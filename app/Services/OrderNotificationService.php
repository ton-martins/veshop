<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\User;
use App\Notifications\OrderStatusNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class OrderNotificationService
{
    public function notifyOrderCreated(Sale $sale): void
    {
        $this->notifyByStatus($sale, 'Novo pedido recebido', "Pedido {$sale->code} aguardando confirmação.");
    }

    public function notifyOrderStatusChanged(Sale $sale): void
    {
        $label = $this->labelForStatus((string) $sale->status);
        $this->notifyByStatus($sale, 'Atualização de pedido', "Pedido {$sale->code} atualizado para {$label}.");
    }

    private function notifyByStatus(Sale $sale, string $title, string $message): void
    {
        $sale->loadMissing([
            'contractor:id,slug',
            'shopCustomer:id',
        ]);

        $slug = (string) ($sale->contractor?->slug ?? '');
        $adminUrl = '/app/orders';
        $customerUrl = $slug !== '' ? "/shop/{$slug}/conta" : '/';

        $adminRecipients = $this->resolveAdminRecipients($sale);
        if ($adminRecipients->isNotEmpty()) {
            Notification::send(
                $adminRecipients,
                new OrderStatusNotification($sale, $title, $message, $adminUrl),
            );
        }

        if ($sale->shopCustomer) {
            $sale->shopCustomer->notify(
                new OrderStatusNotification($sale, $title, $message, $customerUrl),
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function resolveAdminRecipients(Sale $sale): Collection
    {
        $contractor = $sale->contractor;
        if (! $contractor) {
            return collect();
        }

        return $contractor->users()
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->get();
    }

    private function labelForStatus(string $status): string
    {
        return match ($status) {
            Sale::STATUS_NEW => 'Novo',
            Sale::STATUS_PENDING_CONFIRMATION => 'Aguardando confirmação',
            Sale::STATUS_CONFIRMED => 'Confirmado',
            Sale::STATUS_AWAITING_PAYMENT => 'Aguardando pagamento',
            Sale::STATUS_PAID => 'Pago',
            Sale::STATUS_REJECTED => 'Rejeitado',
            Sale::STATUS_CANCELLED => 'Cancelado',
            Sale::STATUS_REFUNDED => 'Reembolsado',
            default => ucfirst($status),
        };
    }
}
