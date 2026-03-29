<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentWebhookReceipt;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\Payments\Exceptions\PaymentProviderException;
use App\Services\Payments\PaymentProviderManager;
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
                'message' => 'Gateway nao encontrado para o contratante.',
            ], 404);
        }

        if (! $this->passesWebhookValidation($request, $gateway)) {
            return response()->json([
                'ok' => false,
                'message' => 'Webhook nao autorizado.',
            ], 403);
        }

        $payload = $request->all();
        $normalizedProviderPayload = [
            'status' => null,
            'transaction_reference' => null,
            'sale_code' => null,
            'event_id' => null,
            'raw_payment' => null,
        ];

        if ((string) $gateway->provider === PaymentGateway::PROVIDER_MERCADO_PAGO) {
            try {
                $normalizedProviderPayload = app(PaymentProviderManager::class)
                    ->normalizeWebhookPayload($gateway, $payload);
            } catch (PaymentProviderException $exception) {
                report($exception);

                return response()->json([
                    'ok' => false,
                    'message' => 'Falha ao normalizar webhook do Mercado Pago.',
                ], 500);
            }
        }

        $status = $normalizedProviderPayload['status']
            ?? $this->normalizeIncomingStatus($payload);

        if ($status === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Status de pagamento ausente ou invalido.',
            ], 422);
        }

        $reference = $normalizedProviderPayload['transaction_reference']
            ?? $this->resolveTransactionReference($payload);
        $saleCode = $normalizedProviderPayload['sale_code']
            ?? $this->resolveSaleCode($payload);
        $eventId = $normalizedProviderPayload['event_id'] ?? null;

        if (is_array($normalizedProviderPayload['raw_payment'] ?? null)) {
            $payload['provider_payment'] = $normalizedProviderPayload['raw_payment'];
        }

        $eventKey = $this->resolveWebhookEventKey($provider, $payload, $status, $reference, $saleCode, $eventId);

        $result = DB::transaction(function () use ($contractor, $gateway, $provider, $payload, $status, $reference, $saleCode, $eventKey): array {
            $receipt = PaymentWebhookReceipt::query()
                ->where('contractor_id', $contractor->id)
                ->where('payment_gateway_id', $gateway->id)
                ->where('event_key', $eventKey)
                ->lockForUpdate()
                ->first();

            if ($receipt && $receipt->processed_at !== null) {
                return [
                    'deduplicated' => true,
                    'data' => null,
                ];
            }

            if (! $receipt) {
                $receipt = PaymentWebhookReceipt::query()->create([
                    'contractor_id' => (int) $contractor->id,
                    'payment_gateway_id' => (int) $gateway->id,
                    'provider' => $provider,
                    'event_key' => $eventKey,
                    'transaction_reference' => $reference !== '' ? $reference : null,
                    'sale_code' => $saleCode !== '' ? $saleCode : null,
                    'status' => $status,
                    'payload' => $payload,
                ]);
            }

            $payment = $this->resolveTargetPayment($contractor, $reference, $saleCode);
            if (! $payment) {
                $receipt->delete();

                return [
                    'deduplicated' => false,
                    'data' => null,
                ];
            }

            $lockedPayment = SalePayment::query()
                ->where('id', $payment->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedPayment) {
                $receipt->delete();

                return [
                    'deduplicated' => false,
                    'data' => null,
                ];
            }

            $paymentStatus = $this->mapWebhookStatusToPaymentStatus($status);
            $saleStatus = $this->mapWebhookStatusToSaleStatus($status, $lockedPayment->sale);

            $gatewayPayload = is_array($lockedPayment->gateway_payload) ? $lockedPayment->gateway_payload : [];
            $gatewayPayload['last_webhook'] = $payload;
            $gatewayPayload['last_webhook_received_at'] = now()->toIso8601String();

            $lockedPayment->fill([
                'status' => $paymentStatus,
                'transaction_reference' => $reference !== '' ? $reference : $lockedPayment->transaction_reference,
                'paid_at' => $paymentStatus === SalePayment::STATUS_PAID ? now() : $lockedPayment->paid_at,
                'gateway_payload' => $gatewayPayload,
            ])->save();

            $sale = $lockedPayment->sale;
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

            $receipt->fill([
                'status' => $status,
                'payload' => $payload,
                'processed_at' => now(),
            ])->save();

            return [
                'deduplicated' => false,
                'data' => [
                    'payment_id' => (int) $lockedPayment->id,
                    'sale_id' => (int) ($lockedPayment->sale_id ?? 0),
                    'payment_status' => $paymentStatus,
                    'sale_status' => $saleStatus,
                ],
            ];
        });

        if ($result['deduplicated'] === true) {
            return response()->json([
                'ok' => true,
                'deduplicated' => true,
                'message' => 'Webhook ja processado.',
            ]);
        }

        if (! is_array($result['data'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Pagamento nao localizado para referencia informada.',
            ], 404);
        }

        if ((int) ($result['data']['sale_id'] ?? 0) > 0) {
            $sale = Sale::query()->find((int) $result['data']['sale_id']);
            if ($sale) {
                app(\App\Services\OrderNotificationService::class)->notifyOrderStatusChanged($sale);
            }
        }

        return response()->json([
            'ok' => true,
            'data' => $result['data'],
        ]);
    }

    private function passesWebhookValidation(Request $request, PaymentGateway $gateway): bool
    {
        if ((string) $gateway->provider === PaymentGateway::PROVIDER_MERCADO_PAGO) {
            $signatureHeader = trim((string) $request->header('x-signature', ''));

            if ($signatureHeader !== '') {
                if ($this->passesMercadoPagoSignatureValidation($request)) {
                    return true;
                }

                if (trim((string) config('services.mercadopago.webhook_secret', '')) !== '') {
                    return false;
                }
            }
        }

        return $this->passesLegacyWebhookSecretValidation($request, $gateway);
    }

    private function passesMercadoPagoSignatureValidation(Request $request): bool
    {
        $secret = trim((string) config('services.mercadopago.webhook_secret', ''));
        if ($secret === '') {
            return false;
        }

        $signatureHeader = trim((string) $request->header('x-signature', ''));
        $requestId = trim((string) $request->header('x-request-id', ''));
        if ($signatureHeader === '' || $requestId === '') {
            return false;
        }

        $signatureMap = [];
        foreach (explode(',', $signatureHeader) as $chunk) {
            $parts = explode('=', trim($chunk), 2);
            if (count($parts) !== 2) {
                continue;
            }

            $signatureMap[strtolower(trim($parts[0]))] = trim($parts[1]);
        }

        $ts = trim((string) ($signatureMap['ts'] ?? ''));
        $v1 = trim((string) ($signatureMap['v1'] ?? ''));
        if ($ts === '' || $v1 === '') {
            return false;
        }

        $dataId = trim((string) (
            $request->query('data.id')
            ?? $request->input('data.id')
            ?? data_get($request->all(), 'data.id')
            ?? ''
        ));

        if ($dataId === '') {
            return false;
        }

        $manifest = sprintf('id:%s;request-id:%s;ts:%s;', strtolower($dataId), $requestId, $ts);
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, strtolower($v1));
    }

    private function passesLegacyWebhookSecretValidation(Request $request, PaymentGateway $gateway): bool
    {
        $credentials = is_array($gateway->credentials) ? $gateway->credentials : [];
        $expected = trim((string) ($credentials['webhook_secret'] ?? ''));

        if ($expected === '') {
            return true;
        }

        $provided = '';
        foreach ([
            (string) $request->header('X-Webhook-Secret', ''),
            (string) $request->input('webhook_secret', ''),
            (string) $request->query('token', ''),
        ] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                $provided = $candidate;
                break;
            }
        }

        return $provided !== '' && hash_equals($expected, $provided);
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

    private function resolveWebhookEventKey(
        string $provider,
        array $payload,
        string $status,
        string $reference,
        string $saleCode,
        ?string $eventId = null
    ): string
    {
        $eventIdValue = trim((string) (
            $eventId
            ?? $payload['event_id']
            ?? $payload['id']
            ?? $payload['event']['id']
            ?? $payload['data']['id']
            ?? $payload['resource']['id']
            ?? ''
        ));

        if ($eventIdValue !== '') {
            return hash('sha256', strtolower($provider).'|'.$eventIdValue);
        }

        $rawPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return hash('sha256', implode('|', [
            strtolower($provider),
            $status,
            $reference,
            $saleCode,
            (string) $rawPayload,
        ]));
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
        return match (strtolower(trim($status))) {
            'paid', 'succeeded', 'approved', 'completed', 'processed', 'accredited' => SalePayment::STATUS_PAID,
            'authorized', 'authorised' => SalePayment::STATUS_AUTHORIZED,
            'action_required', 'waiting_transfer', 'pending', 'waiting', 'in_process', 'processing' => SalePayment::STATUS_PENDING,
            'failed', 'declined', 'denied', 'error', 'rejected' => SalePayment::STATUS_FAILED,
            'cancelled', 'canceled', 'voided', 'expired' => SalePayment::STATUS_CANCELLED,
            'refunded', 'chargeback', 'charged_back' => SalePayment::STATUS_REFUNDED,
            default => SalePayment::STATUS_PENDING,
        };
    }

    private function mapWebhookStatusToSaleStatus(string $status, ?Sale $sale): string
    {
        $currentStatus = (string) ($sale?->status ?? Sale::STATUS_PENDING_CONFIRMATION);

        return match (strtolower(trim($status))) {
            'paid', 'succeeded', 'approved', 'completed', 'processed', 'accredited' => Sale::STATUS_PAID,
            'authorized', 'authorised', 'pending', 'waiting', 'action_required', 'waiting_transfer', 'in_process', 'processing' => Sale::STATUS_AWAITING_PAYMENT,
            'refunded', 'chargeback', 'charged_back' => Sale::STATUS_REFUNDED,
            'cancelled', 'canceled', 'voided', 'expired' => Sale::STATUS_CANCELLED,
            'rejected', 'failed' => Sale::STATUS_REJECTED,
            default => $currentStatus,
        };
    }
}
