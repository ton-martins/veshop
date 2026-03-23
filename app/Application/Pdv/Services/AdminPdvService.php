<?php

namespace App\Application\Pdv\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Http\Requests\Admin\CloseCashSessionRequest;
use App\Http\Requests\Admin\OpenCashSessionRequest;
use App\Http\Requests\Admin\StorePdvClientRequest;
use App\Http\Requests\Admin\StorePdvSaleRequest;
use App\Http\Requests\Admin\UpdatePdvFeaturedProductsRequest;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\InventoryMovement;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Support\PaymentFeeSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminPdvService
{
    use ResolvesCurrentContractor;

    public function index(Request $request): Response|RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        if ($contractor->niche() === Contractor::NICHE_SERVICES) {
            $timezone = trim((string) ($contractor->timezone ?: config('app.timezone', 'America/Sao_Paulo')));
            if ($timezone === '') {
                $timezone = (string) config('app.timezone', 'America/Sao_Paulo');
            }

            return redirect()->route('admin.services.pdv', [
                'layout' => 'day',
                'reference_date' => now($timezone)->toDateString(),
            ]);
        }

        $openCashSession = CashSession::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', CashSession::STATUS_OPEN)
            ->with('openedBy:id,name')
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->first();

        $cashSummary = [
            'cash_in' => 0.0,
            'cash_out' => 0.0,
            'expected_balance' => $openCashSession ? (float) $openCashSession->opening_balance : 0.0,
        ];

        if ($openCashSession) {
            $totals = CashMovement::query()
                ->where('contractor_id', $contractor->id)
                ->where('cash_session_id', $openCashSession->id)
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as total_in")
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as total_out")
                ->first();

            $cashIn = (float) ($totals?->total_in ?? 0);
            $cashOut = (float) ($totals?->total_out ?? 0);

            $cashSummary = [
                'cash_in' => $cashIn,
                'cash_out' => $cashOut,
                'expected_balance' => $cashIn - $cashOut,
            ];
        }

        $products = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->with([
                'category:id,name',
                'variations' => static fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->select(['id', 'product_id', 'name', 'sku', 'sale_price', 'stock_quantity', 'attributes']),
            ])
            ->orderByDesc('is_pdv_featured')
            ->orderBy('pdv_featured_order')
            ->orderBy('name')
            ->limit(250)
            ->get([
                'id',
                'name',
                'sku',
                'sale_price',
                'stock_quantity',
                'unit',
                'image_url',
                'category_id',
                'is_pdv_featured',
                'pdv_featured_order',
            ])
            ->map(static function (Product $product): array {
                $activeVariations = $product->variations
                    ->filter(static fn (ProductVariation $variation): bool => (int) $variation->stock_quantity > 0)
                    ->values();

                $hasVariations = $activeVariations->isNotEmpty();
                $stockQuantity = $hasVariations
                    ? (int) $activeVariations->sum(static fn (ProductVariation $variation): int => (int) $variation->stock_quantity)
                    : (int) $product->stock_quantity;
                $displayPrice = $hasVariations
                    ? (float) $activeVariations->min(static fn (ProductVariation $variation): float => (float) $variation->sale_price)
                    : (float) $product->sale_price;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'sale_price' => $displayPrice,
                    'stock_quantity' => $stockQuantity,
                    'unit' => $product->unit,
                    'image_url' => $product->image_url,
                    'category_id' => $product->category_id ? (int) $product->category_id : null,
                    'category_name' => $product->category?->name ?? 'Sem categoria',
                    'is_pdv_featured' => (bool) $product->is_pdv_featured,
                    'pdv_featured_order' => $product->pdv_featured_order ? (int) $product->pdv_featured_order : null,
                    'has_variations' => $hasVariations,
                    'variations' => $activeVariations
                        ->map(static fn (ProductVariation $variation): array => [
                            'id' => (int) $variation->id,
                            'name' => (string) $variation->name,
                            'sku' => $variation->sku ? (string) $variation->sku : null,
                            'sale_price' => (float) $variation->sale_price,
                            'stock_quantity' => (int) $variation->stock_quantity,
                            'attributes' => is_array($variation->attributes) ? $variation->attributes : [],
                        ])
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $clients = Client::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(250)
            ->get(['id', 'name'])
            ->map(static fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
            ])
            ->values()
            ->all();

        $paymentMethods = PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->with('paymentGateway:id,name')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static fn (PaymentMethod $method): array => [
                'id' => $method->id,
                'name' => $method->name,
                'code' => $method->code,
                'is_default' => (bool) $method->is_default,
                'allows_installments' => (bool) $method->allows_installments,
                'max_installments' => $method->max_installments,
                'payment_gateway_id' => $method->payment_gateway_id,
                'payment_gateway_name' => $method->paymentGateway?->name,
                'fee_fixed' => round((float) ($method->fee_fixed ?? 0), 2),
                'fee_percent' => round((float) ($method->fee_percent ?? 0), 2),
            ])
            ->values()
            ->all();

        $recentSales = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('source', Sale::SOURCE_PDV)
            ->where('status', Sale::STATUS_COMPLETED)
            ->with([
                'client:id,name',
                'payments.paymentMethod:id,name',
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(static function (Sale $sale): array {
                $paymentNames = $sale->payments
                    ->map(static fn (SalePayment $payment): ?string => $payment->paymentMethod?->name)
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'id' => $sale->id,
                    'code' => $sale->code,
                    'customer' => $sale->client?->name ?? 'Consumidor final',
                    'total_amount' => (float) $sale->total_amount,
                    'payment_label' => $paymentNames->isNotEmpty() ? $paymentNames->implode(' + ') : 'Não informado',
                    'completed_at' => optional($sale->completed_at)->format('d/m/Y H:i'),
                ];
            })
            ->values()
            ->all();

        $initialAction = trim((string) $request->string('action')->toString());
        if (! in_array($initialAction, ['open-cash', 'close-cash'], true)) {
            $initialAction = null;
        }

        $initialClientId = (int) $request->session()->pull('pdv_new_client_id', 0);
        if ($initialClientId > 0) {
            $clientIds = collect($clients)->pluck('id')->map(static fn (mixed $id): int => (int) $id);
            if (! $clientIds->contains($initialClientId)) {
                $initialClientId = 0;
            }
        }

        return Inertia::render('Admin/Pdv/Index', [
            'cashSession' => $openCashSession ? [
                'id' => $openCashSession->id,
                'code' => $openCashSession->code,
                'status' => $openCashSession->status,
                'opened_at' => optional($openCashSession->opened_at)->format('d/m/Y H:i'),
                'opening_balance' => (float) $openCashSession->opening_balance,
                'opened_by' => $openCashSession->openedBy?->name,
            ] : null,
            'cashSummary' => $cashSummary,
            'products' => $products,
            'clients' => $clients,
            'paymentMethods' => $paymentMethods,
            'recentSales' => $recentSales,
            'initialAction' => $initialAction,
            'initialClientId' => $initialClientId > 0 ? $initialClientId : null,
        ]);
    }

    public function openCashSession(OpenCashSessionRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $alreadyOpen = CashSession::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', CashSession::STATUS_OPEN)
            ->exists();

        if ($alreadyOpen) {
            throw ValidationException::withMessages([
                'cash_session' => 'Já existe um caixa aberto para este contratante.',
            ]);
        }

        $data = $request->validated();
        $openingBalance = (float) ($data['opening_balance'] ?? 0);
        $notes = $data['notes'] ?? null;
        $user = $request->user();

        DB::transaction(function () use ($contractor, $openingBalance, $notes, $user): void {
            $cashSession = CashSession::query()->create([
                'contractor_id' => $contractor->id,
                'opened_by_user_id' => $user?->id,
                'code' => $this->generateCashSessionCode($contractor),
                'status' => CashSession::STATUS_OPEN,
                'opened_at' => now(),
                'opening_balance' => $openingBalance,
                'expected_balance' => $openingBalance,
                'notes' => $notes,
            ]);

            CashMovement::query()->create([
                'contractor_id' => $contractor->id,
                'cash_session_id' => $cashSession->id,
                'user_id' => $user?->id,
                'type' => 'opening_balance',
                'direction' => 'in',
                'amount' => $openingBalance,
                'description' => 'Abertura de caixa',
                'reference_type' => CashSession::class,
                'reference_id' => $cashSession->id,
                'occurred_at' => now(),
            ]);
        });

        return back()->with('status', 'Caixa aberto com sucesso.');
    }

    public function closeCashSession(CloseCashSessionRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $cashSession = CashSession::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', CashSession::STATUS_OPEN)
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->first();

        if (! $cashSession) {
            throw ValidationException::withMessages([
                'cash_session' => 'Nenhum caixa aberto para fechamento.',
            ]);
        }

        $totals = CashMovement::query()
            ->where('contractor_id', $contractor->id)
            ->where('cash_session_id', $cashSession->id)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) as total_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) as total_out")
            ->first();

        $expectedBalance = (float) ($totals?->total_in ?? 0) - (float) ($totals?->total_out ?? 0);
        $closingBalance = (float) ($request->validated()['closing_balance'] ?? 0);
        $difference = $closingBalance - $expectedBalance;

        $cashSession->fill([
            'closed_by_user_id' => $request->user()?->id,
            'status' => CashSession::STATUS_CLOSED,
            'closed_at' => now(),
            'closing_balance' => $closingBalance,
            'expected_balance' => $expectedBalance,
            'difference_amount' => $difference,
            'notes' => $request->validated()['notes'] ?? $cashSession->notes,
        ])->save();

        return back()->with('status', 'Caixa fechado com sucesso.');
    }

    public function storeSale(StorePdvSaleRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $cashSession = CashSession::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', CashSession::STATUS_OPEN)
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->first();

        if (! $cashSession) {
            throw ValidationException::withMessages([
                'cash_session' => 'Abra o caixa para realizar vendas no PDV.',
            ]);
        }

        $data = $request->validated();
        $clientId = $data['client_id'] ?? null;
        if ($clientId !== null) {
            $clientExists = Client::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $clientId)
                ->exists();

            if (! $clientExists) {
                throw ValidationException::withMessages([
                    'client_id' => 'Cliente inválido para o contratante ativo.',
                ]);
            }
        }

        $paymentMethod = PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', (int) ($data['payment_method_id'] ?? 0))
            ->where('is_active', true)
            ->first();

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Forma de pagamento inválida para o contratante ativo.',
            ]);
        }

        $installments = null;
        if ($paymentMethod->allows_installments) {
            $installments = (int) ($data['installments'] ?? 0);

            if ($installments < 2) {
                throw ValidationException::withMessages([
                    'installments' => 'Informe a quantidade de parcelas para esta forma de pagamento.',
                ]);
            }

            $maxInstallments = (int) ($paymentMethod->max_installments ?? 0);
            if ($maxInstallments > 0 && $installments > $maxInstallments) {
                throw ValidationException::withMessages([
                    'installments' => "Esta forma permite no máximo {$maxInstallments} parcelas.",
                ]);
            }
        }

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

        DB::transaction(function () use ($contractor, $cashSession, $clientId, $paymentMethod, $installments, $data, $groupedItems, $request): void {
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
                    'variations:id,product_id,name,sku,sale_price,cost_price,stock_quantity,is_active,attributes',
                ])
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Um ou mais produtos são inválidos para o contratante ativo.',
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
                        'items' => 'Uma ou mais variações não são válidas para o contratante ativo.',
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
                /** @var Product $product */
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

                    $currentVariationStock = (int) $variation->stock_quantity;
                    if ($safeQuantity > $currentVariationStock) {
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

                    $currentStock = (int) $product->stock_quantity;
                    if ($safeQuantity > $currentStock) {
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

            $discount = round((float) ($data['discount_amount'] ?? 0), 2);
            $manualSurcharge = round((float) ($data['surcharge_amount'] ?? 0), 2);
            $baseAmount = round($subtotal - $discount + $manualSurcharge, 2);
            $paymentFeeSnapshot = PaymentFeeSnapshot::fromPaymentMethod($paymentMethod);
            $paymentFeeAmount = PaymentFeeSnapshot::resolveFeeAmount($baseAmount, $paymentFeeSnapshot);
            $surcharge = round($manualSurcharge + $paymentFeeAmount, 2);
            $total = round($baseAmount + $paymentFeeAmount, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'O valor total da venda precisa ser maior que zero.',
                ]);
            }

            $sale = Sale::query()->create([
                'contractor_id' => $contractor->id,
                'cash_session_id' => $cashSession->id,
                'client_id' => $clientId,
                'user_id' => $request->user()?->id,
                'code' => $this->generateSaleCode($contractor),
                'source' => Sale::SOURCE_PDV,
                'status' => Sale::STATUS_COMPLETED,
                'subtotal_amount' => $subtotal,
                'discount_amount' => $discount,
                'surcharge_amount' => $surcharge,
                'total_amount' => $total,
                'paid_amount' => $total,
                'change_amount' => 0,
                'notes' => $data['notes'] ?? null,
                'completed_at' => now(),
                'metadata' => [
                    'checkout_mode' => 'pdv',
                    'payment_method_snapshot' => $paymentFeeSnapshot,
                    'charges' => [
                        'subtotal_amount' => round($subtotal, 2),
                        'discount_amount' => round($discount, 2),
                        'manual_surcharge_amount' => round($manualSurcharge, 2),
                        'payment_fee_amount' => round($paymentFeeAmount, 2),
                        'payment_fee_fixed' => round((float) ($paymentFeeSnapshot['fee_fixed'] ?? 0), 2),
                        'payment_fee_percent' => round((float) ($paymentFeeSnapshot['fee_percent'] ?? 0), 2),
                        'base_amount_before_fee' => round($baseAmount, 2),
                        'surcharge_amount' => round($surcharge, 2),
                        'total_amount' => round($total, 2),
                    ],
                ],
            ]);

            foreach ($preparedLines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                /** @var ProductVariation|null $variation */
                $variation = $line['variation'];
                $quantity = (int) $line['quantity'];

                $saleItem = SaleItem::query()->create([
                    'contractor_id' => $contractor->id,
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_variation_id' => $variation?->id,
                    'description' => (string) ($line['description'] ?? $product->name),
                    'sku' => trim((string) ($line['sku'] ?? $product->sku)) !== ''
                        ? trim((string) ($line['sku'] ?? $product->sku))
                        : null,
                    'quantity' => $quantity,
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

                $movementBalanceBefore = (int) $product->stock_quantity;
                $movementBalanceAfter = max(0, $movementBalanceBefore - $quantity);

                if ($variation) {
                    $movementBalanceBefore = (int) $variation->stock_quantity;
                    $movementBalanceAfter = max(0, $movementBalanceBefore - $quantity);
                    $variation->stock_quantity = $movementBalanceAfter;
                    $variation->save();
                }

                $productBalanceBefore = (int) $product->stock_quantity;
                $productBalanceAfter = max(0, $productBalanceBefore - $quantity);
                $product->stock_quantity = $productBalanceAfter;
                $product->save();

                InventoryMovement::query()->create([
                    'contractor_id' => $contractor->id,
                    'product_id' => $product->id,
                    'product_variation_id' => $variation?->id,
                    'sale_item_id' => $saleItem->id,
                    'user_id' => $request->user()?->id,
                    'type' => InventoryMovement::TYPE_OUT,
                    'quantity' => $quantity,
                    'balance_before' => $movementBalanceBefore,
                    'balance_after' => $movementBalanceAfter,
                    'unit_cost' => $variation?->cost_price ?? $product->cost_price,
                    'reason' => $variation
                        ? "Venda PDV {$sale->code} - variação {$variation->name}"
                        : "Venda PDV {$sale->code}",
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'occurred_at' => now(),
                ]);
            }

            SalePayment::query()->create([
                'contractor_id' => $contractor->id,
                'sale_id' => $sale->id,
                'payment_method_id' => $paymentMethod->id,
                'payment_gateway_id' => $paymentMethod->payment_gateway_id,
                'status' => SalePayment::STATUS_PAID,
                'amount' => $total,
                'installments' => $installments,
                'paid_at' => now(),
                'metadata' => [
                    'checkout_mode' => 'pdv',
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

            if ($paymentMethod->code === PaymentMethod::CODE_CASH) {
                CashMovement::query()->create([
                    'contractor_id' => $contractor->id,
                    'cash_session_id' => $cashSession->id,
                    'user_id' => $request->user()?->id,
                    'type' => 'sale_payment',
                    'direction' => 'in',
                    'amount' => $total,
                    'description' => "Recebimento da venda {$sale->code}",
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'occurred_at' => now(),
                    'metadata' => [
                        'payment_method_code' => $paymentMethod->code,
                    ],
                ]);
            }
        });

        return back()->with('status', 'Venda concluída com sucesso.');
    }

    public function updateFeaturedProducts(UpdatePdvFeaturedProductsRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $productIds = collect($request->validated('product_ids', []))
            ->map(static fn (mixed $id): int => (int) $id)
            ->values();

        if ($productIds->isNotEmpty()) {
            $ownedCount = Product::query()
                ->where('contractor_id', $contractor->id)
                ->whereIn('id', $productIds->all())
                ->count();

            if ($ownedCount !== $productIds->count()) {
                throw ValidationException::withMessages([
                    'product_ids' => 'A seleção possui produtos inválidos para o contratante ativo.',
                ]);
            }
        }

        DB::transaction(function () use ($contractor, $productIds): void {
            Product::query()
                ->where('contractor_id', $contractor->id)
                ->update([
                    'is_pdv_featured' => false,
                    'pdv_featured_order' => null,
                ]);

            $productIds->each(function (int $productId, int $index) use ($contractor): void {
                Product::query()
                    ->where('contractor_id', $contractor->id)
                    ->where('id', $productId)
                    ->update([
                        'is_pdv_featured' => true,
                        'pdv_featured_order' => $index + 1,
                    ]);
            });
        });

        return back()->with('status', 'Produtos prioritários do PDV atualizados com sucesso.');
    }

    public function storeClient(StorePdvClientRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $client = Client::query()->create([
            ...$request->validated(),
            'contractor_id' => $contractor->id,
            'is_active' => true,
        ]);

        return back()
            ->with('status', 'Cliente criado com sucesso no PDV.')
            ->with('pdv_new_client_id', $client->id);
    }

    private function generateCashSessionCode(Contractor $contractor): string
    {
        do {
            $code = 'CXA-'.now()->format('Ymd-His').'-'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

            $exists = CashSession::query()
                ->where('contractor_id', $contractor->id)
                ->where('code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }

    private function generateSaleCode(Contractor $contractor): string
    {
        do {
            $code = 'VDA-'.now()->format('Ymd-His').'-'.str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

            $exists = Sale::query()
                ->where('contractor_id', $contractor->id)
                ->where('code', $code)
                ->exists();
        } while ($exists);

        return $code;
    }
}
