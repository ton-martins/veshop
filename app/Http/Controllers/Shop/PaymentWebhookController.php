<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $slug, string $provider): JsonResponse
    {
        $contractor = Contractor::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $gateway = PaymentGateway::query()
            ->where('contractor_id', $contractor->id)
            ->where('provider', $provider)
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (! $gateway) {
            return response()->json([
                'ok' => false,
                'message' => 'Gateway não encontrado para o contratante.',
            ], 404);
        }

        if (! $this->passesWebhookSecretValidation($request, $gateway)) {
            return response()->json([
                'ok' => false,
                'message' => 'Webhook não autorizado.',
            ], 403);
        }

        $payload = $request->all();
        $status = $this->normalizeIncomingStatus($payload);

        if ($status === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Status de pagamento ausente ou inválido.',
            ], 422);
        }

        $reference = $this->resolveTransactionReference($payload);
        $saleCode = $this->resolveSaleCode($payload);

        $result = DB::transaction(function () use ($contractor, $gateway, $payload, $status, $reference, $saleCode): ?array {
            $payment = $this->resolveTargetPayment($contractor, $reference, $saleCode);
            if (! $payment) {
                return null;
            }

            $paymentStatus = $this->mapWebhookStatusToPaymentStatus($status);
            $saleStatus = $this->mapWebhookStatusToSaleStatus($status, $payment->sale);

            $gatewayPayload = is_array($payment->gateway_payload) ? $payment->gateway_payload : [];
            $gatewayPayload['last_webhook'] = $payload;
            $gatewayPayload['last_webhook_received_at'] = now()->toIso8601String();

            $payment->fill([
                'status' => $paymentStatus,
                'transaction_reference' => $reference ?: $payment->transaction_reference,
                'paid_at' => $paymentStatus === SalePayment::STATUS_PAID ? now() : $payment->paid_at,
                'gateway_payload' => $gatewayPayload,
            ])->save();

            $sale = $payment->sale;
            if ($sale) {
                $sale->fill([
                    'status' => $saleStatus,
                    'paid_amount' => $paymentStatus === SalePayment::STATUS_PAID
                        ? (float) $sale->total_amount
                        : (float) $sale->paid_amount,
                    'completed_at' => $paymentStatus === SalePayment::STATUS_PAID
                        ? ($sale->completed_at ?? now())
                        : $sale->completed_at,
                    'cancelled_at' => in_array($saleStatus, [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED], true)
                        ? ($sale->cancelled_at ?? now())
                        : $sale->cancelled_at,
                ])->save();
            }

            $gateway->forceFill([
                'last_health_check_at' => now(),
            ])->save();

            return [
                'payment_id' => (int) $payment->id,
                'sale_id' => (int) ($payment->sale_id ?? 0),
                'payment_status' => $paymentStatus,
                'sale_status' => $saleStatus,
            ];
        });

        if (! $result) {
            return response()->json([
                'ok' => false,
                'message' => 'Pagamento não localizado para referência informada.',
            ], 404);
        }

        if ((int) ($result['sale_id'] ?? 0) > 0) {
            $sale = Sale::query()->find((int) $result['sale_id']);
            if ($sale) {
                app(\App\Services\OrderNotificationService::class)->notifyOrderStatusChanged($sale);
            }
        }

        return response()->json([
            'ok' => true,
            'data' => $result,
        ]);
    }

    private function passesWebhookSecretValidation(Request $request, PaymentGateway $gateway): bool
    {
        $credentials = is_array($gateway->credentials) ? $gateway->credentials : [];
        $expected = trim((string) ($credentials['webhook_secret'] ?? ''));

        if ($expected === '') {
            return true;
        }

        $provided = trim((string) ($request->header('X-Webhook-Secret') ?? $request->input('webhook_secret', '')));

        return hash_equals($expected, $provided);
    }

    private function normalizeIncomingStatus(array $payload): ?string
    {
        $raw = $payload['status']
            ?? $payload['payment_status']
            ?? $payload['data']['status']
            ?? null;

        if ($raw === null) {
            return null;
        }

        $status = strtolower(trim((string) $raw));

        return $status !== '' ? $status : null;
    }

    private function resolveTransactionReference(array $payload): string
    {
        $value = $payload['transaction_reference']
            ?? $payload['transaction_id']
            ?? $payload['reference']
            ?? $payload['external_reference']
            ?? $payload['data']['transaction_reference']
            ?? $payload['data']['transaction_id']
            ?? '';

        return trim((string) $value);
    }

    private function resolveSaleCode(array $payload): string
    {
        $value = $payload['sale_code']
            ?? $payload['order_code']
            ?? $payload['metadata']['sale_code']
            ?? $payload['data']['sale_code']
            ?? '';

        return trim((string) $value);
    }

    private function resolveTargetPayment(Contractor $contractor, string $reference, string $saleCode): ?SalePayment
    {
        if ($reference !== '') {
            $byReference = SalePayment::query()
                ->where('contractor_id', $contractor->id)
                ->where('transaction_reference', $reference)
                ->latest('id')
                ->first();

            if ($byReference) {
                return $byReference;
            }
        }

        if ($saleCode !== '') {
            $sale = Sale::query()
                ->where('contractor_id', $contractor->id)
                ->where('code', $saleCode)
                ->latest('id')
                ->first();

            if ($sale) {
                return SalePayment::query()
                    ->where('contractor_id', $contractor->id)
                    ->where('sale_id', $sale->id)
                    ->latest('id')
                    ->first();
            }
        }

        return null;
    }

    private function mapWebhookStatusToPaymentStatus(string $status): string
    {
        return match ($status) {
            'paid', 'succeeded', 'approved', 'completed' => SalePayment::STATUS_PAID,
            'authorized', 'authorised' => SalePayment::STATUS_AUTHORIZED,
            'failed', 'declined', 'denied', 'error' => SalePayment::STATUS_FAILED,
            'cancelled', 'canceled', 'voided' => SalePayment::STATUS_CANCELLED,
            'refunded', 'chargeback' => SalePayment::STATUS_REFUNDED,
            default => SalePayment::STATUS_PENDING,
        };
    }

    private function mapWebhookStatusToSaleStatus(string $status, ?Sale $sale): string
    {
        $currentStatus = (string) ($sale?->status ?? Sale::STATUS_PENDING_CONFIRMATION);

        return match ($status) {
            'paid', 'succeeded', 'approved', 'completed' => Sale::STATUS_PAID,
            'authorized', 'authorised', 'pending', 'waiting' => Sale::STATUS_AWAITING_PAYMENT,
            'refunded', 'chargeback' => Sale::STATUS_REFUNDED,
            'cancelled', 'canceled', 'voided' => Sale::STATUS_CANCELLED,
            default => $currentStatus,
        };
    }
}
