<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shop\StoreShopCheckoutRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerFavorite;
use App\Support\StorefrontSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicShopController extends Controller
{
    public function show(string $slug): Response
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        $products = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->with('category:id,name,slug')
            ->orderByDesc('is_pdv_featured')
            ->orderBy('pdv_featured_order')
            ->orderBy('name')
            ->get([
                'id',
                'category_id',
                'name',
                'sku',
                'description',
                'sale_price',
                'stock_quantity',
                'unit',
                'image_url',
            ]);

        $productsCountByCategory = $products
            ->groupBy('category_id')
            ->map(static fn (Collection $items): int => $items->count());

        $categories = Category::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(static function (Category $category) use ($productsCountByCategory): array {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'products_count' => (int) ($productsCountByCategory->get($category->id, 0)),
                ];
            })
            ->filter(static fn (array $category): bool => $category['products_count'] > 0)
            ->values()
            ->all();

        $productsPayload = $products
            ->map(fn (Product $product): array => $this->toProductPayload($product))
            ->values()
            ->all();

        return Inertia::render('Public/Shop', [
            'contractor' => $this->toContractorPayload($contractor),
            'categories' => $categories,
            'products' => $productsPayload,
            'storefront' => $this->resolveStorefrontPayload($contractor, $products),
            'payment_methods' => $this->resolveCheckoutPaymentMethods($contractor),
            'shipping_config' => $this->resolveShippingConfigPayload($contractor),
            'shop_auth' => $this->resolveShopAuthPayload($contractor),
        ]);
    }

    public function product(string $slug, int $product): Response
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        $selectedProduct = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', $product)
            ->where('is_active', true)
            ->with('category:id,name,slug')
            ->firstOrFail();

        $relatedProducts = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->where('id', '!=', $selectedProduct->id)
            ->where(function ($query) use ($selectedProduct): void {
                if ($selectedProduct->category_id) {
                    $query->where('category_id', $selectedProduct->category_id);
                } else {
                    $query->whereNull('category_id');
                }
            })
            ->orderByDesc('is_pdv_featured')
            ->orderBy('pdv_featured_order')
            ->orderBy('name')
            ->limit(8)
            ->get([
                'id',
                'category_id',
                'name',
                'sku',
                'description',
                'sale_price',
                'stock_quantity',
                'unit',
                'image_url',
            ])
            ->map(fn (Product $item): array => $this->toProductPayload($item))
            ->values()
            ->all();

        return Inertia::render('Public/ShopProduct', [
            'contractor' => $this->toContractorPayload($contractor),
            'product' => $this->toProductPayload($selectedProduct),
            'related_products' => $relatedProducts,
            'shop_auth' => $this->resolveShopAuthPayload($contractor),
        ]);
    }

    public function checkout(StoreShopCheckoutRequest $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        $data = $request->validated();
        $shopCustomer = $this->resolveCurrentShopCustomerForContractor($contractor);

        if (! $shopCustomer) {
            throw ValidationException::withMessages([
                'order' => 'Faça login para finalizar o pedido.',
            ]);
        }

        if ($contractor->requiresEmailVerification() && ! $shopCustomer->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'order' => 'Confirme seu e-mail para finalizar o pedido.',
            ]);
        }

        if (! $shopCustomer->hasRequiredAddressForCheckout()) {
            throw ValidationException::withMessages([
                'order' => 'Complete seu endereço (CEP, logradouro, bairro, cidade e UF) em Minha conta para finalizar o pedido.',
            ]);
        }

        $paymentMethod = null;
        $paymentMethodId = (int) ($data['payment_method_id'] ?? 0);

        if ($paymentMethodId > 0) {
            $paymentMethod = PaymentMethod::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $paymentMethodId)
                ->where('is_active', true)
                ->first();

            if (! $paymentMethod) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Forma de pagamento inválida para esta loja.',
                ]);
            }
        }

        $rawItems = collect($data['items'] ?? []);
        if ($rawItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Adicione ao menos um item ao carrinho.',
            ]);
        }

        /** @var Collection<int, int> $quantitiesByProduct */
        $quantitiesByProduct = $rawItems
            ->groupBy(static fn (array $row): int => (int) $row['product_id'])
            ->map(static fn (Collection $rows): int => $rows->sum(static fn (array $row): int => (int) $row['quantity']));

        $createdSaleId = null;

        DB::transaction(function () use ($contractor, $data, $paymentMethod, $quantitiesByProduct, $shopCustomer, &$createdSaleId): void {
            $productIds = $quantitiesByProduct->keys()->map(static fn (mixed $id): int => (int) $id)->values();

            $products = Product::query()
                ->where('contractor_id', $contractor->id)
                ->where('is_active', true)
                ->whereIn('id', $productIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Um ou mais produtos são inválidos para esta loja.',
                ]);
            }

            $subtotal = 0.0;
            $preparedLines = [];

            foreach ($quantitiesByProduct as $productId => $quantity) {
                $safeProductId = (int) $productId;
                $safeQuantity = (int) $quantity;
                /** @var Product|null $product */
                $product = $products->get($safeProductId);

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Produto não encontrado para o carrinho.',
                    ]);
                }

                if ($safeQuantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Quantidade inválida para o produto {$product->name}.",
                    ]);
                }

                if ($safeQuantity > (int) $product->stock_quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Estoque insuficiente para o produto {$product->name}.",
                    ]);
                }

                $unitPrice = (float) $product->sale_price;
                $lineTotal = round($unitPrice * $safeQuantity, 2);
                $subtotal += $lineTotal;

                $preparedLines[] = [
                    'product' => $product,
                    'quantity' => $safeQuantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $shippingQuote = $this->resolveShippingQuote($contractor, $data, $subtotal);
            $shippingAmount = (float) $shippingQuote['amount'];
            $total = round($subtotal + $shippingAmount, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'O valor total do pedido precisa ser maior que zero.',
                ]);
            }

            $client = $this->resolveOrCreateCheckoutClient($contractor, $data, $shopCustomer);

            if ($client && (int) ($shopCustomer->client_id ?? 0) !== (int) $client->id) {
                $shopCustomer->client_id = $client->id;
                $shopCustomer->save();
            }

            $sale = Sale::query()->create([
                'contractor_id' => $contractor->id,
                'cash_session_id' => null,
                'client_id' => $client?->id,
                'shop_customer_id' => $shopCustomer->id,
                'user_id' => null,
                'code' => $this->generateCatalogOrderCode($contractor),
                'source' => Sale::SOURCE_CATALOG,
                'status' => Sale::STATUS_PENDING_CONFIRMATION,
                'subtotal_amount' => $subtotal,
                'discount_amount' => 0,
                'surcharge_amount' => $shippingAmount,
                'total_amount' => $total,
                'paid_amount' => 0,
                'change_amount' => 0,
                'shipping_mode' => (string) $shippingQuote['mode'],
                'shipping_amount' => $shippingAmount,
                'shipping_estimate_days' => $shippingQuote['estimate_days'],
                'shipping_address' => $shippingQuote['address'],
                'notes' => $data['notes'] ?? null,
                'metadata' => [
                    'checkout_channel' => 'public_shop',
                    'customer_name' => $data['customer_name'] ?? $shopCustomer->name,
                    'customer_phone' => $this->normalizePhone($data['customer_phone'] ?? $shopCustomer->phone),
                    'customer_email' => $data['customer_email'] ?? $shopCustomer->email,
                    'shipping_mode' => $shippingQuote['mode'],
                    'shipping_label' => $shippingQuote['label'],
                    'shipping_amount' => $shippingAmount,
                ],
            ]);

            foreach ($preparedLines as $line) {
                /** @var Product $product */
                $product = $line['product'];

                SaleItem::query()->create([
                    'contractor_id' => $contractor->id,
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => 0,
                    'total_amount' => $line['line_total'],
                ]);
            }

            if ($paymentMethod) {
                SalePayment::query()->create([
                    'contractor_id' => $contractor->id,
                    'sale_id' => $sale->id,
                    'payment_method_id' => $paymentMethod->id,
                    'payment_gateway_id' => $paymentMethod->payment_gateway_id,
                    'status' => SalePayment::STATUS_PENDING,
                    'amount' => $total,
                    'installments' => null,
                    'paid_at' => null,
                ]);
            }

            $createdSaleId = (int) $sale->id;
        });

        if ($createdSaleId) {
            $createdSale = Sale::query()->find($createdSaleId);
            if ($createdSale) {
                app(\App\Services\OrderNotificationService::class)->notifyOrderCreated($createdSale);
            }
        }

        return back()->with('status', 'Pedido enviado com sucesso. Aguarde a confirmação da loja.');
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
            'phone' => $contractor->phone,
            'primary_color' => $contractor->brand_primary_color,
            'logo_url' => $this->normalizePublicAssetUrl($contractor->brand_logo_url),
            'avatar_url' => $this->normalizePublicAssetUrl($contractor->brand_avatar_url),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toProductPayload(Product $product): array
    {
        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'sale_price' => round((float) $product->sale_price, 2),
            'stock_quantity' => (int) $product->stock_quantity,
            'unit' => $product->unit,
            'image_url' => $this->normalizePublicAssetUrl($product->image_url),
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
     * @return array<int, array{id: int, name: string, code: string}>
     */
    private function resolveCheckoutPaymentMethods(Contractor $contractor): array
    {
        return PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(static fn (PaymentMethod $method): array => [
                'id' => (int) $method->id,
                'name' => (string) $method->name,
                'code' => (string) $method->code,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveShippingConfigPayload(Contractor $contractor): array
    {
        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $shipping = is_array($settings['shop_shipping'] ?? null) ? $settings['shop_shipping'] : [];

        $pickupEnabled = (bool) ($shipping['pickup_enabled'] ?? true);
        $deliveryEnabled = (bool) ($shipping['delivery_enabled'] ?? true);
        $fixedFee = max(0, round((float) ($shipping['fixed_fee'] ?? 0), 2));
        $freeOver = max(0, round((float) ($shipping['free_over'] ?? 0), 2));
        $estimateDays = (int) ($shipping['estimated_days'] ?? 2);

        return [
            'pickup_enabled' => $pickupEnabled,
            'delivery_enabled' => $deliveryEnabled,
            'fixed_fee' => $fixedFee,
            'free_over' => $freeOver,
            'estimated_days' => $estimateDays > 0 ? $estimateDays : null,
        ];
    }

    /**
     * @param Collection<int, Product> $products
     * @return array<string, mixed>
     */
    private function resolveStorefrontPayload(Contractor $contractor, Collection $products): array
    {
        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $storefront = StorefrontSettings::normalize($contractor, $settings['shop_storefront'] ?? []);

        $availableProductIds = $products
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $availableIdMap = array_flip($availableProductIds);

        $promotionIds = collect($storefront['promotions']['product_ids'] ?? [])
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => isset($availableIdMap[$id]))
            ->unique()
            ->values()
            ->all();

        if ($promotionIds === []) {
            $promotionIds = array_slice($availableProductIds, 0, 8);
        }

        $storefront['promotions']['product_ids'] = $promotionIds;

        return $storefront;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{
     *   mode: string,
     *   label: string,
     *   amount: float,
     *   estimate_days: int|null,
     *   address: array<string, string>|null
     * }
     */
    private function resolveShippingQuote(Contractor $contractor, array $data, float $subtotal): array
    {
        $config = $this->resolveShippingConfigPayload($contractor);
        $mode = (string) ($data['delivery_mode'] ?? Sale::SHIPPING_MODE_PICKUP);

        if ($mode === Sale::SHIPPING_MODE_DELIVERY) {
            if (! (bool) ($config['delivery_enabled'] ?? true)) {
                throw ValidationException::withMessages([
                    'delivery_mode' => 'Entrega indisponível para esta loja no momento.',
                ]);
            }

            $fixedFee = (float) ($config['fixed_fee'] ?? 0);
            $freeOver = (float) ($config['free_over'] ?? 0);

            $amount = $fixedFee;
            if ($freeOver > 0 && $subtotal >= $freeOver) {
                $amount = 0;
            }

            return [
                'mode' => Sale::SHIPPING_MODE_DELIVERY,
                'label' => 'Entrega',
                'amount' => round(max(0, $amount), 2),
                'estimate_days' => isset($config['estimated_days']) ? (int) $config['estimated_days'] : null,
                'address' => [
                    'postal_code' => trim((string) ($data['shipping_postal_code'] ?? '')),
                    'street' => trim((string) ($data['shipping_street'] ?? '')),
                    'number' => trim((string) ($data['shipping_number'] ?? '')),
                    'complement' => trim((string) ($data['shipping_complement'] ?? '')),
                    'district' => trim((string) ($data['shipping_district'] ?? '')),
                    'city' => trim((string) ($data['shipping_city'] ?? '')),
                    'state' => strtoupper(trim((string) ($data['shipping_state'] ?? ''))),
                ],
            ];
        }

        if (! (bool) ($config['pickup_enabled'] ?? true)) {
            throw ValidationException::withMessages([
                'delivery_mode' => 'Retirada indisponível para esta loja no momento.',
            ]);
        }

        return [
            'mode' => Sale::SHIPPING_MODE_PICKUP,
            'label' => 'Retirada na loja',
            'amount' => 0.0,
            'estimate_days' => null,
            'address' => null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveOrCreateCheckoutClient(Contractor $contractor, array $data, ?ShopCustomer $shopCustomer = null): ?Client
    {
        $email = isset($data['customer_email'])
            ? trim((string) $data['customer_email'])
            : trim((string) ($shopCustomer?->email ?? ''));
        $phone = $this->normalizePhone($data['customer_phone'] ?? ($shopCustomer?->phone ?? null));
        $name = trim((string) ($data['customer_name'] ?? ($shopCustomer?->name ?? '')));

        if ($name === '') {
            return null;
        }

        if ($shopCustomer && $shopCustomer->client_id) {
            $existingClient = Client::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $shopCustomer->client_id)
                ->first();

            if ($existingClient) {
                $existingClient->fill([
                    'name' => $name,
                    'email' => $email !== '' ? $email : $existingClient->email,
                    'phone' => $phone !== '' ? $phone : $existingClient->phone,
                    'city' => trim((string) ($data['shipping_city'] ?? '')) !== '' ? trim((string) $data['shipping_city']) : $existingClient->city,
                    'state' => trim((string) ($data['shipping_state'] ?? '')) !== '' ? strtoupper(trim((string) $data['shipping_state'])) : $existingClient->state,
                    'is_active' => true,
                ])->save();

                return $existingClient;
            }
        }

        $query = Client::query()
            ->where('contractor_id', $contractor->id);

        if ($email !== '') {
            $client = (clone $query)->where('email', $email)->first();
            if ($client) {
                $client->fill([
                    'name' => $name,
                    'phone' => $phone ?: $client->phone,
                    'city' => trim((string) ($data['shipping_city'] ?? '')) !== '' ? trim((string) $data['shipping_city']) : $client->city,
                    'state' => trim((string) ($data['shipping_state'] ?? '')) !== '' ? strtoupper(trim((string) $data['shipping_state'])) : $client->state,
                    'is_active' => true,
                ])->save();

                return $client;
            }
        }

        if ($phone !== '') {
            $client = (clone $query)->where('phone', $phone)->first();
            if ($client) {
                $client->fill([
                    'name' => $name,
                    'email' => $email !== '' ? $email : $client->email,
                    'city' => trim((string) ($data['shipping_city'] ?? '')) !== '' ? trim((string) $data['shipping_city']) : $client->city,
                    'state' => trim((string) ($data['shipping_state'] ?? '')) !== '' ? strtoupper(trim((string) $data['shipping_state'])) : $client->state,
                    'is_active' => true,
                ])->save();

                return $client;
            }
        }

        return Client::query()->create([
            'contractor_id' => $contractor->id,
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'city' => trim((string) ($data['shipping_city'] ?? '')) !== '' ? trim((string) $data['shipping_city']) : null,
            'state' => trim((string) ($data['shipping_state'] ?? '')) !== '' ? strtoupper(trim((string) $data['shipping_state'])) : null,
            'is_active' => true,
        ]);
    }

    private function normalizePhone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? ''));

        return is_string($digits) ? trim($digits) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveShopAuthPayload(Contractor $contractor): array
    {
        $customer = $this->resolveCurrentShopCustomerForContractor($contractor);
        $requiresEmailVerification = $contractor->requiresEmailVerification();

        return [
            'authenticated' => (bool) $customer,
            'requires_email_verification' => $requiresEmailVerification,
            'email_verified' => $customer
                ? (! $requiresEmailVerification || $customer->hasVerifiedEmail())
                : false,
            'address_complete' => $customer ? $customer->hasRequiredAddressForCheckout() : false,
            'missing_address_fields' => $customer ? $customer->missingRequiredAddressFieldsForCheckout() : [],
            'favorite_product_ids' => $customer
                ? $this->resolveFavoriteProductIds($contractor, $customer)
                : [],
            'customer' => $customer ? [
                'id' => (int) $customer->id,
                'name' => (string) $customer->name,
                'email' => (string) ($customer->email ?? ''),
                'phone' => (string) ($customer->phone ?? ''),
                'cep' => (string) ($customer->cep ?? ''),
                'street' => (string) ($customer->street ?? ''),
                'neighborhood' => (string) ($customer->neighborhood ?? ''),
                'city' => (string) ($customer->city ?? ''),
                'state' => (string) ($customer->state ?? ''),
            ] : null,
        ];
    }

    private function resolveCurrentShopCustomerForContractor(Contractor $contractor): ?ShopCustomer
    {
        /** @var ShopCustomer|null $customer */
        $customer = Auth::guard('shop')->user();

        if (! $customer) {
            return null;
        }

        return (int) $customer->contractor_id === (int) $contractor->id
            ? $customer
            : null;
    }

    private function generateCatalogOrderCode(Contractor $contractor): string
    {
        do {
            $code = 'PED-'.now()->format('Ymd-His').'-'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

            $exists = Sale::query()
                ->where('contractor_id', $contractor->id)
                ->where('code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }

    /**
     * @return array<int, int>
     */
    private function resolveFavoriteProductIds(Contractor $contractor, ShopCustomer $customer): array
    {
        return ShopCustomerFavorite::query()
            ->where('contractor_id', $contractor->id)
            ->where('shop_customer_id', $customer->id)
            ->orderByDesc('id')
            ->pluck('product_id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->filter(static fn (int $value): bool => $value > 0)
            ->unique()
            ->values()
            ->all();
    }
}
