<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerFavorite;
use App\Support\BrazilData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShopAccountController extends Controller
{
    public function show(Request $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        /** @var ShopCustomer|null $customer */
        $customer = $request->user('shop');
        abort_unless($customer, 403);
        abort_unless((int) $customer->contractor_id === (int) $contractor->id, 403);

        return redirect()->route('shop.show', [
            'slug' => $contractor->slug,
            'conta' => 1,
        ]);
    }

    public function updateProfile(Request $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        /** @var ShopCustomer|null $customer */
        $customer = $request->user('shop');
        abort_unless($customer, 403);
        abort_unless((int) $customer->contractor_id === (int) $contractor->id, 403);

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'regex:/^\(\d{2}\)\s\d{5}-\d{4}$/'],
            'cep' => ['nullable', 'string', 'regex:/^\d{5}-\d{3}$/'],
            'street' => ['nullable', 'string', 'max:160'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', Rule::in(BrazilData::STATE_CODES)],
        ]);

        $phone = BrazilData::normalizePhone($validated['phone'] ?? null);
        $cep = BrazilData::normalizeCep($validated['cep'] ?? null);
        $street = trim((string) ($validated['street'] ?? ''));
        $number = trim((string) ($validated['number'] ?? ''));
        $complement = trim((string) ($validated['complement'] ?? ''));
        $neighborhood = trim((string) ($validated['neighborhood'] ?? ''));
        $city = trim((string) ($validated['city'] ?? ''));
        $state = BrazilData::normalizeState($validated['state'] ?? null);

        $customer->fill([
            'phone' => $phone !== '' ? $phone : null,
            'cep' => $cep !== '' ? $cep : null,
            'street' => $street !== '' ? $street : null,
            'number' => $number !== '' ? $number : null,
            'complement' => $complement !== '' ? $complement : null,
            'neighborhood' => $neighborhood !== '' ? $neighborhood : null,
            'city' => $city !== '' ? $city : null,
            'state' => $state !== '' ? $state : null,
        ])->save();

        $client = $customer->client;
        if (! $client) {
            $client = Client::query()->create([
                'contractor_id' => $contractor->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $phone !== '' ? $phone : null,
                'cep' => $cep !== '' ? $cep : null,
                'street' => $street !== '' ? $street : null,
                'number' => $number !== '' ? $number : null,
                'complement' => $complement !== '' ? $complement : null,
                'neighborhood' => $neighborhood !== '' ? $neighborhood : null,
                'city' => $city !== '' ? $city : null,
                'state' => $state !== '' ? $state : null,
                'is_active' => true,
            ]);

            $customer->forceFill(['client_id' => $client->id])->save();
        } else {
            $client->fill([
                'phone' => $phone !== '' ? $phone : null,
                'cep' => $cep !== '' ? $cep : null,
                'street' => $street !== '' ? $street : null,
                'number' => $number !== '' ? $number : null,
                'complement' => $complement !== '' ? $complement : null,
                'neighborhood' => $neighborhood !== '' ? $neighborhood : null,
                'city' => $city !== '' ? $city : null,
                'state' => $state !== '' ? $state : null,
            ])->save();
        }

        return back()->with('status', 'Dados atualizados com sucesso.');
    }

    public function markNotificationsAsRead(Request $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        /** @var ShopCustomer|null $customer */
        $customer = $request->user('shop');
        abort_unless($customer, 403);
        abort_unless((int) $customer->contractor_id === (int) $contractor->id, 403);

        $validated = $request->validate([
            'id' => ['nullable', 'string', 'max:255'],
        ]);

        $notificationId = trim((string) ($validated['id'] ?? ''));

        if ($notificationId !== '') {
            $customer->unreadNotifications()
                ->where('id', $notificationId)
                ->update(['read_at' => now()]);

            return back();
        }

        $customer->unreadNotifications->markAsRead();

        return back();
    }

    private function resolveActiveContractorBySlug(string $slug): Contractor
    {
        return Contractor::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function toContractorPayload(Contractor $contractor): array
    {
        return [
            'id' => $contractor->id,
            'slug' => $contractor->slug,
            'name' => $contractor->name,
            'brand_name' => $contractor->brand_name,
            'primary_color' => $contractor->brand_primary_color,
            'logo_url' => $this->normalizePublicAssetUrl($contractor->brand_logo_url),
            'avatar_url' => $this->normalizePublicAssetUrl($contractor->brand_avatar_url),
        ];
    }

    private function normalizePublicAssetUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $normalized = is_string($path) && $path !== '' ? $path : $value;

        if (str_starts_with($normalized, '/storage/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/')) {
            return '/'.ltrim($normalized, '/');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toFavoritePayload(Contractor $contractor, ShopCustomerFavorite $favorite): ?array
    {
        $product = $favorite->product;
        if (! $product) {
            return null;
        }

        if ((int) $product->contractor_id !== (int) $contractor->id) {
            return null;
        }

        return [
            'id' => (int) $favorite->id,
            'product_id' => (int) $product->id,
            'name' => (string) $product->name,
            'sale_price' => round((float) $product->sale_price, 2),
            'stock_quantity' => (int) $product->stock_quantity,
            'is_active' => (bool) $product->is_active,
            'image_url' => $this->normalizePublicAssetUrl($product->image_url),
            'url' => route('shop.product.show', ['slug' => $contractor->slug, 'product' => $product->id]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toOrderPayload(Sale $sale): array
    {
        $latestPayment = $sale->payments
            ->sortByDesc('id')
            ->first();

        $status = $this->resolveStatusMeta((string) $sale->status);
        $payments = $sale->payments
            ->map(static fn (SalePayment $payment): ?string => $payment->paymentMethod?->name)
            ->filter()
            ->values();

        return [
            'id' => (int) $sale->id,
            'code' => (string) $sale->code,
            'status' => $status,
            'total_amount' => (float) $sale->total_amount,
            'created_at' => optional($sale->created_at)->format('d/m/Y H:i'),
            'items' => $sale->items->map(static fn ($item): array => [
                'description' => (string) $item->description,
                'quantity' => (int) $item->quantity,
                'total_amount' => (float) $item->total_amount,
            ])->values()->all(),
            'payment_label' => $payments->isNotEmpty() ? $payments->implode(' + ') : 'Não informado',
            'payment' => $this->toOrderPaymentPayload($sale, $latestPayment),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toOrderPaymentPayload(Sale $sale, ?SalePayment $salePayment): ?array
    {
        if (! $salePayment) {
            return null;
        }

        $gatewayPayload = is_array($salePayment->gateway_payload) ? $salePayment->gateway_payload : [];
        $paymentIntent = data_get($gatewayPayload, 'payment_intent');
        if (! is_array($paymentIntent)) {
            $paymentIntent = [];
        }

        $saleMetadata = is_array($sale->metadata) ? $sale->metadata : [];
        $saleIntent = data_get($saleMetadata, 'payment_intent');
        if (! is_array($saleIntent)) {
            $saleIntent = [];
        }

        $transactionReference = trim((string) ($salePayment->transaction_reference
            ?? ($paymentIntent['transaction_reference'] ?? ($saleIntent['transaction_reference'] ?? ''))));
        $paymentMethodCode = strtolower(trim((string) ($salePayment->paymentMethod?->code ?? '')));
        $paymentMethodName = trim((string) ($salePayment->paymentMethod?->name ?? ''));
        $provider = trim((string) (data_get($salePayment->metadata ?? [], 'provider')
            ?? ($paymentIntent['provider'] ?? ($saleIntent['provider'] ?? ''))));
        $ticketUrl = trim((string) ($paymentIntent['ticket_url'] ?? ($saleIntent['ticket_url'] ?? '')));
        $qrCode = trim((string) ($paymentIntent['qr_code'] ?? ($saleIntent['qr_code'] ?? '')));
        $qrCodeBase64 = trim((string) ($paymentIntent['qr_code_base64'] ?? ($saleIntent['qr_code_base64'] ?? '')));
        $expiresAt = $paymentIntent['date_of_expiration'] ?? ($saleIntent['date_of_expiration'] ?? null);

        return [
            'status' => (string) $salePayment->status,
            'status_label' => $this->resolvePaymentStatusLabel((string) $salePayment->status),
            'method_code' => $paymentMethodCode,
            'method_name' => $paymentMethodName,
            'provider' => $provider,
            'transaction_reference' => $transactionReference,
            'amount' => round((float) $salePayment->amount, 2),
            'ticket_url' => $ticketUrl,
            'qr_code' => $qrCode,
            'qr_code_base64' => $qrCodeBase64,
            'expires_at' => $expiresAt,
            'is_pix' => $paymentMethodCode === PaymentMethod::CODE_PIX && $qrCode !== '',
        ];
    }

    private function resolvePaymentStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            SalePayment::STATUS_PAID => 'Pago',
            SalePayment::STATUS_AUTHORIZED => 'Autorizado',
            SalePayment::STATUS_PENDING => 'Aguardando pagamento',
            SalePayment::STATUS_CANCELLED => 'Cancelado',
            SalePayment::STATUS_REFUNDED => 'Reembolsado',
            SalePayment::STATUS_FAILED => 'Falhou',
            default => ucfirst(strtolower(trim($status))),
        };
    }

    /**
     * @return array{value: string, label: string, tone: string}
     */
    private function resolveStatusMeta(string $status): array
    {
        return match ($status) {
            Sale::STATUS_NEW => ['value' => $status, 'label' => 'Novo', 'tone' => 'bg-blue-100 text-blue-700'],
            Sale::STATUS_PENDING_CONFIRMATION => ['value' => $status, 'label' => 'Aguardando confirmação', 'tone' => 'bg-blue-100 text-blue-700'],
            Sale::STATUS_CONFIRMED => ['value' => $status, 'label' => 'Confirmado', 'tone' => 'bg-amber-100 text-amber-700'],
            Sale::STATUS_AWAITING_PAYMENT => ['value' => $status, 'label' => 'Aguardando pagamento', 'tone' => 'bg-amber-100 text-amber-700'],
            Sale::STATUS_PAID => ['value' => $status, 'label' => 'Pago', 'tone' => 'bg-emerald-100 text-emerald-700'],
            Sale::STATUS_REJECTED => ['value' => $status, 'label' => 'Rejeitado', 'tone' => 'bg-rose-100 text-rose-700'],
            Sale::STATUS_CANCELLED => ['value' => $status, 'label' => 'Cancelado', 'tone' => 'bg-rose-100 text-rose-700'],
            default => ['value' => $status, 'label' => ucfirst($status), 'tone' => 'bg-slate-100 text-slate-700'],
        };
    }
}
