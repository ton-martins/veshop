<?php

namespace App\Application\Storefront\Services;

use App\Http\Requests\Shop\StoreShopCheckoutRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalog;
use App\Models\ServiceCategory;
use App\Models\ServiceOrder;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerFavorite;
use App\Notifications\ServiceBookingCreatedNotification;
use App\Services\OrderNotificationService;
use App\Services\Payments\Exceptions\PaymentProviderException;
use App\Services\Payments\PaymentProviderManager;
use App\Support\PaymentFeeSnapshot;
use App\Support\StorefrontSettings;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicShopService
{
    public function show(string $slug): Response
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $storefront = StorefrontSettings::normalize($contractor, $settings['shop_storefront'] ?? []);

        if (
            $contractor->niche() === Contractor::NICHE_SERVICES
            || ($storefront['template'] ?? StorefrontSettings::defaultTemplate($contractor)) === StorefrontSettings::TEMPLATE_SERVICES
        ) {
            $catalog = $this->resolveServiceCatalogPayload($contractor);
            $storefrontPayload = $this->resolveServiceStorefrontPayload($contractor, collect($catalog['services'] ?? []));
            $storeAvailability = $this->resolveStoreAvailabilityPayload($contractor, $storefrontPayload);

            return Inertia::render('Public/ServiceShop', [
                'contractor' => $this->toContractorPayload($contractor),
                'storefront' => $storefrontPayload,
                'store_availability' => $storeAvailability,
                'categories' => $catalog['categories'],
                'services' => $catalog['services'],
                'shop_auth' => $this->resolveShopAuthPayload($contractor),
                'shop_account' => $this->resolveShopAccountPayload($contractor),
                'bookings' => $this->resolveServiceBookingsPayload($contractor),
            ]);
        }

        $products = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->with([
                'category:id,name,slug,parent_id',
                'images:id,product_id,image_url,image_path,sort_order',
                'variations:id,product_id,name,sku,sale_price,stock_quantity,is_active,sort_order,attributes',
            ])
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

        $productsCountByCategoryDirect = $products
            ->groupBy('category_id')
            ->map(static fn (Collection $items): int => $items->count());

        $categoriesCollection = Category::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);

        $childrenByParent = $categoriesCollection
            ->groupBy(static fn (Category $category): int => (int) ($category->parent_id ?? 0));

        $totalCountByCategory = [];
        $resolveCategoryCount = function (int $categoryId) use (&$resolveCategoryCount, &$totalCountByCategory, $childrenByParent, $productsCountByCategoryDirect): int {
            if (array_key_exists($categoryId, $totalCountByCategory)) {
                return $totalCountByCategory[$categoryId];
            }

            $direct = (int) ($productsCountByCategoryDirect->get($categoryId, 0));
            /** @var Collection<int, Category> $children */
            $children = $childrenByParent->get($categoryId, collect());

            $childrenTotal = $children->sum(static fn (Category $child): int => $resolveCategoryCount((int) $child->id));
            $total = $direct + $childrenTotal;
            $totalCountByCategory[$categoryId] = $total;

            return $total;
        };

        $categories = $categoriesCollection
            ->map(static function (Category $category) use ($resolveCategoryCount): array {
                return [
                    'id' => (int) $category->id,
                    'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
                    'name' => (string) $category->name,
                    'slug' => (string) $category->slug,
                    'products_count' => $resolveCategoryCount((int) $category->id),
                ];
            })
            ->filter(static fn (array $category): bool => (int) $category['products_count'] > 0)
            ->values()
            ->all();

        $productsPayload = $products
            ->map(fn (Product $product): array => $this->toProductPayload($product))
            ->values()
            ->all();
        $storefrontPayload = $this->resolveStorefrontPayload($contractor, $products);
        $storeAvailability = $this->resolveStoreAvailabilityPayload($contractor, $storefrontPayload);

        return Inertia::render('Public/Shop', [
            'contractor' => $this->toContractorPayload($contractor),
            'categories' => $categories,
            'products' => $productsPayload,
            'storefront' => $storefrontPayload,
            'store_availability' => $storeAvailability,
            'payment_methods' => $this->resolveCheckoutPaymentMethods($contractor),
            'shipping_config' => $this->resolveShippingConfigPayload($contractor),
            'shop_auth' => $this->resolveShopAuthPayload($contractor),
            'shop_account' => $this->resolveShopAccountPayload($contractor),
        ]);
    }

    public function product(string $slug, int $product): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', $product)
            ->where('is_active', true)
            ->firstOrFail(['id']);

        return redirect()->route('shop.show', [
            'slug' => $contractor->slug,
            'produto' => $product,
        ]);
    }

    public function bookService(Request $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        $shopCustomer = $this->resolveCurrentShopCustomerForContractor($contractor);
        $storeAvailability = $this->resolveStoreAvailabilityPayload($contractor);

        if (! $shopCustomer) {
            throw ValidationException::withMessages([
                'booking' => 'Faça login para agendar um serviço.',
            ]);
        }

        if ($contractor->requiresEmailVerification() && ! $shopCustomer->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'booking' => 'Confirme seu e-mail para concluir o agendamento.',
            ]);
        }

        if (! (bool) ($storeAvailability['can_book'] ?? true)) {
            throw ValidationException::withMessages([
                'booking' => (string) ($storeAvailability['message'] ?? 'Agendamentos indisponíveis no momento.'),
            ]);
        }

        $validated = $request->validate([
            'service_catalog_id' => [
                'required',
                'integer',
                Rule::exists('service_catalogs', 'id')->where(
                    static fn ($query) => $query->where('contractor_id', $contractor->id)->where('is_active', true)
                ),
            ],
            'scheduled_for' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var ServiceCatalog|null $service */
        $service = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->where('id', (int) $validated['service_catalog_id'])
            ->first();

        if (! $service) {
            throw ValidationException::withMessages([
                'service_catalog_id' => 'Serviço inválido para este catálogo.',
            ]);
        }

        $timezone = $this->resolveContractorTimezone($contractor);
        $scheduledFor = Carbon::parse((string) $validated['scheduled_for'], $timezone);
        $durationMinutes = max(15, (int) ($service->duration_minutes ?? 60));
        $businessHours = is_array($storeAvailability['business_hours'] ?? null)
            ? $storeAvailability['business_hours']
            : StorefrontSettings::normalizeBusinessHours([]);
        $hoursCheck = $this->validateDateTimeAgainstBusinessHours($scheduledFor, $businessHours, $durationMinutes);
        if (! (bool) ($hoursCheck['allowed'] ?? false)) {
            throw ValidationException::withMessages([
                'scheduled_for' => (string) ($hoursCheck['message'] ?? 'Escolha um horário dentro do funcionamento da loja.'),
            ]);
        }
        $endsAt = $scheduledFor->copy()->addMinutes($durationMinutes);

        $client = $this->resolveOrCreateCheckoutClient($contractor, [
            'customer_name' => (string) $shopCustomer->name,
            'customer_email' => (string) $shopCustomer->email,
            'customer_phone' => (string) $shopCustomer->phone,
            'shipping_city' => (string) $shopCustomer->city,
            'shipping_state' => (string) $shopCustomer->state,
        ], $shopCustomer);

        if ($client && (int) ($shopCustomer->client_id ?? 0) !== (int) $client->id) {
            $shopCustomer->client_id = $client->id;
            $shopCustomer->save();
        }

        $createdOrderId = null;

        DB::transaction(function () use ($contractor, $shopCustomer, $validated, $service, $scheduledFor, $endsAt, $client, &$createdOrderId): void {
            $order = ServiceOrder::query()->create([
                'contractor_id' => $contractor->id,
                'client_id' => $client?->id,
                'service_catalog_id' => $service->id,
                'code' => $this->generateServiceOrderCode($contractor),
                'title' => 'Agendamento online - '.$service->name,
                'description' => trim((string) ($validated['notes'] ?? '')) ?: null,
                'scheduled_for' => $scheduledFor,
                'due_at' => $endsAt,
                'status' => ServiceOrder::STATUS_OPEN,
                'priority' => 'normal',
                'assigned_to_name' => null,
                'estimated_amount' => (float) $service->base_price,
                'final_amount' => (float) $service->base_price,
                'metadata' => [
                    'channel' => 'public_service_shop',
                    'shop_customer_id' => (int) $shopCustomer->id,
                    'customer_name' => (string) $shopCustomer->name,
                    'customer_email' => (string) $shopCustomer->email,
                    'customer_phone' => (string) ($shopCustomer->phone ?? ''),
                ],
            ]);
            $createdOrderId = (int) $order->id;

            ServiceAppointment::query()->create([
                'contractor_id' => $contractor->id,
                'service_order_id' => $order->id,
                'client_id' => $client?->id,
                'service_catalog_id' => $service->id,
                'title' => 'Agendamento online - '.$service->name,
                'starts_at' => $scheduledFor,
                'ends_at' => $endsAt,
                'status' => ServiceAppointment::STATUS_SCHEDULED,
                'location' => 'Online',
                'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
            ]);
        });

        $createdOrder = $createdOrderId > 0
            ? ServiceOrder::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $createdOrderId)
                ->with([
                    'contractor:id,slug,phone,settings',
                    'service:id,name',
                    'client:id,name',
                ])
                ->first()
            : null;

        $whatsappPayload = $this->resolveServiceBookingWhatsappPayload($contractor, $createdOrder ?? null);

        if ($createdOrder) {
            $this->notifyServiceBookingCreated($contractor, $createdOrder, $whatsappPayload['url'] ?? null);
        }

        return back()
            ->with('status', 'Agendamento enviado com sucesso.')
            ->with('service_booking_whatsapp_url', $whatsappPayload['url'] ?? null)
            ->with('service_booking_whatsapp_message', $whatsappPayload['message'] ?? null);
    }

    public function checkout(StoreShopCheckoutRequest $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        $storeAvailability = $this->resolveStoreAvailabilityPayload($contractor);
        $data = $request->validated();
        $shopCustomer = $this->resolveCurrentShopCustomerForContractor($contractor);
        $idempotencyKey = $this->resolveCheckoutIdempotencyKey($request, $data);

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

        if (! (bool) ($storeAvailability['can_checkout'] ?? true)) {
            throw ValidationException::withMessages([
                'order' => (string) ($storeAvailability['message'] ?? 'Loja indisponível no momento para novos pedidos.'),
            ]);
        }

        if (! $shopCustomer->hasRequiredAddressForCheckout()) {
            throw ValidationException::withMessages([
                'order' => 'Complete seu endereço (CEP, logradouro, bairro, cidade e UF) em Minha conta para finalizar o pedido.',
            ]);
        }

        if ($idempotencyKey !== null) {
            $existingSale = Sale::query()
                ->where('contractor_id', $contractor->id)
                ->where('checkout_idempotency_key', $idempotencyKey)
                ->latest('id')
                ->first();

            if ($existingSale) {
                return back()->with('status', 'Pedido já recebido. Estamos processando sua solicitação.');
            }
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

        $paymentGateway = $this->resolveCheckoutPaymentGateway($contractor, $paymentMethod);

        $rawItems = collect($data['items'] ?? []);
        if ($rawItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Adicione ao menos um item ao carrinho.',
            ]);
        }

        /** @var Collection<int, array{product_id:int,variation_id:int|null,quantity:int}> $groupedItems */
        $groupedItems = $rawItems
            ->map(static function (array $row): array {
                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'variation_id' => isset($row['variation_id']) && $row['variation_id'] !== ''
                        ? (int) $row['variation_id']
                        : null,
                    'quantity' => (int) ($row['quantity'] ?? 0),
                ];
            })
            ->filter(static fn (array $row): bool => $row['product_id'] > 0 && $row['quantity'] > 0)
            ->groupBy(static fn (array $row): string => "{$row['product_id']}|".((int) ($row['variation_id'] ?? 0)))
            ->map(static function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'product_id' => (int) ($first['product_id'] ?? 0),
                    'variation_id' => isset($first['variation_id']) && (int) $first['variation_id'] > 0
                        ? (int) $first['variation_id']
                        : null,
                    'quantity' => (int) $rows->sum(static fn (array $item): int => (int) ($item['quantity'] ?? 0)),
                ];
            })
            ->values();

        if ($groupedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Adicione ao menos um item válido ao carrinho.',
            ]);
        }

        $createdSaleId = null;

        try {
            DB::transaction(function () use ($contractor, $data, $paymentMethod, $paymentGateway, $groupedItems, $shopCustomer, $idempotencyKey, &$createdSaleId): void {
                $productIds = $groupedItems
                    ->pluck('product_id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->unique()
                    ->values();

                $variationIds = $groupedItems
                    ->pluck('variation_id')
                    ->filter(static fn (mixed $id): bool => (int) $id > 0)
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values();

                $products = Product::query()
                    ->where('contractor_id', $contractor->id)
                    ->where('is_active', true)
                    ->whereIn('id', $productIds->all())
                    ->with([
                        'variations:id,product_id,name,sku,sale_price,stock_quantity,is_active,attributes',
                    ])
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($products->count() !== $productIds->count()) {
                    throw ValidationException::withMessages([
                        'items' => 'Um ou mais produtos são inválidos para esta loja.',
                    ]);
                }

                $variationsById = collect();
                if ($variationIds->isNotEmpty()) {
                    $variationsById = ProductVariation::query()
                        ->where('contractor_id', $contractor->id)
                        ->whereIn('id', $variationIds->all())
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                    if ($variationsById->count() !== $variationIds->count()) {
                        throw ValidationException::withMessages([
                            'items' => 'Uma ou mais variações não são válidas para esta loja.',
                        ]);
                    }
                }

                $subtotal = 0.0;
                $preparedLines = [];

                foreach ($groupedItems as $row) {
                    $safeProductId = (int) ($row['product_id'] ?? 0);
                    $safeVariationId = isset($row['variation_id']) && (int) $row['variation_id'] > 0
                        ? (int) $row['variation_id']
                        : null;
                    $safeQuantity = (int) ($row['quantity'] ?? 0);
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

                    $variation = null;
                    $unitPrice = (float) $product->sale_price;
                    $description = (string) $product->name;
                    $sku = $product->sku;

                    if ($safeVariationId !== null) {
                        /** @var ProductVariation|null $variation */
                        $variation = $variationsById->get($safeVariationId);
                        if (! $variation || (int) $variation->product_id !== (int) $product->id) {
                            throw ValidationException::withMessages([
                                'items' => "Variação inválida para o produto {$product->name}.",
                            ]);
                        }

                        if (! (bool) $variation->is_active) {
                            throw ValidationException::withMessages([
                                'items' => "A variação selecionada para {$product->name} está inativa.",
                            ]);
                        }

                        if ($safeQuantity > (int) $variation->stock_quantity) {
                            throw ValidationException::withMessages([
                                'items' => "Estoque insuficiente para a variação {$variation->name}.",
                            ]);
                        }

                        $unitPrice = (float) $variation->sale_price;
                        $description = trim($product->name.' - '.$variation->name);
                        $sku = $variation->sku ?: $product->sku;
                    } else {
                        $hasActiveVariations = $product->variations
                            ->contains(static fn (ProductVariation $productVariation): bool => (bool) $productVariation->is_active);

                        if ($hasActiveVariations) {
                            throw ValidationException::withMessages([
                                'items' => "Selecione uma variação para o produto {$product->name}.",
                            ]);
                        }

                        if ($safeQuantity > (int) $product->stock_quantity) {
                            throw ValidationException::withMessages([
                                'items' => "Estoque insuficiente para o produto {$product->name}.",
                            ]);
                        }
                    }

                    $lineTotal = round($unitPrice * $safeQuantity, 2);
                    $subtotal += $lineTotal;

                    $preparedLines[] = [
                        'product' => $product,
                        'variation' => $variation,
                        'description' => $description,
                        'sku' => $sku,
                        'quantity' => $safeQuantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $shippingQuote = $this->resolveShippingQuote($contractor, $data, $subtotal);
                $shippingAmount = (float) $shippingQuote['amount'];
                $baseAmount = round($subtotal + $shippingAmount, 2);
                $paymentFeeSnapshot = PaymentFeeSnapshot::fromPaymentMethod($paymentMethod);
                $paymentFeeAmount = PaymentFeeSnapshot::resolveFeeAmount($baseAmount, $paymentFeeSnapshot);
                $surchargeAmount = round($shippingAmount + $paymentFeeAmount, 2);
                $total = round($baseAmount + $paymentFeeAmount, 2);

                if ($total <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'O valor total do pedido precisa ser maior que zero.',
                    ]);
                }

                $usesIntegratedGateway = $paymentMethod
                    && $paymentGateway
                    && $this->isIntegratedGatewayCheckout($paymentGateway);

                if (
                    $usesIntegratedGateway
                    && ! $this->supportsIntegratedGatewayCheckoutNow($paymentMethod, $paymentGateway)
                ) {
                    throw ValidationException::withMessages([
                        'payment_method_id' => $this->resolveUnsupportedIntegratedCheckoutMessage($paymentMethod),
                    ]);
                }

                $checkoutMode = $usesIntegratedGateway ? 'integrated' : 'manual';

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
                    'checkout_idempotency_key' => $idempotencyKey,
                    'source' => Sale::SOURCE_CATALOG,
                    'status' => Sale::STATUS_PENDING_CONFIRMATION,
                    'subtotal_amount' => $subtotal,
                    'discount_amount' => 0,
                    'surcharge_amount' => $surchargeAmount,
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
                        'checkout_idempotency_key' => $idempotencyKey,
                        'checkout_mode' => $checkoutMode,
                        'customer_name' => $data['customer_name'] ?? $shopCustomer->name,
                        'customer_phone' => $this->normalizePhone($data['customer_phone'] ?? $shopCustomer->phone),
                        'customer_email' => $data['customer_email'] ?? $shopCustomer->email,
                        'shipping_mode' => $shippingQuote['mode'],
                        'shipping_label' => $shippingQuote['label'],
                        'shipping_amount' => $shippingAmount,
                        'payment_method_snapshot' => $paymentFeeSnapshot,
                        'charges' => [
                            'subtotal_amount' => round($subtotal, 2),
                            'shipping_amount' => round($shippingAmount, 2),
                            'payment_fee_amount' => round($paymentFeeAmount, 2),
                            'payment_fee_fixed' => round((float) ($paymentFeeSnapshot['fee_fixed'] ?? 0), 2),
                            'payment_fee_percent' => round((float) ($paymentFeeSnapshot['fee_percent'] ?? 0), 2),
                            'base_amount_before_fee' => round($baseAmount, 2),
                            'surcharge_amount' => round($surchargeAmount, 2),
                            'total_amount' => round($total, 2),
                        ],
                    ],
                ]);

                foreach ($preparedLines as $line) {
                    /** @var Product $product */
                    $product = $line['product'];
                    /** @var ProductVariation|null $variation */
                    $variation = $line['variation'];

                    SaleItem::query()->create([
                        'contractor_id' => $contractor->id,
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'product_variation_id' => $variation?->id,
                        'description' => (string) ($line['description'] ?? $product->name),
                        'sku' => trim((string) ($line['sku'] ?? $product->sku)) !== ''
                            ? trim((string) ($line['sku'] ?? $product->sku))
                            : null,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'discount_amount' => 0,
                        'total_amount' => $line['line_total'],
                        'metadata' => $variation ? [
                            'variation_id' => (int) $variation->id,
                            'variation_name' => (string) $variation->name,
                            'variation_sku' => $variation->sku ? (string) $variation->sku : null,
                            'variation_attributes' => is_array($variation->attributes) ? $variation->attributes : [],
                        ] : null,
                    ]);
                }

                if ($paymentMethod) {
                    $salePayment = SalePayment::query()->create([
                        'contractor_id' => $contractor->id,
                        'sale_id' => $sale->id,
                        'payment_method_id' => $paymentMethod->id,
                        'payment_gateway_id' => $paymentGateway?->id,
                        'status' => SalePayment::STATUS_PENDING,
                        'amount' => $total,
                        'installments' => null,
                        'paid_at' => null,
                        'metadata' => [
                            'checkout_mode' => $checkoutMode,
                            'fee_snapshot' => [
                                'base_amount' => round($baseAmount, 2),
                                'fee_amount' => round($paymentFeeAmount, 2),
                                'fee_fixed' => round((float) ($paymentFeeSnapshot['fee_fixed'] ?? 0), 2),
                                'fee_percent' => round((float) ($paymentFeeSnapshot['fee_percent'] ?? 0), 2),
                                'payment_method_code' => $paymentFeeSnapshot['payment_method_code'],
                                'payment_method_name' => $paymentFeeSnapshot['payment_method_name'],
                            ],
                        ],
                    ]);

                    if ($paymentGateway && $this->supportsIntegratedGatewayCheckoutNow($paymentMethod, $paymentGateway)) {
                        $this->createCheckoutIntegratedIntent(
                            $contractor,
                            $sale,
                            $salePayment,
                            $paymentMethod,
                            $paymentGateway,
                            $idempotencyKey
                        );
                    }
                }

                $createdSaleId = (int) $sale->id;
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicateCheckoutIdempotencyException($exception)) {
                throw $exception;
            }

            return back()->with('status', 'Pedido já recebido. Estamos processando sua solicitação.');
        }

        $checkoutPayment = null;
        $checkoutManual = null;
        if ($createdSaleId) {
            $createdSale = Sale::query()->find($createdSaleId);
            if ($createdSale) {
                app(OrderNotificationService::class)->notifyOrderCreated($createdSale);
                $checkoutPayment = $this->resolveCheckoutPaymentPayload($createdSale);
                if (! ($checkoutPayment !== null && $this->isIntegratedPaymentPayload($checkoutPayment))) {
                    $checkoutManual = $this->resolveManualCheckoutPayload($contractor, $createdSale);
                }
            }
        }

        $redirect = back()->with('status', 'Pedido enviado com sucesso. Aguarde a confirmação da loja.');
        if ($checkoutPayment !== null && $this->isIntegratedPaymentPayload($checkoutPayment)) {
            $redirect = $redirect->with('checkout_payment', $checkoutPayment);
        } elseif ($checkoutManual !== null) {
            $redirect = $redirect->with('checkout_manual', $checkoutManual);
        }

        return $redirect;
    }

    public function checkoutPaymentStatus(Request $request, string $slug, int $sale): JsonResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        $shopCustomer = $this->resolveCurrentShopCustomerForContractor($contractor);

        if (! $shopCustomer) {
            return response()->json([
                'ok' => false,
                'message' => 'Acesso não autorizado.',
            ], 403);
        }

        $saleModel = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', $sale)
            ->whereIn('source', [Sale::SOURCE_CATALOG, Sale::SOURCE_ORDER])
            ->where(function ($query) use ($shopCustomer): void {
                $query->where('shop_customer_id', $shopCustomer->id);

                if ($shopCustomer->client_id) {
                    $query->orWhere('client_id', $shopCustomer->client_id);
                }
            })
            ->first();

        if (! $saleModel) {
            return response()->json([
                'ok' => false,
                'message' => 'Pedido não encontrado.',
            ], 404);
        }

        $checkoutPayment = $this->resolveCheckoutPaymentPayload($saleModel);
        if ($checkoutPayment === null || ! $this->isIntegratedPaymentPayload($checkoutPayment)) {
            return response()->json([
                'ok' => false,
                'message' => 'Cobrança integrada não localizada para este pedido.',
            ], 404);
        }

        if ($this->shouldReconcileCheckoutIntegratedPayment($checkoutPayment)) {
            $this->reconcileCheckoutIntegratedPayment($saleModel, $checkoutPayment);
            $checkoutPayment = $this->resolveCheckoutPaymentPayload($saleModel->refresh()) ?? $checkoutPayment;
        }

        return response()->json([
            'ok' => true,
            'payment' => $checkoutPayment,
        ]);
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
        $images = $product->images
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($image): array => [
                'id' => (int) $image->id,
                'image_url' => $this->normalizePublicAssetUrl($image->image_url ?: $image->image_path),
                'sort_order' => (int) $image->sort_order,
            ])
            ->filter(static fn (array $row): bool => trim((string) ($row['image_url'] ?? '')) !== '')
            ->values()
            ->all();

        if ($images === [] && trim((string) $product->image_url) !== '') {
            $images[] = [
                'id' => 0,
                'image_url' => $this->normalizePublicAssetUrl($product->image_url),
                'sort_order' => 0,
            ];
        }

        $activeVariations = $product->variations
            ->filter(static fn (ProductVariation $variation): bool => (bool) $variation->is_active)
            ->values();

        $variations = $activeVariations
            ->map(static fn (ProductVariation $variation): array => [
                'id' => (int) $variation->id,
                'name' => (string) $variation->name,
                'sku' => $variation->sku ? (string) $variation->sku : null,
                'sale_price' => round((float) $variation->sale_price, 2),
                'stock_quantity' => (int) $variation->stock_quantity,
                'attributes' => is_array($variation->attributes) ? $variation->attributes : [],
            ])
            ->all();

        $variationStock = (int) $activeVariations
            ->sum(static fn (ProductVariation $variation): int => (int) $variation->stock_quantity);
        $hasVariations = $activeVariations->isNotEmpty();
        $baseSalePrice = $hasVariations
            ? (float) $activeVariations->min(static fn (ProductVariation $variation): float => (float) $variation->sale_price)
            : round((float) $product->sale_price, 2);

        return [
            'id' => $product->id,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'category_parent_id' => $product->category?->parent_id ? (int) $product->category?->parent_id : null,
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'sale_price' => round($baseSalePrice, 2),
            'stock_quantity' => $hasVariations ? $variationStock : (int) $product->stock_quantity,
            'unit' => $product->unit,
            'image_url' => $this->normalizePublicAssetUrl($product->image_url),
            'images' => $images,
            'variations' => $variations,
            'has_variations' => $hasVariations,
        ];
    }

    /**
     * @return array{categories: array<int, array<string, mixed>>, services: array<int, array<string, mixed>>}
     */
    private function resolveServiceCatalogPayload(Contractor $contractor): array
    {
        $categories = ServiceCategory::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->withCount([
                'services as services_count' => static fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (ServiceCategory $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'services_count' => (int) ($category->services_count ?? 0),
            ])
            ->values()
            ->all();

        $fallbackImage = $this->normalizePublicAssetUrl($contractor->brand_avatar_url ?: $contractor->brand_logo_url);

        $services = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderByDesc('updated_at')
            ->orderBy('name')
            ->get([
                'id',
                'service_category_id',
                'name',
                'code',
                'description',
                'image_url',
                'duration_minutes',
                'base_price',
            ])
            ->map(function (ServiceCatalog $service) use ($fallbackImage): array {
                $duration = max(15, (int) ($service->duration_minutes ?? 60));

                return [
                    'id' => (int) $service->id,
                    'service_category_id' => $service->service_category_id ? (int) $service->service_category_id : null,
                    'category_name' => $service->category?->name ? (string) $service->category->name : 'Serviço',
                    'name' => (string) $service->name,
                    'code' => (string) ($service->code ?? ''),
                    'description' => trim((string) ($service->description ?? '')),
                    'duration_minutes' => $duration,
                    'duration_label' => $duration >= 60
                        ? rtrim(rtrim(number_format($duration / 60, 1, ',', '.'), '0'), ',').'h'
                        : $duration.' min',
                    'base_price' => round((float) $service->base_price, 2),
                    'rating' => 5.0,
                    'reviews_label' => 'Novos atendimentos',
                    'coupon_label' => '5% OFF no primeiro agendamento',
                    'image_url' => $this->normalizePublicAssetUrl((string) ($service->image_url ?? '')) ?: $fallbackImage,
                ];
            })
            ->values()
            ->all();

        return [
            'categories' => $categories,
            'services' => $services,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $services
     * @return array<string, mixed>
     */
    private function resolveServiceStorefrontPayload(Contractor $contractor, Collection $services): array
    {
        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $storefront = StorefrontSettings::normalize($contractor, $settings['shop_storefront'] ?? []);

        $availableServiceIds = $services
            ->map(static fn (array $service): int => (int) ($service['id'] ?? 0))
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $availableIdMap = array_flip($availableServiceIds);

        $promotionIds = collect($storefront['promotions']['service_ids'] ?? [])
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => isset($availableIdMap[$id]))
            ->unique()
            ->values()
            ->all();

        if ($promotionIds === []) {
            $promotionIds = array_slice($availableServiceIds, 0, 8);
        }

        $storefront['promotions']['service_ids'] = $promotionIds;

        return $storefront;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveServiceBookingsPayload(Contractor $contractor): array
    {
        $customer = $this->resolveCurrentShopCustomerForContractor($contractor);
        if (! $customer) {
            return [];
        }

        if ($contractor->requiresEmailVerification() && ! $customer->hasVerifiedEmail()) {
            return [];
        }

        return ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->where(function ($query) use ($customer): void {
                $query->where('metadata->shop_customer_id', (int) $customer->id);

                if ($customer->client_id) {
                    $query->orWhere('client_id', $customer->client_id);
                }
            })
            ->with([
                'service:id,name,duration_minutes',
                'appointments:id,service_order_id,starts_at,ends_at,status',
            ])
            ->orderByDesc('scheduled_for')
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(fn (ServiceOrder $order): array => $this->toServiceBookingPayload($order))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toServiceBookingPayload(ServiceOrder $order): array
    {
        $appointment = $order->appointments
            ->sortByDesc(static fn (ServiceAppointment $item) => (string) ($item->starts_at ?? $item->created_at))
            ->first();
        $statusMeta = $this->resolveServiceOrderStatusMeta((string) $order->status);
        $scheduled = $order->scheduled_for ?: $appointment?->starts_at;
        $endsAt = $appointment?->ends_at;

        return [
            'id' => (int) $order->id,
            'code' => (string) $order->code,
            'title' => (string) $order->title,
            'service_name' => $order->service?->name ? (string) $order->service->name : 'Serviço',
            'scheduled_for' => $scheduled ? $scheduled->format('Y-m-d\\TH:i') : null,
            'scheduled_label' => $scheduled ? $scheduled->format('d/m/Y H:i') : 'Horário a confirmar',
            'ends_at' => $endsAt ? $endsAt->format('Y-m-d\\TH:i') : null,
            'status' => $statusMeta,
            'estimated_amount' => round((float) ($order->estimated_amount ?? 0), 2),
            'final_amount' => round((float) ($order->final_amount ?? 0), 2),
            'notes' => trim((string) ($order->description ?? '')),
        ];
    }

    /**
     * @return array{value: string, label: string, tone: string}
     */
    private function resolveServiceOrderStatusMeta(string $status): array
    {
        return match ($status) {
            ServiceOrder::STATUS_OPEN => ['value' => $status, 'label' => 'Agendado', 'tone' => 'bg-amber-100 text-amber-700'],
            ServiceOrder::STATUS_IN_PROGRESS => ['value' => $status, 'label' => 'Em atendimento', 'tone' => 'bg-blue-100 text-blue-700'],
            ServiceOrder::STATUS_WAITING => ['value' => $status, 'label' => 'Aguardando', 'tone' => 'bg-slate-100 text-slate-700'],
            ServiceOrder::STATUS_DONE => ['value' => $status, 'label' => 'Concluído', 'tone' => 'bg-emerald-100 text-emerald-700'],
            ServiceOrder::STATUS_CANCELLED => ['value' => $status, 'label' => 'Cancelado', 'tone' => 'bg-rose-100 text-rose-700'],
            default => ['value' => $status, 'label' => 'Em andamento', 'tone' => 'bg-slate-100 text-slate-700'],
        };
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
     * @return array<int, array{
     *   id:int,
     *   name:string,
     *   code:string,
     *   fee_fixed:float,
     *   fee_percent:float,
     *   checkout_mode:string
     * }>
     */
    private function resolveCheckoutPaymentMethods(Contractor $contractor): array
    {
        $hasActiveMercadoPagoGateway = PaymentGateway::query()
            ->where('contractor_id', $contractor->id)
            ->where('provider', PaymentGateway::PROVIDER_MERCADO_PAGO)
            ->where('is_active', true)
            ->exists();

        return PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->with('paymentGateway:id,provider,is_active')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'fee_fixed', 'fee_percent', 'payment_gateway_id', 'settings'])
            ->map(static function (PaymentMethod $method) use ($hasActiveMercadoPagoGateway): array {
                $gateway = $method->paymentGateway;
                $settings = is_array($method->settings) ? $method->settings : [];
                $integrationProvider = strtolower(trim((string) data_get($settings, 'gateway_integration.provider', '')));
                $isIntegratedBySettings = $integrationProvider !== ''
                    && $integrationProvider !== PaymentGateway::PROVIDER_MANUAL
                    && $hasActiveMercadoPagoGateway;
                $isIntegrated = $gateway
                    && (bool) $gateway->is_active
                    && (string) $gateway->provider !== PaymentGateway::PROVIDER_MANUAL;

                return [
                    'id' => (int) $method->id,
                    'name' => (string) $method->name,
                    'code' => (string) $method->code,
                    'fee_fixed' => round((float) ($method->fee_fixed ?? 0), 2),
                    'fee_percent' => round((float) ($method->fee_percent ?? 0), 2),
                    'checkout_mode' => ($isIntegrated || $isIntegratedBySettings) ? 'integrated' : 'manual',
                ];
            })
            ->values()
            ->all();
    }

    private function resolveCheckoutPaymentGateway(Contractor $contractor, ?PaymentMethod $paymentMethod): ?PaymentGateway
    {
        if (! $paymentMethod) {
            return null;
        }

        $gatewayId = (int) ($paymentMethod->payment_gateway_id ?? 0);
        if ($gatewayId > 0) {
            $gateway = PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $gatewayId)
                ->where('is_active', true)
                ->first();

            if ($gateway) {
                return $gateway;
            }
        }

        if (! $this->methodLooksIntegratedForMercadoPago($paymentMethod)) {
            return null;
        }

        return PaymentGateway::query()
            ->where('contractor_id', $contractor->id)
            ->where('provider', PaymentGateway::PROVIDER_MERCADO_PAGO)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->latest('id')
            ->first();
    }

    private function methodLooksIntegratedForMercadoPago(PaymentMethod $paymentMethod): bool
    {
        $settings = is_array($paymentMethod->settings) ? $paymentMethod->settings : [];
        $integrationProvider = strtolower(trim((string) data_get($settings, 'gateway_integration.provider', '')));

        if ($integrationProvider === PaymentGateway::PROVIDER_MERCADO_PAGO) {
            return true;
        }

        if ($integrationProvider !== '') {
            return false;
        }

        return (int) ($paymentMethod->payment_gateway_id ?? 0) > 0
            && in_array(strtolower(trim((string) $paymentMethod->code)), PaymentMethod::INTEGRATED_CODES, true);
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
     * @param  Collection<int, Product>  $products
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
     * @param  array<string, mixed>|null  $storefront
     * @return array<string, mixed>
     */
    private function resolveStoreAvailabilityPayload(Contractor $contractor, ?array $storefront = null): array
    {
        $normalizedStorefront = is_array($storefront)
            ? StorefrontSettings::normalize($contractor, $storefront)
            : StorefrontSettings::normalize(
                $contractor,
                (is_array($contractor->settings) ? $contractor->settings['shop_storefront'] ?? [] : [])
            );

        $timezone = $this->resolveContractorTimezone($contractor);
        $now = Carbon::now($timezone);
        $businessHours = StorefrontSettings::normalizeBusinessHours($normalizedStorefront['business_hours'] ?? []);
        $todayKey = $this->resolveBusinessDayKey($now);
        $todayHours = is_array($businessHours[$todayKey] ?? null)
            ? $businessHours[$todayKey]
            : ['enabled' => false, 'open' => '00:00', 'close' => '23:59'];

        $storeOnline = (bool) ($normalizedStorefront['store_online'] ?? true);
        $isOpenNow = $storeOnline && $this->isTimeWithinWindow($now, $todayHours);
        $nextOpen = $this->resolveNextOpeningFromHours($businessHours, $now);

        $statusLabel = 'Aberto agora';
        $message = '';

        if (! $storeOnline) {
            $statusLabel = 'Loja em manutenção';
            $message = trim((string) ($normalizedStorefront['offline_message'] ?? ''))
                ?: 'Loja temporariamente indisponível. Tente novamente mais tarde.';
        } elseif (! $isOpenNow) {
            $statusLabel = 'Fechado agora';
            $message = $nextOpen
                ? "Loja fechada no momento. {$nextOpen['label']}."
                : 'Loja fechada no momento.';
        }

        return [
            'store_online' => $storeOnline,
            'is_open_now' => $isOpenNow,
            'can_checkout' => $storeOnline && $isOpenNow,
            'can_book' => $storeOnline,
            'message' => $message,
            'status_label' => $statusLabel,
            'next_open_label' => $nextOpen['label'] ?? null,
            'timezone' => $timezone,
            'current_day' => $todayKey,
            'current_time' => $now->format('H:i'),
            'business_hours' => $businessHours,
            'booking_slots' => $this->resolveBookingSlotsFromBusinessHours($businessHours, $now),
        ];
    }

    /**
     * @param  array<string, array{enabled: bool, open: string, close: string}>  $businessHours
     * @return array{allowed: bool, message: string}
     */
    private function validateDateTimeAgainstBusinessHours(Carbon $dateTime, array $businessHours, int $durationMinutes = 0): array
    {
        $dayKey = $this->resolveBusinessDayKey($dateTime);
        $dayLabel = $this->resolveBusinessDayLabel($dayKey);
        $dayHours = is_array($businessHours[$dayKey] ?? null)
            ? $businessHours[$dayKey]
            : ['enabled' => false, 'open' => '00:00', 'close' => '23:59'];

        if (! (bool) ($dayHours['enabled'] ?? false)) {
            return [
                'allowed' => false,
                'message' => "Não há atendimento em {$dayLabel}.",
            ];
        }

        $open = trim((string) ($dayHours['open'] ?? '00:00'));
        $close = trim((string) ($dayHours['close'] ?? '23:59'));
        if ($this->hourToMinutes($close) <= $this->hourToMinutes($open)) {
            return [
                'allowed' => false,
                'message' => "Horário indisponível em {$dayLabel}.",
            ];
        }

        $slotStartMinutes = ((int) $dateTime->format('H') * 60) + (int) $dateTime->format('i');
        $openMinutes = $this->hourToMinutes($open);
        $closeMinutes = $this->hourToMinutes($close);

        if ($slotStartMinutes < $openMinutes || $slotStartMinutes >= $closeMinutes) {
            return [
                'allowed' => false,
                'message' => "Escolha um horário entre {$open} e {$close} em {$dayLabel}.",
            ];
        }

        $safeDuration = max(1, $durationMinutes);
        if (($slotStartMinutes + $safeDuration) > $closeMinutes) {
            return [
                'allowed' => false,
                'message' => "Escolha um horário que termine até {$close} em {$dayLabel}.",
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
        ];
    }

    /**
     * @param  array<string, array{enabled: bool, open: string, close: string}>  $businessHours
     * @return array<int, array{
     *   day_key: string,
     *   day_label: string,
     *   date: string,
     *   label: string,
     *   open: string,
     *   close: string,
     *   slots: array<int, array{value: string, label: string}>
     * }>
     */
    private function resolveBookingSlotsFromBusinessHours(
        array $businessHours,
        Carbon $referenceDateTime,
        int $horizonDays = 14,
        int $slotIntervalMinutes = 30
    ): array {
        $days = [];
        $safeHorizonDays = max(1, min(31, $horizonDays));
        $safeInterval = max(5, min(120, $slotIntervalMinutes));
        $reference = $referenceDateTime->copy()->seconds(0);

        for ($offset = 0; $offset < $safeHorizonDays; $offset++) {
            $date = $reference->copy()->startOfDay()->addDays($offset);
            $dayKey = $this->resolveBusinessDayKey($date);
            $dayLabel = $this->resolveBusinessDayLabel($dayKey);
            $dayHours = is_array($businessHours[$dayKey] ?? null)
                ? $businessHours[$dayKey]
                : ['enabled' => false, 'open' => '00:00', 'close' => '23:59'];

            if (! (bool) ($dayHours['enabled'] ?? false)) {
                continue;
            }

            $open = trim((string) ($dayHours['open'] ?? '00:00'));
            $close = trim((string) ($dayHours['close'] ?? '23:59'));
            $openMinutes = $this->hourToMinutes($open);
            $closeMinutes = $this->hourToMinutes($close);

            if ($closeMinutes <= $openMinutes) {
                continue;
            }

            $slotRows = [];

            for ($minute = $openMinutes; $minute < $closeMinutes; $minute += $safeInterval) {
                $slot = $date->copy()->addMinutes($minute);

                if ($slot->lessThanOrEqualTo($reference)) {
                    continue;
                }

                $slotRows[] = [
                    'value' => $slot->format('Y-m-d\TH:i'),
                    'label' => $slot->format('H:i'),
                ];
            }

            if ($slotRows === []) {
                continue;
            }

            $days[] = [
                'day_key' => $dayKey,
                'day_label' => $dayLabel,
                'date' => $date->format('Y-m-d'),
                'label' => $dayLabel.', '.$date->format('d/m'),
                'open' => $open,
                'close' => $close,
                'slots' => $slotRows,
            ];
        }

        return $days;
    }

    private function resolveContractorTimezone(Contractor $contractor): string
    {
        $fallback = (string) config('app.timezone', 'UTC');
        if (! in_array($fallback, timezone_identifiers_list(), true)) {
            $fallback = 'UTC';
        }

        $candidate = trim((string) ($contractor->timezone ?: ''));
        if ($candidate === '') {
            return $fallback;
        }

        return in_array($candidate, timezone_identifiers_list(), true)
            ? $candidate
            : $fallback;
    }

    /**
     * @param  array{enabled: bool, open: string, close: string}  $dayHours
     */
    private function isTimeWithinWindow(Carbon $dateTime, array $dayHours): bool
    {
        if (! (bool) ($dayHours['enabled'] ?? false)) {
            return false;
        }

        $openMinutes = $this->hourToMinutes((string) ($dayHours['open'] ?? '00:00'));
        $closeMinutes = $this->hourToMinutes((string) ($dayHours['close'] ?? '23:59'));
        if ($closeMinutes <= $openMinutes) {
            return false;
        }

        $currentMinutes = ((int) $dateTime->format('H') * 60) + (int) $dateTime->format('i');

        return $currentMinutes >= $openMinutes && $currentMinutes <= $closeMinutes;
    }

    /**
     * @param  array<string, array{enabled: bool, open: string, close: string}>  $businessHours
     * @return array{day_key: string, open: string, label: string}|null
     */
    private function resolveNextOpeningFromHours(array $businessHours, Carbon $fromDateTime): ?array
    {
        for ($offset = 0; $offset < 7; $offset++) {
            $candidateDate = $fromDateTime->copy()->addDays($offset);
            $dayKey = $this->resolveBusinessDayKey($candidateDate);
            $dayHours = is_array($businessHours[$dayKey] ?? null)
                ? $businessHours[$dayKey]
                : ['enabled' => false, 'open' => '00:00', 'close' => '23:59'];

            if (! (bool) ($dayHours['enabled'] ?? false)) {
                continue;
            }

            $open = trim((string) ($dayHours['open'] ?? '00:00'));
            $close = trim((string) ($dayHours['close'] ?? '23:59'));
            if ($this->hourToMinutes($close) <= $this->hourToMinutes($open)) {
                continue;
            }

            [$openHour, $openMinute] = explode(':', $open) + [0, 0];
            $openingDateTime = $candidateDate->copy()->setTime((int) $openHour, (int) $openMinute);

            if ($offset === 0 && $fromDateTime->greaterThanOrEqualTo($openingDateTime)) {
                continue;
            }

            $dayLabel = $this->resolveBusinessDayLabel($dayKey);
            $label = $offset === 0
                ? "Abre hoje às {$open}"
                : ($offset === 1
                    ? "Abre amanhã às {$open}"
                    : "Abre {$dayLabel} às {$open}");

            return [
                'day_key' => $dayKey,
                'open' => $open,
                'label' => $label,
            ];
        }

        return null;
    }

    private function resolveBusinessDayKey(Carbon $dateTime): string
    {
        $key = strtolower($dateTime->englishDayOfWeek);

        return array_key_exists($key, StorefrontSettings::BUSINESS_HOUR_DAYS)
            ? $key
            : 'monday';
    }

    private function resolveBusinessDayLabel(string $dayKey): string
    {
        return StorefrontSettings::BUSINESS_HOUR_DAYS[$dayKey] ?? 'Dia';
    }

    private function hourToMinutes(string $time): int
    {
        $safe = trim($time);
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $safe) !== 1) {
            return 0;
        }

        [$hour, $minute] = explode(':', $safe) + [0, 0];

        return ((int) $hour * 60) + (int) $minute;
    }

    /**
     * @param  array<string, mixed>  $data
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
     * @param  array<string, mixed>  $data
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
     * @return array{url: string|null, message: string|null}
     */
    private function resolveServiceBookingWhatsappPayload(Contractor $contractor, ?ServiceOrder $order): array
    {
        if (! $order) {
            return [
                'url' => null,
                'message' => null,
            ];
        }

        $message = $this->buildServiceBookingWhatsappMessage($order);
        $whatsappPhone = $this->normalizeWhatsappPhone($contractor->phone);
        $whatsappUrl = $whatsappPhone !== null
            ? 'https://wa.me/'.$whatsappPhone.'?text='.rawurlencode($message)
            : null;

        return [
            'url' => $whatsappUrl,
            'message' => $message,
        ];
    }

    private function buildServiceBookingWhatsappMessage(ServiceOrder $order): string
    {
        $serviceName = trim((string) ($order->service?->name ?? $order->title));
        $clientName = trim((string) ($order->client?->name ?? data_get($order->metadata, 'customer_name', '')));
        $scheduledFor = $order->scheduled_for?->format('d/m/Y H:i') ?? 'A confirmar';

        $lines = [
            "Olá! Acabei de enviar o agendamento {$order->code}.",
        ];

        if ($serviceName !== '') {
            $lines[] = "Serviço: {$serviceName}.";
        }

        if ($clientName !== '') {
            $lines[] = "Cliente: {$clientName}.";
        }

        $lines[] = "Data e hora: {$scheduledFor}.";

        return implode("\n", $lines);
    }

    private function notifyServiceBookingCreated(Contractor $contractor, ServiceOrder $order, ?string $whatsappUrl): void
    {
        $order->loadMissing([
            'contractor:id,slug,settings',
            'service:id,name',
            'client:id,name',
        ]);

        $title = 'Novo agendamento recebido';
        $serviceName = trim((string) ($order->service?->name ?? $order->title));
        $customerName = trim((string) ($order->client?->name ?? data_get($order->metadata, 'customer_name', '')));
        $messageParts = [
            "Agendamento {$order->code} registrado",
        ];

        if ($serviceName !== '') {
            $messageParts[] = "serviço {$serviceName}";
        }

        if ($customerName !== '') {
            $messageParts[] = "cliente {$customerName}";
        }

        $message = implode(' • ', $messageParts).'.';
        $targetUrl = '/app/services/orders';

        $recipients = $contractor->users()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new ServiceBookingCreatedNotification($order, $title, $message, $targetUrl, $whatsappUrl),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveManualCheckoutPayload(Contractor $contractor, Sale $sale): ?array
    {
        $sale->loadMissing([
            'payments:id,sale_id,payment_method_id',
            'payments.paymentMethod:id,name',
        ]);

        /** @var SalePayment|null $latestPayment */
        $latestPayment = $sale->payments
            ->sortByDesc('id')
            ->first();

        $paymentMethodName = trim((string) ($latestPayment?->paymentMethod?->name ?? ''));
        if ($paymentMethodName === '') {
            $paymentMethodName = 'A combinar com a loja';
        }

        $message = $this->buildManualCheckoutMessage($sale, $paymentMethodName);
        $whatsappPhone = $this->normalizeWhatsappPhone($contractor->phone);
        $whatsappUrl = $whatsappPhone !== null
            ? 'https://wa.me/'.$whatsappPhone.'?text='.rawurlencode($message)
            : null;

        return [
            'sale_id' => (int) $sale->id,
            'sale_code' => (string) $sale->code,
            'total_amount' => round((float) $sale->total_amount, 2),
            'payment_method_name' => $paymentMethodName,
            'contractor_phone' => $contractor->phone ? (string) $contractor->phone : null,
            'whatsapp_url' => $whatsappUrl,
            'message' => $message,
        ];
    }

    private function buildManualCheckoutMessage(Sale $sale, string $paymentMethodName): string
    {
        $formattedTotal = 'R$ '.number_format((float) $sale->total_amount, 2, ',', '.');

        return "Olá! Acabei de finalizar o pedido {$sale->code}.\n"
            ."Total do pedido: {$formattedTotal}.\n"
            ."Forma de pagamento: {$paymentMethodName}.\n"
            .'Pode confirmar para mim, por favor?';
    }

    private function normalizeWhatsappPhone(?string $value): ?string
    {
        $digits = $this->normalizePhone($value);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '55') && in_array(strlen($digits), [12, 13], true)) {
            return $digits;
        }

        if (in_array(strlen($digits), [10, 11], true)) {
            return '55'.$digits;
        }

        return null;
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
                'number' => (string) ($customer->number ?? ''),
                'complement' => (string) ($customer->complement ?? ''),
                'neighborhood' => (string) ($customer->neighborhood ?? ''),
                'city' => (string) ($customer->city ?? ''),
                'state' => (string) ($customer->state ?? ''),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveShopAccountPayload(Contractor $contractor): ?array
    {
        $customer = $this->resolveCurrentShopCustomerForContractor($contractor);
        if (! $customer) {
            return null;
        }

        $requiresEmailVerification = $contractor->requiresEmailVerification();
        if ($requiresEmailVerification && ! $customer->hasVerifiedEmail()) {
            return [
                'orders' => [],
                'notifications' => [],
                'notifications_unread_count' => 0,
            ];
        }

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
                'payments:id,sale_id,status,amount,payment_method_id,transaction_reference,gateway_payload,metadata',
                'payments.paymentMethod:id,code,name',
            ])
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (Sale $sale): array => $this->toShopOrderPayload($sale))
            ->values()
            ->all();

        $statusNotifications = $customer->notifications()
            ->latest()
            ->limit(40)
            ->get()
            ->filter(fn (DatabaseNotification $notification): bool => $this->isShopStatusNotification($notification))
            ->take(20)
            ->values();

        $notifications = $statusNotifications
            ->map(fn (DatabaseNotification $notification): array => $this->toShopStatusNotificationPayload($notification))
            ->values()
            ->all();

        $notificationsUnreadCount = $statusNotifications
            ->filter(fn (DatabaseNotification $notification): bool => $notification->read_at === null)
            ->count();

        return [
            'orders' => $orders,
            'notifications' => $notifications,
            'notifications_unread_count' => $notificationsUnreadCount,
        ];
    }

    private function isShopStatusNotification(DatabaseNotification $notification): bool
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return isset($data['order_status'])
            || isset($data['service_order_status']);
    }

    /**
     * @return array<string, mixed>
     */
    private function toShopStatusNotificationPayload(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => (string) $notification->id,
            'title' => trim((string) ($data['title'] ?? 'Atualização')),
            'message' => trim((string) ($data['message'] ?? '')),
            'target_url' => trim((string) ($data['target_url'] ?? '')),
            'created_at' => optional($notification->created_at)?->toIso8601String(),
            'created_at_label' => optional($notification->created_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'read_at' => optional($notification->read_at)?->toIso8601String(),
            'is_read' => $notification->read_at !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toShopOrderPayload(Sale $sale): array
    {
        /** @var SalePayment|null $latestPayment */
        $latestPayment = $sale->payments
            ->sortByDesc('id')
            ->first();
        $statusValue = (string) $sale->status;
        $paymentMethods = $sale->payments
            ->map(static fn (SalePayment $payment): ?string => $payment->paymentMethod?->name)
            ->filter()
            ->values();

        return [
            'id' => (int) $sale->id,
            'code' => (string) $sale->code,
            'created_at' => optional($sale->created_at)->format('d/m/Y H:i'),
            'status' => [
                'value' => $statusValue,
                'label' => $this->resolveSaleStatusLabel($statusValue),
                'tone' => $this->resolveSaleStatusTone($statusValue),
            ],
            'total_amount' => (float) $sale->total_amount,
            'items' => $sale->items->map(static fn (SaleItem $item): array => [
                'description' => (string) $item->description,
                'quantity' => (int) $item->quantity,
                'total_amount' => (float) $item->total_amount,
            ])->values()->all(),
            'payment_label' => $paymentMethods->isNotEmpty() ? $paymentMethods->implode(' + ') : 'Não informado',
            'payment' => $this->toShopOrderPaymentPayload($sale, $latestPayment),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toShopOrderPaymentPayload(Sale $sale, ?SalePayment $salePayment): ?array
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
        $paymentMethodCode = strtolower(trim((string) (
            $salePayment->paymentMethod?->code
            ?? ($paymentIntent['payment_method_code'] ?? ($saleIntent['payment_method_code'] ?? ''))
        )));
        $paymentMethodName = trim((string) (
            $salePayment->paymentMethod?->name
            ?? ($paymentIntent['payment_method_name'] ?? ($saleIntent['payment_method_name'] ?? ''))
        ));
        $provider = trim((string) (data_get($salePayment->metadata ?? [], 'provider')
            ?? data_get($salePayment, 'paymentGateway.provider')
            ?? ($paymentIntent['provider'] ?? ($saleIntent['provider'] ?? ''))));
        $ticketUrl = trim((string) ($paymentIntent['ticket_url'] ?? ($saleIntent['ticket_url'] ?? '')));
        $qrCode = trim((string) ($paymentIntent['qr_code'] ?? ($saleIntent['qr_code'] ?? '')));
        $qrCodeBase64 = trim((string) ($paymentIntent['qr_code_base64'] ?? ($saleIntent['qr_code_base64'] ?? '')));
        $checkoutUrl = trim((string) ($paymentIntent['checkout_url'] ?? ($saleIntent['checkout_url'] ?? '')));
        $expiresAt = $paymentIntent['date_of_expiration'] ?? ($saleIntent['date_of_expiration'] ?? null);

        $payload = [
            'status' => (string) $salePayment->status,
            'status_label' => $this->resolveSalePaymentStatusLabel((string) $salePayment->status),
            'method_code' => $paymentMethodCode,
            'method_name' => $paymentMethodName,
            'provider' => $provider,
            'transaction_reference' => $transactionReference,
            'amount' => round((float) $salePayment->amount, 2),
            'ticket_url' => $ticketUrl,
            'checkout_url' => $checkoutUrl,
            'qr_code' => $qrCode,
            'qr_code_base64' => $qrCodeBase64,
            'expires_at' => $expiresAt,
        ];

        $payload['is_pix'] = $this->isPixPaymentPayload([
            'provider' => $provider,
            'payment_method_code' => $paymentMethodCode,
            'ticket_url' => $ticketUrl,
            'checkout_url' => $checkoutUrl,
            'qr_code' => $qrCode,
            'qr_code_base64' => $qrCodeBase64,
            'transaction_reference' => $transactionReference,
        ]);
        $payload['is_integrated'] = $this->isIntegratedPaymentPayload([
            'provider' => $provider,
            'payment_method_code' => $paymentMethodCode,
            'checkout_url' => $checkoutUrl,
            'qr_code' => $qrCode,
            'qr_code_base64' => $qrCodeBase64,
            'ticket_url' => $ticketUrl,
            'transaction_reference' => $transactionReference,
        ]);

        return $payload;
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

    private function generateServiceOrderCode(Contractor $contractor): string
    {
        do {
            $code = 'SVC-'.now()->format('Ymd-His').'-'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

            $exists = ServiceOrder::query()
                ->where('contractor_id', $contractor->id)
                ->where('code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }

    private function isIntegratedGatewayCheckout(PaymentGateway $paymentGateway): bool
    {
        return (bool) $paymentGateway->is_active
            && (string) $paymentGateway->provider !== PaymentGateway::PROVIDER_MANUAL;
    }

    private function resolveIntegratedMethodCode(PaymentMethod $paymentMethod): ?string
    {
        $settings = is_array($paymentMethod->settings) ? $paymentMethod->settings : [];
        $integrationCode = strtolower(trim((string) data_get($settings, 'gateway_integration.payment_method_code', '')));
        if (in_array($integrationCode, PaymentMethod::INTEGRATED_CODES, true)) {
            return $integrationCode;
        }

        $code = strtolower(trim((string) $paymentMethod->code));
        if (in_array($code, PaymentMethod::INTEGRATED_CODES, true)) {
            return $code;
        }

        if (str_contains($code, 'pix')) {
            return PaymentMethod::CODE_PIX;
        }
        if (str_contains($code, 'boleto')) {
            return PaymentMethod::CODE_BOLETO;
        }
        if (str_contains($code, 'credit') || str_contains($code, 'credito')) {
            return PaymentMethod::CODE_CREDIT_CARD;
        }
        if (str_contains($code, 'debit') || str_contains($code, 'debito')) {
            return PaymentMethod::CODE_DEBIT_CARD;
        }

        return null;
    }

    private function supportsIntegratedGatewayCheckoutNow(PaymentMethod $paymentMethod, PaymentGateway $paymentGateway): bool
    {
        if ((string) $paymentGateway->provider !== PaymentGateway::PROVIDER_MERCADO_PAGO) {
            return false;
        }

        return $this->resolveIntegratedMethodCode($paymentMethod) !== null;
    }

    private function resolveUnsupportedIntegratedCheckoutMessage(PaymentMethod $paymentMethod): string
    {
        $code = $this->resolveIntegratedMethodCode($paymentMethod);
        if ($code !== null) {
            return 'Esta forma automática está temporariamente indisponível. Tente novamente em instantes.';
        }

        return 'Esta forma de pagamento integrada não está disponível para checkout online.';
    }

    private function createCheckoutIntegratedIntent(
        Contractor $contractor,
        Sale $sale,
        SalePayment $salePayment,
        PaymentMethod $paymentMethod,
        PaymentGateway $paymentGateway,
        ?string $checkoutIdempotencyKey
    ): void {
        $methodCode = $this->resolveIntegratedMethodCode($paymentMethod);
        if ($methodCode === null) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Forma de pagamento integrada inválida para este checkout.',
            ]);
        }

        $credentials = is_array($paymentGateway->credentials) ? $paymentGateway->credentials : [];
        $webhookSecret = trim((string) ($credentials['webhook_secret'] ?? ''));

        $notificationUrl = route('shop.payments.webhook', [
            'slug' => $contractor->slug,
            'provider' => $paymentGateway->provider,
        ]);

        if ($webhookSecret !== '') {
            $notificationUrl .= (str_contains($notificationUrl, '?') ? '&' : '?')
                .'token='.rawurlencode($webhookSecret);
        }

        $notificationUrl = $this->normalizePixWebhookNotificationUrl($notificationUrl);

        $payerEmail = trim((string) data_get($sale->metadata, 'customer_email', ''));
        $payerName = trim((string) data_get($sale->metadata, 'customer_name', ''));
        if ($payerName === '') {
            $payerName = trim((string) ($sale->client?->name ?? ''));
        }

        $payerPhone = trim((string) data_get($sale->metadata, 'customer_phone', ''));
        if ($payerPhone === '') {
            $payerPhone = trim((string) ($sale->client?->phone ?? ''));
        }

        $payerDocument = trim((string) ($sale->client?->document ?? ''));
        $payerAddress = $this->resolveCheckoutPayerAddressPayload($sale);
        $backUrls = $this->resolveCheckoutBackUrls($contractor, $sale);
        $statementDescriptor = $this->resolveCheckoutStatementDescriptor($contractor, $sale);
        $itemCategoryId = $this->resolveCheckoutItemCategoryId($contractor);
        $idempotency = $checkoutIdempotencyKey !== null && $checkoutIdempotencyKey !== ''
            ? $checkoutIdempotencyKey.'-'.$methodCode
            : 'sale-'.$sale->id.'-payment-'.$salePayment->id.'-'.Str::random(8);

        try {
            $intent = app(PaymentProviderManager::class)->createPaymentIntent(
                $paymentGateway,
                $sale,
                $salePayment,
                $methodCode,
                [
                    'idempotency_key' => substr($idempotency, 0, 80),
                    'notification_url' => $notificationUrl,
                    'payer_email' => $payerEmail,
                    'payer_name' => $payerName,
                    'payer_phone' => $payerPhone,
                    'payer_document' => $payerDocument,
                    'payer_address' => $payerAddress,
                    'back_urls' => $backUrls,
                    'statement_descriptor' => $statementDescriptor,
                    'item_category_id' => $itemCategoryId,
                    'store_name' => trim((string) ($contractor->brand_name ?: $contractor->name)),
                    'description' => 'Pedido '.$sale->code,
                    'expires_at' => now()->addMinutes(30),
                    'max_installments' => (bool) $paymentMethod->allows_installments
                        ? max(1, (int) ($paymentMethod->max_installments ?? 1))
                        : 1,
                ]
            );
        } catch (PaymentProviderException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'order' => $this->resolveCheckoutIntegratedProviderErrorMessage($exception),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'order' => 'Falha interna ao iniciar o pagamento automático. Tente novamente em instantes.',
            ]);
        }

        $this->applyCheckoutPaymentIntentResult($sale, $salePayment, $intent);
    }

    /**
     * @param  array<string, mixed>  $checkoutPayment
     */
    private function shouldReconcileCheckoutIntegratedPayment(array $checkoutPayment): bool
    {
        if (! $this->isIntegratedPaymentPayload($checkoutPayment)) {
            return false;
        }

        $provider = strtolower(trim((string) ($checkoutPayment['provider'] ?? '')));
        $transactionReference = trim((string) ($checkoutPayment['transaction_reference'] ?? ''));
        $paymentStatus = strtolower(trim((string) ($checkoutPayment['payment_status'] ?? '')));
        $checkoutUrl = trim((string) ($checkoutPayment['checkout_url'] ?? ''));

        if ($provider !== PaymentGateway::PROVIDER_MERCADO_PAGO || $transactionReference === '') {
            return false;
        }

        if ($this->isPixPaymentPayload($checkoutPayment)) {
            $hasVisiblePixData = $this->hasPixCheckoutData(
                (string) ($checkoutPayment['payment_method_code'] ?? ''),
                (string) ($checkoutPayment['qr_code'] ?? ''),
                (string) ($checkoutPayment['qr_code_base64'] ?? ''),
                (string) ($checkoutPayment['ticket_url'] ?? '')
            );

            return ! $hasVisiblePixData
                || in_array($paymentStatus, [SalePayment::STATUS_PENDING, SalePayment::STATUS_AUTHORIZED], true);
        }

        if ($checkoutUrl !== '') {
            return false;
        }

        return in_array($paymentStatus, [SalePayment::STATUS_PENDING, SalePayment::STATUS_AUTHORIZED], true);
    }

    /**
     * @param  array<string, mixed>  $checkoutPayment
     */
    private function reconcileCheckoutIntegratedPayment(Sale $sale, array $checkoutPayment): void
    {
        $paymentGatewayColumns = ['id', 'contractor_id', 'provider', 'is_active', 'credentials'];
        if (Schema::hasColumns('payment_gateways', ['mp_access_token', 'mp_refresh_token', 'mp_token_expires_at', 'mp_status'])) {
            $paymentGatewayColumns = array_merge($paymentGatewayColumns, [
                'mp_access_token',
                'mp_refresh_token',
                'mp_token_expires_at',
                'mp_status',
            ]);
        }

        $salePayment = SalePayment::query()
            ->where('contractor_id', $sale->contractor_id)
            ->where('sale_id', $sale->id)
            ->with([
                'paymentGateway:'.implode(',', $paymentGatewayColumns),
                'paymentMethod:id,code',
            ])
            ->latest('id')
            ->first();

        if (! $salePayment) {
            return;
        }

        $paymentGateway = $salePayment->paymentGateway;
        if (! $paymentGateway && $salePayment->payment_gateway_id) {
            $paymentGateway = PaymentGateway::query()
                ->where('contractor_id', $sale->contractor_id)
                ->where('id', (int) $salePayment->payment_gateway_id)
                ->first();
        }

        if (
            ! $paymentGateway
            || (string) $paymentGateway->provider !== PaymentGateway::PROVIDER_MERCADO_PAGO
            || ! (bool) $paymentGateway->is_active
        ) {
            return;
        }

        $transactionReference = trim((string) (
            $salePayment->transaction_reference
            ?? data_get($salePayment->gateway_payload, 'payment_intent.transaction_reference')
            ?? data_get($sale->metadata, 'payment_intent.transaction_reference')
            ?? ''
        ));

        if ($transactionReference === '') {
            return;
        }

        $paymentMethodCode = strtolower(trim((string) (
            $salePayment->paymentMethod?->code
            ?? $checkoutPayment['payment_method_code']
            ?? PaymentMethod::CODE_PIX
        )));

        try {
            $intent = app(PaymentProviderManager::class)->fetchPaymentIntent(
                $paymentGateway,
                $transactionReference,
                $paymentMethodCode
            );
        } catch (\Throwable $exception) {
            report($exception);

            return;
        }

        $this->applyCheckoutPaymentIntentResult($sale, $salePayment, $intent);
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function applyCheckoutPaymentIntentResult(Sale $sale, SalePayment $salePayment, array $intent): void
    {
        $paymentStatus = $this->mapProviderStatusToSalePaymentStatus((string) ($intent['status'] ?? ''));
        $gatewayPayload = is_array($salePayment->gateway_payload) ? $salePayment->gateway_payload : [];
        $gatewayPayload['payment_intent'] = $intent;

        $salePayment->fill([
            'status' => $paymentStatus,
            'transaction_reference' => (string) ($intent['transaction_reference'] ?? $salePayment->transaction_reference),
            'gateway_payload' => $gatewayPayload,
            'metadata' => array_filter([
                ...(is_array($salePayment->metadata) ? $salePayment->metadata : []),
                'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                'intent_status' => (string) ($intent['status'] ?? ''),
                'payment_method_code' => (string) ($intent['payment_method_code'] ?? ''),
                'ticket_url' => (string) ($intent['ticket_url'] ?? ''),
                'checkout_url' => (string) ($intent['checkout_url'] ?? ''),
                'date_of_expiration' => $intent['date_of_expiration'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            'paid_at' => $paymentStatus === SalePayment::STATUS_PAID ? now() : $salePayment->paid_at,
        ])->save();

        $saleMetadata = is_array($sale->metadata) ? $sale->metadata : [];
        $saleMetadata['payment_intent'] = [
            'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            'status' => (string) ($intent['status'] ?? ''),
            'transaction_reference' => (string) ($intent['transaction_reference'] ?? ''),
            'ticket_url' => (string) ($intent['ticket_url'] ?? ''),
            'checkout_url' => (string) ($intent['checkout_url'] ?? ''),
            'qr_code' => (string) ($intent['qr_code'] ?? ''),
            'qr_code_base64' => (string) ($intent['qr_code_base64'] ?? ''),
            'payment_method_code' => (string) ($intent['payment_method_code'] ?? ''),
            'date_of_expiration' => $intent['date_of_expiration'] ?? null,
        ];
        $sale->metadata = $saleMetadata;

        if ($paymentStatus === SalePayment::STATUS_PAID) {
            $sale->status = Sale::STATUS_PAID;
            $sale->paid_amount = (float) $sale->total_amount;
            $sale->completed_at = $sale->completed_at ?? now();
        } elseif (in_array($paymentStatus, [SalePayment::STATUS_PENDING, SalePayment::STATUS_AUTHORIZED], true)) {
            $sale->status = Sale::STATUS_AWAITING_PAYMENT;
        } elseif ($paymentStatus === SalePayment::STATUS_CANCELLED) {
            $sale->status = Sale::STATUS_CANCELLED;
        } elseif ($paymentStatus === SalePayment::STATUS_REFUNDED) {
            $sale->status = Sale::STATUS_REFUNDED;
        } elseif ($paymentStatus === SalePayment::STATUS_FAILED) {
            $sale->status = Sale::STATUS_REJECTED;
        }

        $sale->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCheckoutPaymentPayload(Sale $sale): ?array
    {
        $salePayment = SalePayment::query()
            ->where('contractor_id', $sale->contractor_id)
            ->where('sale_id', $sale->id)
            ->with([
                'paymentMethod:id,code,name',
                'paymentGateway:id,provider',
            ])
            ->latest('id')
            ->first();

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
        $paymentMethodCode = strtolower(trim((string) (
            $salePayment->paymentMethod?->code
            ?? ($paymentIntent['payment_method_code'] ?? ($saleIntent['payment_method_code'] ?? ''))
        )));
        $paymentMethodName = trim((string) (
            $salePayment->paymentMethod?->name
            ?? ($paymentIntent['payment_method_name'] ?? ($saleIntent['payment_method_name'] ?? ''))
        ));
        $provider = trim((string) (data_get($salePayment->metadata ?? [], 'provider')
            ?? data_get($salePayment, 'paymentGateway.provider')
            ?? ($paymentIntent['provider'] ?? ($saleIntent['provider'] ?? ''))));
        $ticketUrl = trim((string) ($paymentIntent['ticket_url'] ?? ($saleIntent['ticket_url'] ?? '')));
        $qrCode = trim((string) ($paymentIntent['qr_code'] ?? ($saleIntent['qr_code'] ?? '')));
        $qrCodeBase64 = trim((string) ($paymentIntent['qr_code_base64'] ?? ($saleIntent['qr_code_base64'] ?? '')));
        $expiresAt = $paymentIntent['date_of_expiration'] ?? ($saleIntent['date_of_expiration'] ?? null);
        $checkoutUrl = trim((string) ($paymentIntent['checkout_url'] ?? ($saleIntent['checkout_url'] ?? '')));

        $payload = [
            'sale_id' => (int) $sale->id,
            'sale_code' => (string) $sale->code,
            'sale_status' => (string) $sale->status,
            'sale_status_label' => $this->resolveSaleStatusLabel((string) $sale->status),
            'payment_id' => (int) $salePayment->id,
            'payment_status' => (string) $salePayment->status,
            'payment_status_label' => $this->resolveSalePaymentStatusLabel((string) $salePayment->status),
            'payment_method_code' => $paymentMethodCode,
            'payment_method_name' => $paymentMethodName,
            'provider' => $provider,
            'transaction_reference' => $transactionReference,
            'amount' => round((float) $salePayment->amount, 2),
            'ticket_url' => $ticketUrl,
            'checkout_url' => $checkoutUrl,
            'qr_code' => $qrCode,
            'qr_code_base64' => $qrCodeBase64,
            'expires_at' => $expiresAt,
        ];

        $payload['is_pix'] = $this->isPixPaymentPayload($payload);
        $payload['is_integrated'] = $this->isIntegratedPaymentPayload($payload);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $checkoutPayment
     */
    private function isPixPaymentPayload(array $checkoutPayment): bool
    {
        $paymentMethodCode = strtolower(trim((string) ($checkoutPayment['payment_method_code'] ?? '')));
        $provider = strtolower(trim((string) ($checkoutPayment['provider'] ?? '')));

        if ($this->hasPixCheckoutData(
            $paymentMethodCode,
            (string) ($checkoutPayment['qr_code'] ?? ''),
            (string) ($checkoutPayment['qr_code_base64'] ?? ''),
            (string) ($checkoutPayment['ticket_url'] ?? '')
        )) {
            return true;
        }

        return $provider === PaymentGateway::PROVIDER_MERCADO_PAGO
            && ($paymentMethodCode === PaymentMethod::CODE_PIX
                || str_contains($paymentMethodCode, 'pix'));
    }

    /**
     * @param  array<string, mixed>  $checkoutPayment
     */
    private function isIntegratedPaymentPayload(array $checkoutPayment): bool
    {
        $provider = strtolower(trim((string) ($checkoutPayment['provider'] ?? '')));
        if ($provider !== PaymentGateway::PROVIDER_MERCADO_PAGO) {
            return false;
        }

        $paymentMethodCode = strtolower(trim((string) ($checkoutPayment['payment_method_code'] ?? '')));
        $transactionReference = trim((string) ($checkoutPayment['transaction_reference'] ?? ''));
        $checkoutUrl = trim((string) ($checkoutPayment['checkout_url'] ?? ''));

        if ($this->isPixPaymentPayload($checkoutPayment)) {
            return true;
        }

        if ($checkoutUrl !== '') {
            return true;
        }

        return $transactionReference !== '' && in_array($paymentMethodCode, PaymentMethod::INTEGRATED_CODES, true);
    }

    private function hasPixCheckoutData(
        string $paymentMethodCode,
        string $qrCode,
        string $qrCodeBase64,
        string $ticketUrl
    ): bool {
        if (trim($qrCode) !== '' || trim($qrCodeBase64) !== '') {
            return true;
        }

        $method = strtolower(trim($paymentMethodCode));

        return ($method === PaymentMethod::CODE_PIX || str_contains($method, 'pix'))
            && trim($ticketUrl) !== '';
    }

    /**
     * @return array<string, string>|null
     */
    private function resolveCheckoutBackUrls(Contractor $contractor, Sale $sale): ?array
    {
        $base = route('shop.show', [
            'slug' => $contractor->slug,
            'conta' => 1,
            'pedido' => $sale->id,
        ]);

        $separator = str_contains($base, '?') ? '&' : '?';
        $success = $this->normalizeCheckoutReturnUrl($base.$separator.'mp_status=approved');
        $pending = $this->normalizeCheckoutReturnUrl($base.$separator.'mp_status=pending');
        $failure = $this->normalizeCheckoutReturnUrl($base.$separator.'mp_status=failure');

        $payload = array_filter([
            'success' => $success,
            'pending' => $pending,
            'failure' => $failure,
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        return $payload !== [] ? $payload : null;
    }

    private function normalizeCheckoutReturnUrl(string $url): ?string
    {
        $safeUrl = trim($url);
        if ($safeUrl === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($safeUrl, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($safeUrl, PHP_URL_HOST));
        if ($host === '') {
            return null;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return app()->environment('local') ? $safeUrl : null;
        }

        if ($scheme === 'http') {
            $safeUrl = preg_replace('/^http:/i', 'https:', $safeUrl, 1) ?: $safeUrl;
            $scheme = 'https';
        }

        if ($scheme !== 'https') {
            return null;
        }

        return $safeUrl;
    }

    /**
     * @return array<string, string>|null
     */
    private function resolveCheckoutPayerAddressPayload(Sale $sale): ?array
    {
        $shipping = is_array($sale->shipping_address) ? $sale->shipping_address : [];
        $client = $sale->client;

        $payload = array_filter([
            'postal_code' => trim((string) ($shipping['postal_code'] ?? $client?->cep ?? '')),
            'street' => trim((string) ($shipping['street'] ?? $client?->street ?? '')),
            'number' => trim((string) ($shipping['number'] ?? $client?->number ?? '')),
            'district' => trim((string) ($shipping['district'] ?? $client?->neighborhood ?? '')),
            'city' => trim((string) ($shipping['city'] ?? $client?->city ?? '')),
            'state' => strtoupper(trim((string) ($shipping['state'] ?? $client?->state ?? ''))),
        ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

        return $payload !== [] ? $payload : null;
    }

    private function resolveCheckoutStatementDescriptor(Contractor $contractor, Sale $sale): string
    {
        $name = trim((string) ($contractor->brand_name ?: $contractor->name));
        if ($name === '') {
            $name = 'Veshop';
        }

        $normalized = Str::upper(Str::ascii($name));
        $normalized = preg_replace('/[^A-Z0-9 ]+/', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', trim($normalized)) ?? '';

        $suffix = ' '.$sale->id;
        $limit = max(1, 22 - strlen($suffix));
        $base = mb_substr($normalized !== '' ? $normalized : 'VESHOP', 0, $limit);

        return trim($base.$suffix);
    }

    private function resolveCheckoutItemCategoryId(Contractor $contractor): string
    {
        return $contractor->niche() === Contractor::NICHE_SERVICES
            ? 'services'
            : 'others';
    }

    private function normalizePixWebhookNotificationUrl(string $url): ?string
    {
        $safeUrl = trim($url);
        if ($safeUrl === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($safeUrl, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($safeUrl, PHP_URL_HOST));

        if ($scheme === 'http' && $host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $safeUrl = preg_replace('/^http:/i', 'https:', $safeUrl, 1) ?: $safeUrl;
            $scheme = 'https';
        }

        if ($scheme !== 'https') {
            return null;
        }

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return null;
        }

        foreach (['.local', '.test', '.localhost', '.invalid'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return null;
            }
        }

        return $safeUrl;
    }

    private function resolveCheckoutIntegratedProviderErrorMessage(PaymentProviderException $exception): string
    {
        $rawMessage = trim((string) $exception->getMessage());
        $normalized = strtolower($rawMessage);
        $default = 'Não foi possível iniciar o pagamento automático agora. Tente novamente em instantes.';

        if ($normalized === '') {
            return $default;
        }

        if (str_contains($normalized, 'unauthorized use of live credentials')) {
            return 'Mercado Pago recusou as credenciais informadas para este ambiente. '
                .'Revise as credenciais de teste/produção no gateway.';
        }

        if (str_contains($normalized, 'não está disponível na conta conectada do mercado pago')
            || str_contains($normalized, 'nao esta disponivel na conta conectada do mercado pago')) {
            return 'A conta Mercado Pago conectada nesta loja não possui Pix habilitado. '
                .'Conecte uma conta com Pix ativo e tente novamente.';
        }

        if (str_contains($normalized, 'notification_url') || str_contains($normalized, 'notificaction_url')) {
            return 'Mercado Pago rejeitou a URL de notificação da loja. '
                .'Use uma URL pública HTTPS ou finalize sem webhook em ambiente local.';
        }

        if ((bool) config('app.debug')) {
            return $rawMessage;
        }

        return $default;
    }

    private function resolveSalePaymentStatusLabel(string $status): string
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

    private function resolveSaleStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            Sale::STATUS_PENDING_CONFIRMATION => 'Aguardando confirmação',
            Sale::STATUS_CONFIRMED => 'Confirmado',
            Sale::STATUS_AWAITING_PAYMENT => 'Aguardando pagamento',
            Sale::STATUS_PAID => 'Pago',
            Sale::STATUS_COMPLETED => 'Concluído',
            Sale::STATUS_CANCELLED => 'Cancelado',
            Sale::STATUS_REJECTED => 'Rejeitado',
            Sale::STATUS_REFUNDED => 'Reembolsado',
            default => ucfirst(strtolower(trim($status))),
        };
    }

    private function resolveSaleStatusTone(string $status): string
    {
        return match (strtolower(trim($status))) {
            Sale::STATUS_PENDING_CONFIRMATION => 'bg-blue-100 text-blue-700',
            Sale::STATUS_CONFIRMED, Sale::STATUS_AWAITING_PAYMENT => 'bg-amber-100 text-amber-700',
            Sale::STATUS_PAID, Sale::STATUS_COMPLETED => 'bg-emerald-100 text-emerald-700',
            Sale::STATUS_REJECTED, Sale::STATUS_CANCELLED => 'bg-rose-100 text-rose-700',
            Sale::STATUS_REFUNDED => 'bg-slate-100 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    private function mapProviderStatusToSalePaymentStatus(string $status): string
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            'approved', 'accredited', 'paid', 'completed', 'processed' => SalePayment::STATUS_PAID,
            'authorized', 'authorised' => SalePayment::STATUS_AUTHORIZED,
            'action_required', 'waiting_transfer', 'pending', 'waiting', 'in_process', 'processing' => SalePayment::STATUS_PENDING,
            'cancelled', 'canceled', 'expired' => SalePayment::STATUS_CANCELLED,
            'refunded', 'charged_back', 'chargeback' => SalePayment::STATUS_REFUNDED,
            'rejected', 'failed', 'denied', 'error' => SalePayment::STATUS_FAILED,
            default => SalePayment::STATUS_PENDING,
        };
    }

    /**
     * @param  array<string, mixed>  $validatedData
     */
    private function resolveCheckoutIdempotencyKey(Request $request, array $validatedData): ?string
    {
        $candidate = $validatedData['idempotency_key']
            ?? $request->header('X-Idempotency-Key')
            ?? null;

        if ($candidate === null) {
            return null;
        }

        $value = trim((string) $candidate);

        if ($value === '') {
            return null;
        }

        return substr($value, 0, 80);
    }

    private function isDuplicateCheckoutIdempotencyException(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = is_array($errorInfo) ? (int) ($errorInfo[1] ?? 0) : 0;

        if ($driverCode === 1062) {
            return true;
        }

        return str_contains(
            strtolower($exception->getMessage()),
            'sales_contractor_checkout_idempotency_unique'
        );
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
