<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerFavorite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopAccountController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        /** @var ShopCustomer|null $customer */
        $customer = $request->user('shop');
        abort_unless($customer, 403);
        abort_unless((int) $customer->contractor_id === (int) $contractor->id, 403);

        $orders = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('source', [Sale::SOURCE_CATALOG, Sale::SOURCE_ORDER])
            ->where(function ($query) use ($customer): void {
                $query->where('shop_customer_id', $customer->id);

                if ($customer->client_id) {
                    $query->orWhere('client_id', $customer->client_id);
                }
            })
            ->with([
                'items:id,sale_id,description,quantity,total_amount',
                'payments:id,sale_id,status,amount,payment_method_id',
                'payments.paymentMethod:id,name',
            ])
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn (Sale $sale): array => $this->toOrderPayload($sale))
            ->values()
            ->all();

        $favorites = ShopCustomerFavorite::query()
            ->where('contractor_id', $contractor->id)
            ->where('shop_customer_id', $customer->id)
            ->with([
                'product:id,contractor_id,name,sale_price,stock_quantity,image_url,is_active',
            ])
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(fn (ShopCustomerFavorite $favorite): ?array => $this->toFavoritePayload($contractor, $favorite))
            ->filter()
            ->values()
            ->all();

        return Inertia::render('Public/ShopAccount', [
            'contractor' => $this->toContractorPayload($contractor),
            'customer' => [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'email' => (string) ($customer->email ?? ''),
                'phone' => (string) ($customer->phone ?? ''),
            ],
            'orders' => $orders,
            'favorites' => $favorites,
            'notifications' => $customer->notifications()
                ->latest('created_at')
                ->limit(40)
                ->get()
                ->map(static function ($notification): array {
                    $data = is_array($notification->data) ? $notification->data : [];

                    return [
                        'id' => (string) $notification->id,
                        'title' => (string) ($data['title'] ?? 'Notificação'),
                        'message' => (string) ($data['message'] ?? ''),
                        'target_url' => (string) ($data['target_url'] ?? ''),
                        'read_at' => optional($notification->read_at)?->toIso8601String(),
                        'created_at' => optional($notification->created_at)?->format('d/m/Y H:i'),
                    ];
                })
                ->values()
                ->all(),
            'notifications_unread_count' => (int) $customer->unreadNotifications()->count(),
        ]);
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
            'logo_url' => $contractor->brand_logo_url,
            'avatar_url' => $contractor->brand_avatar_url,
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
        ];
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
