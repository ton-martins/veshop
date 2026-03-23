<?php

namespace App\Application\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalog;
use App\Models\ServiceOrder;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceScheduleService
{
    use ResolvesCurrentContractor;

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());
        $layout = $this->normalizeLayout((string) $request->string('layout')->toString());
        $timezone = trim((string) ($contractor->timezone ?: config('app.timezone', 'America/Sao_Paulo')));
        if ($timezone === '') {
            $timezone = (string) config('app.timezone', 'America/Sao_Paulo');
        }

        $referenceDate = trim((string) $request->string('reference_date')->toString());
        $reference = $this->resolveReferenceDate($referenceDate, $timezone);
        [$periodStart, $periodEnd] = $this->resolvePeriodRange($layout, $reference);

        $query = ServiceAppointment::query()
            ->where('contractor_id', $contractor->id)
            ->with([
                'client:id,name',
                'service:id,name',
                'serviceOrder:id,code,assigned_to_name',
            ]);

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhereHas('client', static function ($clientQuery) use ($search): void {
                        $clientQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('serviceOrder', static function ($orderQuery) use ($search): void {
                        $orderQuery->where('code', 'like', "%{$search}%")
                            ->orWhere('assigned_to_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $query->whereBetween('starts_at', [$periodStart, $periodEnd]);

        $appointments = $query
            ->orderBy('starts_at')
            ->paginate(500)
            ->withQueryString()
            ->through(static fn (ServiceAppointment $appointment): array => [
                'id' => (int) $appointment->id,
                'title' => (string) $appointment->title,
                'service_order_id' => $appointment->service_order_id ? (int) $appointment->service_order_id : null,
                'service_order_code' => $appointment->serviceOrder?->code ? (string) $appointment->serviceOrder->code : '',
                'client_id' => $appointment->client_id ? (int) $appointment->client_id : null,
                'client_name' => $appointment->client?->name ? (string) $appointment->client->name : 'Não informado',
                'service_catalog_id' => $appointment->service_catalog_id ? (int) $appointment->service_catalog_id : null,
                'service_name' => $appointment->service?->name ? (string) $appointment->service->name : '',
                'starts_at' => optional($appointment->starts_at)?->format('Y-m-d\\TH:i'),
                'ends_at' => optional($appointment->ends_at)?->format('Y-m-d\\TH:i'),
                'time_label' => sprintf(
                    '%s - %s',
                    optional($appointment->starts_at)?->format('H:i'),
                    optional($appointment->ends_at)?->format('H:i')
                ),
                'status' => (string) $appointment->status,
                'location' => $appointment->location ? (string) $appointment->location : '',
                'notes' => $appointment->notes ? (string) $appointment->notes : '',
                'technician' => $appointment->serviceOrder?->assigned_to_name ? (string) $appointment->serviceOrder->assigned_to_name : '-',
            ]);

        $now = now($timezone);
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $next24h = $now->copy()->addDay();

        $stats = [
            'today' => ServiceAppointment::query()
                ->where('contractor_id', $contractor->id)
                ->whereBetween('starts_at', [$todayStart, $todayEnd])
                ->count(),
            'next_24h' => ServiceAppointment::query()
                ->where('contractor_id', $contractor->id)
                ->whereBetween('starts_at', [$now, $next24h])
                ->count(),
            'teams' => ServiceOrder::query()
                ->where('contractor_id', $contractor->id)
                ->whereNotNull('assigned_to_name')
                ->distinct()
                ->count('assigned_to_name'),
        ];

        $clients = Client::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Client $client): array => [
                'id' => (int) $client->id,
                'name' => (string) $client->name,
            ])
            ->values()
            ->all();

        $services = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (ServiceCatalog $service): array => [
                'id' => (int) $service->id,
                'name' => (string) $service->name,
            ])
            ->values()
            ->all();

        $orders = ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [
                ServiceOrder::STATUS_OPEN,
                ServiceOrder::STATUS_IN_PROGRESS,
                ServiceOrder::STATUS_WAITING,
            ])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'code', 'title'])
            ->map(static fn (ServiceOrder $order): array => [
                'id' => (int) $order->id,
                'label' => trim(sprintf('%s - %s', $order->code, $order->title)),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Services/Schedule', [
            'appointments' => $appointments,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'layout' => $layout,
                'reference_date' => $reference->toDateString(),
            ],
            'period' => [
                'starts_at' => $periodStart->toIso8601String(),
                'ends_at' => $periodEnd->toIso8601String(),
            ],
            'timezone' => $timezone,
            'clients' => $clients,
            'services' => $services,
            'orders' => $orders,
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $this->validatePayload($request, $contractor, null, true);
        $data['contractor_id'] = $contractor->id;

        ServiceAppointment::query()->create($data);

        return back()->with('status', 'Compromisso criado com sucesso.');
    }

    public function update(Request $request, ServiceAppointment $serviceAppointment): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $serviceAppointment = $this->resolveOwnedAppointment($contractor, $serviceAppointment);
        $serviceAppointment->fill($this->validatePayload($request, $contractor, $serviceAppointment))->save();

        return back()->with('status', 'Compromisso atualizado com sucesso.');
    }

    public function destroy(Request $request, ServiceAppointment $serviceAppointment): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $serviceAppointment = $this->resolveOwnedAppointment($contractor, $serviceAppointment);
        $serviceAppointment->delete();

        return back()->with('status', 'Compromisso removido com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(
        Request $request,
        Contractor $contractor,
        ?ServiceAppointment $currentAppointment = null,
        bool $enforceFutureStart = false,
    ): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'service_order_id' => [
                'nullable',
                'integer',
                Rule::exists('service_orders', 'id')->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'service_catalog_id' => [
                'required',
                'integer',
                Rule::exists('service_catalogs', 'id')->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'location' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $timezone = trim((string) ($contractor->timezone ?: config('app.timezone', 'America/Sao_Paulo')));
        if ($timezone === '') {
            $timezone = (string) config('app.timezone', 'America/Sao_Paulo');
        }

        $startsAt = Carbon::parse((string) $data['starts_at'], $timezone);
        $now = now($timezone);

        $currentStartsAt = $currentAppointment?->starts_at
            ? Carbon::parse((string) $currentAppointment->starts_at, $timezone)
            : null;
        $isSameAsCurrent = $currentStartsAt !== null && $startsAt->equalTo($currentStartsAt);

        if ($startsAt->lessThan($now) && ($enforceFutureStart || ! $isSameAsCurrent)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Informe uma data e hora atual ou futura para o agendamento.',
            ]);
        }

        return $data;
    }

    private function resolveOwnedAppointment(Contractor $contractor, ServiceAppointment $serviceAppointment): ServiceAppointment
    {
        abort_unless((int) $serviceAppointment->contractor_id === (int) $contractor->id, 404);

        return $serviceAppointment;
    }

    private function normalizeLayout(string $layout): string
    {
        return in_array($layout, ['day', 'week', 'month'], true) ? $layout : 'month';
    }

    private function resolveReferenceDate(string $referenceDate, string $timezone): Carbon
    {
        if ($referenceDate !== '') {
            try {
                return Carbon::parse($referenceDate, $timezone)->startOfDay();
            } catch (\Throwable) {
                // fallback para hoje no timezone do contratante
            }
        }

        return now($timezone)->startOfDay();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriodRange(string $layout, Carbon $reference): array
    {
        if ($layout === 'day') {
            return [
                $reference->copy()->startOfDay(),
                $reference->copy()->endOfDay(),
            ];
        }

        if ($layout === 'week') {
            return [
                $reference->copy()->startOfWeek(Carbon::MONDAY)->startOfDay(),
                $reference->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay(),
            ];
        }

        return [
            $reference->copy()->startOfMonth()->startOfDay(),
            $reference->copy()->endOfMonth()->endOfDay(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => ServiceAppointment::STATUS_SCHEDULED, 'label' => 'Agendado'],
            ['value' => ServiceAppointment::STATUS_CONFIRMED, 'label' => 'Confirmado'],
            ['value' => ServiceAppointment::STATUS_IN_SERVICE, 'label' => 'Em atendimento'],
            ['value' => ServiceAppointment::STATUS_DONE, 'label' => 'Concluído'],
            ['value' => ServiceAppointment::STATUS_CANCELLED, 'label' => 'Cancelado'],
            ['value' => ServiceAppointment::STATUS_NO_SHOW, 'label' => 'Não compareceu'],
        ];
    }
}
