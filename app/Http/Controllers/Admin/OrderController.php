<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SecurityAuditLog;
use App\Services\SecurityAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * @var list<string>
     */
    private const ORDER_SOURCES = [Sale::SOURCE_CATALOG, Sale::SOURCE_ORDER];

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
                'items:id,sale_id,product_id,description,sku,quantity,unit_price,discount_amount,total_amount',
                'items.product:id,image_url',
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

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'orderStats' => $totals,
            'pipeline' => $pipeline,
            'statusOptions' => $this->resolveStatusOptions(),
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
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_contact' => ['nullable', 'string', 'max:160'],
            'shipping_mode' => ['nullable', Rule::in([Sale::SHIPPING_MODE_PICKUP, Sale::SHIPPING_MODE_DELIVERY])],
            'shipping_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_estimate_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updatedSaleId = null;
        $updatedSaleCode = null;
        $changedFields = [];

        DB::transaction(function () use ($contractor, $sale, $validated, &$updatedSaleId, &$updatedSaleCode, &$changedFields): void {
            $lockedSale = $this->lockOrderForContractor($contractor, $sale->id);

            if (! $this->canEditOrder($lockedSale)) {
                throw ValidationException::withMessages([
                    'order' => 'Este pedido não pode ser editado no status atual.',
                ]);
            }

            $metadata = is_array($lockedSale->metadata) ? $lockedSale->metadata : [];
            $before = [
                'customer_name' => trim((string) ($metadata['customer_name'] ?? ($lockedSale->client?->name ?? ''))),
                'customer_contact' => trim((string) ($metadata['customer_contact'] ?? ($lockedSale->client?->phone ?? ($lockedSale->client?->email ?? '')))),
                'shipping_mode' => (string) ($lockedSale->shipping_mode ?? ''),
                'shipping_amount' => (float) ($lockedSale->shipping_amount ?? 0),
                'shipping_estimate_days' => $lockedSale->shipping_estimate_days !== null ? (int) $lockedSale->shipping_estimate_days : null,
                'notes' => trim((string) ($lockedSale->notes ?? '')),
            ];

            $nextCustomerName = trim((string) ($validated['customer_name'] ?? ''));
            $nextCustomerContact = trim((string) ($validated['customer_contact'] ?? ''));
            $nextShippingMode = array_key_exists('shipping_mode', $validated)
                ? (string) ($validated['shipping_mode'] ?? '')
                : (string) ($lockedSale->shipping_mode ?? '');
            $nextShippingAmount = array_key_exists('shipping_amount', $validated)
                ? (float) ($validated['shipping_amount'] ?? 0)
                : (float) ($lockedSale->shipping_amount ?? 0);
            $nextShippingEstimateDays = array_key_exists('shipping_estimate_days', $validated)
                ? ($validated['shipping_estimate_days'] !== null ? (int) $validated['shipping_estimate_days'] : null)
                : ($lockedSale->shipping_estimate_days !== null ? (int) $lockedSale->shipping_estimate_days : null);
            $nextNotes = trim((string) ($validated['notes'] ?? ''));

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
                'shipping_mode' => $nextShippingMode !== '' ? $nextShippingMode : null,
                'shipping_amount' => $nextShippingAmount,
                'shipping_estimate_days' => $nextShippingEstimateDays,
                'notes' => $nextNotes !== '' ? $nextNotes : null,
                'metadata' => $metadata,
            ])->save();

            $after = [
                'customer_name' => $nextCustomerName,
                'customer_contact' => $nextCustomerContact,
                'shipping_mode' => $nextShippingMode,
                'shipping_amount' => $nextShippingAmount,
                'shipping_estimate_days' => $nextShippingEstimateDays,
                'notes' => $nextNotes,
            ];

            foreach ($after as $field => $afterValue) {
                $beforeValue = $before[$field] ?? null;
                if ($beforeValue !== $afterValue) {
                    $changedFields[] = $field;
                }
            }

            $updatedSaleId = (int) $lockedSale->id;
            $updatedSaleCode = (string) $lockedSale->code;
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

            /** @var Collection<int, int> $quantitiesByProduct */
            $quantitiesByProduct = $lockedSale->items
                ->groupBy(static fn ($item): int => (int) $item->product_id)
                ->map(static fn (Collection $items): int => $items->sum(static fn ($item): int => (int) $item->quantity));

            $products = Product::query()
                ->where('contractor_id', $contractor->id)
                ->whereIn('id', $quantitiesByProduct->keys()->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($quantitiesByProduct as $productId => $quantity) {
                /** @var Product|null $product */
                $product = $products->get((int) $productId);
                if (! $product) {
                    throw ValidationException::withMessages([
                        'order' => 'Não foi possível confirmar: item sem produto válido.',
                    ]);
                }

                if ((int) $product->stock_quantity < (int) $quantity) {
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

                $balanceBefore = (int) $product->stock_quantity;
                $balanceAfter = max(0, $balanceBefore - (int) $item->quantity);

                $product->stock_quantity = $balanceAfter;
                $product->save();

                InventoryMovement::query()->create([
                    'contractor_id' => $contractor->id,
                    'product_id' => $product->id,
                    'sale_item_id' => $item->id,
                    'user_id' => $request->user()?->id,
                    'type' => InventoryMovement::TYPE_OUT,
                    'quantity' => (int) $item->quantity,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'unit_cost' => $product->cost_price,
                    'reason' => "Confirmação do pedido {$lockedSale->code}",
                    'reference_type' => Sale::class,
                    'reference_id' => $lockedSale->id,
                    'occurred_at' => now(),
                ]);
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
                /** @var Collection<int, int> $quantitiesByProduct */
                $quantitiesByProduct = $lockedSale->items
                    ->groupBy(static fn ($item): int => (int) $item->product_id)
                    ->map(static fn (Collection $items): int => $items->sum(static fn ($item): int => (int) $item->quantity));

                $products = Product::query()
                    ->where('contractor_id', $contractor->id)
                    ->whereIn('id', $quantitiesByProduct->keys()->all())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($lockedSale->items as $item) {
                    /** @var Product|null $product */
                    $product = $products->get((int) $item->product_id);
                    if (! $product) {
                        continue;
                    }

                    $balanceBefore = (int) $product->stock_quantity;
                    $balanceAfter = $balanceBefore + (int) $item->quantity;

                    $product->stock_quantity = $balanceAfter;
                    $product->save();

                    InventoryMovement::query()->create([
                        'contractor_id' => $contractor->id,
                        'product_id' => $product->id,
                        'sale_item_id' => $item->id,
                        'user_id' => $request->user()?->id,
                        'type' => InventoryMovement::TYPE_RETURN,
                        'quantity' => (int) $item->quantity,
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'unit_cost' => $product->cost_price,
                        'reason' => "Cancelamento do pedido {$lockedSale->code}",
                        'reference_type' => Sale::class,
                        'reference_id' => $lockedSale->id,
                        'occurred_at' => now(),
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

    private function lockOrderForContractor(Contractor $contractor, int $saleId): Sale
    {
        $sale = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', $saleId)
            ->whereIn('source', self::ORDER_SOURCES)
            ->with(['items:id,sale_id,product_id,quantity', 'payments:id,sale_id,status'])
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

        return [
            'id' => (int) $sale->id,
            'code' => (string) $sale->code,
            'customer' => $customerName,
            'customer_contact' => $customerContact,
            'channel' => 'Loja virtual',
            'total_amount' => (float) $sale->total_amount,
            'total_items' => (int) $sale->items->sum(static fn ($item): int => (int) $item->quantity),
            'items' => $sale->items
                ->map(static fn ($item): array => [
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
            'shipping_mode' => $sale->shipping_mode !== null ? (string) $sale->shipping_mode : null,
            'shipping_amount' => (float) $sale->shipping_amount,
            'shipping_estimate_days' => $sale->shipping_estimate_days !== null ? (int) $sale->shipping_estimate_days : null,
            'notes' => $sale->notes !== null ? (string) $sale->notes : null,
            'can_confirm' => in_array($sale->status, [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION], true),
            'can_reject' => in_array($sale->status, [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION], true),
            'can_mark_paid' => in_array($sale->status, [Sale::STATUS_CONFIRMED, Sale::STATUS_AWAITING_PAYMENT], true),
            'can_cancel' => ! in_array($sale->status, [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED], true),
            'can_edit' => $this->canEditOrder($sale),
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

        app(\App\Services\OrderNotificationService::class)->notifyOrderStatusChanged($sale);
    }
}
