<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Contractor;
use App\Models\FinancialEntry;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    use ResolvesCurrentContractor;

    /**
     * @var list<string>
     */
    private const TABS = ['payables', 'receivables'];

    /**
     * @var list<string>
     */
    private const STATUS_FILTERS = ['pending', 'overdue', 'paid', 'cancelled'];

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $tab = $this->resolveTab($request);
        $search = trim((string) $request->string('search')->toString());
        $status = strtolower(trim((string) $request->string('status')->toString()));
        if (! in_array($status, self::STATUS_FILTERS, true)) {
            $status = '';
        }

        $entryType = $tab === 'receivables'
            ? FinancialEntry::TYPE_RECEIVABLE
            : FinancialEntry::TYPE_PAYABLE;

        $entriesQuery = FinancialEntry::query()
            ->where('contractor_id', $contractor->id)
            ->where('type', $entryType)
            ->with('paymentMethod:id,name')
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [FinancialEntry::STATUS_PENDING])
            ->orderBy('due_date')
            ->orderByDesc('id');

        if ($search !== '') {
            $entriesQuery->where(function (Builder $query) use ($search): void {
                $query->where('counterparty_name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $this->applyStatusFilter($entriesQuery, $status);

        $financeEntries = $entriesQuery
            ->paginate(20)
            ->withQueryString()
            ->through(fn (FinancialEntry $entry): array => $this->toFinancialEntryPayload($entry));

        return Inertia::render('Admin/Finance/Index', [
            'initialTab' => $tab,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => [
                ['value' => 'pending', 'label' => 'Em aberto'],
                ['value' => 'overdue', 'label' => 'Vencido'],
                ['value' => 'paid', 'label' => 'Liquidado'],
                ['value' => 'cancelled', 'label' => 'Cancelado'],
            ],
            'financeEntries' => $financeEntries,
            'financeStats' => [
                'payables' => $this->buildStatsForType($contractor->id, FinancialEntry::TYPE_PAYABLE),
                'receivables' => $this->buildStatsForType($contractor->id, FinancialEntry::TYPE_RECEIVABLE),
            ],
            'paymentConfig' => $this->resolvePaymentConfig($contractor),
        ]);
    }

    public function payments(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        return Inertia::render('Admin/Finance/Payments', [
            'paymentConfig' => $this->resolvePaymentConfig($contractor),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePaymentConfig(Contractor $contractor): array
    {
        $gateways = PaymentGateway::query()
            ->where('contractor_id', $contractor->id)
            ->withCount('paymentMethods')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(static function (PaymentGateway $gateway): array {
                $credentials = is_array($gateway->credentials) ? $gateway->credentials : [];

                return [
                    'id' => $gateway->id,
                    'provider' => $gateway->provider,
                    'name' => $gateway->name,
                    'is_active' => (bool) $gateway->is_active,
                    'is_default' => (bool) $gateway->is_default,
                    'is_sandbox' => (bool) $gateway->is_sandbox,
                    'credentials_status' => [
                        'access_token_configured' => trim((string) ($credentials['access_token'] ?? '')) !== '',
                        'webhook_secret_configured' => trim((string) ($credentials['webhook_secret'] ?? '')) !== '',
                    ],
                    'methods_count' => (int) $gateway->payment_methods_count,
                    'last_health_check_at' => optional($gateway->last_health_check_at)?->format('d/m/Y H:i'),
                ];
            })
            ->values()
            ->all();

        $methods = PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->with('paymentGateway:id,name,provider')
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static function (PaymentMethod $method): array {
                return [
                    'id' => $method->id,
                    'code' => $method->code,
                    'name' => $method->name,
                    'payment_gateway_id' => $method->payment_gateway_id,
                    'payment_gateway_name' => $method->paymentGateway?->name,
                    'payment_gateway_provider' => $method->paymentGateway?->provider,
                    'is_active' => (bool) $method->is_active,
                    'is_default' => (bool) $method->is_default,
                    'allows_installments' => (bool) $method->allows_installments,
                    'max_installments' => $method->max_installments,
                    'fee_fixed' => $method->fee_fixed !== null ? (float) $method->fee_fixed : null,
                    'fee_percent' => $method->fee_percent !== null ? (float) $method->fee_percent : null,
                    'sort_order' => (int) $method->sort_order,
                ];
            })
            ->values()
            ->all();

        $activeGateways = PaymentGateway::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $activeMethods = PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $defaultGateway = PaymentGateway::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_default', true)
            ->first();

        $defaultMethod = PaymentMethod::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_default', true)
            ->first();

        return [
            'gateways' => $gateways,
            'methods' => $methods,
            'provider_options' => [
                ['value' => PaymentGateway::PROVIDER_MANUAL, 'label' => 'Operação manual'],
                ['value' => PaymentGateway::PROVIDER_MERCADO_PAGO, 'label' => 'Mercado Pago'],
            ],
            'stats' => [
                'gateways_total' => count($gateways),
                'gateways_active' => $activeGateways,
                'methods_total' => count($methods),
                'methods_active' => $activeMethods,
                'default_gateway' => $defaultGateway?->name,
                'default_method' => $defaultMethod?->name,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildStatsForType(int $contractorId, string $type): array
    {
        $today = today();
        $nextWeek = $today->copy()->addDays(7);
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $baseQuery = FinancialEntry::query()
            ->where('contractor_id', $contractorId)
            ->where('type', $type);

        $openNextSeven = (float) (clone $baseQuery)
            ->where('status', FinancialEntry::STATUS_PENDING)
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $nextWeek)
            ->sum('amount');

        $overdueAmount = (float) (clone $baseQuery)
            ->where('status', FinancialEntry::STATUS_PENDING)
            ->whereDate('due_date', '<', $today)
            ->sum('amount');

        $paidMonth = (float) (clone $baseQuery)
            ->where('status', FinancialEntry::STATUS_PAID)
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $openTotal = (float) (clone $baseQuery)
            ->where('status', FinancialEntry::STATUS_PENDING)
            ->sum('amount');

        if ($type === FinancialEntry::TYPE_RECEIVABLE) {
            $defaultRate = $openTotal > 0
                ? round(($overdueAmount / $openTotal) * 100, 1)
                : 0.0;

            return [
                'next_7' => $this->asCurrency($openNextSeven),
                'late' => $this->asCurrency($overdueAmount),
                'received' => $this->asCurrency($paidMonth),
                'default_rate' => number_format($defaultRate, 1, ',', '.').'%',
            ];
        }

        return [
            'next_7' => $this->asCurrency($openNextSeven),
            'late' => $this->asCurrency($overdueAmount),
            'paid' => $this->asCurrency($paidMonth),
            'projection' => $this->asCurrency($openTotal),
        ];
    }

    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === '') {
            return;
        }

        if ($status === 'overdue') {
            $query
                ->where('status', FinancialEntry::STATUS_PENDING)
                ->whereDate('due_date', '<', today());

            return;
        }

        if (in_array($status, [FinancialEntry::STATUS_PENDING, FinancialEntry::STATUS_PAID, FinancialEntry::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toFinancialEntryPayload(FinancialEntry $entry): array
    {
        $statusKey = $this->resolveEntryStatusKey($entry);

        return [
            'id' => (int) $entry->id,
            'type' => (string) $entry->type,
            'primary' => (string) $entry->counterparty_name,
            'counterparty_name' => (string) $entry->counterparty_name,
            'reference' => trim((string) ($entry->reference ?? '')),
            'due' => optional($entry->due_date)?->format('d/m/Y'),
            'due_date_raw' => optional($entry->due_date)?->format('Y-m-d'),
            'issue_date_raw' => optional($entry->issue_date)?->format('Y-m-d'),
            'value' => $this->asCurrency((float) $entry->amount),
            'amount_raw' => (float) $entry->amount,
            'status' => $this->entryStatusLabel($statusKey),
            'status_key' => $statusKey,
            'status_tone' => $this->entryStatusTone($statusKey),
            'notes' => trim((string) ($entry->notes ?? '')),
            'payment_method_id' => $entry->payment_method_id,
            'payment_method_name' => $entry->paymentMethod?->name,
            'paid_at_raw' => optional($entry->paid_at)?->format('Y-m-d H:i:s'),
            'document_url' => $entry->document_path !== null
                ? Storage::disk('public')->url($entry->document_path)
                : null,
            'document_name' => $entry->document_original_name,
            'can_edit' => true,
            'can_delete' => $entry->status !== FinancialEntry::STATUS_PAID,
        ];
    }

    private function resolveEntryStatusKey(FinancialEntry $entry): string
    {
        if ($entry->status === FinancialEntry::STATUS_PENDING && $entry->due_date?->lt(today())) {
            return 'overdue';
        }

        return (string) $entry->status;
    }

    private function entryStatusLabel(string $status): string
    {
        return match ($status) {
            'overdue' => 'Vencido',
            FinancialEntry::STATUS_PENDING => 'Em aberto',
            FinancialEntry::STATUS_PAID => 'Liquidado',
            FinancialEntry::STATUS_CANCELLED => 'Cancelado',
            default => ucfirst($status),
        };
    }

    private function entryStatusTone(string $status): string
    {
        return match ($status) {
            'overdue' => 'bg-rose-100 text-rose-700',
            FinancialEntry::STATUS_PENDING => 'bg-amber-100 text-amber-700',
            FinancialEntry::STATUS_PAID => 'bg-emerald-100 text-emerald-700',
            FinancialEntry::STATUS_CANCELLED => 'bg-slate-200 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    private function resolveTab(Request $request): string
    {
        $tab = trim((string) $request->string('tab')->toString());

        return in_array($tab, self::TABS, true)
            ? $tab
            : 'payables';
    }

    private function asCurrency(float $value): string
    {
        return 'R$ '.number_format($value, 2, ',', '.');
    }

}


