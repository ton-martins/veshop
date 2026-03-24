<?php

namespace App\Jobs;

use App\Models\Contractor;
use App\Models\FinancialEntry;
use App\Models\Product;
use App\Models\ReportExport;
use App\Models\Sale;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalog;
use App\Models\ServiceOrder;
use App\Notifications\ReportExportReadyNotification;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MAX_DETAIL_ROWS = 1000;

    public int $timeout = 300;

    public int $tries = 3;

    public function __construct(
        public readonly int $reportExportId,
    ) {
        $this->connection = (string) config('queue.workloads.exports.connection', config('queue.default'));
        $this->queue = (string) config('queue.workloads.exports.queue', 'default');
        $this->afterCommit = true;
    }

    public function handle(): void
    {
        $export = ReportExport::query()
            ->with([
                'requestedBy:id,name',
                'contractor:id,name,brand_name,brand_logo_url,brand_avatar_url,timezone',
            ])
            ->find($this->reportExportId);

        if (! $export || $export->type !== ReportExport::TYPE_DASHBOARD || ! $export->contractor) {
            return;
        }

        $filters = is_array($export->filters) ? $export->filters : [];
        $format = $this->normalizeFormat((string) ($filters['format'] ?? ReportExport::FORMAT_CSV));
        $includeDetails = (bool) ($filters['include_details'] ?? true);

        $moduleCodes = collect(is_array($filters['module_codes'] ?? null) ? $filters['module_codes'] : [])
            ->map(static fn (mixed $code): string => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($moduleCodes === []) {
            $moduleCodes = $this->defaultModuleCodes($export->contractor);
        }

        [$startLocal, $endLocal] = $this->resolveRange($export->contractor, $filters);
        $startUtc = $startLocal->setTimezone('UTC');
        $endUtc = $endLocal->setTimezone('UTC');

        $export->forceFill([
            'status' => ReportExport::STATUS_PROCESSING,
            'started_at' => now(),
            'finished_at' => null,
            'failed_at' => null,
            'error_message' => null,
            'queue_connection' => (string) $this->connection,
            'queue_name' => (string) $this->queue,
        ])->save();

        $sections = $this->buildSections(
            contractor: $export->contractor,
            moduleCodes: $moduleCodes,
            startLocal: $startLocal,
            endLocal: $endLocal,
            startUtc: $startUtc,
            endUtc: $endUtc,
            includeDetails: $includeDetails,
        );

        if ($sections === []) {
            $sections[] = $this->emptySection($startLocal, $endLocal);
        }

        $rowsCount = collect($sections)->sum(
            static fn (array $section): int => is_array($section['rows'] ?? null) ? count($section['rows']) : 0
        );

        $disk = 'local';
        $directory = "exports/contractors/{$export->contractor_id}";
        Storage::disk($disk)->makeDirectory($directory);

        $extension = $this->resolveExtension($format);
        $defaultFilename = sprintf(
            'relatorio-%d-%d-%s.%s',
            (int) $export->contractor_id,
            (int) $export->id,
            now()->format('Ymd-His'),
            $extension
        );
        $filename = $this->resolveOutputFilename(
            disk: $disk,
            directory: $directory,
            filters: $filters,
            defaultFilename: $defaultFilename,
            extension: $extension,
            exportId: (int) $export->id,
        );
        $path = "{$directory}/{$filename}";

        $identity = $this->resolveContractorIdentity($export->contractor);

        $viewData = [
            'contractor' => $export->contractor,
            'identity' => $identity,
            'period' => [
                'start' => $startLocal->format('d/m/Y'),
                'end' => $endLocal->format('d/m/Y'),
            ],
            'sections' => $sections,
            'generated_at' => now()->format('d/m/Y H:i'),
        ];

        match ($format) {
            ReportExport::FORMAT_PDF => $this->storePdf($disk, $path, $viewData),
            ReportExport::FORMAT_EXCEL => $this->storeExcel($disk, $path, $viewData),
            default => $this->storeCsv($disk, $path, $sections),
        };

        $export->forceFill([
            'status' => ReportExport::STATUS_COMPLETED,
            'file_disk' => $disk,
            'file_path' => $path,
            'file_name' => $filename,
            'row_count' => $rowsCount,
            'finished_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        if ($export->requestedBy) {
            $export->requestedBy->notify(new ReportExportReadyNotification($export));
        }
    }

    public function failed(Throwable $exception): void
    {
        ReportExport::query()
            ->whereKey($this->reportExportId)
            ->update([
                'status' => ReportExport::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => Str::limit($exception->getMessage(), 2000, ''),
                'finished_at' => null,
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveRange(Contractor $contractor, array $filters): array
    {
        $timezone = (string) ($contractor->timezone ?: config('app.timezone', 'UTC'));
        $now = CarbonImmutable::now($timezone);

        $fallbackStart = $now->startOfMonth();
        $fallbackEnd = $now->endOfMonth();

        $start = $this->parseDate((string) ($filters['date_from'] ?? ''), $timezone, $fallbackStart)->startOfDay();
        $end = $this->parseDate((string) ($filters['date_to'] ?? ''), $timezone, $fallbackEnd)->endOfDay();

        if ($end->lessThan($start)) {
            $end = $start->endOfDay();
        }

        if ($start->diffInDays($end) > 366) {
            $end = $start->addDays(366)->endOfDay();
        }

        return [$start, $end];
    }

    private function parseDate(string $value, string $timezone, CarbonImmutable $fallback): CarbonImmutable
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $fallback;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $trimmed, $timezone);
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function normalizeFormat(string $value): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, ReportExport::availableFormats(), true)
            ? $normalized
            : ReportExport::FORMAT_CSV;
    }

    private function resolveExtension(string $format): string
    {
        return match ($format) {
            ReportExport::FORMAT_PDF => 'pdf',
            ReportExport::FORMAT_EXCEL => 'xls',
            default => 'csv',
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function resolveOutputFilename(
        string $disk,
        string $directory,
        array $filters,
        string $defaultFilename,
        string $extension,
        int $exportId
    ): string {
        $baseName = $this->sanitizeCustomFileName($filters['custom_file_name'] ?? null);
        if ($baseName === null) {
            return $defaultFilename;
        }

        $filename = "{$baseName}.{$extension}";
        if (Storage::disk($disk)->exists("{$directory}/{$filename}")) {
            $filename = "{$baseName}-{$exportId}.{$extension}";
        }

        return $filename;
    }

    private function sanitizeCustomFileName(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $withoutExtension = preg_replace('/\.[a-z0-9]{2,5}$/iu', '', $raw);
        $candidate = is_string($withoutExtension) ? $withoutExtension : $raw;

        $candidate = preg_replace('/[\\\\\/:"*?<>|]+/u', '', $candidate);
        $candidate = is_string($candidate) ? $candidate : '';
        $candidate = preg_replace('/\s+/u', ' ', $candidate);
        $candidate = is_string($candidate) ? trim($candidate, " .\t\n\r\0\x0B") : '';

        if ($candidate === '') {
            return null;
        }

        return mb_substr($candidate, 0, 120);
    }

    /**
     * @return list<string>
     */
    private function defaultModuleCodes(Contractor $contractor): array
    {
        return $contractor->niche() === Contractor::NICHE_SERVICES
            ? ['services', 'schedule', 'services_catalog']
            : ['commercial', 'orders', 'catalog'];
    }

    /**
     * @param  array<int, string>  $moduleCodes
     * @return array<int, array<string, mixed>>
     */
    private function buildSections(
        Contractor $contractor,
        array $moduleCodes,
        CarbonImmutable $startLocal,
        CarbonImmutable $endLocal,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        bool $includeDetails,
    ): array {
        $sections = [];

        foreach ($moduleCodes as $moduleCode) {
            $section = match ($moduleCode) {
                'commercial' => $this->commercialSection($contractor, $startLocal, $endLocal, $startUtc, $endUtc, $includeDetails),
                'pdv' => $this->pdvSection($contractor, $startLocal, $endLocal, $startUtc, $endUtc, $includeDetails),
                'orders' => $this->ordersSection($contractor, $startLocal, $endLocal, $startUtc, $endUtc, $includeDetails),
                'finance' => $this->financeSection($contractor, $startLocal, $endLocal, $includeDetails),
                'catalog' => $this->catalogSection($contractor, $includeDetails),
                'services',
                'service_orders' => $this->servicesSection($contractor, $startLocal, $endLocal, $startUtc, $endUtc, $includeDetails),
                'schedule' => $this->scheduleSection($contractor, $startLocal, $endLocal, $startUtc, $endUtc, $includeDetails),
                'services_catalog',
                'services_storefront' => $this->servicesCatalogSection($contractor, $includeDetails),
                default => null,
            };

            if (is_array($section)) {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function commercialSection(
        Contractor $contractor,
        CarbonImmutable $startLocal,
        CarbonImmutable $endLocal,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        bool $includeDetails,
    ): ?array {
        if (! Schema::hasTable('sales')) {
            return null;
        }

        $base = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
            ->where(function ($query) use ($startUtc, $endUtc): void {
                $this->applySaleDateConstraint($query, $startUtc, $endUtc);
            });

        $totalOrders = (int) (clone $base)->count();
        $totalRevenue = (float) (clone $base)->sum('total_amount');
        $averageTicket = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0.0;

        if ($includeDetails) {
            $rows = (clone $base)
                ->with(['client:id,name', 'shopCustomer:id,name'])
                ->orderByDesc('id')
                ->limit(self::MAX_DETAIL_ROWS)
                ->get()
                ->map(fn (Sale $sale): array => [
                    'codigo' => (string) $sale->code,
                    'origem' => strtoupper((string) $sale->source),
                    'status' => (string) $sale->status,
                    'cliente' => (string) ($sale->client?->name ?? $sale->shopCustomer?->name ?? 'Consumidor final'),
                    'total' => $this->formatCurrency((float) $sale->total_amount),
                    'data' => optional($sale->completed_at ?? $sale->created_at)?->format('d/m/Y H:i') ?? '-',
                ])
                ->values()
                ->all();

            $columns = [
                ['key' => 'codigo', 'label' => 'Código'],
                ['key' => 'origem', 'label' => 'Origem'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'cliente', 'label' => 'Cliente'],
                ['key' => 'total', 'label' => 'Total'],
                ['key' => 'data', 'label' => 'Data'],
            ];
        } else {
            $rows = (clone $base)
                ->selectRaw('source')
                ->selectRaw('COUNT(*) as total_pedidos')
                ->selectRaw('SUM(total_amount) as faturamento')
                ->groupBy('source')
                ->orderByDesc('faturamento')
                ->get()
                ->map(static fn ($row): array => [
                    'origem' => strtoupper((string) $row->source),
                    'pedidos' => (int) ($row->total_pedidos ?? 0),
                    'faturamento' => number_format((float) ($row->faturamento ?? 0), 2, ',', '.'),
                ])
                ->values()
                ->all();

            $columns = [
                ['key' => 'origem', 'label' => 'Origem'],
                ['key' => 'pedidos', 'label' => 'Pedidos'],
                ['key' => 'faturamento', 'label' => 'Faturamento (R$)'],
            ];
        }

        return [
            'title' => 'Comercial',
            'description' => "Período de {$startLocal->format('d/m/Y')} até {$endLocal->format('d/m/Y')}.",
            'summary' => [
                ['label' => 'Pedidos faturados', 'value' => (string) $totalOrders],
                ['label' => 'Faturamento total', 'value' => $this->formatCurrency($totalRevenue)],
                ['label' => 'Ticket médio', 'value' => $this->formatCurrency($averageTicket)],
            ],
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pdvSection(
        Contractor $contractor,
        CarbonImmutable $startLocal,
        CarbonImmutable $endLocal,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        bool $includeDetails,
    ): ?array {
        if (! Schema::hasTable('sales')) {
            return null;
        }

        $base = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('source', Sale::SOURCE_PDV)
            ->whereIn('status', [Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
            ->where(function ($query) use ($startUtc, $endUtc): void {
                $this->applySaleDateConstraint($query, $startUtc, $endUtc);
            });

        return $this->salesChannelSection(
            title: 'PDV',
            description: "Vendas do PDV entre {$startLocal->format('d/m/Y')} e {$endLocal->format('d/m/Y')}.",
            base: $base,
            includeDetails: $includeDetails,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ordersSection(
        Contractor $contractor,
        CarbonImmutable $startLocal,
        CarbonImmutable $endLocal,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        bool $includeDetails,
    ): ?array {
        if (! Schema::hasTable('sales')) {
            return null;
        }

        $base = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('source', [Sale::SOURCE_ORDER, Sale::SOURCE_CATALOG])
            ->whereIn('status', [Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
            ->where(function ($query) use ($startUtc, $endUtc): void {
                $this->applySaleDateConstraint($query, $startUtc, $endUtc);
            });

        return $this->salesChannelSection(
            title: 'Pedidos',
            description: "Pedidos online entre {$startLocal->format('d/m/Y')} e {$endLocal->format('d/m/Y')}.",
            base: $base,
            includeDetails: $includeDetails,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function salesChannelSection(string $title, string $description, mixed $base, bool $includeDetails): array
    {
        $totalOrders = (int) (clone $base)->count();
        $totalRevenue = (float) (clone $base)->sum('total_amount');

        if ($includeDetails) {
            $rows = (clone $base)
                ->with(['client:id,name', 'shopCustomer:id,name'])
                ->orderByDesc('id')
                ->limit(self::MAX_DETAIL_ROWS)
                ->get()
                ->map(fn (Sale $sale): array => [
                    'codigo' => (string) $sale->code,
                    'status' => (string) $sale->status,
                    'cliente' => (string) ($sale->client?->name ?? $sale->shopCustomer?->name ?? 'Consumidor final'),
                    'total' => $this->formatCurrency((float) $sale->total_amount),
                    'data' => optional($sale->completed_at ?? $sale->created_at)?->format('d/m/Y H:i') ?? '-',
                ])
                ->values()
                ->all();

            $columns = [
                ['key' => 'codigo', 'label' => 'Código'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'cliente', 'label' => 'Cliente'],
                ['key' => 'total', 'label' => 'Total'],
                ['key' => 'data', 'label' => 'Data'],
            ];
        } else {
            $rows = (clone $base)
                ->selectRaw('status')
                ->selectRaw('COUNT(*) as total_pedidos')
                ->selectRaw('SUM(total_amount) as total_faturado')
                ->groupBy('status')
                ->orderByDesc('total_faturado')
                ->get()
                ->map(static fn ($row): array => [
                    'status' => (string) $row->status,
                    'pedidos' => (int) ($row->total_pedidos ?? 0),
                    'faturamento' => number_format((float) ($row->total_faturado ?? 0), 2, ',', '.'),
                ])
                ->values()
                ->all();

            $columns = [
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'pedidos', 'label' => 'Pedidos'],
                ['key' => 'faturamento', 'label' => 'Faturamento (R$)'],
            ];
        }

        return [
            'title' => $title,
            'description' => $description,
            'summary' => [
                ['label' => 'Total de vendas', 'value' => (string) $totalOrders],
                ['label' => 'Faturamento', 'value' => $this->formatCurrency($totalRevenue)],
            ],
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function financeSection(
        Contractor $contractor,
        CarbonImmutable $startLocal,
        CarbonImmutable $endLocal,
        bool $includeDetails,
    ): ?array {
        if (! Schema::hasTable('financial_entries')) {
            return null;
        }

        $base = FinancialEntry::query()
            ->where('contractor_id', $contractor->id)
            ->whereBetween('due_date', [$startLocal->toDateString(), $endLocal->toDateString()]);

        $payables = (float) (clone $base)
            ->where('type', FinancialEntry::TYPE_PAYABLE)
            ->sum('amount');
        $receivables = (float) (clone $base)
            ->where('type', FinancialEntry::TYPE_RECEIVABLE)
            ->sum('amount');
        $pending = (int) (clone $base)
            ->where('status', FinancialEntry::STATUS_PENDING)
            ->count();

        if ($includeDetails) {
            $rows = (clone $base)
                ->orderByDesc('due_date')
                ->orderByDesc('id')
                ->limit(self::MAX_DETAIL_ROWS)
                ->get()
                ->map(fn (FinancialEntry $entry): array => [
                    'tipo' => $entry->type === FinancialEntry::TYPE_RECEIVABLE ? 'Receber' : 'Pagar',
                    'status' => (string) $entry->status,
                    'parte' => (string) ($entry->counterparty_name ?: '-'),
                    'referencia' => (string) ($entry->reference ?: '-'),
                    'valor' => $this->formatCurrency((float) $entry->amount),
                    'vencimento' => optional($entry->due_date)?->format('d/m/Y') ?? '-',
                ])
                ->values()
                ->all();

            $columns = [
                ['key' => 'tipo', 'label' => 'Tipo'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'parte', 'label' => 'Contraparte'],
                ['key' => 'referencia', 'label' => 'Referência'],
                ['key' => 'valor', 'label' => 'Valor'],
                ['key' => 'vencimento', 'label' => 'Vencimento'],
            ];
        } else {
            $rows = (clone $base)
                ->selectRaw('type')
                ->selectRaw('status')
                ->selectRaw('COUNT(*) as total_lancamentos')
                ->selectRaw('SUM(amount) as total_valor')
                ->groupBy('type', 'status')
                ->orderByDesc('total_valor')
                ->get()
                ->map(static fn ($row): array => [
                    'tipo' => (string) $row->type,
                    'status' => (string) $row->status,
                    'lancamentos' => (int) ($row->total_lancamentos ?? 0),
                    'valor' => number_format((float) ($row->total_valor ?? 0), 2, ',', '.'),
                ])
                ->values()
                ->all();

            $columns = [
                ['key' => 'tipo', 'label' => 'Tipo'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'lancamentos', 'label' => 'Lançamentos'],
                ['key' => 'valor', 'label' => 'Valor (R$)'],
            ];
        }

        return [
            'title' => 'Financeiro',
            'description' => "Lançamentos entre {$startLocal->format('d/m/Y')} e {$endLocal->format('d/m/Y')}.",
            'summary' => [
                ['label' => 'Total a receber', 'value' => $this->formatCurrency($receivables)],
                ['label' => 'Total a pagar', 'value' => $this->formatCurrency($payables)],
                ['label' => 'Pendências', 'value' => (string) $pending],
            ],
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function catalogSection(Contractor $contractor, bool $includeDetails): ?array
    {
        if (! Schema::hasTable('products')) {
            return null;
        }

        $base = Product::query()
            ->where('contractor_id', $contractor->id);

        $total = (int) (clone $base)->count();
        $active = (int) (clone $base)->where('is_active', true)->count();

        $rows = (clone $base)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit($includeDetails ? self::MAX_DETAIL_ROWS : 250)
            ->get()
            ->map(fn (Product $product): array => [
                'produto' => (string) $product->name,
                'sku' => (string) ($product->sku ?: '-'),
                'preco' => $this->formatCurrency((float) $product->sale_price),
                'estoque' => (string) (int) $product->stock_quantity,
                'status' => $product->is_active ? 'Ativo' : 'Inativo',
            ])
            ->values()
            ->all();

        return [
            'title' => 'Catálogo de produtos',
            'description' => 'Portfólio ativo para operação comercial.',
            'summary' => [
                ['label' => 'Produtos cadastrados', 'value' => (string) $total],
                ['label' => 'Produtos ativos', 'value' => (string) $active],
            ],
            'columns' => [
                ['key' => 'produto', 'label' => 'Produto'],
                ['key' => 'sku', 'label' => 'SKU'],
                ['key' => 'preco', 'label' => 'Preço'],
                ['key' => 'estoque', 'label' => 'Estoque'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function servicesSection(
        Contractor $contractor,
        CarbonImmutable $startLocal,
        CarbonImmutable $endLocal,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        bool $includeDetails,
    ): ?array {
        if (! Schema::hasTable('service_orders')) {
            return null;
        }

        $base = ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->where(function ($query) use ($startUtc, $endUtc): void {
                $this->applyServiceOrderDateConstraint($query, $startUtc, $endUtc);
            });

        $completedOrders = (int) (clone $base)->where('status', ServiceOrder::STATUS_DONE)->count();
        $totalOrders = (int) (clone $base)->count();
        $totalRevenue = (float) (clone $base)->where('status', ServiceOrder::STATUS_DONE)->sum('final_amount');

        if ($includeDetails) {
            $rows = (clone $base)
                ->with(['client:id,name', 'service:id,name'])
                ->orderByDesc('updated_at')
                ->limit(self::MAX_DETAIL_ROWS)
                ->get()
                ->map(fn (ServiceOrder $order): array => [
                    'codigo' => (string) $order->code,
                    'titulo' => (string) ($order->service?->name ?? $order->title),
                    'cliente' => (string) ($order->client?->name ?? '-'),
                    'status' => (string) $order->status,
                    'valor' => $this->formatCurrency((float) $order->final_amount),
                    'data' => optional($order->finished_at ?? $order->updated_at)?->format('d/m/Y H:i') ?? '-',
                ])
                ->values()
                ->all();

            $columns = [
                ['key' => 'codigo', 'label' => 'Código'],
                ['key' => 'titulo', 'label' => 'Serviço'],
                ['key' => 'cliente', 'label' => 'Cliente'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'valor', 'label' => 'Valor'],
                ['key' => 'data', 'label' => 'Data'],
            ];
        } else {
            $rows = (clone $base)
                ->selectRaw('status')
                ->selectRaw('COUNT(*) as total_ordens')
                ->selectRaw('SUM(final_amount) as total_faturado')
                ->groupBy('status')
                ->orderByDesc('total_faturado')
                ->get()
                ->map(static fn ($row): array => [
                    'status' => (string) $row->status,
                    'ordens' => (int) ($row->total_ordens ?? 0),
                    'faturamento' => number_format((float) ($row->total_faturado ?? 0), 2, ',', '.'),
                ])
                ->values()
                ->all();

            $columns = [
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'ordens', 'label' => 'Ordens'],
                ['key' => 'faturamento', 'label' => 'Faturamento (R$)'],
            ];
        }

        return [
            'title' => 'Ordens de serviço',
            'description' => "Ordens entre {$startLocal->format('d/m/Y')} e {$endLocal->format('d/m/Y')}.",
            'summary' => [
                ['label' => 'Ordens no período', 'value' => (string) $totalOrders],
                ['label' => 'Ordens concluídas', 'value' => (string) $completedOrders],
                ['label' => 'Faturamento', 'value' => $this->formatCurrency($totalRevenue)],
            ],
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function scheduleSection(
        Contractor $contractor,
        CarbonImmutable $startLocal,
        CarbonImmutable $endLocal,
        CarbonImmutable $startUtc,
        CarbonImmutable $endUtc,
        bool $includeDetails,
    ): ?array {
        if (! Schema::hasTable('service_appointments')) {
            return null;
        }

        $base = ServiceAppointment::query()
            ->where('contractor_id', $contractor->id)
            ->whereBetween('starts_at', [$startUtc, $endUtc]);

        $total = (int) (clone $base)->count();
        $confirmed = (int) (clone $base)->where('status', ServiceAppointment::STATUS_CONFIRMED)->count();
        $done = (int) (clone $base)->where('status', ServiceAppointment::STATUS_DONE)->count();

        $rows = (clone $base)
            ->with(['client:id,name', 'service:id,name'])
            ->orderBy('starts_at')
            ->limit($includeDetails ? self::MAX_DETAIL_ROWS : 250)
            ->get()
            ->map(fn (ServiceAppointment $appointment): array => [
                'servico' => (string) ($appointment->service?->name ?? $appointment->title),
                'cliente' => (string) ($appointment->client?->name ?? '-'),
                'inicio' => optional($appointment->starts_at)?->format('d/m/Y H:i') ?? '-',
                'fim' => optional($appointment->ends_at)?->format('d/m/Y H:i') ?? '-',
                'status' => (string) $appointment->status,
            ])
            ->values()
            ->all();

        return [
            'title' => 'Agenda',
            'description' => "Agendamentos entre {$startLocal->format('d/m/Y')} e {$endLocal->format('d/m/Y')}.",
            'summary' => [
                ['label' => 'Total de agendamentos', 'value' => (string) $total],
                ['label' => 'Confirmados', 'value' => (string) $confirmed],
                ['label' => 'Concluídos', 'value' => (string) $done],
            ],
            'columns' => [
                ['key' => 'servico', 'label' => 'Serviço'],
                ['key' => 'cliente', 'label' => 'Cliente'],
                ['key' => 'inicio', 'label' => 'Início'],
                ['key' => 'fim', 'label' => 'Fim'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function servicesCatalogSection(Contractor $contractor, bool $includeDetails): ?array
    {
        if (! Schema::hasTable('service_catalogs')) {
            return null;
        }

        $base = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id);

        $total = (int) (clone $base)->count();
        $active = (int) (clone $base)->where('is_active', true)->count();

        $rows = (clone $base)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit($includeDetails ? self::MAX_DETAIL_ROWS : 250)
            ->get()
            ->map(fn (ServiceCatalog $service): array => [
                'servico' => (string) $service->name,
                'codigo' => (string) ($service->code ?: '-'),
                'duracao' => (string) ((int) $service->duration_minutes).' min',
                'preco' => $this->formatCurrency((float) $service->base_price),
                'status' => $service->is_active ? 'Ativo' : 'Inativo',
            ])
            ->values()
            ->all();

        return [
            'title' => 'Catálogo de serviços',
            'description' => 'Portfólio disponível para agendamento e execução.',
            'summary' => [
                ['label' => 'Serviços cadastrados', 'value' => (string) $total],
                ['label' => 'Serviços ativos', 'value' => (string) $active],
            ],
            'columns' => [
                ['key' => 'servico', 'label' => 'Serviço'],
                ['key' => 'codigo', 'label' => 'Código'],
                ['key' => 'duracao', 'label' => 'Duração'],
                ['key' => 'preco', 'label' => 'Preço'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySection(CarbonImmutable $startLocal, CarbonImmutable $endLocal): array
    {
        return [
            'title' => 'Resumo geral',
            'description' => "Período de {$startLocal->format('d/m/Y')} até {$endLocal->format('d/m/Y')}.",
            'summary' => [
                ['label' => 'Observação', 'value' => 'Nenhum módulo elegível foi selecionado para exportação.'],
            ],
            'columns' => [],
            'rows' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storePdf(string $disk, string $path, array $data): void
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml(View::make('exports.reports.pdf', $data)->render(), 'UTF-8');
        $dompdf->render();

        Storage::disk($disk)->put($path, $dompdf->output());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function storeExcel(string $disk, string $path, array $data): void
    {
        $html = View::make('exports.reports.excel', $data)->render();
        Storage::disk($disk)->put($path, $html);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function storeCsv(string $disk, string $path, array $sections): void
    {
        $absolutePath = Storage::disk($disk)->path($path);
        $stream = fopen($absolutePath, 'wb');

        if ($stream === false) {
            throw new RuntimeException('Não foi possível criar o arquivo CSV de exportação.');
        }

        try {
            fwrite($stream, "\xEF\xBB\xBF");

            foreach ($sections as $section) {
                fputcsv($stream, [(string) ($section['title'] ?? '')], ';');
                $description = trim((string) ($section['description'] ?? ''));
                if ($description !== '') {
                    fputcsv($stream, [$description], ';');
                }

                foreach (($section['summary'] ?? []) as $summary) {
                    fputcsv($stream, [
                        (string) ($summary['label'] ?? ''),
                        (string) ($summary['value'] ?? ''),
                    ], ';');
                }

                $columns = collect($section['columns'] ?? [])
                    ->filter(fn (mixed $column): bool => is_array($column))
                    ->values();

                if ($columns->isNotEmpty()) {
                    fputcsv($stream, $columns->pluck('label')->map(static fn ($value) => (string) $value)->all(), ';');
                }

                foreach (($section['rows'] ?? []) as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $values = $columns->map(static function (array $column) use ($row): string {
                        $key = (string) ($column['key'] ?? '');

                        return (string) ($row[$key] ?? '');
                    })->all();

                    if ($values !== []) {
                        fputcsv($stream, $values, ';');
                    }
                }

                fputcsv($stream, [''], ';');
            }
        } catch (Throwable $exception) {
            fclose($stream);
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        fclose($stream);
    }

    /**
     * @return array{name: string, initials: string, logo_data_uri: string|null}
     */
    private function resolveContractorIdentity(Contractor $contractor): array
    {
        $name = trim((string) ($contractor->brand_name ?: $contractor->name));
        if ($name === '') {
            $name = 'Contratante';
        }

        $initials = collect(explode(' ', $name))
            ->filter()
            ->take(2)
            ->map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        if ($initials === '') {
            $initials = 'CT';
        }

        $logoDataUri = $this->resolveImageDataUri((string) ($contractor->brand_logo_url ?? ''));
        if ($logoDataUri === null) {
            $logoDataUri = $this->resolveImageDataUri((string) ($contractor->brand_avatar_url ?? ''));
        }

        return [
            'name' => $name,
            'initials' => $initials,
            'logo_data_uri' => $logoDataUri,
        ];
    }

    private function resolveImageDataUri(string $reference): ?string
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        if (Str::startsWith($reference, 'data:image/')) {
            return $reference;
        }

        if (Str::startsWith($reference, ['http://', 'https://'])) {
            try {
                $response = Http::timeout(8)->get($reference);
                if (! $response->successful()) {
                    return null;
                }

                $contentType = strtolower(trim((string) $response->header('Content-Type', 'image/png')));
                $body = $response->body();
                if ($body === '') {
                    return null;
                }

                return "data:{$contentType};base64,".base64_encode($body);
            } catch (Throwable) {
                return null;
            }
        }

        $candidates = [
            public_path(ltrim($reference, '/')),
            storage_path('app/public/'.ltrim($reference, '/')),
            storage_path('app/'.ltrim($reference, '/')),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '' || ! is_file($candidate)) {
                continue;
            }

            $bytes = @file_get_contents($candidate);
            if ($bytes === false || $bytes === '') {
                continue;
            }

            $mime = mime_content_type($candidate) ?: 'image/png';

            return "data:{$mime};base64,".base64_encode($bytes);
        }

        return null;
    }

    private function formatCurrency(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

    private function applySaleDateConstraint(mixed $query, CarbonImmutable $startUtc, CarbonImmutable $endUtc): void
    {
        $query
            ->whereBetween('completed_at', [$startUtc, $endUtc])
            ->orWhere(static function ($fallback) use ($startUtc, $endUtc): void {
                $fallback
                    ->whereNull('completed_at')
                    ->whereBetween('created_at', [$startUtc, $endUtc]);
            });
    }

    private function applyServiceOrderDateConstraint(mixed $query, CarbonImmutable $startUtc, CarbonImmutable $endUtc): void
    {
        $query
            ->whereBetween('finished_at', [$startUtc, $endUtc])
            ->orWhere(static function ($fallback) use ($startUtc, $endUtc): void {
                $fallback
                    ->whereNull('finished_at')
                    ->whereBetween('updated_at', [$startUtc, $endUtc]);
            });
    }
}
