<?php

namespace App\Application\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\ServiceCatalog;
use App\Models\ServiceOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceOrderService
{
    use ResolvesCurrentContractor;

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());

        $query = ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->with([
                'client:id,name',
                'service:id,name',
            ]);

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('client', static function ($clientQuery) use ($search): void {
                        $clientQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $orders = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(static fn (ServiceOrder $order): array => [
                'id' => (int) $order->id,
                'code' => (string) $order->code,
                'title' => (string) $order->title,
                'description' => $order->description ? (string) $order->description : null,
                'client_id' => $order->client_id ? (int) $order->client_id : null,
                'client_name' => $order->client?->name ? (string) $order->client->name : 'Não informado',
                'service_catalog_id' => $order->service_catalog_id ? (int) $order->service_catalog_id : null,
                'service_name' => $order->service?->name ? (string) $order->service->name : '-',
                'scheduled_for' => optional($order->scheduled_for)?->format('Y-m-d\TH:i'),
                'due_at' => optional($order->due_at)?->format('Y-m-d\TH:i'),
                'status' => (string) $order->status,
                'priority' => (string) $order->priority,
                'assigned_to_name' => $order->assigned_to_name ? (string) $order->assigned_to_name : '',
                'estimated_amount' => (float) $order->estimated_amount,
                'final_amount' => (float) $order->final_amount,
                'created_at' => optional($order->created_at)?->format('d/m/Y H:i'),
            ]);

        $stats = [
            'open' => ServiceOrder::query()
                ->where('contractor_id', $contractor->id)
                ->where('status', ServiceOrder::STATUS_OPEN)
                ->count(),
            'in_progress' => ServiceOrder::query()
                ->where('contractor_id', $contractor->id)
                ->where('status', ServiceOrder::STATUS_IN_PROGRESS)
                ->count(),
            'done_month' => ServiceOrder::query()
                ->where('contractor_id', $contractor->id)
                ->where('status', ServiceOrder::STATUS_DONE)
                ->whereBetween('finished_at', [now($contractor->timezone)->startOfMonth(), now($contractor->timezone)->endOfMonth()])
                ->count(),
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

        return Inertia::render('Admin/Services/Orders', [
            'orders' => $orders,
            'clients' => $clients,
            'services' => $services,
            'stats' => $stats,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'statusOptions' => $this->statusOptions(),
            'priorityOptions' => $this->priorityOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $this->validatePayload($request, $contractor);
        $data['contractor_id'] = $contractor->id;
        $data['code'] = trim((string) ($data['code'] ?? '')) !== ''
            ? trim((string) $data['code'])
            : $this->nextOrderCode($contractor);

        ServiceOrder::query()->create($data);

        return back()->with('status', 'Ordem de serviço criada com sucesso.');
    }

    public function update(Request $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $serviceOrder = $this->resolveOwnedServiceOrder($contractor, $serviceOrder);

        $data = $this->validatePayload($request, $contractor, $serviceOrder);
        if (! empty($data['code'])) {
            $data['code'] = trim((string) $data['code']);
        }

        if (($data['status'] ?? $serviceOrder->status) === ServiceOrder::STATUS_DONE && ! $serviceOrder->finished_at) {
            $data['finished_at'] = now($contractor->timezone);
        }

        $serviceOrder->fill($data)->save();

        return back()->with('status', 'Ordem de serviço atualizada com sucesso.');
    }

    public function destroy(Request $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $serviceOrder = $this->resolveOwnedServiceOrder($contractor, $serviceOrder);
        $serviceOrder->delete();

        return back()->with('status', 'Ordem de serviço removida com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, Contractor $contractor, ?ServiceOrder $serviceOrder = null): array
    {
        return $request->validate([
            'code' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('service_orders', 'code')
                    ->where(static fn ($query) => $query->where('contractor_id', $contractor->id))
                    ->ignore($serviceOrder?->id),
            ],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'service_catalog_id' => [
                'nullable',
                'integer',
                Rule::exists('service_catalogs', 'id')->where(static fn ($query) => $query->where('contractor_id', $contractor->id)),
            ],
            'scheduled_for' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(array_column($this->statusOptions(), 'value'))],
            'priority' => ['required', Rule::in(array_column($this->priorityOptions(), 'value'))],
            'assigned_to_name' => ['nullable', 'string', 'max:120'],
            'estimated_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'final_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ]);
    }

    private function resolveOwnedServiceOrder(Contractor $contractor, ServiceOrder $serviceOrder): ServiceOrder
    {
        abort_unless((int) $serviceOrder->contractor_id === (int) $contractor->id, 404);

        return $serviceOrder;
    }

    private function nextOrderCode(Contractor $contractor): string
    {
        $prefix = now($contractor->timezone)->format('Ymd');
        $count = ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->whereDate('created_at', now($contractor->timezone)->toDateString())
            ->count() + 1;

        return sprintf('OS-%s-%04d', $prefix, $count);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => ServiceOrder::STATUS_OPEN, 'label' => 'Triagem'],
            ['value' => ServiceOrder::STATUS_IN_PROGRESS, 'label' => 'Em execução'],
            ['value' => ServiceOrder::STATUS_WAITING, 'label' => 'Aguardando'],
            ['value' => ServiceOrder::STATUS_DONE, 'label' => 'Finalizada'],
            ['value' => ServiceOrder::STATUS_CANCELLED, 'label' => 'Cancelada'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function priorityOptions(): array
    {
        return [
            ['value' => 'low', 'label' => 'Baixa'],
            ['value' => 'normal', 'label' => 'Normal'],
            ['value' => 'high', 'label' => 'Alta'],
        ];
    }
}
