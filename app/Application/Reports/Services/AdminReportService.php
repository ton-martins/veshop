<?php

namespace App\Application\Reports\Services;

use App\Application\Reports\Support\ReportPeriod;
use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Jobs\GenerateReportExportJob;
use App\Jobs\GenerateSalesExportJob;
use App\Models\Contractor;
use App\Models\Module;
use App\Models\ReportExport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AdminReportService
{
    use ResolvesCurrentContractor;

    /**
     * @var array<int, array{code: string, label: string, description: string, requires: list<string>}>
     */
    private const EXPORT_MODULES = [
        [
            'code' => 'commercial',
            'label' => 'Comercial',
            'description' => 'Pedidos e faturamento do fluxo comercial.',
            'requires' => ['commercial'],
        ],
        [
            'code' => 'pdv',
            'label' => 'PDV',
            'description' => 'Vendas e desempenho do ponto de venda.',
            'requires' => ['pdv'],
        ],
        [
            'code' => 'orders',
            'label' => 'Pedidos',
            'description' => 'Pedidos da loja virtual e do catálogo.',
            'requires' => ['orders', 'commercial'],
        ],
        [
            'code' => 'finance',
            'label' => 'Financeiro',
            'description' => 'Contas a pagar, a receber e status de pagamentos.',
            'requires' => ['finance'],
        ],
        [
            'code' => 'catalog',
            'label' => 'Catálogo de produtos',
            'description' => 'Portfólio de produtos, preços e estoque.',
            'requires' => ['catalog'],
        ],
        [
            'code' => 'services',
            'label' => 'Ordens de serviço',
            'description' => 'Ordens, receitas e status operacionais.',
            'requires' => ['services', 'service_orders'],
        ],
        [
            'code' => 'schedule',
            'label' => 'Agenda',
            'description' => 'Atendimentos e agendamentos por período.',
            'requires' => ['schedule'],
        ],
        [
            'code' => 'services_catalog',
            'label' => 'Catálogo de serviços',
            'description' => 'Serviços ativos, duração e preço base.',
            'requires' => ['services_catalog', 'services_storefront'],
        ],
    ];

    public function __construct(
        private readonly ReportAnalyticsService $analyticsService,
        private readonly ReportProfileResolver $profileResolver,
    ) {}

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $timezone = (string) ($contractor->timezone ?: config('app.timezone', 'UTC'));
        $period = ReportPeriod::fromRequest($request, $timezone);
        $profile = $this->profileResolver->resolveOverviewProfile($contractor);
        $overview = $this->analyticsService->buildOverview($contractor, $period, $profile);
        $stats = $this->resolveLegacyStats($overview['metric_cards'] ?? []);

        $exportModules = $this->resolveExportModules($contractor);
        $defaultModuleCodes = $this->resolveDefaultModuleCodes($contractor, $exportModules);
        /** @var User|null $user */
        $user = $request->user();
        $canManageExportFiles = $user instanceof User && $user->isAdmin();

        $exportsHistory = ReportExport::query()
            ->where('contractor_id', $contractor->id)
            ->with('requestedBy:id,name')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (ReportExport $item): array => [
                'id' => (int) $item->id,
                'file' => $this->resolveExportFilename($item),
                'format' => $this->resolveExportFormat($item),
                'is_pdf' => $this->resolveExportFormat($item) === ReportExport::FORMAT_PDF,
                'status' => $this->statusLabel((string) $item->status),
                'status_tone' => $this->statusTone((string) $item->status),
                'status_value' => (string) $item->status,
                'by' => (string) ($item->requestedBy?->name ?? 'Sistema'),
                'when' => optional($item->created_at)?->format('d/m/Y H:i'),
                'rows' => $item->row_count,
                'error' => $item->error_message,
                'download_url' => $canManageExportFiles && $item->status === ReportExport::STATUS_COMPLETED
                    ? route('admin.reports.exports.download', ['reportExport' => $item->id], false)
                    : null,
                'preview_url' => $canManageExportFiles && $item->status === ReportExport::STATUS_COMPLETED
                    ? route('admin.reports.exports.download', ['reportExport' => $item->id, 'inline' => 1], false)
                    : null,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Reports/Index', [
            'stats' => $stats,
            'metricCards' => $overview['metric_cards'] ?? [],
            'topItems' => $overview['top_items'] ?? ['title' => '', 'description' => '', 'kind' => '', 'items' => []],
            'filters' => array_merge($period->toArray(), [
                'period_options' => ReportPeriod::options(),
            ]),
            'reportContext' => $this->reportContext($contractor, $timezone),
            'exportsHistory' => $exportsHistory,
            'exportModules' => $exportModules,
            'exportDefaults' => [
                'format' => ReportExport::FORMAT_PDF,
                'include_details' => true,
                'module_codes' => $defaultModuleCodes,
                'date_from' => $period->startDate(),
                'date_to' => $period->endDate(),
                'custom_file_name' => '',
            ],
        ]);
    }

    public function export(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'format' => ['required', 'string', Rule::in(ReportExport::availableFormats())],
            'module_codes' => ['nullable', 'array'],
            'module_codes.*' => ['string', 'max:80'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'include_details' => ['nullable', 'boolean'],
            'custom_file_name' => ['nullable', 'string', 'max:120'],
        ]);

        /** @var User|null $user */
        $user = $request->user();

        $queueConnection = (string) config('queue.workloads.exports.connection', config('queue.default'));
        $queueName = (string) config('queue.workloads.exports.queue', 'exports');

        $availableModuleCodes = collect($this->resolveExportModules($contractor))
            ->pluck('code')
            ->map(static fn (mixed $code): string => strtolower(trim((string) $code)))
            ->values();

        $requestedModuleCodes = collect(is_array($validated['module_codes'] ?? null) ? $validated['module_codes'] : [])
            ->map(static fn (mixed $code): string => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->filter(fn (string $code): bool => $availableModuleCodes->contains($code))
            ->values()
            ->all();

        if ($requestedModuleCodes === []) {
            $requestedModuleCodes = $this->resolveDefaultModuleCodes($contractor, $this->resolveExportModules($contractor));
        }

        $customFileName = $this->sanitizeCustomFileName($validated['custom_file_name'] ?? null);

        $export = ReportExport::query()->create([
            'contractor_id' => $contractor->id,
            'requested_by_user_id' => $user?->id,
            'type' => ReportExport::TYPE_DASHBOARD,
            'status' => ReportExport::STATUS_PENDING,
            'queue_connection' => $queueConnection,
            'queue_name' => $queueName,
            'filters' => [
                'format' => strtolower((string) $validated['format']),
                'module_codes' => $requestedModuleCodes,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'include_details' => (bool) ($validated['include_details'] ?? true),
                'custom_file_name' => $customFileName,
            ],
        ]);

        GenerateReportExportJob::dispatch((int) $export->id)
            ->onConnection($queueConnection)
            ->onQueue($queueName);

        return back()->with('status', 'Exportação enfileirada. O processamento seguirá em segundo plano.');
    }

    public function exportSales(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $queueConnection = (string) config('queue.workloads.exports.connection', config('queue.default'));
        $queueName = (string) config('queue.workloads.exports.queue', 'exports');

        /** @var User|null $user */
        $user = $request->user();

        $export = ReportExport::query()->create([
            'contractor_id' => $contractor->id,
            'requested_by_user_id' => $user?->id,
            'type' => ReportExport::TYPE_SALES,
            'status' => ReportExport::STATUS_PENDING,
            'queue_connection' => $queueConnection,
            'queue_name' => $queueName,
            'filters' => [
                'month' => now()->format('Y-m'),
                'format' => ReportExport::FORMAT_CSV,
            ],
        ]);

        GenerateSalesExportJob::dispatch((int) $export->id)
            ->onConnection($queueConnection)
            ->onQueue($queueName);

        return back()->with('status', 'Exportação enfileirada. O processamento seguirá em segundo plano.');
    }

    public function download(Request $request, ReportExport $reportExport): HttpResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);

        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        abort_unless((int) $reportExport->contractor_id === (int) $contractor->id, 404);
        abort_unless((string) $reportExport->status === ReportExport::STATUS_COMPLETED, 404);
        abort_unless($reportExport->file_disk && $reportExport->file_path, 404);
        abort_unless(Storage::disk($reportExport->file_disk)->exists($reportExport->file_path), 404);

        $filename = $reportExport->file_name ?? basename((string) $reportExport->file_path);
        $format = $this->resolveExportFormat($reportExport);
        $inline = $request->boolean('inline') && $format === ReportExport::FORMAT_PDF;

        if ($inline) {
            $content = Storage::disk($reportExport->file_disk)->get($reportExport->file_path);

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return Storage::disk($reportExport->file_disk)->download(
            $reportExport->file_path,
            $filename,
        );
    }

    private function resolveExportFilename(ReportExport $export): string
    {
        if ($export->file_name !== null && trim((string) $export->file_name) !== '') {
            return (string) $export->file_name;
        }

        $extension = match ($this->resolveExportFormat($export)) {
            ReportExport::FORMAT_PDF => 'pdf',
            ReportExport::FORMAT_EXCEL => 'xls',
            default => 'csv',
        };

        return strtoupper((string) $export->type)."-{$export->id}.{$extension}";
    }

    private function resolveExportFormat(ReportExport $export): string
    {
        $filters = is_array($export->filters) ? $export->filters : [];
        $format = strtolower(trim((string) ($filters['format'] ?? '')));

        if (in_array($format, ReportExport::availableFormats(), true)) {
            return $format;
        }

        $filename = strtolower((string) ($export->file_name ?? ''));

        if (str_ends_with($filename, '.pdf')) {
            return ReportExport::FORMAT_PDF;
        }

        if (str_ends_with($filename, '.xls') || str_ends_with($filename, '.xlsx')) {
            return ReportExport::FORMAT_EXCEL;
        }

        return ReportExport::FORMAT_CSV;
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

    private function statusLabel(string $status): string
    {
        return match ($status) {
            ReportExport::STATUS_PENDING => 'Em fila',
            ReportExport::STATUS_PROCESSING => 'Processando',
            ReportExport::STATUS_COMPLETED => 'Concluído',
            ReportExport::STATUS_FAILED => 'Falhou',
            default => ucfirst($status),
        };
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            ReportExport::STATUS_PENDING => 'bg-blue-100 text-blue-700',
            ReportExport::STATUS_PROCESSING => 'bg-amber-100 text-amber-700',
            ReportExport::STATUS_COMPLETED => 'bg-emerald-100 text-emerald-700',
            ReportExport::STATUS_FAILED => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $metricCards
     * @return array{revenue: float, orders: int, stock_turn: float, margin: float}
     */
    private function resolveLegacyStats(array $metricCards): array
    {
        $cardsByKey = collect($metricCards)->keyBy(static fn (array $card): string => (string) ($card['key'] ?? ''));

        $revenue = (float) (
            $cardsByKey->get('commercial_revenue')['value']
            ?? $cardsByKey->get('services_revenue')['value']
            ?? 0
        );

        $orders = (int) (
            $cardsByKey->get('commercial_orders')['value']
            ?? $cardsByKey->get('services_completed_orders')['value']
            ?? 0
        );

        return [
            'revenue' => $revenue,
            'orders' => $orders,
            'stock_turn' => 0.0,
            'margin' => 0.0,
        ];
    }

    /**
     * @return array<int, array{code: string, label: string, description: string}>
     */
    private function resolveExportModules(Contractor $contractor): array
    {
        $isServicesNiche = $contractor->niche() === Contractor::NICHE_SERVICES;
        $enabledModuleCodes = collect($contractor->enabledModules())
            ->map(static fn (mixed $code): string => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();

        if ($enabledModuleCodes->isEmpty()) {
            $enabledModuleCodes = collect($contractor->niche() === Contractor::NICHE_SERVICES ? ['services'] : ['commercial']);
        }

        $modulesByCode = Module::query()
            ->whereIn('code', $enabledModuleCodes->all())
            ->where('is_active', true)
            ->get(['code', 'name'])
            ->keyBy(static fn (Module $module): string => strtolower(trim((string) $module->code)));

        return collect(self::EXPORT_MODULES)
            ->reject(static function (array $item) use ($isServicesNiche): bool {
                return $isServicesNiche
                    && strtolower(trim((string) ($item['code'] ?? ''))) === 'pdv';
            })
            ->filter(function (array $item) use ($enabledModuleCodes): bool {
                $required = collect($item['requires'] ?? [])
                    ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
                    ->filter()
                    ->values();

                return $required->contains(fn (string $code): bool => $enabledModuleCodes->contains($code));
            })
            ->map(function (array $item) use ($modulesByCode): array {
                $baseCode = collect($item['requires'] ?? [])->first();
                $module = $baseCode ? $modulesByCode->get(strtolower(trim((string) $baseCode))) : null;

                return [
                    'code' => (string) $item['code'],
                    'label' => (string) ($module?->name ?: $item['label']),
                    'description' => (string) $item['description'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{code: string, label: string, description: string}>  $exportModules
     * @return array<int, string>
     */
    private function resolveDefaultModuleCodes(Contractor $contractor, array $exportModules): array
    {
        $available = collect($exportModules)
            ->pluck('code')
            ->map(static fn (mixed $code): string => strtolower(trim((string) $code)))
            ->values();

        $defaults = $contractor->niche() === Contractor::NICHE_SERVICES
            ? ['services', 'schedule', 'services_catalog', 'finance']
            : ['commercial', 'orders', 'catalog', 'finance'];

        $resolved = collect($defaults)
            ->filter(fn (string $code): bool => $available->contains($code))
            ->values()
            ->all();

        if ($resolved !== []) {
            return $resolved;
        }

        return $available->take(3)->values()->all();
    }

    /**
     * @return array<string, string>
     */
    private function reportContext(Contractor $contractor, string $timezone): array
    {
        $niche = $contractor->niche();
        $businessType = $contractor->businessType();

        return [
            'niche' => $niche,
            'niche_label' => $niche === Contractor::NICHE_SERVICES ? 'Serviços' : 'Comércio',
            'business_type' => $businessType,
            'business_type_label' => Contractor::labelForBusinessType($businessType),
            'plan_name' => $contractor->activePlanName(),
            'timezone' => $timezone,
        ];
    }
}
