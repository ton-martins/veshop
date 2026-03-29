<?php

namespace App\Application\Sales\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SecurityAuditLog;
use App\Services\OrderNotificationService;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderService
{
    use ResolvesCurrentContractor;

    /**
     * @var list<string>
     */
    private const ORDER_SOURCES = [Sale::SOURCE_CATALOG, Sale::SOURCE_ORDER];

    private const DELIVERY_STATUS_PREPARING = 'preparing';

    private const DELIVERY_STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';

    private const DELIVERY_STATUS_DELIVERED = 'delivered';

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());
        $pipelineFilter = trim((string) $request->string('pipeline')->toString());

        $baseQuery = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('source', self::ORDER_SOURCES);

        $ordersQuery = (clone $baseQuery)
            ->with([
                'client:id,name,email,phone',
                'items:id,sale_id,product_id,product_variation_id,description,sku,quantity,unit_price,discount_amount,total_amount,metadata',
                'items.product:id,image_url',
                'items.productVariation:id,name',
                'payments:id,sale_id,payment_method_id,status,amount',
                'payments.paymentMethod:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($search !== '') {
            $ordersQuery->where(function ($query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search): void {
                        $clientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== '') {
            $ordersQuery->where('status', $status);
        }

        $pipelineStatuses = $this->resolvePipelineStatuses($pipelineFilter);
        if ($pipelineStatuses !== []) {
            $ordersQuery->whereIn('status', $pipelineStatuses);
        }

        $orders = $ordersQuery
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Sale $sale): array => $this->toOrderPayload($sale));

        $totals = [
            'all' => (clone $baseQuery)->count(),
            'pending_confirmation' => (clone $baseQuery)
                ->whereIn('status', [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION])
                ->count(),
            'awaiting_payment' => (clone $baseQuery)
                ->whereIn('status', [Sale::STATUS_CONFIRMED, Sale::STATUS_AWAITING_PAYMENT])
                ->count(),
            'paid_today' => (clone $baseQuery)
                ->where('status', Sale::STATUS_PAID)
                ->whereDate('completed_at', today())
                ->count(),
            'cancelled' => (clone $baseQuery)
                ->whereIn('status', [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED])
                ->count(),
        ];

        $pipeline = [
            ['key' => 'pending_confirmation', 'label' => 'Aguardando confirmação', 'qty' => (int) $totals['pending_confirmation'], 'tone' => 'bg-blue-100 text-blue-700'],
            ['key' => 'awaiting_payment', 'label' => 'Aguardando pagamento', 'qty' => (int) $totals['awaiting_payment'], 'tone' => 'bg-amber-100 text-amber-700'],
            ['key' => 'paid', 'label' => 'Pagos hoje', 'qty' => (int) $totals['paid_today'], 'tone' => 'bg-emerald-100 text-emerald-700'],
            ['key' => 'cancelled', 'label' => 'Rejeitados/cancelados', 'qty' => (int) $totals['cancelled'], 'tone' => 'bg-rose-100 text-rose-700'],
        ];

        $clients = Client::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email', 'phone'])
            ->map(static fn (Client $client): array => [
                'id' => (int) $client->id,
                'name' => (string) $client->name,
                'email' => $client->email ? (string) $client->email : null,
                'phone' => $client->phone ? (string) $client->phone : null,
            ])
            ->values()
            ->all();

        $products = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->with([
                'variations' => static fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->select(['id', 'product_id', 'name', 'sku', 'sale_price', 'stock_quantity', 'attributes']),
            ])
            ->orderBy('name')
            ->limit(800)
            ->get(['id', 'name', 'sku', 'sale_price', 'stock_quantity', 'image_url'])
            ->map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sku' => $product->sku ? (string) $product->sku : null,
                'sale_price' => (float) $product->sale_price,
                'stock_quantity' => (int) $product->stock_quantity,
                'image_url' => $product->image_url ? (string) $product->image_url : null,
                'variations' => $product->variations
                    ->map(static fn (ProductVariation $variation): array => [
                        'id' => (int) $variation->id,
                        'name' => (string) $variation->name,
                        'sku' => $variation->sku ? (string) $variation->sku : null,
                        'sale_price' => (float) $variation->sale_price,
                        'stock_quantity' => (int) $variation->stock_quantity,
                        'attributes' => is_array($variation->attributes) ? $variation->attributes : [],
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'orderStats' => $totals,
            'pipeline' => $pipeline,
            'statusOptions' => $this->resolveStatusOptions(),
            'clients' => $clients,
            'products' => $products,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'pipeline' => in_array($pipelineFilter, ['pending_confirmation', 'awaiting_payment', 'paid', 'cancelled'], true)
                    ? $pipelineFilter
                    : 'all',
            ],
        ]);
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_contact' => ['nullable', 'string', 'max:160'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'shipping_mode' => ['nullable', Rule::in([Sale::SHIPPING_MODE_PICKUP, Sale::SHIPPING_MODE_DELIVERY])],
            'shipping_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'shipping_estimate_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:80'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.variation_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $clientId = isset($validated['client_id']) && $validated['client_id'] !== ''
            ? (int) $validated['client_id']
            : null;
        $canEditCustomer = $this->canEditOrderCustomer($sale);

        $selectedClient = null;
        if ($clientId !== null && $canEditCustomer) {
            $selectedClient = Client::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $clientId)
                ->where('is_active', true)
                ->first();

            if (! $selectedClient) {
                throw ValidationException::withMessages([
                    'client_id' => 'Cliente inválido para o contratante ativo.',
                ]);
            }
        }

        if (! $canEditCustomer) {
            $clientId = $sale->client_id ? (int) $sale->client_id : null;
        }

        $requestedItems = collect($validated['items'])
            ->map(static function (array $row): array {
                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'variation_id' => isset($row['variation_id']) && $row['variation_id'] !== ''
                        ? (int) $row['variation_id']
                        : null,
                    'quantity' => (int) ($row['quantity'] ?? 0),
                    'discount_amount' => round((float) ($row['discount_amount'] ?? 0), 2),
                ];
            })
            ->filter(static fn (array $row): bool => $row['product_id'] > 0 && $row['quantity'] > 0)
            ->values();

        if ($requestedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Adicione ao menos um produto no pedido.',
            ]);
        }

        $updatedSaleId = null;
        $updatedSaleCode = null;
        $changedFields = [];
        $oldTotalForLog = null;
        $newTotalForLog = null;

        DB::transaction(function () use (
            $contractor,
            $sale,
            $validated,
            $requestedItems,
            $clientId,
            $selectedClient,
            $request,
            &$updatedSaleId,
            &$updatedSaleCode,
            &$changedFields,
            &$oldTotalForLog,
            &$newTotalForLog
        ): void {
            $lockedSale = $this->lockOrderForContractor($contractor, $sale->id);

            if (! $this->canEditOrder($lockedSale)) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido não pode ser editado no status atual.',
                ]);
            }

            $effectiveClientId = $clientId;
            $effectiveSelectedClient = $selectedClient;
            if (! $this->canEditOrderCustomer($lockedSale)) {
                $effectiveClientId = $lockedSale->client_id ? (int) $lockedSale->client_id : null;
                $effectiveSelectedClient = $lockedSale->client;
            }

            $metadata = is_array($lockedSale->metadata) ? $lockedSale->metadata : [];
            $beforeSurchargeComponents = $this->resolveOrderSurchargeComponents($lockedSale, $metadata);
            $beforeItems = $this->normalizeExistingItems($lockedSale->items);
            $beforeSubtotal = round((float) $lockedSale->subtotal_amount, 2);
            $beforeTotal = round((float) $lockedSale->total_amount, 2);
            $beforeDiscount = round((float) $lockedSale->discount_amount, 2);
            $beforeSurcharge = round((float) $lockedSale->surcharge_amount, 2);
            $beforeClientId = $lockedSale->client_id ? (int) $lockedSale->client_id : null;
            $beforeShippingMode = (string) ($lockedSale->shipping_mode ?? '');
            $beforeShippingAmount = round((float) ($lockedSale->shipping_amount ?? 0), 2);
            $beforeShippingEstimateDays = $lockedSale->shipping_estimate_days !== null ? (int) $lockedSale->shipping_estimate_days : null;
            $beforeNotes = trim((string) ($lockedSale->notes ?? ''));

            $before = [
                'customer_name' => trim((string) ($metadata['customer_name'] ?? ($lockedSale->client?->name ?? ''))),
                'customer_contact' => trim((string) ($metadata['customer_contact'] ?? ($lockedSale->client?->phone ?? ($lockedSale->client?->email ?? '')))),
            ];

            $nextShippingAmount = array_key_exists('shipping_amount', $validated)
                ? round((float) ($validated['shipping_amount'] ?? 0), 2)
                : $beforeShippingAmount;
            $nextShippingMode = array_key_exists('shipping_mode', $validated)
                ? (string) ($validated['shipping_mode'] ?? '')
                : $beforeShippingMode;
            $nextShippingEstimateDays = array_key_exists('shipping_estimate_days', $validated)
                ? ($validated['shipping_estimate_days'] !== null ? (int) $validated['shipping_estimate_days'] : null)
                : $beforeShippingEstimateDays;

            $stockReduced = (bool) ($metadata['stock_reduced'] ?? false);
            $stockRestored = (bool) ($metadata['stock_restored'] ?? false);
            $shouldAdjustStock = $stockReduced && ! $stockRestored;

            $groupedRequestedItems = $this->groupRequestedItems($requestedItems);
            $oldLineMeta = $lockedSale->items
                ->groupBy(fn (SaleItem $item): string => $this->resolveItemLineKey(
                    (int) $item->product_id,
                    $item->product_variation_id ? (int) $item->product_variation_id : null
                ))
                ->map(static function (Collection $rows): array {
                    /** @var SaleItem|null $first */
                    $first = $rows->first();

                    return [
                        'product_id' => $first ? (int) $first->product_id : 0,
                        'variation_id' => $first && $first->product_variation_id
                            ? (int) $first->product_variation_id
                            : null,
                    ];
                });
            $oldQtyByLine = $lockedSale->items
                ->groupBy(fn (SaleItem $item): string => $this->resolveItemLineKey(
                    (int) $item->product_id,
                    $item->product_variation_id ? (int) $item->product_variation_id : null
                ))
                ->map(static fn (Collection $rows): int => (int) $rows->sum(static fn (SaleItem $item): int => (int) $item->quantity));

            $allProductIds = $oldLineMeta
                ->pluck('product_id')
                ->merge($groupedRequestedItems->pluck('product_id'))
                ->map(static fn (mixed $value): int => (int) $value)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values();

            $requestedVariationIds = $groupedRequestedItems
                ->pluck('variation_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values();
            $allVariationIds = $oldLineMeta
                ->pluck('variation_id')
                ->merge($requestedVariationIds)
                ->map(static fn (mixed $value): int => (int) $value)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values();

            $products = Product::query()
                ->where('contractor_id', $contractor->id)
                ->whereIn('id', $allProductIds->all())
                ->with([
                    'variations:id,product_id,is_active',
                ])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $allProductIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Um ou mais produtos são inválidos para o contratante ativo.',
                ]);
            }

            $variationsById = collect();
            if ($allVariationIds->isNotEmpty()) {
                $variationsById = ProductVariation::withTrashed()
                    ->where('contractor_id', $contractor->id)
                    ->whereIn('id', $allVariationIds->all())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($requestedVariationIds->isNotEmpty() && $variationsById->only($requestedVariationIds->all())->count() !== $requestedVariationIds->count()) {
                    throw ValidationException::withMessages([
                        'items' => 'Uma ou mais variações não são válidas para o contratante ativo.',
                    ]);
                }
            }

            $preparedLines = [];
            $subtotal = 0.0;
            $lineDiscountTotal = 0.0;

            foreach ($groupedRequestedItems as $lineKey => $row) {
                $safeProductId = (int) ($row['product_id'] ?? 0);
                $safeVariationId = isset($row['variation_id']) && (int) $row['variation_id'] > 0
                    ? (int) $row['variation_id']
                    : null;
                $quantity = (int) ($row['quantity'] ?? 0);
                $requestedLineDiscount = round((float) ($row['discount_amount'] ?? 0), 2);

                /** @var Product|null $product */
                $product = $products->get($safeProductId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Produto inválido na edição do pedido.',
                    ]);
                }

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Quantidade inválida para o produto {$product->name}.",
                    ]);
                }

                $variation = null;
                $unitPrice = round((float) $product->sale_price, 2);
                $description = (string) $product->name;
                $sku = $product->sku ? (string) $product->sku : null;

                if ($safeVariationId !== null) {
                    /** @var ProductVariation|null $variation */
                    $variation = $variationsById->get($safeVariationId);
                    if (! $variation || (int) $variation->product_id !== $safeProductId) {
                        throw ValidationException::withMessages([
                            'items' => "Variação inválida para o produto {$product->name}.",
                        ]);
                    }

                    if (! (bool) $variation->is_active && ! $oldQtyByLine->has((string) $lineKey)) {
                        throw ValidationException::withMessages([
                            'items' => "A variação {$variation->name} está inativa para o produto {$product->name}.",
                        ]);
                    }

                    $oldQuantity = (int) ($oldQtyByLine->get((string) $lineKey) ?? 0);
                    $delta = $quantity - $oldQuantity;
                    $availableStock = (int) $variation->stock_quantity;

                    if ($shouldAdjustStock && $delta > 0 && $delta > $availableStock) {
                        throw ValidationException::withMessages([
                            'items' => "Estoque insuficiente para a variação {$variation->name}.",
                        ]);
                    }

                    if (! $shouldAdjustStock && $quantity > $availableStock) {
                        throw ValidationException::withMessages([
                            'items' => "Estoque insuficiente para a variação {$variation->name}.",
                        ]);
                    }

                    $unitPrice = round((float) $variation->sale_price, 2);
                    $description = trim($product->name.' - '.$variation->name);
                    $sku = $variation->sku ? (string) $variation->sku : $sku;
                } else {
                    $hasActiveVariations = $product->variations
                        ->contains(static fn (ProductVariation $productVariation): bool => (bool) $productVariation->is_active);

                    if ($hasActiveVariations && ! $oldQtyByLine->has((string) $lineKey)) {
                        throw ValidationException::withMessages([
                            'items' => "Selecione uma variação para o produto {$product->name}.",
                        ]);
                    }

                    $oldQuantity = (int) ($oldQtyByLine->get((string) $lineKey) ?? 0);
                    $delta = $quantity - $oldQuantity;
                    $availableStock = (int) $product->stock_quantity;

                    if ($shouldAdjustStock && $delta > 0 && $delta > $availableStock) {
                        throw ValidationException::withMessages([
                            'items' => "Estoque insuficiente para o produto {$product->name}.",
                        ]);
                    }

                    if (! $shouldAdjustStock && $quantity > $availableStock) {
                        throw ValidationException::withMessages([
                            'items' => "Estoque insuficiente para o produto {$product->name}.",
                        ]);
                    }
                }

                $lineSubtotal = round($unitPrice * $quantity, 2);
                $lineDiscount = min(max($requestedLineDiscount, 0.0), $lineSubtotal);
                $lineTotal = round($lineSubtotal - $lineDiscount, 2);

                $preparedLines[(string) $lineKey] = [
                    'line_key' => (string) $lineKey,
                    'product' => $product,
                    'variation' => $variation,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotal,
                    'discount_amount' => $lineDiscount,
                    'line_total' => $lineTotal,
                    'description' => $description,
                    'sku' => $sku,
                ];

                $subtotal += $lineSubtotal;
                $lineDiscountTotal += $lineDiscount;
            }

            $globalDiscount = round((float) ($validated['discount_amount'] ?? 0), 2);
            $discountTotal = round($lineDiscountTotal + $globalDiscount, 2);
            $preservedNonShippingSurcharge = round((float) ($beforeSurchargeComponents['non_shipping_surcharge'] ?? 0), 2);
            $paymentFeeAmount = round((float) ($beforeSurchargeComponents['payment_fee_amount'] ?? 0), 2);
            $surcharge = round($nextShippingAmount + $preservedNonShippingSurcharge, 2);
            $total = round($subtotal - $discountTotal + $surcharge, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'O valor total do pedido precisa ser maior que zero.',
                ]);
            }

            $afterItems = collect($preparedLines)
                ->sortBy('line_key')
                ->values()
                ->map(static fn (array $line): array => [
                    'product_id' => (int) $line['product']->id,
                    'variation_id' => isset($line['variation']) && $line['variation'] instanceof ProductVariation
                        ? (int) $line['variation']->id
                        : null,
                    'quantity' => (int) $line['quantity'],
                    'discount_amount' => round((float) $line['discount_amount'], 2),
                ])
                ->all();

            $itemsChanged = serialize($beforeItems) !== serialize($afterItems);

            $nextCustomerName = trim((string) ($validated['customer_name'] ?? ''));
            if ($nextCustomerName === '' && $effectiveSelectedClient) {
                $nextCustomerName = trim((string) $effectiveSelectedClient->name);
            }

            $nextCustomerContact = trim((string) ($validated['customer_contact'] ?? ''));
            if ($nextCustomerContact === '' && $effectiveSelectedClient) {
                $nextCustomerContact = trim((string) ($effectiveSelectedClient->phone ?? $effectiveSelectedClient->email ?? ''));
            }

            if (! $this->canEditOrderCustomer($lockedSale)) {
                $nextCustomerName = $before['customer_name'];
                $nextCustomerContact = $before['customer_contact'];
            }

            $nextNotes = trim((string) ($validated['notes'] ?? ''));

            $hasFinancialChange = (
                abs($beforeSubtotal - $subtotal) > 0.0001
                || abs($beforeTotal - $total) > 0.0001
                || abs($beforeDiscount - $discountTotal) > 0.0001
                || abs($beforeSurcharge - $surcharge) > 0.0001
            );

            $hasShippingChange = (
                $beforeShippingMode !== $nextShippingMode
                || abs($beforeShippingAmount - $nextShippingAmount) > 0.0001
                || $beforeShippingEstimateDays !== $nextShippingEstimateDays
            );

            $hasClientOrMetaChange = (
                $beforeClientId !== $effectiveClientId
                || $before['customer_name'] !== $nextCustomerName
                || $before['customer_contact'] !== $nextCustomerContact
                || $beforeNotes !== $nextNotes
            );

            if (! $itemsChanged && ! $hasFinancialChange && ! $hasShippingChange && ! $hasClientOrMetaChange) {
                $updatedSaleId = (int) $lockedSale->id;
                $updatedSaleCode = (string) $lockedSale->code;
                $oldTotalForLog = $beforeTotal;
                $newTotalForLog = $beforeTotal;

                return;
            }

            $newSaleItemsByLineKey = [];
            if ($itemsChanged) {
                $lockedSale->items()->delete();

                foreach ($preparedLines as $lineKey => $line) {
                    /** @var Product $product */
                    $product = $line['product'];
                    /** @var ProductVariation|null $variation */
                    $variation = $line['variation'];

                    $newSaleItemsByLineKey[(string) $lineKey] = SaleItem::query()->create([
                        'contractor_id' => $contractor->id,
                        'sale_id' => $lockedSale->id,
                        'product_id' => (int) $product->id,
                        'product_variation_id' => $variation?->id,
                        'description' => (string) ($line['description'] ?? $product->name),
                        'sku' => trim((string) ($line['sku'] ?? $product->sku)) !== ''
                            ? trim((string) ($line['sku'] ?? $product->sku))
                            : null,
                        'quantity' => (int) $line['quantity'],
                        'unit_price' => (float) $line['unit_price'],
                        'discount_amount' => (float) $line['discount_amount'],
                        'total_amount' => (float) $line['line_total'],
                        'metadata' => $variation ? [
                            'variation_id' => (int) $variation->id,
                            'variation_name' => (string) $variation->name,
                            'variation_sku' => $variation->sku ? (string) $variation->sku : null,
                            'variation_attributes' => is_array($variation->attributes) ? $variation->attributes : [],
                        ] : null,
                    ]);
                }

                if ($shouldAdjustStock) {
                    $allLineMeta = collect($preparedLines)
                        ->mapWithKeys(static fn (array $line, string $lineKey): array => [
                            (string) $lineKey => [
                                'product_id' => (int) $line['product']->id,
                                'variation_id' => isset($line['variation']) && $line['variation'] instanceof ProductVariation
                                    ? (int) $line['variation']->id
                                    : null,
                            ],
                        ])
                        ->merge($oldLineMeta);

                    $allLineKeys = $oldQtyByLine->keys()
                        ->merge(array_keys($preparedLines))
                        ->map(static fn (mixed $key): string => (string) $key)
                        ->unique()
                        ->values();

                    foreach ($allLineKeys as $lineKeyValue) {
                        $lineKey = (string) $lineKeyValue;
                        $lineMeta = $allLineMeta->get($lineKey);
                        if (! is_array($lineMeta)) {
                            continue;
                        }

                        $productId = (int) ($lineMeta['product_id'] ?? 0);
                        $variationId = isset($lineMeta['variation_id']) && (int) $lineMeta['variation_id'] > 0
                            ? (int) $lineMeta['variation_id']
                            : null;

                        /** @var Product|null $product */
                        $product = $products->get($productId);
                        if (! $product) {
                            continue;
                        }

                        /** @var ProductVariation|null $variation */
                        $variation = $variationId !== null
                            ? $variationsById->get($variationId)
                            : null;

                        $oldQuantity = (int) ($oldQtyByLine->get($lineKey) ?? 0);
                        $newQuantity = (int) (($preparedLines[$lineKey]['quantity'] ?? null) ?? 0);
                        $delta = $newQuantity - $oldQuantity;

                        if ($delta === 0) {
                            continue;
                        }

                        $movementQuantity = abs($delta);
                        $movementType = $delta > 0
                            ? InventoryMovement::TYPE_OUT
                            : InventoryMovement::TYPE_RETURN;

                        $movementBalanceBefore = (int) $product->stock_quantity;
                        $movementBalanceAfter = $delta > 0
                            ? max(0, $movementBalanceBefore - $movementQuantity)
                            : $movementBalanceBefore + $movementQuantity;
                        $movementUnitCost = (float) $product->cost_price;

                        if ($variation) {
                            $variationBalanceBefore = (int) $variation->stock_quantity;
                            $variationBalanceAfter = $delta > 0
                                ? max(0, $variationBalanceBefore - $movementQuantity)
                                : $variationBalanceBefore + $movementQuantity;

                            $variation->stock_quantity = $variationBalanceAfter;
                            $variation->save();

                            $movementBalanceBefore = $variationBalanceBefore;
                            $movementBalanceAfter = $variationBalanceAfter;
                            $movementUnitCost = (float) ($variation->cost_price ?? $product->cost_price);
                        }

                        $productBalanceBefore = (int) $product->stock_quantity;
                        $productBalanceAfter = $delta > 0
                            ? max(0, $productBalanceBefore - $movementQuantity)
                            : $productBalanceBefore + $movementQuantity;
                        $product->stock_quantity = $productBalanceAfter;
                        $product->save();

                        $saleItemId = isset($newSaleItemsByLineKey[$lineKey])
                            ? (int) $newSaleItemsByLineKey[$lineKey]->id
                            : null;

                        InventoryMovement::query()->create([
                            'contractor_id' => $contractor->id,
                            'product_id' => $productId,
                            'product_variation_id' => $variation?->id,
                            'sale_item_id' => $saleItemId,
                            'user_id' => $request->user()?->id,
                            'type' => $movementType,
                            'quantity' => $movementQuantity,
                            'balance_before' => $movementBalanceBefore,
                            'balance_after' => $movementBalanceAfter,
                            'unit_cost' => $movementUnitCost,
                            'reason' => $variation
                                ? "Ajuste de edição do pedido {$lockedSale->code} - variação {$variation->name}"
                                : "Ajuste de edição do pedido {$lockedSale->code}",
                            'reference_type' => Sale::class,
                            'reference_id' => $lockedSale->id,
                            'occurred_at' => now(),
                            'metadata' => [
                                'old_quantity' => $oldQuantity,
                                'new_quantity' => $newQuantity,
                                'product_balance_before' => $productBalanceBefore,
                                'product_balance_after' => $productBalanceAfter,
                            ],
                        ]);
                    }
                }
            }

            $paymentsAdjusted = false;
            if (abs($beforeTotal - $total) > 0.0001) {
                $paymentsAdjusted = $this->redistributePayments($lockedSale, $total);
            }

            if ($nextCustomerName === '') {
                unset($metadata['customer_name']);
            } else {
                $metadata['customer_name'] = $nextCustomerName;
            }

            if ($nextCustomerContact === '') {
                unset($metadata['customer_contact']);
            } else {
                $metadata['customer_contact'] = $nextCustomerContact;
            }

            $charges = is_array($metadata['charges'] ?? null) ? $metadata['charges'] : [];
            $paymentMethodSnapshot = is_array($metadata['payment_method_snapshot'] ?? null)
                ? $metadata['payment_method_snapshot']
                : [];

            $paymentFeeFixed = round(max(0, (float) ($charges['payment_fee_fixed'] ?? ($paymentMethodSnapshot['fee_fixed'] ?? 0))), 2);
            $paymentFeePercent = round(max(0, (float) ($charges['payment_fee_percent'] ?? ($paymentMethodSnapshot['fee_percent'] ?? 0))), 2);
            $baseAmountBeforeFee = round(max(0, $subtotal - $discountTotal + $nextShippingAmount), 2);

            $metadata['charges'] = [
                ...$charges,
                'subtotal_amount' => round($subtotal, 2),
                'discount_amount' => round($discountTotal, 2),
                'shipping_amount' => round($nextShippingAmount, 2),
                'payment_fee_amount' => round($paymentFeeAmount, 2),
                'payment_fee_fixed' => $paymentFeeFixed,
                'payment_fee_percent' => $paymentFeePercent,
                'base_amount_before_fee' => $baseAmountBeforeFee,
                'surcharge_amount' => round($surcharge, 2),
                'total_amount' => round($total, 2),
            ];

            $lockedSale->fill([
                'client_id' => $effectiveClientId,
                'shipping_mode' => $nextShippingMode !== '' ? $nextShippingMode : null,
                'shipping_amount' => $nextShippingAmount,
                'shipping_estimate_days' => $nextShippingEstimateDays,
                'subtotal_amount' => round($subtotal, 2),
                'discount_amount' => round($discountTotal, 2),
                'surcharge_amount' => round($surcharge, 2),
                'total_amount' => round($total, 2),
                'paid_amount' => in_array((string) $lockedSale->status, [Sale::STATUS_PAID, Sale::STATUS_COMPLETED], true)
                    ? round($total, 2)
                    : min(round((float) $lockedSale->paid_amount, 2), round($total, 2)),
                'notes' => $nextNotes !== '' ? $nextNotes : null,
                'metadata' => $metadata,
            ])->save();

            if ($beforeClientId !== $effectiveClientId) {
                $changedFields[] = 'client_id';
            }
            if ($before['customer_name'] !== $nextCustomerName) {
                $changedFields[] = 'customer_name';
            }
            if ($before['customer_contact'] !== $nextCustomerContact) {
                $changedFields[] = 'customer_contact';
            }
            if ($beforeShippingMode !== $nextShippingMode) {
                $changedFields[] = 'shipping_mode';
            }
            if (abs($beforeShippingAmount - $nextShippingAmount) > 0.0001) {
                $changedFields[] = 'shipping_amount';
            }
            if ($beforeShippingEstimateDays !== $nextShippingEstimateDays) {
                $changedFields[] = 'shipping_estimate_days';
            }
            if ($beforeNotes !== $nextNotes) {
                $changedFields[] = 'notes';
            }
            if ($itemsChanged) {
                $changedFields[] = 'items';
                if ($shouldAdjustStock) {
                    $changedFields[] = 'stock';
                }
            }
            if (abs($beforeSubtotal - $subtotal) > 0.0001) {
                $changedFields[] = 'subtotal_amount';
            }
            if (abs($beforeDiscount - $discountTotal) > 0.0001) {
                $changedFields[] = 'discount_amount';
            }
            if (abs($beforeSurcharge - $surcharge) > 0.0001) {
                $changedFields[] = 'surcharge_amount';
            }
            if (abs($beforeTotal - $total) > 0.0001) {
                $changedFields[] = 'total_amount';
                $changedFields[] = 'paid_amount';
            }
            if ($paymentsAdjusted) {
                $changedFields[] = 'payments';
            }

            $changedFields = array_values(array_unique($changedFields));
            $updatedSaleId = (int) $lockedSale->id;
            $updatedSaleCode = (string) $lockedSale->code;
            $oldTotalForLog = $beforeTotal;
            $newTotalForLog = $total;
        });

        if ($changedFields !== [] && $updatedSaleId) {
            app(SecurityAuditLogger::class)->log(
                $request,
                'order.updated.admin',
                SecurityAuditLog::SEVERITY_INFO,
                $contractor->id,
                [
                    'sale_id' => $updatedSaleId,
                    'sale_code' => $updatedSaleCode,
                    'changed_fields' => $changedFields,
                    'old_total' => $oldTotalForLog,
                    'new_total' => $newTotalForLog,
                ],
            );
        }

        return back()->with(
            'status',
            $changedFields !== []
                ? 'Pedido atualizado com sucesso.'
                : 'Nenhuma alteração foi aplicada no pedido.'
        );
    }

    public function confirm(Request $request, Sale $sale): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updatedSaleId = null;

        DB::transaction(function () use ($contractor, $sale, $validated, $request, &$updatedSaleId): void {
            $lockedSale = $this->lockOrderForContractor($contractor, $sale->id);

            if (! in_array($lockedSale->status, [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido não pode ser confirmado no status atual.',
                ]);
            }

            if ($lockedSale->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido não possui itens para confirmação.',
                ]);
            }

            $metadata = is_array($lockedSale->metadata) ? $lockedSale->metadata : [];
            $stockReduced = (bool) ($metadata['stock_reduced'] ?? false);
            $stockRestored = (bool) ($metadata['stock_restored'] ?? false);

            if (! $stockReduced || $stockRestored) {
            $productIds = $lockedSale->items
                ->pluck('product_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->filter(static fn (int $value): bool => $value > 0)
                ->unique()
                ->values();
            $variationIds = $lockedSale->items
                ->pluck('product_variation_id')
                ->map(static fn (mixed $value): int => (int) $value)
                ->filter(static fn (int $value): bool => $value > 0)
                ->unique()
                ->values();

            $products = Product::query()
                ->where('contractor_id', $contractor->id)
                ->whereIn('id', $productIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'order' => 'Não foi possível confirmar: item sem produto válido.',
                ]);
            }

            $variationsById = collect();
            if ($variationIds->isNotEmpty()) {
                $variationsById = ProductVariation::withTrashed()
                    ->where('contractor_id', $contractor->id)
                    ->whereIn('id', $variationIds->all())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($variationsById->count() !== $variationIds->count()) {
                    throw ValidationException::withMessages([
                        'order' => 'Não foi possível confirmar: variação inválida em um dos itens.',
                    ]);
                }
            }

            foreach ($lockedSale->items as $item) {
                /** @var Product|null $product */
                $product = $products->get((int) $item->product_id);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'order' => 'Não foi possível confirmar: item sem produto válido.',
                    ]);
                }

                $variationId = $item->product_variation_id ? (int) $item->product_variation_id : null;
                if ($variationId !== null) {
                    /** @var ProductVariation|null $variation */
                    $variation = $variationsById->get($variationId);
                    if (! $variation || (int) $variation->product_id !== (int) $product->id) {
                        throw ValidationException::withMessages([
                            'order' => "Variação inválida para o produto {$product->name}.",
                        ]);
                    }

                    if ((int) $variation->stock_quantity < (int) $item->quantity) {
                        throw ValidationException::withMessages([
                            'order' => "Estoque insuficiente para a variação {$variation->name}.",
                        ]);
                    }

                    continue;
                }

                if ((int) $product->stock_quantity < (int) $item->quantity) {
                    throw ValidationException::withMessages([
                        'order' => "Estoque insuficiente para o produto {$product->name}.",
                    ]);
                }
            }

            foreach ($lockedSale->items as $item) {
                /** @var Product|null $product */
                $product = $products->get((int) $item->product_id);
                if (! $product) {
                    continue;
                }

                $variationId = $item->product_variation_id ? (int) $item->product_variation_id : null;
                /** @var ProductVariation|null $variation */
                $variation = $variationId !== null
                    ? $variationsById->get($variationId)
                    : null;

                $quantity = (int) $item->quantity;
                $movementBalanceBefore = (int) $product->stock_quantity;
                $movementBalanceAfter = max(0, $movementBalanceBefore - $quantity);
                $movementUnitCost = (float) $product->cost_price;

                if ($variation) {
                    $variationBalanceBefore = (int) $variation->stock_quantity;
                    $variationBalanceAfter = max(0, $variationBalanceBefore - $quantity);
                    $variation->stock_quantity = $variationBalanceAfter;
                    $variation->save();

                    $movementBalanceBefore = $variationBalanceBefore;
                    $movementBalanceAfter = $variationBalanceAfter;
                    $movementUnitCost = (float) ($variation->cost_price ?? $product->cost_price);
                }

                $productBalanceBefore = (int) $product->stock_quantity;
                $productBalanceAfter = max(0, $productBalanceBefore - $quantity);
                $product->stock_quantity = $productBalanceAfter;
                $product->save();

                InventoryMovement::query()->create([
                    'contractor_id' => $contractor->id,
                    'product_id' => $product->id,
                    'product_variation_id' => $variation?->id,
                    'sale_item_id' => $item->id,
                    'user_id' => $request->user()?->id,
                    'type' => InventoryMovement::TYPE_OUT,
                    'quantity' => $quantity,
                    'balance_before' => $movementBalanceBefore,
                    'balance_after' => $movementBalanceAfter,
                    'unit_cost' => $movementUnitCost,
                    'reason' => $variation
                        ? "Confirmação do pedido {$lockedSale->code} - variação {$variation->name}"
                        : "Confirmação do pedido {$lockedSale->code}",
                    'reference_type' => Sale::class,
                    'reference_id' => $lockedSale->id,
                    'occurred_at' => now(),
                    'metadata' => [
                        'product_balance_before' => $productBalanceBefore,
                        'product_balance_after' => $productBalanceAfter,
                    ],
                ]);
            }

            }

            $hasPendingPayment = $lockedSale->payments
                ->contains(static fn (SalePayment $payment): bool => in_array($payment->status, [SalePayment::STATUS_PENDING, SalePayment::STATUS_AUTHORIZED], true));

            $status = $hasPendingPayment
                ? Sale::STATUS_AWAITING_PAYMENT
                : Sale::STATUS_CONFIRMED;

            $metadata = is_array($lockedSale->metadata) ? $lockedSale->metadata : [];
            $metadata['stock_reduced'] = true;
            $metadata['stock_reduced_at'] = now()->toIso8601String();
            $metadata['confirmed_at'] = now()->toIso8601String();

            $lockedSale->fill([
                'status' => $status,
                'notes' => $this->appendNote($lockedSale->notes, $validated['notes'] ?? null),
                'metadata' => $metadata,
            ])->save();

            $updatedSaleId = (int) $lockedSale->id;
        });

        $this->notifyOrderStatusChanged($updatedSaleId);

        return back()->with('status', 'Pedido confirmado com sucesso.');
    }

    public function markAsAwaitingPayment(Request $request, Sale $sale): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updatedSaleId = null;

        DB::transaction(function () use ($contractor, $sale, $validated, &$updatedSaleId): void {
            $lockedSale = $this->lockOrderForContractor($contractor, $sale->id);

            if (in_array($lockedSale->status, [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED, Sale::STATUS_REFUNDED], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido não pode voltar para aguardando pagamento no status atual.',
                ]);
            }

            SalePayment::query()
                ->where('contractor_id', $contractor->id)
                ->where('sale_id', $lockedSale->id)
                ->whereIn('status', [SalePayment::STATUS_AUTHORIZED, SalePayment::STATUS_PAID])
                ->update([
                    'status' => SalePayment::STATUS_PENDING,
                    'paid_at' => null,
                ]);

            $lockedSale->fill([
                'status' => Sale::STATUS_AWAITING_PAYMENT,
                'paid_amount' => 0,
                'completed_at' => null,
                'notes' => $this->appendNote($lockedSale->notes, $validated['notes'] ?? null),
            ])->save();

            $updatedSaleId = (int) $lockedSale->id;
        });

        $this->notifyOrderStatusChanged($updatedSaleId);

        return back()->with('status', 'Pedido marcado como aguardando pagamento.');
    }

    public function reject(Request $request, Sale $sale): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $updatedSaleId = null;

        DB::transaction(function () use ($contractor, $sale, $validated, &$updatedSaleId): void {
            $lockedSale = $this->lockOrderForContractor($contractor, $sale->id);

            if (! in_array($lockedSale->status, [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido não pode ser rejeitado no status atual.',
                ]);
            }

            $metadata = is_array($lockedSale->metadata) ? $lockedSale->metadata : [];
            $metadata['rejected_at'] = now()->toIso8601String();
            $metadata['rejection_reason'] = $validated['reason'];

            $lockedSale->fill([
                'status' => Sale::STATUS_REJECTED,
                'notes' => $this->appendNote($lockedSale->notes, "Motivo da rejeição: {$validated['reason']}"),
                'metadata' => $metadata,
                'cancelled_at' => now(),
            ])->save();

            SalePayment::query()
                ->where('contractor_id', $contractor->id)
                ->where('sale_id', $lockedSale->id)
                ->whereIn('status', [SalePayment::STATUS_PENDING, SalePayment::STATUS_AUTHORIZED])
                ->update([
                    'status' => SalePayment::STATUS_CANCELLED,
                ]);

            $updatedSaleId = (int) $lockedSale->id;
        });

        $this->notifyOrderStatusChanged($updatedSaleId);

        return back()->with('status', 'Pedido rejeitado com sucesso.');
    }

    public function markAsPaid(Request $request, Sale $sale): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updatedSaleId = null;

        DB::transaction(function () use ($contractor, $sale, $validated, &$updatedSaleId): void {
            $lockedSale = $this->lockOrderForContractor($contractor, $sale->id);

            if (! in_array($lockedSale->status, [Sale::STATUS_CONFIRMED, Sale::STATUS_AWAITING_PAYMENT], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido não pode ser marcado como pago no status atual.',
                ]);
            }

            $payment = SalePayment::query()
                ->where('contractor_id', $contractor->id)
                ->where('sale_id', $lockedSale->id)
                ->orderByDesc('id')
                ->first();

            if ($payment) {
                $payment->fill([
                    'status' => SalePayment::STATUS_PAID,
                    'paid_at' => now(),
                ])->save();
            }

            $lockedSale->fill([
                'status' => Sale::STATUS_PAID,
                'paid_amount' => (float) $lockedSale->total_amount,
                'completed_at' => now(),
                'notes' => $this->appendNote($lockedSale->notes, $validated['notes'] ?? null),
            ])->save();

            $updatedSaleId = (int) $lockedSale->id;
        });

        $this->notifyOrderStatusChanged($updatedSaleId);

        return back()->with('status', 'Pedido marcado como pago.');
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $updatedSaleId = null;

        DB::transaction(function () use ($contractor, $sale, $validated, $request, &$updatedSaleId): void {
            $lockedSale = $this->lockOrderForContractor($contractor, $sale->id);

            if (in_array($lockedSale->status, [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido já está cancelado.',
                ]);
            }

            $metadata = is_array($lockedSale->metadata) ? $lockedSale->metadata : [];
            $stockReduced = (bool) ($metadata['stock_reduced'] ?? false);
            $stockRestored = (bool) ($metadata['stock_restored'] ?? false);

            if ($stockReduced && ! $stockRestored) {
                $productIds = $lockedSale->items
                    ->pluck('product_id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->filter(static fn (int $value): bool => $value > 0)
                    ->unique()
                    ->values();
                $variationIds = $lockedSale->items
                    ->pluck('product_variation_id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->filter(static fn (int $value): bool => $value > 0)
                    ->unique()
                    ->values();

                $products = Product::query()
                    ->where('contractor_id', $contractor->id)
                    ->whereIn('id', $productIds->all())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $variationsById = collect();
                if ($variationIds->isNotEmpty()) {
                    $variationsById = ProductVariation::withTrashed()
                        ->where('contractor_id', $contractor->id)
                        ->whereIn('id', $variationIds->all())
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');
                }

                foreach ($lockedSale->items as $item) {
                    /** @var Product|null $product */
                    $product = $products->get((int) $item->product_id);
                    if (! $product) {
                        continue;
                    }

                    $variationId = $item->product_variation_id ? (int) $item->product_variation_id : null;
                    /** @var ProductVariation|null $variation */
                    $variation = $variationId !== null
                        ? $variationsById->get($variationId)
                        : null;

                    $quantity = (int) $item->quantity;
                    $movementBalanceBefore = (int) $product->stock_quantity;
                    $movementBalanceAfter = $movementBalanceBefore + $quantity;
                    $movementUnitCost = (float) $product->cost_price;

                    if ($variation) {
                        $variationBalanceBefore = (int) $variation->stock_quantity;
                        $variationBalanceAfter = $variationBalanceBefore + $quantity;
                        $variation->stock_quantity = $variationBalanceAfter;
                        $variation->save();

                        $movementBalanceBefore = $variationBalanceBefore;
                        $movementBalanceAfter = $variationBalanceAfter;
                        $movementUnitCost = (float) ($variation->cost_price ?? $product->cost_price);
                    }

                    $productBalanceBefore = (int) $product->stock_quantity;
                    $productBalanceAfter = $productBalanceBefore + $quantity;
                    $product->stock_quantity = $productBalanceAfter;
                    $product->save();

                    InventoryMovement::query()->create([
                        'contractor_id' => $contractor->id,
                        'product_id' => $product->id,
                        'product_variation_id' => $variation?->id,
                        'sale_item_id' => $item->id,
                        'user_id' => $request->user()?->id,
                        'type' => InventoryMovement::TYPE_RETURN,
                        'quantity' => $quantity,
                        'balance_before' => $movementBalanceBefore,
                        'balance_after' => $movementBalanceAfter,
                        'unit_cost' => $movementUnitCost,
                        'reason' => $variation
                            ? "Cancelamento do pedido {$lockedSale->code} - variação {$variation->name}"
                            : "Cancelamento do pedido {$lockedSale->code}",
                        'reference_type' => Sale::class,
                        'reference_id' => $lockedSale->id,
                        'occurred_at' => now(),
                        'metadata' => [
                            'product_balance_before' => $productBalanceBefore,
                            'product_balance_after' => $productBalanceAfter,
                        ],
                    ]);
                }

                $metadata['stock_restored'] = true;
                $metadata['stock_restored_at'] = now()->toIso8601String();
            }

            $metadata['cancelled_at'] = now()->toIso8601String();
            if (! empty($validated['reason'])) {
                $metadata['cancellation_reason'] = $validated['reason'];
            }

            $lockedSale->fill([
                'status' => Sale::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'notes' => $this->appendNote(
                    $lockedSale->notes,
                    ! empty($validated['reason']) ? "Motivo do cancelamento: {$validated['reason']}" : null
                ),
                'metadata' => $metadata,
            ])->save();

            $payments = SalePayment::query()
                ->where('contractor_id', $contractor->id)
                ->where('sale_id', $lockedSale->id)
                ->get();

            foreach ($payments as $payment) {
                if ($payment->status === SalePayment::STATUS_PAID) {
                    $payment->fill([
                        'status' => SalePayment::STATUS_REFUNDED,
                    ])->save();

                    continue;
                }

                $payment->fill([
                    'status' => SalePayment::STATUS_CANCELLED,
                ])->save();
            }

            $updatedSaleId = (int) $lockedSale->id;
        });

        $this->notifyOrderStatusChanged($updatedSaleId);

        return back()->with('status', 'Pedido cancelado com sucesso.');
    }

    public function updateDeliveryStatus(Request $request, Sale $sale): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'delivery_status' => ['required', Rule::in([
                self::DELIVERY_STATUS_PREPARING,
                self::DELIVERY_STATUS_OUT_FOR_DELIVERY,
                self::DELIVERY_STATUS_DELIVERED,
            ])],
        ]);

        $updatedSaleId = null;
        $updatedSaleCode = null;
        $nextStatus = (string) $validated['delivery_status'];

        DB::transaction(function () use ($contractor, $sale, $nextStatus, &$updatedSaleId, &$updatedSaleCode): void {
            $lockedSale = $this->lockOrderForContractor($contractor, $sale->id);

            if ((string) $lockedSale->shipping_mode !== Sale::SHIPPING_MODE_DELIVERY) {
                throw ValidationException::withMessages([
                    'delivery_status' => 'Somente pedidos de entrega aceitam status de entrega.',
                ]);
            }

            if (in_array((string) $lockedSale->status, [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED], true)) {
                throw ValidationException::withMessages([
                    'delivery_status' => 'Pedido cancelado/rejeitado não permite atualização de entrega.',
                ]);
            }

            $metadata = is_array($lockedSale->metadata) ? $lockedSale->metadata : [];
            $currentStatus = strtolower(trim((string) ($metadata['delivery_status'] ?? '')));
            if (! in_array($currentStatus, [
                self::DELIVERY_STATUS_PREPARING,
                self::DELIVERY_STATUS_OUT_FOR_DELIVERY,
                self::DELIVERY_STATUS_DELIVERED,
            ], true)) {
                $currentStatus = self::DELIVERY_STATUS_PREPARING;
            }

            if ($currentStatus === $nextStatus) {
                return;
            }

            $metadata['delivery_status'] = $nextStatus;
            $metadata['delivery_status_updated_at'] = now()->toIso8601String();
            if ($nextStatus === self::DELIVERY_STATUS_OUT_FOR_DELIVERY) {
                $metadata['delivery_out_for_delivery_at'] = now()->toIso8601String();
            }
            if ($nextStatus === self::DELIVERY_STATUS_DELIVERED) {
                $metadata['delivery_delivered_at'] = now()->toIso8601String();
                if ((string) $lockedSale->status === Sale::STATUS_PAID) {
                    $lockedSale->status = Sale::STATUS_COMPLETED;
                    $lockedSale->completed_at = $lockedSale->completed_at ?? now();
                }
            }

            $lockedSale->metadata = $metadata;
            $lockedSale->save();

            $updatedSaleId = (int) $lockedSale->id;
            $updatedSaleCode = (string) $lockedSale->code;
        });

        if (! $updatedSaleId) {
            return back()->with('status', 'Status de entrega já estava atualizado.');
        }

        app(SecurityAuditLogger::class)->log(
            $request,
            'order.delivery_status.updated.admin',
            SecurityAuditLog::SEVERITY_INFO,
            $contractor->id,
            [
                'sale_id' => $updatedSaleId,
                'sale_code' => $updatedSaleCode,
                'delivery_status' => $nextStatus,
            ],
        );

        $this->notifyOrderStatusChanged($updatedSaleId);

        return back()->with('status', 'Status de entrega atualizado com sucesso.');
    }

    private function lockOrderForContractor(Contractor $contractor, int $saleId): Sale
    {
        $sale = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', $saleId)
            ->whereIn('source', self::ORDER_SOURCES)
            ->with([
                'client:id,name,email,phone',
                'items:id,sale_id,product_id,product_variation_id,description,sku,quantity,unit_price,discount_amount,total_amount,metadata',
                'items.product:id,image_url',
                'items.productVariation:id,name,cost_price',
                'payments:id,sale_id,payment_method_id,status,amount',
                'payments.paymentMethod:id,name,code',
            ])
            ->lockForUpdate()
            ->first();

        if (! $sale) {
            abort(404);
        }

        return $sale;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function resolveStatusOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todos os status'],
            ['value' => Sale::STATUS_PENDING_CONFIRMATION, 'label' => 'Aguardando confirmação'],
            ['value' => Sale::STATUS_NEW, 'label' => 'Novo'],
            ['value' => Sale::STATUS_CONFIRMED, 'label' => 'Confirmado'],
            ['value' => Sale::STATUS_AWAITING_PAYMENT, 'label' => 'Aguardando pagamento'],
            ['value' => Sale::STATUS_PAID, 'label' => 'Pago'],
            ['value' => Sale::STATUS_REJECTED, 'label' => 'Rejeitado'],
            ['value' => Sale::STATUS_CANCELLED, 'label' => 'Cancelado'],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolvePipelineStatuses(string $pipeline): array
    {
        return match ($pipeline) {
            'pending_confirmation' => [Sale::STATUS_PENDING_CONFIRMATION, Sale::STATUS_NEW],
            'awaiting_payment' => [Sale::STATUS_AWAITING_PAYMENT, Sale::STATUS_CONFIRMED],
            'paid' => [Sale::STATUS_PAID],
            'cancelled' => [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function toOrderPayload(Sale $sale): array
    {
        $metadata = is_array($sale->metadata) ? $sale->metadata : [];
        $status = $this->resolveStatusMeta((string) $sale->status);
        $paymentLabel = $sale->payments
            ->map(static fn (SalePayment $payment): ?string => $payment->paymentMethod?->name)
            ->filter()
            ->unique()
            ->values()
            ->implode(' + ');

        $customerName = trim((string) (($metadata['customer_name'] ?? null) ?? ($sale->client?->name ?? '')));
        if ($customerName === '') {
            $customerName = 'Consumidor final';
        }

        $customerContact = trim((string) (($metadata['customer_contact'] ?? null) ?? ($sale->client?->phone ?? '')));
        if ($customerContact === '') {
            $customerContact = trim((string) ($sale->client?->email ?? ($metadata['customer_email'] ?? ($metadata['customer_phone'] ?? ''))));
        }

        $itemDiscountTotal = round((float) $sale->items->sum(static fn (SaleItem $item): float => (float) $item->discount_amount), 2);
        $globalDiscountAmount = max(0.0, round((float) $sale->discount_amount - $itemDiscountTotal, 2));
        $surchargeComponents = $this->resolveOrderSurchargeComponents($sale, $metadata);
        $shippingMode = $sale->shipping_mode !== null ? (string) $sale->shipping_mode : Sale::SHIPPING_MODE_PICKUP;
        $shippingModeLabel = $shippingMode === Sale::SHIPPING_MODE_DELIVERY ? 'Entrega' : 'Retirada na loja';
        $shippingModeTone = $shippingMode === Sale::SHIPPING_MODE_DELIVERY
            ? 'bg-emerald-100 text-emerald-700'
            : 'bg-blue-100 text-blue-700';
        $deliveryStatus = $this->resolveDeliveryStatusMeta(
            $shippingMode === Sale::SHIPPING_MODE_DELIVERY
                ? (string) ($metadata['delivery_status'] ?? '')
                : ''
        );
        $source = (string) $sale->source;
        $channelLabel = match ($source) {
            Sale::SOURCE_ORDER => 'Pedido direto',
            Sale::SOURCE_CATALOG => 'Loja virtual',
            Sale::SOURCE_PDV => 'PDV',
            default => 'Pedido',
        };

        return [
            'id' => (int) $sale->id,
            'code' => (string) $sale->code,
            'source' => $source,
            'client_id' => $sale->client_id ? (int) $sale->client_id : null,
            'customer' => $customerName,
            'customer_contact' => $customerContact,
            'channel' => $channelLabel,
            'subtotal_amount' => (float) $sale->subtotal_amount,
            'discount_amount' => (float) $sale->discount_amount,
            'global_discount_amount' => $globalDiscountAmount,
            'non_shipping_surcharge_amount' => (float) $surchargeComponents['non_shipping_surcharge'],
            'payment_fee_amount' => (float) $surchargeComponents['payment_fee_amount'],
            'total_amount' => (float) $sale->total_amount,
            'total_items' => (int) $sale->items->sum(static fn ($item): int => (int) $item->quantity),
            'items' => $sale->items
                ->map(static fn (SaleItem $item): array => [
                    'product_id' => $item->product_id ? (int) $item->product_id : null,
                    'variation_id' => $item->product_variation_id ? (int) $item->product_variation_id : null,
                    'variation_name' => (string) (data_get($item->metadata, 'variation_name')
                        ?: ($item->productVariation?->name ?? '')),
                    'description' => (string) $item->description,
                    'sku' => $item->sku !== null ? (string) $item->sku : null,
                    'image_url' => $item->product?->image_url !== null ? (string) $item->product->image_url : null,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_amount' => (float) $item->discount_amount,
                    'total_amount' => (float) $item->total_amount,
                ])
                ->values()
                ->all(),
            'status' => $status,
            'payment_label' => $paymentLabel !== '' ? $paymentLabel : 'Não informado',
            'created_at' => optional($sale->created_at)->format('d/m/Y H:i'),
            'shipping_mode' => $shippingMode,
            'shipping_mode_label' => $shippingModeLabel,
            'shipping_mode_tone' => $shippingModeTone,
            'delivery_status' => $deliveryStatus,
            'shipping_amount' => (float) $sale->shipping_amount,
            'shipping_estimate_days' => $sale->shipping_estimate_days !== null ? (int) $sale->shipping_estimate_days : null,
            'notes' => $sale->notes !== null ? (string) $sale->notes : null,
            'can_confirm' => in_array($sale->status, [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION], true),
            'can_reject' => in_array($sale->status, [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION], true),
            'can_set_awaiting_payment' => in_array($sale->status, [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION, Sale::STATUS_CONFIRMED, Sale::STATUS_PAID], true),
            'can_mark_paid' => in_array($sale->status, [Sale::STATUS_CONFIRMED, Sale::STATUS_AWAITING_PAYMENT], true),
            'can_cancel' => ! in_array($sale->status, [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED], true),
            'can_update_delivery_status' => $shippingMode === Sale::SHIPPING_MODE_DELIVERY
                && ! in_array($sale->status, [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED], true),
            'can_edit' => $this->canEditOrder($sale),
            'can_edit_customer' => $this->canEditOrderCustomer($sale),
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

    private function canEditOrder(Sale $sale): bool
    {
        return ! in_array($sale->status, [
            Sale::STATUS_CANCELLED,
            Sale::STATUS_REJECTED,
            Sale::STATUS_COMPLETED,
            Sale::STATUS_REFUNDED,
        ], true);
    }

    private function canEditOrderCustomer(Sale $sale): bool
    {
        return (string) $sale->source === Sale::SOURCE_PDV;
    }

    /**
     * @return array{value: string, label: string, tone: string}
     */
    private function resolveDeliveryStatusMeta(string $status): array
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            self::DELIVERY_STATUS_OUT_FOR_DELIVERY => [
                'value' => self::DELIVERY_STATUS_OUT_FOR_DELIVERY,
                'label' => 'Em entrega',
                'tone' => 'bg-blue-100 text-blue-700',
            ],
            self::DELIVERY_STATUS_DELIVERED => [
                'value' => self::DELIVERY_STATUS_DELIVERED,
                'label' => 'Entregue',
                'tone' => 'bg-emerald-100 text-emerald-700',
            ],
            default => [
                'value' => self::DELIVERY_STATUS_PREPARING,
                'label' => 'Em preparo',
                'tone' => 'bg-amber-100 text-amber-700',
            ],
        };
    }

    /**
     * @param  Collection<int, SaleItem>  $items
     * @return list<array{product_id:int,variation_id:int|null,quantity:int,discount_amount:float}>
     */
    private function normalizeExistingItems(Collection $items): array
    {
        return $items
            ->groupBy(fn (SaleItem $item): string => $this->resolveItemLineKey(
                (int) $item->product_id,
                $item->product_variation_id ? (int) $item->product_variation_id : null
            ))
            ->map(static function (Collection $rows): array {
                /** @var SaleItem|null $first */
                $first = $rows->first();

                return [
                    'product_id' => $first ? (int) $first->product_id : 0,
                    'variation_id' => $first && $first->product_variation_id
                        ? (int) $first->product_variation_id
                        : null,
                    'quantity' => (int) $rows->sum(static fn (SaleItem $item): int => (int) $item->quantity),
                    'discount_amount' => round((float) $rows->sum(static fn (SaleItem $item): float => (float) $item->discount_amount), 2),
                ];
            })
            ->sortBy(fn (array $line): string => $this->resolveItemLineKey(
                (int) $line['product_id'],
                isset($line['variation_id']) && (int) $line['variation_id'] > 0
                    ? (int) $line['variation_id']
                    : null
            ))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{product_id:int,variation_id:int|null,quantity:int,discount_amount:float}>  $requestedItems
     * @return Collection<string, array{product_id:int,variation_id:int|null,quantity:int,discount_amount:float}>
     */
    private function groupRequestedItems(Collection $requestedItems): Collection
    {
        return $requestedItems
            ->groupBy(fn (array $row): string => $this->resolveItemLineKey(
                (int) ($row['product_id'] ?? 0),
                isset($row['variation_id']) && (int) $row['variation_id'] > 0
                    ? (int) $row['variation_id']
                    : null
            ))
            ->map(static function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'product_id' => (int) ($first['product_id'] ?? 0),
                    'variation_id' => isset($first['variation_id']) && (int) $first['variation_id'] > 0
                        ? (int) $first['variation_id']
                        : null,
                    'quantity' => (int) $rows->sum(static fn (array $row): int => (int) ($row['quantity'] ?? 0)),
                    'discount_amount' => round((float) $rows->sum(static fn (array $row): float => (float) ($row['discount_amount'] ?? 0)), 2),
                ];
            });
    }

    private function resolveItemLineKey(int $productId, ?int $variationId = null): string
    {
        $safeProductId = max(0, $productId);
        $safeVariationId = $variationId !== null && $variationId > 0 ? $variationId : 0;

        return "{$safeProductId}|{$safeVariationId}";
    }

    private function redistributePayments(Sale $sale, float $targetTotal): bool
    {
        $payments = $sale->payments->values();
        if ($payments->isEmpty()) {
            return false;
        }

        $targetTotal = round($targetTotal, 2);
        $currentTotal = round((float) $payments->sum(static fn (SalePayment $payment): float => (float) $payment->amount), 2);

        if (abs($currentTotal - $targetTotal) <= 0.0001) {
            return false;
        }

        if ($payments->count() === 1) {
            /** @var SalePayment $single */
            $single = $payments->first();
            $single->amount = $targetTotal;
            $single->save();

            return true;
        }

        $remaining = $targetTotal;
        $lastIndex = $payments->count() - 1;

        foreach ($payments as $index => $payment) {
            $amount = 0.0;

            if ($index === $lastIndex) {
                $amount = round($remaining, 2);
            } elseif ($currentTotal > 0) {
                $ratio = (float) $payment->amount / $currentTotal;
                $amount = round($targetTotal * $ratio, 2);
                $remaining = round($remaining - $amount, 2);
            }

            $payment->amount = $amount;
            $payment->save();
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{shipping_amount:float,payment_fee_amount:float,non_shipping_surcharge:float}
     */
    private function resolveOrderSurchargeComponents(Sale $sale, array $metadata): array
    {
        $shippingAmount = round(max(0, (float) $sale->shipping_amount), 2);
        $surchargeAmount = round(max(0, (float) $sale->surcharge_amount), 2);
        $derivedNonShipping = round(max(0, $surchargeAmount - $shippingAmount), 2);

        $charges = is_array($metadata['charges'] ?? null) ? $metadata['charges'] : [];
        $paymentFeeFromCharges = round(max(0, (float) ($charges['payment_fee_amount'] ?? 0)), 2);
        $paymentFeeAmount = $paymentFeeFromCharges > 0 ? $paymentFeeFromCharges : $derivedNonShipping;

        return [
            'shipping_amount' => $shippingAmount,
            'payment_fee_amount' => $paymentFeeAmount,
            'non_shipping_surcharge' => max($derivedNonShipping, $paymentFeeAmount),
        ];
    }

    private function appendNote(?string $base, ?string $extra): ?string
    {
        $current = trim((string) ($base ?? ''));
        $addition = trim((string) ($extra ?? ''));

        if ($addition === '') {
            return $current !== '' ? $current : null;
        }

        if ($current === '') {
            return $addition;
        }

        return "{$current}\n\n{$addition}";
    }

    private function notifyOrderStatusChanged(?int $saleId): void
    {
        if (! $saleId) {
            return;
        }

        $sale = Sale::query()->find($saleId);
        if (! $sale) {
            return;
        }

        app(OrderNotificationService::class)->notifyOrderStatusChanged($sale);
    }
}
