<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;
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
                'payments.paymentMethod:id,name',
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

        return Inertia::render('Admin/Sales/Index', [
            'sales' => $sales,
            'saleStats' => $totals,
            'pipeline' => $pipeline,
            'statusOptions' => $this->resolveStatusOptions(),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'pipeline' => in_array($pipelineFilter, ['draft', 'open', 'completed', 'cancelled'], true)
                    ? $pipelineFilter
                    : 'all',
            ],
        ]);
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

        return [
            'id' => (int) $sale->id,
            'code' => (string) $sale->code,
            'customer' => $customerName,
            'customer_contact' => $customerContact,
            'channel' => 'PDV',
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
            'created_at' => optional($sale->completed_at ?? $sale->created_at)->format('d/m/Y H:i'),
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

