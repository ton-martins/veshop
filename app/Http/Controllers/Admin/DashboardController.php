<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\CashSession;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\ServiceCatalog;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $isServices = $contractor->hasModule(Contractor::MODULE_SERVICES);

        $pdvStats = $this->resolvePdvStats($contractor);

        $commercialOverview = [
            'operations' => [
                'orders_today' => 0,
                'in_production' => 0,
                'monthly_revenue' => 0,
                'clients' => Client::query()
                    ->where('contractor_id', $contractor->id)
                    ->count(),
                'pending_quotes' => 0,
                'deliveries_today' => 0,
                'recent_orders' => [],
            ],
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

        $servicesOverview = [
            'stats' => [
                'open_orders' => 0,
                'today' => 0,
                'catalog' => $serviceCatalogCount,
                'revenue' => 0,
                'active_services' => $serviceActiveCount,
                'avg_price' => $serviceAveragePrice !== null ? (float) $serviceAveragePrice : 0.0,
            ],
            'queue' => [],
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
     * @return array{
     *   sales_today: float,
     *   sales_count: int,
     *   avg_ticket: float,
     *   cash_open: bool,
     *   pending_quotes: int,
     *   recent_sales: array<int, array{id: string, customer: string, payment: string, amount: string, time: string}>,
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
                'client:id,name',
                'payments.paymentMethod:id,name',
            ])
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(static function (Sale $sale): array {
                $paymentNames = $sale->payments
                    ->map(static fn (SalePayment $payment): ?string => $payment->paymentMethod?->name)
                    ->filter()
                    ->unique()
                    ->values();

                $completedAt = $sale->completed_at ?? $sale->created_at;
                $amount = (float) $sale->total_amount;

                return [
                    'id' => $sale->code,
                    'customer' => $sale->client?->name ?? 'Consumidor final',
                    'payment' => $paymentNames->isNotEmpty()
                        ? $paymentNames->implode(' + ')
                        : 'Não informado',
                    'amount' => 'R$ '.number_format($amount, 2, ',', '.'),
                    'time' => $completedAt?->format('H:i') ?? '--:--',
                ];
            })
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
}
