<?php

namespace App\Http\Controllers;

use App\Http\Requests\Shop\StoreShopCheckoutRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\ShopCustomer;
use App\Models\ShopCustomerFavorite;
use App\Support\PaymentFeeSnapshot;
use App\Support\StorefrontSettings;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\Payments\Exceptions\PaymentProviderException;
use App\Services\Payments\PaymentProviderManager;

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

    public function checkout(StoreShopCheckoutRequest $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
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
                return back()->with('status', 'Pedido ja recebido. Estamos processando sua solicitacao.');
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

        $paymentGateway = null;
        if ($paymentMethod?->payment_gateway_id) {
            $paymentGateway = PaymentGateway::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $paymentMethod->payment_gateway_id)
                ->where('is_active', true)
                ->first();
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

        try {
            DB::transaction(function () use ($contractor, $data, $paymentMethod, $paymentGateway, $quantitiesByProduct, $shopCustomer, $idempotencyKey, &$createdSaleId): void {
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

            $checkoutMode = $paymentMethod && $paymentGateway && $this->shouldCreatePixIntent($paymentMethod, $paymentGateway)
                ? 'integrated'
                : 'manual';

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

                    if ($paymentGateway && $this->shouldCreatePixIntent($paymentMethod, $paymentGateway)) {
                        $this->createCheckoutPixIntent(
                            $contractor,
                            $sale,
                            $salePayment,
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

            return back()->with('status', 'Pedido ja recebido. Estamos processando sua solicitacao.');
        }

        $checkoutPayment = null;
        $checkoutManual = null;
        if ($createdSaleId) {
            $createdSale = Sale::query()->find($createdSaleId);
            if ($createdSale) {
                app(\App\Services\OrderNotificationService::class)->notifyOrderCreated($createdSale);
                $checkoutPayment = $this->resolveCheckoutPaymentPayload($createdSale);
                if (! ($checkoutPayment !== null && $this->isPixPaymentPayload($checkoutPayment))) {
                    $checkoutManual = $this->resolveManualCheckoutPayload($contractor, $createdSale);
                }
            }
        }

        $redirect = back()->with('status', 'Pedido enviado com sucesso. Aguarde a confirmação da loja.');
        if ($checkoutPayment !== null && $this->isPixPaymentPayload($checkoutPayment)) {
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
        if ($checkoutPayment === null || ! $this->isPixPaymentPayload($checkoutPayment)) {
            return response()->json([
                'ok' => false,
                'message' => 'Cobrança Pix não localizada para este pedido.',
            ], 404);
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
        return PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->with('paymentGateway:id,provider,is_active')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'fee_fixed', 'fee_percent', 'payment_gateway_id'])
            ->map(static function (PaymentMethod $method): array {
                $gateway = $method->paymentGateway;
                $isIntegrated = $gateway
                    && (bool) $gateway->is_active
                    && (string) $gateway->provider !== PaymentGateway::PROVIDER_MANUAL;

                return [
                    'id' => (int) $method->id,
                    'name' => (string) $method->name,
                    'code' => (string) $method->code,
                    'fee_fixed' => round((float) ($method->fee_fixed ?? 0), 2),
                    'fee_percent' => round((float) ($method->fee_percent ?? 0), 2),
                    'checkout_mode' => $isIntegrated ? 'integrated' : 'manual',
                ];
            })
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

        return [
            'orders' => $orders,
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
            'status_label' => $this->resolveSalePaymentStatusLabel((string) $salePayment->status),
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

    private function shouldCreatePixIntent(PaymentMethod $paymentMethod, PaymentGateway $paymentGateway): bool
    {
        return (string) $paymentMethod->code === PaymentMethod::CODE_PIX
            && (string) $paymentGateway->provider === PaymentGateway::PROVIDER_MERCADO_PAGO;
    }

    private function createCheckoutPixIntent(
        Contractor $contractor,
        Sale $sale,
        SalePayment $salePayment,
        PaymentGateway $paymentGateway,
        ?string $checkoutIdempotencyKey
    ): void {
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

        $payerEmail = trim((string) data_get($sale->metadata, 'customer_email', ''));
        $idempotency = $checkoutIdempotencyKey !== null && $checkoutIdempotencyKey !== ''
            ? $checkoutIdempotencyKey.'-pix'
            : 'sale-'.$sale->id.'-payment-'.$salePayment->id.'-'.Str::random(8);

        try {
            $intent = app(PaymentProviderManager::class)->createPixPayment(
                $paymentGateway,
                $sale,
                $salePayment,
                [
                    'idempotency_key' => substr($idempotency, 0, 80),
                    'notification_url' => $notificationUrl,
                    'payer_email' => $payerEmail,
                    'description' => 'Pedido '.$sale->code,
                    'expires_at' => now()->addMinutes(30),
                ]
            );
        } catch (PaymentProviderException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'order' => 'Nao foi possivel iniciar o pagamento Pix agora. Tente novamente em instantes.',
            ]);
        }

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
                'ticket_url' => (string) ($intent['ticket_url'] ?? ''),
                'date_of_expiration' => $intent['date_of_expiration'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            'paid_at' => $paymentStatus === SalePayment::STATUS_PAID ? now() : null,
        ])->save();

        $saleMetadata = is_array($sale->metadata) ? $sale->metadata : [];
        $saleMetadata['payment_intent'] = [
            'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
            'status' => (string) ($intent['status'] ?? ''),
            'transaction_reference' => (string) ($intent['transaction_reference'] ?? ''),
            'ticket_url' => (string) ($intent['ticket_url'] ?? ''),
            'qr_code' => (string) ($intent['qr_code'] ?? ''),
            'qr_code_base64' => (string) ($intent['qr_code_base64'] ?? ''),
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
            ->with('paymentMethod:id,code,name')
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
        $paymentMethodCode = strtolower(trim((string) ($salePayment->paymentMethod?->code ?? '')));
        $paymentMethodName = trim((string) ($salePayment->paymentMethod?->name ?? ''));
        $provider = trim((string) (data_get($salePayment->metadata ?? [], 'provider')
            ?? ($paymentIntent['provider'] ?? ($saleIntent['provider'] ?? ''))));
        $ticketUrl = trim((string) ($paymentIntent['ticket_url'] ?? ($saleIntent['ticket_url'] ?? '')));
        $qrCode = trim((string) ($paymentIntent['qr_code'] ?? ($saleIntent['qr_code'] ?? '')));
        $qrCodeBase64 = trim((string) ($paymentIntent['qr_code_base64'] ?? ($saleIntent['qr_code_base64'] ?? '')));
        $expiresAt = $paymentIntent['date_of_expiration'] ?? ($saleIntent['date_of_expiration'] ?? null);

        return [
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
            'qr_code' => $qrCode,
            'qr_code_base64' => $qrCodeBase64,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param array<string, mixed> $checkoutPayment
     */
    private function isPixPaymentPayload(array $checkoutPayment): bool
    {
        return (string) ($checkoutPayment['payment_method_code'] ?? '') === PaymentMethod::CODE_PIX
            && trim((string) ($checkoutPayment['qr_code'] ?? '')) !== '';
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
            'approved', 'accredited', 'paid', 'completed' => SalePayment::STATUS_PAID,
            'authorized', 'authorised' => SalePayment::STATUS_AUTHORIZED,
            'cancelled', 'canceled' => SalePayment::STATUS_CANCELLED,
            'refunded', 'charged_back', 'chargeback' => SalePayment::STATUS_REFUNDED,
            'rejected', 'failed', 'denied', 'error' => SalePayment::STATUS_FAILED,
            default => SalePayment::STATUS_PENDING,
        };
    }

    /**
     * @param array<string, mixed> $validatedData
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
