<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalog;
use App\Models\ServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceScheduleController extends Controller
{
    use ResolvesCurrentContractor;

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());
        $date = trim((string) $request->string('date')->toString());
        $dateFilter = $date !== '' ? $date : now($contractor->timezone)->toDateString();

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

        if ($dateFilter !== '') {
            $query->whereDate('starts_at', $dateFilter);
        }

        $appointments = $query
            ->orderBy('starts_at')
            ->paginate(20)
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
                'starts_at' => optional($appointment->starts_at)?->format('Y-m-d\TH:i'),
                'ends_at' => optional($appointment->ends_at)?->format('Y-m-d\TH:i'),
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

        $todayStart = now($contractor->timezone)->startOfDay();
        $todayEnd = now($contractor->timezone)->endOfDay();
        $next24h = now($contractor->timezone)->addDay();

        $stats = [
            'today' => ServiceAppointment::query()
                ->where('contractor_id', $contractor->id)
                ->whereBetween('starts_at', [$todayStart, $todayEnd])
                ->count(),
            'next_24h' => ServiceAppointment::query()
                ->where('contractor_id', $contractor->id)
                ->whereBetween('starts_at', [now($contractor->timezone), $next24h])
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
                'date' => $dateFilter,
            ],
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

        $data = $this->validatePayload($request, $contractor);
        $data['contractor_id'] = $contractor->id;

        ServiceAppointment::query()->create($data);

        return back()->with('status', 'Compromisso criado com sucesso.');
    }

    public function update(Request $request, ServiceAppointment $serviceAppointment): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $serviceAppointment = $this->resolveOwnedAppointment($contractor, $serviceAppointment);
        $serviceAppointment->fill($this->validatePayload($request, $contractor))->save();

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
    private function validatePayload(Request $request, Contractor $contractor): array
    {
        return $request->validate([
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
    }


    private function resolveOwnedAppointment(Contractor $contractor, ServiceAppointment $serviceAppointment): ServiceAppointment
    {
        abort_unless((int) $serviceAppointment->contractor_id === (int) $contractor->id, 404);

        return $serviceAppointment;
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


