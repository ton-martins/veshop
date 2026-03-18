<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SecurityAuditLog;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());
        $pipelineFilter = trim((string) $request->string('pipeline')->toString());

        $baseQuery = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('source', Sale::SOURCE_PDV);

        $salesQuery = (clone $baseQuery)
            ->with([
                'client:id,name,email,phone',
                'items:id,sale_id,product_id,description,sku,quantity,unit_price,discount_amount,total_amount',
                'items.product:id,image_url',
                'payments:id,sale_id,payment_method_id,status,amount',
                'payments.paymentMethod:id,name,code',
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($search !== '') {
            $salesQuery->where(function ($query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search): void {
                        $clientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== '') {
            $salesQuery->where('status', $status);
        }

        $pipelineStatuses = $this->resolvePipelineStatuses($pipelineFilter);
        if ($pipelineStatuses !== []) {
            $salesQuery->whereIn('status', $pipelineStatuses);
        }

        $sales = $salesQuery
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Sale $sale): array => $this->toSalePayload($sale));

        $totals = [
            'all' => (clone $baseQuery)->count(),
            'draft' => (clone $baseQuery)
                ->where('status', Sale::STATUS_DRAFT)
                ->count(),
            'open' => (clone $baseQuery)
                ->whereIn('status', [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION, Sale::STATUS_CONFIRMED, Sale::STATUS_AWAITING_PAYMENT])
                ->count(),
            'completed' => (clone $baseQuery)
                ->whereIn('status', [Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
                ->count(),
            'cancelled' => (clone $baseQuery)
                ->whereIn('status', [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED, Sale::STATUS_REFUNDED])
                ->count(),
        ];

        $pipeline = [
            ['key' => 'draft', 'label' => 'Orçamentos', 'qty' => (int) $totals['draft'], 'tone' => 'bg-slate-100 text-slate-700'],
            ['key' => 'open', 'label' => 'Em aberto', 'qty' => (int) $totals['open'], 'tone' => 'bg-amber-100 text-amber-700'],
            ['key' => 'completed', 'label' => 'Concluídas', 'qty' => (int) $totals['completed'], 'tone' => 'bg-emerald-100 text-emerald-700'],
            ['key' => 'cancelled', 'label' => 'Canceladas/estornadas', 'qty' => (int) $totals['cancelled'], 'tone' => 'bg-rose-100 text-rose-700'],
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
            ->orderBy('name')
            ->limit(800)
            ->get(['id', 'name', 'sku', 'sale_price', 'stock_quantity', 'image_url', 'is_active'])
            ->map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sku' => $product->sku ? (string) $product->sku : null,
                'sale_price' => (float) $product->sale_price,
                'stock_quantity' => (int) $product->stock_quantity,
                'image_url' => $product->image_url ? (string) $product->image_url : null,
                'is_active' => (bool) $product->is_active,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Sales/Index', [
            'sales' => $sales,
            'saleStats' => $totals,
            'pipeline' => $pipeline,
            'statusOptions' => $this->resolveStatusOptions(),
            'clients' => $clients,
            'products' => $products,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'pipeline' => in_array($pipelineFilter, ['draft', 'open', 'completed', 'cancelled'], true)
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
            'surcharge_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:80'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);

        $clientId = isset($validated['client_id']) && $validated['client_id'] !== ''
            ? (int) $validated['client_id']
            : null;

        $selectedClient = null;
        if ($clientId !== null) {
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

        $requestedItems = collect($validated['items'])
            ->map(static function (array $row): array {
                return [
                    'product_id' => (int) ($row['product_id'] ?? 0),
                    'quantity' => (int) ($row['quantity'] ?? 0),
                    'discount_amount' => round((float) ($row['discount_amount'] ?? 0), 2),
                ];
            })
            ->filter(static fn (array $row): bool => $row['product_id'] > 0 && $row['quantity'] > 0)
            ->values();

        if ($requestedItems->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Adicione ao menos um produto na venda.',
            ]);
        }

        $updatedSaleId = null;
        $updatedSaleCode = null;
        $changedFields = [];
        $oldTotalForLog = null;
        $newTotalForLog = null;

        DB::transaction(function () use ($contractor, $sale, $validated, $requestedItems, $clientId, $selectedClient, $request, &$updatedSaleId, &$updatedSaleCode, &$changedFields, &$oldTotalForLog, &$newTotalForLog): void {
            $lockedSale = $this->lockSaleForContractor($contractor, $sale->id);

            if (! $this->canEditSale($lockedSale)) {
                throw ValidationException::withMessages([
                    'sale' => 'Esta venda não pode ser editada no status atual.',
                ]);
            }

            $metadata = is_array($lockedSale->metadata) ? $lockedSale->metadata : [];

            $beforeItems = $this->normalizeExistingItems($lockedSale->items);
            $beforeTotal = round((float) $lockedSale->total_amount, 2);
            $beforeDiscount = round((float) $lockedSale->discount_amount, 2);
            $beforeSurcharge = round((float) $lockedSale->surcharge_amount, 2);
            $beforeClientId = $lockedSale->client_id ? (int) $lockedSale->client_id : null;
            $beforeNotes = trim((string) ($lockedSale->notes ?? ''));

            $before = [
                'customer_name' => trim((string) ($metadata['customer_name'] ?? ($lockedSale->client?->name ?? ''))),
                'customer_contact' => trim((string) ($metadata['customer_contact'] ?? ($lockedSale->client?->phone ?? ($lockedSale->client?->email ?? '')))),
            ];

            $groupedRequestedItems = $this->groupRequestedItems($requestedItems);
            $oldQtyByProduct = $lockedSale->items
                ->groupBy(static fn (SaleItem $item): int => (int) $item->product_id)
                ->map(static fn (Collection $rows): int => (int) $rows->sum(static fn (SaleItem $item): int => (int) $item->quantity));

            $allProductIds = $oldQtyByProduct->keys()
                ->merge($groupedRequestedItems->keys())
                ->map(static fn (mixed $value): int => (int) $value)
                ->unique()
                ->values();

            $products = Product::query()
                ->where('contractor_id', $contractor->id)
                ->whereIn('id', $allProductIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($products->count() !== $allProductIds->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Um ou mais produtos são inválidos para o contratante ativo.',
                ]);
            }

            $preparedLines = [];
            $subtotal = 0.0;
            $lineDiscountTotal = 0.0;

            foreach ($groupedRequestedItems as $productId => $row) {
                $safeProductId = (int) $productId;
                $quantity = (int) ($row['quantity'] ?? 0);
                $requestedLineDiscount = round((float) ($row['discount_amount'] ?? 0), 2);

                /** @var Product|null $product */
                $product = $products->get($safeProductId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'Produto inválido na edição da venda.',
                    ]);
                }

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Quantidade inválida para o produto {$product->name}.",
                    ]);
                }

                $oldQuantity = (int) ($oldQtyByProduct->get($safeProductId) ?? 0);
                $delta = $quantity - $oldQuantity;
                if ($delta > 0 && $delta > (int) $product->stock_quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Estoque insuficiente para o produto {$product->name}.",
                    ]);
                }

                $unitPrice = round((float) $product->sale_price, 2);
                $lineSubtotal = round($unitPrice * $quantity, 2);
                $lineDiscount = min(max($requestedLineDiscount, 0.0), $lineSubtotal);
                $lineTotal = round($lineSubtotal - $lineDiscount, 2);

                $preparedLines[$safeProductId] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_subtotal' => $lineSubtotal,
                    'discount_amount' => $lineDiscount,
                    'line_total' => $lineTotal,
                ];

                $subtotal += $lineSubtotal;
                $lineDiscountTotal += $lineDiscount;
            }

            $globalDiscount = round((float) ($validated['discount_amount'] ?? 0), 2);
            $surcharge = round((float) ($validated['surcharge_amount'] ?? 0), 2);
            $discountTotal = round($lineDiscountTotal + $globalDiscount, 2);
            $total = round($subtotal - $discountTotal + $surcharge, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'O valor total da venda precisa ser maior que zero.',
                ]);
            }

            $afterItems = collect($preparedLines)
                ->map(static fn (array $line, int $productId): array => [
                    'product_id' => $productId,
                    'quantity' => (int) $line['quantity'],
                    'discount_amount' => round((float) $line['discount_amount'], 2),
                ])
                ->sortBy('product_id')
                ->values()
                ->all();

            $itemsChanged = serialize($beforeItems) !== serialize($afterItems);

            $nextCustomerName = trim((string) ($validated['customer_name'] ?? ''));
            if ($nextCustomerName === '' && $selectedClient) {
                $nextCustomerName = trim((string) $selectedClient->name);
            }

            $nextCustomerContact = trim((string) ($validated['customer_contact'] ?? ''));
            if ($nextCustomerContact === '' && $selectedClient) {
                $nextCustomerContact = trim((string) ($selectedClient->phone ?? $selectedClient->email ?? ''));
            }

            $nextNotes = trim((string) ($validated['notes'] ?? ''));

            $hasFinancialChange = (
                abs($beforeTotal - $total) > 0.0001
                || abs($beforeDiscount - $discountTotal) > 0.0001
                || abs($beforeSurcharge - $surcharge) > 0.0001
            );

            $hasClientOrMetaChange = (
                $beforeClientId !== $clientId
                || $before['customer_name'] !== $nextCustomerName
                || $before['customer_contact'] !== $nextCustomerContact
                || $beforeNotes !== $nextNotes
            );

            if (! $itemsChanged && ! $hasFinancialChange && ! $hasClientOrMetaChange) {
                $updatedSaleId = (int) $lockedSale->id;
                $updatedSaleCode = (string) $lockedSale->code;
                $oldTotalForLog = $beforeTotal;
                $newTotalForLog = $beforeTotal;

                return;
            }

            $newSaleItemsByProduct = [];
            if ($itemsChanged) {
                $lockedSale->items()->delete();

                foreach ($preparedLines as $productId => $line) {
                    /** @var Product $product */
                    $product = $line['product'];

                    $newSaleItemsByProduct[$productId] = SaleItem::query()->create([
                        'contractor_id' => $contractor->id,
                        'sale_id' => $lockedSale->id,
                        'product_id' => (int) $product->id,
                        'description' => (string) $product->name,
                        'sku' => $product->sku ? (string) $product->sku : null,
                        'quantity' => (int) $line['quantity'],
                        'unit_price' => (float) $line['unit_price'],
                        'discount_amount' => (float) $line['discount_amount'],
                        'total_amount' => (float) $line['line_total'],
                    ]);
                }

                foreach ($allProductIds as $productIdValue) {
                    $productId = (int) $productIdValue;
                    /** @var Product|null $product */
                    $product = $products->get($productId);
                    if (! $product) {
                        continue;
                    }

                    $oldQuantity = (int) ($oldQtyByProduct->get($productId) ?? 0);
                    $newQuantity = (int) (($preparedLines[$productId]['quantity'] ?? null) ?? 0);
                    $delta = $newQuantity - $oldQuantity;

                    if ($delta === 0) {
                        continue;
                    }

                    $balanceBefore = (int) $product->stock_quantity;
                    $movementQuantity = abs($delta);
                    $movementType = $delta > 0
                        ? InventoryMovement::TYPE_OUT
                        : InventoryMovement::TYPE_RETURN;

                    $balanceAfter = $delta > 0
                        ? max(0, $balanceBefore - $movementQuantity)
                        : $balanceBefore + $movementQuantity;

                    $product->stock_quantity = $balanceAfter;
                    $product->save();

                    $saleItemId = isset($newSaleItemsByProduct[$productId])
                        ? (int) $newSaleItemsByProduct[$productId]->id
                        : null;

                    InventoryMovement::query()->create([
                        'contractor_id' => $contractor->id,
                        'product_id' => $productId,
                        'sale_item_id' => $saleItemId,
                        'user_id' => $request->user()?->id,
                        'type' => $movementType,
                        'quantity' => $movementQuantity,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'unit_cost' => $product->cost_price,
                        'reason' => "Ajuste de edição da venda {$lockedSale->code}",
                        'reference_type' => Sale::class,
                        'reference_id' => $lockedSale->id,
                        'occurred_at' => now(),
                        'metadata' => [
                            'old_quantity' => $oldQuantity,
                            'new_quantity' => $newQuantity,
                        ],
                    ]);
                }
            }

            $paymentsAdjusted = false;
            if (abs($beforeTotal - $total) > 0.0001) {
                $paymentsAdjusted = $this->redistributePayments($lockedSale, $total);
                $this->registerCashAdjustmentIfNeeded($contractor, $lockedSale, $beforeTotal, $total, $request);
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

            $lockedSale->fill([
                'client_id' => $clientId,
                'subtotal_amount' => round($subtotal, 2),
                'discount_amount' => $discountTotal,
                'surcharge_amount' => $surcharge,
                'total_amount' => $total,
                'paid_amount' => in_array((string) $lockedSale->status, [Sale::STATUS_PAID, Sale::STATUS_COMPLETED], true)
                    ? $total
                    : min(round((float) $lockedSale->paid_amount, 2), $total),
                'notes' => $nextNotes !== '' ? $nextNotes : null,
                'metadata' => $metadata,
            ])->save();

            if ($beforeClientId !== $clientId) {
                $changedFields[] = 'client_id';
            }
            if ($before['customer_name'] !== $nextCustomerName) {
                $changedFields[] = 'customer_name';
            }
            if ($before['customer_contact'] !== $nextCustomerContact) {
                $changedFields[] = 'customer_contact';
            }
            if ($beforeNotes !== $nextNotes) {
                $changedFields[] = 'notes';
            }
            if ($itemsChanged) {
                $changedFields[] = 'items';
                $changedFields[] = 'stock';
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
                'sale.updated.admin',
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
                ? 'Venda atualizada com sucesso.'
                : 'Nenhuma alteração foi aplicada na venda.'
        );
    }

    private function resolveCurrentContractor(Request $request): ?Contractor
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $user->loadMissing('contractors');
        $availableContractors = $user->contractors->values();

        if ($availableContractors->isEmpty()) {
            return null;
        }

        $sessionContractorId = (int) $request->session()->get('current_contractor_id', 0);
        if ($sessionContractorId > 0) {
            $selected = $availableContractors->firstWhere('id', $sessionContractorId);
            if ($selected) {
                return $selected;
            }
        }

        $fallback = $availableContractors->first();
        if ($fallback) {
            $request->session()->put('current_contractor_id', $fallback->id);
        }

        return $fallback;
    }

    private function lockSaleForContractor(Contractor $contractor, int $saleId): Sale
    {
        $sale = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', $saleId)
            ->where('source', Sale::SOURCE_PDV)
            ->with([
                'client:id,name,email,phone',
                'items:id,sale_id,product_id,description,sku,quantity,unit_price,discount_amount,total_amount',
                'items.product:id,image_url',
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

    private function canEditSale(Sale $sale): bool
    {
        return ! in_array($sale->status, [
            Sale::STATUS_CANCELLED,
            Sale::STATUS_REJECTED,
            Sale::STATUS_REFUNDED,
        ], true);
    }

    /**
     * @param Collection<int, SaleItem> $items
     * @return list<array{product_id:int,quantity:int,discount_amount:float}>
     */
    private function normalizeExistingItems(Collection $items): array
    {
        return $items
            ->groupBy(static fn (SaleItem $item): int => (int) $item->product_id)
            ->map(static fn (Collection $rows, int|string $productId): array => [
                'product_id' => (int) $productId,
                'quantity' => (int) $rows->sum(static fn (SaleItem $item): int => (int) $item->quantity),
                'discount_amount' => round((float) $rows->sum(static fn (SaleItem $item): float => (float) $item->discount_amount), 2),
            ])
            ->sortBy('product_id')
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array{product_id:int,quantity:int,discount_amount:float}> $requestedItems
     * @return Collection<int, array{product_id:int,quantity:int,discount_amount:float}>
     */
    private function groupRequestedItems(Collection $requestedItems): Collection
    {
        return $requestedItems
            ->groupBy(static fn (array $row): int => (int) $row['product_id'])
            ->map(static fn (Collection $rows, int|string $productId): array => [
                'product_id' => (int) $productId,
                'quantity' => (int) $rows->sum(static fn (array $row): int => (int) $row['quantity']),
                'discount_amount' => round((float) $rows->sum(static fn (array $row): float => (float) ($row['discount_amount'] ?? 0)), 2),
            ]);
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

    private function registerCashAdjustmentIfNeeded(Contractor $contractor, Sale $sale, float $oldTotal, float $newTotal, Request $request): void
    {
        $difference = round($newTotal - $oldTotal, 2);
        if (abs($difference) <= 0.0001) {
            return;
        }

        if (! $sale->cash_session_id) {
            return;
        }

        $hasCashPayment = $sale->payments->contains(static function (SalePayment $payment): bool {
            return (string) ($payment->paymentMethod?->code ?? '') === 'cash';
        });

        if (! $hasCashPayment) {
            return;
        }

        CashMovement::query()->create([
            'contractor_id' => $contractor->id,
            'cash_session_id' => $sale->cash_session_id,
            'user_id' => $request->user()?->id,
            'type' => 'sale_adjustment',
            'direction' => $difference > 0 ? 'in' : 'out',
            'amount' => abs($difference),
            'description' => "Ajuste financeiro da venda {$sale->code}",
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'occurred_at' => now(),
            'metadata' => [
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
            ],
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function resolveStatusOptions(): array
    {
        return [
            ['value' => '', 'label' => 'Todos os status'],
            ['value' => Sale::STATUS_DRAFT, 'label' => 'Orçamento'],
            ['value' => Sale::STATUS_NEW, 'label' => 'Novo'],
            ['value' => Sale::STATUS_PENDING_CONFIRMATION, 'label' => 'Aguardando confirmação'],
            ['value' => Sale::STATUS_CONFIRMED, 'label' => 'Confirmado'],
            ['value' => Sale::STATUS_AWAITING_PAYMENT, 'label' => 'Aguardando pagamento'],
            ['value' => Sale::STATUS_PAID, 'label' => 'Pago'],
            ['value' => Sale::STATUS_COMPLETED, 'label' => 'Concluído'],
            ['value' => Sale::STATUS_CANCELLED, 'label' => 'Cancelado'],
            ['value' => Sale::STATUS_REJECTED, 'label' => 'Rejeitado'],
            ['value' => Sale::STATUS_REFUNDED, 'label' => 'Estornado'],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolvePipelineStatuses(string $pipeline): array
    {
        return match ($pipeline) {
            'draft' => [Sale::STATUS_DRAFT],
            'open' => [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION, Sale::STATUS_CONFIRMED, Sale::STATUS_AWAITING_PAYMENT],
            'completed' => [Sale::STATUS_PAID, Sale::STATUS_COMPLETED],
            'cancelled' => [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED, Sale::STATUS_REFUNDED],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function toSalePayload(Sale $sale): array
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

        return [
            'id' => (int) $sale->id,
            'code' => (string) $sale->code,
            'client_id' => $sale->client_id ? (int) $sale->client_id : null,
            'customer' => $customerName,
            'customer_contact' => $customerContact,
            'channel' => 'PDV',
            'subtotal_amount' => (float) $sale->subtotal_amount,
            'discount_amount' => (float) $sale->discount_amount,
            'global_discount_amount' => $globalDiscountAmount,
            'surcharge_amount' => (float) $sale->surcharge_amount,
            'total_amount' => (float) $sale->total_amount,
            'total_items' => (int) $sale->items->sum(static fn (SaleItem $item): int => (int) $item->quantity),
            'items' => $sale->items
                ->map(static fn (SaleItem $item): array => [
                    'product_id' => $item->product_id ? (int) $item->product_id : null,
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
            'created_at' => optional($sale->completed_at ?? $sale->created_at)->format('d/m/Y H:i'),
            'notes' => $sale->notes !== null ? (string) $sale->notes : null,
            'can_edit' => $this->canEditSale($sale),
        ];
    }

    /**
     * @return array{value: string, label: string, tone: string}
     */
    private function resolveStatusMeta(string $status): array
    {
        return match ($status) {
            Sale::STATUS_DRAFT => ['value' => $status, 'label' => 'Orçamento', 'tone' => 'bg-slate-100 text-slate-700'],
            Sale::STATUS_NEW => ['value' => $status, 'label' => 'Novo', 'tone' => 'bg-blue-100 text-blue-700'],
            Sale::STATUS_PENDING_CONFIRMATION => ['value' => $status, 'label' => 'Aguardando confirmação', 'tone' => 'bg-blue-100 text-blue-700'],
            Sale::STATUS_CONFIRMED => ['value' => $status, 'label' => 'Confirmado', 'tone' => 'bg-amber-100 text-amber-700'],
            Sale::STATUS_AWAITING_PAYMENT => ['value' => $status, 'label' => 'Aguardando pagamento', 'tone' => 'bg-amber-100 text-amber-700'],
            Sale::STATUS_PAID => ['value' => $status, 'label' => 'Pago', 'tone' => 'bg-emerald-100 text-emerald-700'],
            Sale::STATUS_COMPLETED => ['value' => $status, 'label' => 'Concluído', 'tone' => 'bg-emerald-100 text-emerald-700'],
            Sale::STATUS_REJECTED => ['value' => $status, 'label' => 'Rejeitado', 'tone' => 'bg-rose-100 text-rose-700'],
            Sale::STATUS_CANCELLED => ['value' => $status, 'label' => 'Cancelado', 'tone' => 'bg-rose-100 text-rose-700'],
            Sale::STATUS_REFUNDED => ['value' => $status, 'label' => 'Estornado', 'tone' => 'bg-rose-100 text-rose-700'],
            default => ['value' => $status, 'label' => ucfirst($status), 'tone' => 'bg-slate-100 text-slate-700'],
        };
    }
}
