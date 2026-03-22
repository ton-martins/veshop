<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Client;
use App\Models\CashSession;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalog;
use App\Models\ServiceOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesCurrentContractor;

    /**
     * @var list<string>
     */
    private const STORE_ORDER_SOURCES = [Sale::SOURCE_CATALOG, Sale::SOURCE_ORDER];

    /**
     * Display the admin dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $isServices = $contractor->hasModule(Contractor::MODULE_SERVICES);

        $pdvStats = $this->resolvePdvStats($contractor);

        $operationsStats = $this->resolveOperationsStats($contractor);
        $operationsStats['clients'] = Client::query()
            ->where('contractor_id', $contractor->id)
            ->count();

        $commercialOverview = [
            'operations' => $operationsStats,
            'pdv' => $pdvStats,
        ];

        $serviceCatalogCount = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->count();

        $serviceActiveCount = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $serviceAveragePrice = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->avg('base_price');

        $servicesTimezoneNow = now($contractor->timezone);
        $servicesTodayStart = $servicesTimezoneNow->copy()->startOfDay();
        $servicesTodayEnd = $servicesTimezoneNow->copy()->endOfDay();
        $servicesMonthStart = $servicesTimezoneNow->copy()->startOfMonth();
        $servicesMonthEnd = $servicesTimezoneNow->copy()->endOfMonth();

        $servicesOpenOrderStatuses = [
            ServiceOrder::STATUS_OPEN,
            ServiceOrder::STATUS_IN_PROGRESS,
            ServiceOrder::STATUS_WAITING,
        ];

        $servicesOpenOrders = ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', $servicesOpenOrderStatuses)
            ->count();

        $servicesTodayAppointments = ServiceAppointment::query()
            ->where('contractor_id', $contractor->id)
            ->whereBetween('starts_at', [$servicesTodayStart, $servicesTodayEnd])
            ->count();

        $servicesRevenue = (float) ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', ServiceOrder::STATUS_DONE)
            ->where(static function ($query) use ($servicesMonthStart, $servicesMonthEnd): void {
                $query
                    ->whereBetween('finished_at', [$servicesMonthStart, $servicesMonthEnd])
                    ->orWhere(static function ($fallback) use ($servicesMonthStart, $servicesMonthEnd): void {
                        $fallback
                            ->whereNull('finished_at')
                            ->whereBetween('updated_at', [$servicesMonthStart, $servicesMonthEnd]);
                    });
            })
            ->sum('final_amount');

        $servicesQueue = ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', $servicesOpenOrderStatuses)
            ->with([
                'client:id,name',
                'service:id,name',
            ])
            ->orderByRaw(
                "CASE status WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END",
                [
                    ServiceOrder::STATUS_OPEN,
                    ServiceOrder::STATUS_IN_PROGRESS,
                    ServiceOrder::STATUS_WAITING,
                ]
            )
            ->orderByDesc('created_at')
            ->limit(12)
            ->get()
            ->map(fn (ServiceOrder $order): array => [
                'id' => (int) $order->id,
                'code' => (string) $order->code,
                'customer' => $order->client?->name ? (string) $order->client->name : 'Não informado',
                'service' => $order->service?->name ? (string) $order->service->name : (string) $order->title,
                'status' => $this->resolveServiceOrderStatusLabel((string) $order->status),
            ])
            ->values()
            ->all();

        $servicesOverview = [
            'stats' => [
                'open_orders' => $servicesOpenOrders,
                'today' => $servicesTodayAppointments,
                'catalog' => $serviceCatalogCount,
                'revenue' => round($servicesRevenue, 2),
                'active_services' => $serviceActiveCount,
                'avg_price' => $serviceAveragePrice !== null ? (float) $serviceAveragePrice : 0.0,
            ],
            'queue' => $servicesQueue,
        ];

        $quickTotals = [
            'products' => Product::query()
                ->where('contractor_id', $contractor->id)
                ->count(),
            'suppliers' => Supplier::query()
                ->where('contractor_id', $contractor->id)
                ->count(),
        ];

        return Inertia::render('Admin/Home', [
            'isServices' => $isServices,
            'overview' => [
                'commercial' => $commercialOverview,
                'services' => $servicesOverview,
                'quick_totals' => $quickTotals,
            ],
        ]);
    }


    /**
     * @return array{
     *   sales_today: float,
     *   sales_count: int,
     *   avg_ticket: float,
     *   cash_open: bool,
     *   pending_quotes: int,
     *   recent_sales: array<int, array<string, mixed>>,
     *   payment_summary: array{pix: float, credit: float, cash: float}
     * }
     */
    private function resolvePdvStats(Contractor $contractor): array
    {
        $fallback = [
            'sales_today' => 0.0,
            'sales_count' => 0,
            'avg_ticket' => 0.0,
            'cash_open' => false,
            'pending_quotes' => 0,
            'recent_sales' => [],
            'payment_summary' => [
                'pix' => 0.0,
                'credit' => 0.0,
                'cash' => 0.0,
            ],
        ];

        $requiredTables = [
            'cash_sessions',
            'sales',
            'sale_payments',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                return $fallback;
            }
        }

        $timezone = (string) ($contractor->timezone ?: config('app.timezone', 'UTC'));
        $nowAtTimezone = now($timezone);
        $dayStartUtc = $nowAtTimezone->copy()->startOfDay()->utc();
        $dayEndUtc = $nowAtTimezone->copy()->endOfDay()->utc();

        $salesTodayQuery = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('source', Sale::SOURCE_PDV)
            ->whereIn('status', [Sale::STATUS_COMPLETED, Sale::STATUS_PAID])
            ->where(static function ($query) use ($dayStartUtc, $dayEndUtc): void {
                $query
                    ->whereBetween('completed_at', [$dayStartUtc, $dayEndUtc])
                    ->orWhere(static function ($fallback) use ($dayStartUtc, $dayEndUtc): void {
                        $fallback
                            ->whereNull('completed_at')
                            ->whereBetween('created_at', [$dayStartUtc, $dayEndUtc]);
                    });
            });

        $salesCount = (clone $salesTodayQuery)->count();
        $salesToday = (float) (clone $salesTodayQuery)->sum('total_amount');
        $avgTicket = $salesCount > 0 ? $salesToday / $salesCount : 0.0;

        $pendingQuotes = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', Sale::STATUS_DRAFT)
            ->where('source', Sale::SOURCE_PDV)
            ->count();

        $cashOpen = CashSession::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', CashSession::STATUS_OPEN)
            ->exists();

        $recentSales = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('source', Sale::SOURCE_PDV)
            ->whereIn('status', [Sale::STATUS_COMPLETED, Sale::STATUS_PAID])
            ->with([
                'client:id,name,email,phone',
                'items:id,sale_id,product_id,description,sku,quantity,unit_price,discount_amount,total_amount',
                'items.product:id,image_url',
                'payments:id,sale_id,payment_method_id,status,amount',
                'payments.paymentMethod:id,name',
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Sale $sale): array => $this->toDashboardOrderPayload($sale, 'PDV', false))
            ->values()
            ->all();

        $paymentSummary = SalePayment::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [SalePayment::STATUS_PAID, SalePayment::STATUS_AUTHORIZED])
            ->whereHas('sale', static function ($query) use ($contractor): void {
                $query
                    ->where('contractor_id', $contractor->id)
                    ->where('source', Sale::SOURCE_PDV);
            })
            ->where(static function ($query) use ($dayStartUtc, $dayEndUtc): void {
                $query
                    ->whereBetween('paid_at', [$dayStartUtc, $dayEndUtc])
                    ->orWhere(static function ($fallback) use ($dayStartUtc, $dayEndUtc): void {
                        $fallback
                            ->whereNull('paid_at')
                            ->whereBetween('created_at', [$dayStartUtc, $dayEndUtc]);
                    });
            })
            ->with('paymentMethod:id,code')
            ->get()
            ->reduce(
                static function (array $carry, SalePayment $payment): array {
                    $code = strtolower((string) ($payment->paymentMethod?->code ?? ''));
                    $amount = (float) $payment->amount;

                    return match ($code) {
                        'pix' => ['pix' => $carry['pix'] + $amount, 'credit' => $carry['credit'], 'cash' => $carry['cash']],
                        'cash' => ['pix' => $carry['pix'], 'credit' => $carry['credit'], 'cash' => $carry['cash'] + $amount],
                        'credit_card', 'debit_card', 'installment' => ['pix' => $carry['pix'], 'credit' => $carry['credit'] + $amount, 'cash' => $carry['cash']],
                        default => $carry,
                    };
                },
                ['pix' => 0.0, 'credit' => 0.0, 'cash' => 0.0]
            );

        return [
            'sales_today' => $salesToday,
            'sales_count' => $salesCount,
            'avg_ticket' => $avgTicket,
            'cash_open' => $cashOpen,
            'pending_quotes' => $pendingQuotes,
            'recent_sales' => $recentSales,
            'payment_summary' => $paymentSummary,
        ];
    }

    /**
     * @return array{
     *   orders_today: int,
     *   in_production: int,
     *   monthly_revenue: float,
     *   clients: int,
     *   pending_quotes: int,
     *   deliveries_today: int,
     *   recent_orders: array<int, array<string, mixed>>
     * }
     */
    private function resolveOperationsStats(Contractor $contractor): array
    {
        $timezone = (string) ($contractor->timezone ?: config('app.timezone', 'UTC'));
        $nowAtTimezone = now($timezone);
        $dayStartUtc = $nowAtTimezone->copy()->startOfDay()->utc();
        $dayEndUtc = $nowAtTimezone->copy()->endOfDay()->utc();
        $monthStartUtc = $nowAtTimezone->copy()->startOfMonth()->utc();
        $monthEndUtc = $nowAtTimezone->copy()->endOfMonth()->utc();

        $baseQuery = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('source', self::STORE_ORDER_SOURCES);

        $ordersToday = (clone $baseQuery)
            ->whereBetween('created_at', [$dayStartUtc, $dayEndUtc])
            ->count();

        $inProduction = (clone $baseQuery)
            ->whereIn('status', [Sale::STATUS_CONFIRMED, Sale::STATUS_AWAITING_PAYMENT])
            ->count();

        $monthlyRevenue = (float) (clone $baseQuery)
            ->whereIn('status', [Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
            ->where(static function ($query) use ($monthStartUtc, $monthEndUtc): void {
                $query
                    ->whereBetween('completed_at', [$monthStartUtc, $monthEndUtc])
                    ->orWhere(static function ($fallback) use ($monthStartUtc, $monthEndUtc): void {
                        $fallback
                            ->whereNull('completed_at')
                            ->whereBetween('created_at', [$monthStartUtc, $monthEndUtc]);
                    });
            })
            ->sum('total_amount');

        $pendingQuotes = (clone $baseQuery)
            ->whereIn('status', [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION])
            ->count();

        $deliveriesToday = (clone $baseQuery)
            ->where('shipping_mode', Sale::SHIPPING_MODE_DELIVERY)
            ->whereIn('status', [Sale::STATUS_CONFIRMED, Sale::STATUS_AWAITING_PAYMENT, Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
            ->where(static function ($query) use ($dayStartUtc, $dayEndUtc): void {
                $query
                    ->whereBetween('completed_at', [$dayStartUtc, $dayEndUtc])
                    ->orWhere(static function ($fallback) use ($dayStartUtc, $dayEndUtc): void {
                        $fallback
                            ->whereNull('completed_at')
                            ->whereBetween('created_at', [$dayStartUtc, $dayEndUtc]);
                    });
            })
            ->count();

        $recentOrders = (clone $baseQuery)
            ->with([
                'client:id,name,email,phone',
                'items:id,sale_id,product_id,description,sku,quantity,unit_price,discount_amount,total_amount',
                'items.product:id,image_url',
                'payments:id,sale_id,payment_method_id,status,amount',
                'payments.paymentMethod:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Sale $sale): array => $this->toDashboardOrderPayload($sale, 'Loja virtual', true))
            ->values()
            ->all();

        return [
            'orders_today' => $ordersToday,
            'in_production' => $inProduction,
            'monthly_revenue' => $monthlyRevenue,
            'clients' => 0,
            'pending_quotes' => $pendingQuotes,
            'deliveries_today' => $deliveriesToday,
            'recent_orders' => $recentOrders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDashboardOrderPayload(Sale $sale, string $channel, bool $allowActions): array
    {
        $metadata = is_array($sale->metadata) ? $sale->metadata : [];
        $status = $this->resolveDashboardStatusMeta((string) $sale->status);

        $paymentLabel = $sale->payments
            ->map(static fn (SalePayment $payment): ?string => $payment->paymentMethod?->name)
            ->filter()
            ->unique()
            ->values()
            ->implode(' + ');

        $customerName = trim((string) ($sale->client?->name ?? ($metadata['customer_name'] ?? '')));
        if ($customerName === '') {
            $customerName = 'Consumidor final';
        }

        $customerContact = trim((string) ($sale->client?->phone ?? ($metadata['customer_phone'] ?? '')));
        if ($customerContact === '') {
            $customerContact = trim((string) ($sale->client?->email ?? ($metadata['customer_email'] ?? '')));
        }

        $items = $sale->items
            ->map(static fn ($item): array => [
                'description' => (string) $item->description,
                'sku' => $item->sku !== null ? (string) $item->sku : null,
                'image_url' => $item->product?->image_url !== null ? (string) $item->product->image_url : null,
                'quantity' => (int) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount_amount' => (float) $item->discount_amount,
                'total_amount' => (float) $item->total_amount,
            ])
            ->values();

        $totalItems = (int) $items->sum(static fn (array $item): int => (int) ($item['quantity'] ?? 0));
        $firstItemDescription = (string) ($items->first()['description'] ?? 'Pedido da loja virtual');
        $description = $firstItemDescription;
        if ($totalItems > 1) {
            $description .= ' +'.($totalItems - 1).' item(ns)';
        }

        $paymentText = $paymentLabel !== '' ? $paymentLabel : 'Não informado';
        $formattedAmount = 'R$ '.number_format((float) $sale->total_amount, 2, ',', '.');
        $createdAt = optional($sale->created_at)->format('d/m/Y H:i');
        $completedAt = $sale->completed_at ?? $sale->created_at;

        return [
            'id' => (int) $sale->id,
            'code' => (string) $sale->code,
            'customer' => $customerName,
            'customer_contact' => $customerContact,
            'channel' => $channel,
            'total_amount' => (float) $sale->total_amount,
            'total_items' => $totalItems,
            'items' => $items->all(),
            'status' => $status,
            'payment_label' => $paymentText,
            'created_at' => $createdAt,
            'description' => $description,
            'amount' => $formattedAmount,
            'payment' => $paymentText,
            'time' => $completedAt?->format('H:i') ?? '--:--',
            'can_confirm' => $allowActions
                && in_array($sale->status, [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION], true),
            'can_reject' => $allowActions
                && in_array($sale->status, [Sale::STATUS_NEW, Sale::STATUS_PENDING_CONFIRMATION], true),
            'can_mark_paid' => $allowActions
                && in_array($sale->status, [Sale::STATUS_CONFIRMED, Sale::STATUS_AWAITING_PAYMENT], true),
            'can_cancel' => $allowActions
                && ! in_array($sale->status, [Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED], true),
        ];
    }

    private function resolveServiceOrderStatusLabel(string $status): string
    {
        return match ($status) {
            ServiceOrder::STATUS_OPEN => 'Triagem',
            ServiceOrder::STATUS_IN_PROGRESS => 'Em execução',
            ServiceOrder::STATUS_WAITING => 'Aguardando',
            ServiceOrder::STATUS_DONE => 'Finalizada',
            ServiceOrder::STATUS_CANCELLED => 'Cancelada',
            default => ucfirst($status),
        };
    }

    /**
     * @return array{value: string, label: string, tone: string}
     */
    private function resolveDashboardStatusMeta(string $status): array
    {
        return match ($status) {
            Sale::STATUS_NEW => ['value' => $status, 'label' => 'Novo', 'tone' => 'bg-blue-100 text-blue-700'],
            Sale::STATUS_PENDING_CONFIRMATION => ['value' => $status, 'label' => 'Aguardando confirmação', 'tone' => 'bg-blue-100 text-blue-700'],
            Sale::STATUS_CONFIRMED => ['value' => $status, 'label' => 'Confirmado', 'tone' => 'bg-amber-100 text-amber-700'],
            Sale::STATUS_AWAITING_PAYMENT => ['value' => $status, 'label' => 'Aguardando pagamento', 'tone' => 'bg-amber-100 text-amber-700'],
            Sale::STATUS_PAID => ['value' => $status, 'label' => 'Pago', 'tone' => 'bg-emerald-100 text-emerald-700'],
            Sale::STATUS_COMPLETED => ['value' => $status, 'label' => 'Concluído', 'tone' => 'bg-emerald-100 text-emerald-700'],
            Sale::STATUS_REJECTED => ['value' => $status, 'label' => 'Rejeitado', 'tone' => 'bg-rose-100 text-rose-700'],
            Sale::STATUS_CANCELLED => ['value' => $status, 'label' => 'Cancelado', 'tone' => 'bg-rose-100 text-rose-700'],
            default => ['value' => $status, 'label' => ucfirst($status), 'tone' => 'bg-slate-100 text-slate-700'],
        };
    }
}


