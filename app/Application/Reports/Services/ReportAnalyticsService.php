<?php

namespace App\Application\Reports\Services;

use App\Application\Reports\Support\ReportPeriod;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalog;
use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportAnalyticsService
{
    public function __construct(
        private readonly ReportMetricCatalog $metricCatalog,
    ) {}

    /**
     * @param  array{
     *   top_items_limit?: int,
     *   timeline_days_limit?: int,
     *   visible_metric_keys?: array<int, string>
     * }  $profile
     * @return array{
     *   metric_cards: array<int, array<string, mixed>>,
     *   top_items: array<string, mixed>,
     *   timeline: array<string, mixed>,
     *   report_cards: array<int, array<string, string>>
     * }
     */
    public function buildOverview(Contractor $contractor, ReportPeriod $period, array $profile = []): array
    {
        $definitions = collect($this->metricCatalog->definitionsForNiche($contractor->niche()));
        $visibleMetricKeys = collect(is_array($profile['visible_metric_keys'] ?? null) ? $profile['visible_metric_keys'] : [])
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();

        if ($visibleMetricKeys->isNotEmpty()) {
            $definitions = $definitions
                ->filter(fn (array $definition): bool => $visibleMetricKeys->contains((string) ($definition['key'] ?? '')))
                ->values();
        }

        $metricCards = $definitions
            ->map(fn (array $definition): array => $this->buildMetricCard($contractor, $period, $definition))
            ->values()
            ->all();

        $topItemsLimit = min(max((int) ($profile['top_items_limit'] ?? 6), 3), 20);
        $timelineDaysLimit = min(max((int) ($profile['timeline_days_limit'] ?? 21), 7), 90);

        return [
            'metric_cards' => $metricCards,
            'top_items' => $this->buildTopItems($contractor, $period, $topItemsLimit),
            'timeline' => $this->buildTimeline($contractor, $period, $timelineDaysLimit),
            'report_cards' => $this->buildReportCards($contractor),
        ];
    }

    /**
     * @param  array<string, string>  $definition
     * @return array<string, mixed>
     */
    private function buildMetricCard(Contractor $contractor, ReportPeriod $period, array $definition): array
    {
        $key = (string) ($definition['key'] ?? '');
        $value = $this->resolveMetricValue($contractor, $period, $key);

        return [
            'key' => $key,
            'label' => (string) ($definition['label'] ?? $key),
            'description' => (string) ($definition['description'] ?? ''),
            'format' => (string) ($definition['format'] ?? 'integer'),
            'value' => $value,
            'tone' => (string) ($definition['tone'] ?? 'bg-slate-100 text-slate-700'),
        ];
    }

    private function resolveMetricValue(Contractor $contractor, ReportPeriod $period, string $metricKey): float|int
    {
        return match ($metricKey) {
            'commercial_revenue' => $this->commercialRevenue($contractor, $period),
            'commercial_orders' => $this->commercialOrders($contractor, $period),
            'commercial_average_ticket' => $this->commercialAverageTicket($contractor, $period),
            'commercial_active_products' => $this->commercialActiveProducts($contractor),
            'services_revenue' => $this->servicesRevenue($contractor, $period),
            'services_completed_orders' => $this->servicesCompletedOrders($contractor, $period),
            'services_appointments' => $this->servicesAppointments($contractor, $period),
            'services_active_catalog' => $this->servicesActiveCatalog($contractor),
            default => 0,
        };
    }

    private function commercialRevenue(Contractor $contractor, ReportPeriod $period): float
    {
        return (float) Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
            ->where(fn ($query) => $this->applySaleCompletionPeriodConstraint($query, $period))
            ->sum('total_amount');
    }

    private function commercialOrders(Contractor $contractor, ReportPeriod $period): int
    {
        return (int) Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereNotIn('status', [Sale::STATUS_DRAFT, Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED, Sale::STATUS_REFUNDED])
            ->whereBetween('created_at', [$period->startUtc(), $period->endUtc()])
            ->count();
    }

    private function commercialAverageTicket(Contractor $contractor, ReportPeriod $period): float
    {
        $revenue = $this->commercialRevenue($contractor, $period);

        $count = (int) Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
            ->where(fn ($query) => $this->applySaleCompletionPeriodConstraint($query, $period))
            ->count();

        return $count > 0 ? round($revenue / $count, 2) : 0.0;
    }

    private function commercialActiveProducts(Contractor $contractor): int
    {
        return (int) Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();
    }

    private function servicesRevenue(Contractor $contractor, ReportPeriod $period): float
    {
        return (float) ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', ServiceOrder::STATUS_DONE)
            ->where(fn ($query) => $this->applyServiceFinishedPeriodConstraint($query, $period))
            ->sum('final_amount');
    }

    private function servicesCompletedOrders(Contractor $contractor, ReportPeriod $period): int
    {
        return (int) ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', ServiceOrder::STATUS_DONE)
            ->where(fn ($query) => $this->applyServiceFinishedPeriodConstraint($query, $period))
            ->count();
    }

    private function servicesAppointments(Contractor $contractor, ReportPeriod $period): int
    {
        return (int) ServiceAppointment::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [
                ServiceAppointment::STATUS_SCHEDULED,
                ServiceAppointment::STATUS_CONFIRMED,
                ServiceAppointment::STATUS_IN_SERVICE,
                ServiceAppointment::STATUS_DONE,
            ])
            ->whereBetween('starts_at', [$period->startUtc(), $period->endUtc()])
            ->count();
    }

    private function servicesActiveCatalog(Contractor $contractor): int
    {
        return (int) ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTopItems(Contractor $contractor, ReportPeriod $period, int $limit): array
    {
        if ($contractor->niche() === Contractor::NICHE_SERVICES) {
            $items = $this->topServices($contractor, $period, $limit);

            return [
                'title' => 'Serviços com melhor resultado',
                'description' => 'Itens com maior faturamento no período selecionado',
                'kind' => 'services',
                'items' => $items,
            ];
        }

        $items = $this->topProducts($contractor, $period, $limit);

        return [
            'title' => 'Produtos com melhor resultado',
            'description' => 'Itens com maior faturamento no período selecionado',
            'kind' => 'products',
            'items' => $items,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topProducts(Contractor $contractor, ReportPeriod $period, int $limit): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sale_items.contractor_id', $contractor->id)
            ->whereNull('sales.deleted_at')
            ->whereIn('sales.status', [Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
            ->where(fn ($query) => $this->applySaleCompletionPeriodConstraint($query, $period, 'sales.completed_at', 'sales.created_at'))
            ->selectRaw('sale_items.product_id')
            ->selectRaw('COALESCE(products.name, sale_items.description) as item_label')
            ->selectRaw('SUM(sale_items.quantity) as quantity_total')
            ->selectRaw('SUM(sale_items.total_amount) as revenue_total')
            ->groupBy('sale_items.product_id', 'products.name', 'sale_items.description')
            ->orderByDesc('revenue_total')
            ->limit($limit)
            ->get()
            ->map(static function ($row, int $index): array {
                $label = trim((string) ($row->item_label ?? ''));
                if ($label === '') {
                    $label = 'Item sem descrição';
                }

                return [
                    'id' => $row->product_id !== null ? 'product-'.$row->product_id : 'manual-'.$index,
                    'label' => $label,
                    'total' => round((float) ($row->revenue_total ?? 0), 2),
                    'volume' => (int) ($row->quantity_total ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topServices(Contractor $contractor, ReportPeriod $period, int $limit): array
    {
        return ServiceOrder::query()
            ->leftJoin('service_catalogs', 'service_catalogs.id', '=', 'service_orders.service_catalog_id')
            ->where('service_orders.contractor_id', $contractor->id)
            ->whereNull('service_catalogs.deleted_at')
            ->where('service_orders.status', ServiceOrder::STATUS_DONE)
            ->where(fn ($query) => $this->applyServiceFinishedPeriodConstraint($query, $period, 'service_orders.finished_at', 'service_orders.updated_at'))
            ->selectRaw('service_orders.service_catalog_id')
            ->selectRaw('COALESCE(service_catalogs.name, service_orders.title) as item_label')
            ->selectRaw('COUNT(*) as orders_total')
            ->selectRaw('SUM(service_orders.final_amount) as revenue_total')
            ->groupBy('service_orders.service_catalog_id', 'service_catalogs.name', 'service_orders.title')
            ->orderByDesc('revenue_total')
            ->limit($limit)
            ->get()
            ->map(static function ($row, int $index): array {
                $label = trim((string) ($row->item_label ?? ''));
                if ($label === '') {
                    $label = 'Serviço sem descrição';
                }

                return [
                    'id' => $row->service_catalog_id !== null ? 'service-'.$row->service_catalog_id : 'manual-'.$index,
                    'label' => $label,
                    'total' => round((float) ($row->revenue_total ?? 0), 2),
                    'volume' => (int) ($row->orders_total ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTimeline(Contractor $contractor, ReportPeriod $period, int $daysLimit): array
    {
        $timelineStartLocal = $period->endLocal
            ->startOfDay()
            ->subDays(max($daysLimit - 1, 0));

        if ($timelineStartLocal->lessThan($period->startLocal->startOfDay())) {
            $timelineStartLocal = $period->startLocal->startOfDay();
        }

        $timelineStartUtc = $timelineStartLocal->setTimezone('UTC');
        $timelineEndUtc = $period->endUtc();

        if ($contractor->niche() === Contractor::NICHE_SERVICES) {
            $series = $this->servicesTimelineSeries($contractor, $timelineStartUtc, $timelineEndUtc, $period->timezone);

            return [
                'title' => 'Evolução diária dos serviços',
                'description' => 'Faturamento por dia no intervalo recente',
                'items' => $this->fillTimelineGaps($timelineStartLocal, $period->endLocal, $series),
            ];
        }

        $series = $this->commercialTimelineSeries($contractor, $timelineStartUtc, $timelineEndUtc, $period->timezone);

        return [
            'title' => 'Evolução diária das vendas',
            'description' => 'Faturamento por dia no intervalo recente',
            'items' => $this->fillTimelineGaps($timelineStartLocal, $period->endLocal, $series),
        ];
    }

    /**
     * @return Collection<string, array{value: float, volume: int}>
     */
    private function commercialTimelineSeries(
        Contractor $contractor,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $timezone
    ): Collection {
        $rows = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
            ->where(fn ($query) => $this->applySaleCompletionPeriodConstraint($query, null, 'completed_at', 'created_at', $startUtc, $endUtc))
            ->get(['id', 'total_amount', 'completed_at', 'created_at']);

        return $rows->reduce(
            static function (Collection $carry, Sale $sale) use ($timezone): Collection {
                $reference = $sale->completed_at ?? $sale->created_at;
                if ($reference === null) {
                    return $carry;
                }

                $localDate = CarbonImmutable::instance($reference)->setTimezone($timezone)->toDateString();
                $current = $carry->get($localDate, ['value' => 0.0, 'volume' => 0]);
                $current['value'] += (float) $sale->total_amount;
                $current['volume'] += 1;
                $carry->put($localDate, $current);

                return $carry;
            },
            collect()
        );
    }

    /**
     * @return Collection<string, array{value: float, volume: int}>
     */
    private function servicesTimelineSeries(
        Contractor $contractor,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        string $timezone
    ): Collection {
        $rows = ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', ServiceOrder::STATUS_DONE)
            ->where(fn ($query) => $this->applyServiceFinishedPeriodConstraint($query, null, 'finished_at', 'updated_at', $startUtc, $endUtc))
            ->get(['id', 'final_amount', 'finished_at', 'updated_at']);

        return $rows->reduce(
            static function (Collection $carry, ServiceOrder $order) use ($timezone): Collection {
                $reference = $order->finished_at ?? $order->updated_at;
                if ($reference === null) {
                    return $carry;
                }

                $localDate = CarbonImmutable::instance($reference)->setTimezone($timezone)->toDateString();
                $current = $carry->get($localDate, ['value' => 0.0, 'volume' => 0]);
                $current['value'] += (float) $order->final_amount;
                $current['volume'] += 1;
                $carry->put($localDate, $current);

                return $carry;
            },
            collect()
        );
    }

    /**
     * @param  Collection<string, array{value: float, volume: int}>  $series
     * @return array<int, array<string, mixed>>
     */
    private function fillTimelineGaps(
        CarbonImmutable $startLocal,
        CarbonImmutable $endLocal,
        Collection $series
    ): array {
        $items = [];
        $cursor = $startLocal->startOfDay();
        $end = $endLocal->endOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $point = $series->get($key, ['value' => 0.0, 'volume' => 0]);
            $items[] = [
                'date' => $key,
                'label' => $cursor->format('d/m'),
                'total' => round((float) ($point['value'] ?? 0), 2),
                'volume' => (int) ($point['volume'] ?? 0),
            ];

            $cursor = $cursor->addDay();
        }

        return $items;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildReportCards(Contractor $contractor): array
    {
        if ($contractor->niche() === Contractor::NICHE_SERVICES) {
            return [
                [
                    'title' => 'Serviços por especialidade',
                    'description' => 'Compare volume e faturamento por tipo de serviço.',
                    'tag' => 'Nicho serviços',
                ],
                [
                    'title' => 'Produtividade operacional',
                    'description' => 'Monitore ordens abertas, concluídas e SLA médio.',
                    'tag' => 'Operação',
                ],
                [
                    'title' => 'Agenda e recorrência',
                    'description' => 'Veja horários de maior demanda e recorrência de clientes.',
                    'tag' => 'Agenda',
                ],
            ];
        }

        return [
            [
                'title' => 'Vendas por canal',
                'description' => 'Compare PDV, pedidos e catálogo em um único painel.',
                'tag' => 'Nicho comércio',
            ],
            [
                'title' => 'Mix de produtos',
                'description' => 'Entenda quais itens geram mais receita e volume.',
                'tag' => 'Catálogo',
            ],
            [
                'title' => 'Margem operacional',
                'description' => 'Acompanhe evolução de faturamento e ticket médio.',
                'tag' => 'Financeiro',
            ],
        ];
    }

    /**
     * @param  string|null  $completedColumn
     * @param  string|null  $createdFallbackColumn
     */
    private function applySaleCompletionPeriodConstraint(
        mixed $query,
        ?ReportPeriod $period,
        string $completedColumn = 'completed_at',
        string $createdFallbackColumn = 'created_at',
        ?CarbonImmutable $startUtc = null,
        ?CarbonImmutable $endUtc = null,
    ): void {
        $start = $startUtc ?? $period?->startUtc();
        $end = $endUtc ?? $period?->endUtc();
        if (! $start || ! $end) {
            return;
        }

        $query
            ->whereBetween($completedColumn, [$start, $end])
            ->orWhere(static function ($fallbackQuery) use ($createdFallbackColumn, $start, $end, $completedColumn): void {
                $fallbackQuery
                    ->whereNull($completedColumn)
                    ->whereBetween($createdFallbackColumn, [$start, $end]);
            });
    }

    /**
     * @param  string|null  $finishedColumn
     * @param  string|null  $updatedFallbackColumn
     */
    private function applyServiceFinishedPeriodConstraint(
        mixed $query,
        ?ReportPeriod $period,
        string $finishedColumn = 'finished_at',
        string $updatedFallbackColumn = 'updated_at',
        ?CarbonImmutable $startUtc = null,
        ?CarbonImmutable $endUtc = null,
    ): void {
        $start = $startUtc ?? $period?->startUtc();
        $end = $endUtc ?? $period?->endUtc();
        if (! $start || ! $end) {
            return;
        }

        $query
            ->whereBetween($finishedColumn, [$start, $end])
            ->orWhere(static function ($fallbackQuery) use ($updatedFallbackColumn, $start, $end, $finishedColumn): void {
                $fallbackQuery
                    ->whereNull($finishedColumn)
                    ->whereBetween($updatedFallbackColumn, [$start, $end]);
            });
    }
}
