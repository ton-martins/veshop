<?php

namespace App\Application\Reports\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\CashSession;
use App\Models\Client;
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

class AdminDashboardService
{
    use ResolvesCurrentContractor;

    /**
     * @var list<string>
     */
    private const STORE_ORDER_SOURCES = [Sale::SOURCE_CATALOG, Sale::SOURCE_ORDER];

    /**
     * Display the admin dashboard.
     */
    public function index(Request $request): Response
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

        $servicesQueue = ServiceAppointment::query()
            ->where('contractor_id', $contractor->id)
            ->whereBetween('starts_at', [$servicesTodayStart, $servicesTodayEnd])
            ->with([
                'client:id,name,phone,email',
                'service:id,name',
                'serviceOrder:id,code,assigned_to_name',
            ])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(function (ServiceAppointment $appointment) use ($contractor): array {
                $statusMeta = $this->resolveServiceAppointmentStatusMeta((string) $appointment->status);
                $timezone = (string) ($contractor->timezone ?: config('app.timezone', 'America/Sao_Paulo'));

                return [
                    'id' => (int) $appointment->id,
                    'code' => $appointment->serviceOrder?->code ? (string) $appointment->serviceOrder->code : null,
                    'customer' => $appointment->client?->name ? (string) $appointment->client->name : 'Não informado',
                    'customer_contact' => $appointment->client?->phone
                        ? (string) $appointment->client->phone
                        : ($appointment->client?->email ? (string) $appointment->client->email : ''),
                    'service' => $appointment->service?->name
                        ? (string) $appointment->service->name
                        : (string) ($appointment->title ?: 'Atendimento sem serviço'),
                    'title' => (string) ($appointment->title ?: ''),
                    'starts_at' => optional($appointment->starts_at)?->setTimezone($timezone)?->format('H:i'),
                    'ends_at' => optional($appointment->ends_at)?->setTimezone($timezone)?->format('H:i'),
                    'location' => $appointment->location ? (string) $appointment->location : '',
                    'notes' => $appointment->notes ? (string) $appointment->notes : '',
                    'technician' => $appointment->serviceOrder?->assigned_to_name ? (string) $appointment->serviceOrder->assigned_to_name : '',
                    'status' => $statusMeta['label'],
                    'status_value' => $statusMeta['value'],
                    'status_tone' => $statusMeta['tone'],
                    'payment_status' => $this->resolveServiceAppointmentPaymentLabel((string) ($appointment->payment_status ?: ServiceAppointment::PAYMENT_STATUS_PENDING)),
                    'payment_status_tone' => $this->resolveServiceAppointmentPaymentTone((string) ($appointment->payment_status ?: ServiceAppointment::PAYMENT_STATUS_PENDING)),
                ];
            })
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
                'client:id,name,email,phone,document',
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
     *   recent_orders: array<int, array<string, mixed>>,
     *   recent_deliveries: array<int, array<string, mixed>>
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
                'client:id,name,email,phone,document',
                'items:id,sale_id,product_id,description,sku,quantity,unit_price,discount_amount,total_amount',
                'items.product:id,image_url',
                'payments:id,sale_id,payment_method_id,status,amount',
                'payments.paymentMethod:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn (Sale $sale): array => $this->toDashboardOrderPayload($sale, 'Loja virtual', true))
            ->values()
            ->all();

        $recentDeliveries = (clone $baseQuery)
            ->where('shipping_mode', Sale::SHIPPING_MODE_DELIVERY)
            ->with([
                'client:id,name,email,phone,document',
                'items:id,sale_id,product_id,description,sku,quantity,unit_price,discount_amount,total_amount',
                'items.product:id,image_url',
                'payments:id,sale_id,payment_method_id,status,amount',
                'payments.paymentMethod:id,name',
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(15)
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
            'recent_deliveries' => $recentDeliveries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toDashboardOrderPayload(Sale $sale, string $channel, bool $allowActions): array
    {
        $metadata = is_array($sale->metadata) ? $sale->metadata : [];
        $status = $this->resolveDashboardStatusMeta((string) $sale->status);
        $shippingMode = strtolower(trim((string) (
            $sale->shipping_mode
            ?? ($metadata['delivery_mode'] ?? '')
        )));
        $shippingModeMeta = $this->resolveDashboardShippingModeMeta($shippingMode);

        $shippingAddress = is_array($sale->shipping_address) ? $sale->shipping_address : [];
        $shippingAddressPayload = [
            'postal_code' => trim((string) ($shippingAddress['postal_code'] ?? '')),
            'street' => trim((string) ($shippingAddress['street'] ?? '')),
            'number' => trim((string) ($shippingAddress['number'] ?? '')),
            'complement' => trim((string) ($shippingAddress['complement'] ?? '')),
            'district' => trim((string) ($shippingAddress['district'] ?? '')),
            'city' => trim((string) ($shippingAddress['city'] ?? '')),
            'state' => strtoupper(trim((string) ($shippingAddress['state'] ?? ''))),
        ];

        $paymentLabel = $sale->payments
            ->map(static fn (SalePayment $payment): ?string => $payment->paymentMethod?->name)
            ->filter()
            ->unique()
            ->values()
            ->implode(' + ');

        $paymentMethods = $sale->payments
            ->map(fn (SalePayment $payment): array => [
                'id' => (int) $payment->id,
                'name' => trim((string) ($payment->paymentMethod?->name ?? 'Não informado')),
                'status' => (string) $payment->status,
                'status_label' => $this->resolveDashboardPaymentStatusLabel((string) $payment->status),
                'amount' => round((float) $payment->amount, 2),
                'amount_label' => 'R$ '.number_format((float) $payment->amount, 2, ',', '.'),
            ])
            ->values()
            ->all();

        $customerName = trim((string) ($sale->client?->name ?? ($metadata['customer_name'] ?? '')));
        if ($customerName === '') {
            $customerName = 'Consumidor final';
        }

        $customerPhone = trim((string) ($sale->client?->phone ?? ($metadata['customer_phone'] ?? '')));
        $customerEmail = trim((string) ($sale->client?->email ?? ($metadata['customer_email'] ?? '')));
        $customerDocument = trim((string) ($sale->client?->document ?? ($metadata['customer_document'] ?? '')));

        $customerContact = $customerPhone;
        if ($customerContact === '') {
            $customerContact = $customerEmail;
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

        $shippingAddressLines = array_filter([
            trim(implode(', ', array_filter([
                $shippingAddressPayload['street'],
                $shippingAddressPayload['number'],
            ], static fn (string $value): bool => $value !== ''))),
            $shippingAddressPayload['district'],
            trim(implode(' - ', array_filter([
                $shippingAddressPayload['city'],
                $shippingAddressPayload['state'],
            ], static fn (string $value): bool => $value !== ''))),
            $shippingAddressPayload['postal_code'] !== '' ? 'CEP '.$shippingAddressPayload['postal_code'] : '',
            $shippingAddressPayload['complement'],
        ], static fn (string $value): bool => $value !== '');

        $shippingAddressText = $shippingAddressLines !== []
            ? implode(' • ', $shippingAddressLines)
            : null;

        $paymentText = $paymentLabel !== '' ? $paymentLabel : 'Não informado';
        $formattedAmount = 'R$ '.number_format((float) $sale->total_amount, 2, ',', '.');
        $createdAt = optional($sale->created_at)->format('d/m/Y H:i');
        $completedAt = $sale->completed_at ?? $sale->created_at;

        return [
            'id' => (int) $sale->id,
            'code' => (string) $sale->code,
            'customer' => $customerName,
            'customer_contact' => $customerContact,
            'customer_phone' => $customerPhone !== '' ? $customerPhone : null,
            'customer_email' => $customerEmail !== '' ? $customerEmail : null,
            'customer_document' => $customerDocument !== '' ? $customerDocument : null,
            'channel' => $channel,
            'total_amount' => (float) $sale->total_amount,
            'total_items' => $totalItems,
            'items' => $items->all(),
            'status' => $status,
            'payment_label' => $paymentText,
            'payment_methods' => $paymentMethods,
            'created_at' => $createdAt,
            'description' => $description,
            'amount' => $formattedAmount,
            'payment' => $paymentText,
            'time' => $completedAt?->format('H:i') ?? '--:--',
            'shipping_mode' => $shippingModeMeta['value'],
            'shipping_mode_label' => $shippingModeMeta['label'],
            'shipping_mode_tone' => $shippingModeMeta['tone'],
            'shipping_address' => $shippingAddressPayload,
            'shipping_address_text' => $shippingAddressText,
            'notes' => trim((string) ($sale->notes ?? '')) !== '' ? trim((string) $sale->notes) : null,
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

    /**
     * @return array{value: string, label: string, tone: string}
     */
    private function resolveDashboardShippingModeMeta(string $shippingMode): array
    {
        return match ($shippingMode) {
            Sale::SHIPPING_MODE_DELIVERY => [
                'value' => Sale::SHIPPING_MODE_DELIVERY,
                'label' => 'Entrega',
                'tone' => 'bg-emerald-100 text-emerald-700',
            ],
            default => [
                'value' => Sale::SHIPPING_MODE_PICKUP,
                'label' => 'Retirada na loja',
                'tone' => 'bg-blue-100 text-blue-700',
            ],
        };
    }

    private function resolveDashboardPaymentStatusLabel(string $status): string
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

    /**
     * @return array{value: string, label: string, tone: string}
     */
    private function resolveServiceAppointmentStatusMeta(string $status): array
    {
        $normalized = strtolower(trim($status));

        return match ($normalized) {
            ServiceAppointment::STATUS_SCHEDULED => ['value' => $normalized, 'label' => 'Agendado', 'tone' => 'bg-slate-100 text-slate-700'],
            ServiceAppointment::STATUS_CONFIRMED => ['value' => $normalized, 'label' => 'Confirmado', 'tone' => 'bg-slate-100 text-slate-700'],
            ServiceAppointment::STATUS_IN_SERVICE => ['value' => $normalized, 'label' => 'Em atendimento', 'tone' => 'bg-blue-100 text-blue-700'],
            ServiceAppointment::STATUS_DONE => ['value' => $normalized, 'label' => 'Concluído', 'tone' => 'bg-emerald-100 text-emerald-700'],
            ServiceAppointment::STATUS_CANCELLED => ['value' => $normalized, 'label' => 'Cancelado', 'tone' => 'bg-rose-100 text-rose-700'],
            ServiceAppointment::STATUS_NO_SHOW => ['value' => $normalized, 'label' => 'Não compareceu', 'tone' => 'bg-rose-100 text-rose-700'],
            default => ['value' => $normalized, 'label' => ucfirst($normalized), 'tone' => 'bg-slate-100 text-slate-700'],
        };
    }

    private function resolveServiceAppointmentPaymentLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            ServiceAppointment::PAYMENT_STATUS_PAID => 'Pago',
            ServiceAppointment::PAYMENT_STATUS_CANCELLED => 'Pagamento cancelado',
            default => 'Pagamento pendente',
        };
    }

    private function resolveServiceAppointmentPaymentTone(string $status): string
    {
        return match (strtolower(trim($status))) {
            ServiceAppointment::PAYMENT_STATUS_PAID => 'bg-emerald-100 text-emerald-700',
            ServiceAppointment::PAYMENT_STATUS_CANCELLED => 'bg-rose-100 text-rose-700',
            default => 'bg-amber-100 text-amber-700',
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
