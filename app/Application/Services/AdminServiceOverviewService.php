<?php

namespace App\Application\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalog;
use App\Models\ServiceOrder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminServiceOverviewService
{
    use ResolvesCurrentContractor;

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $todayStart = now($contractor->timezone)->startOfDay();
        $todayEnd = now($contractor->timezone)->endOfDay();

        $serviceCount = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $openOrderCount = ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [
                ServiceOrder::STATUS_OPEN,
                ServiceOrder::STATUS_IN_PROGRESS,
                ServiceOrder::STATUS_WAITING,
            ])
            ->count();

        $todayAppointmentsCount = ServiceAppointment::query()
            ->where('contractor_id', $contractor->id)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->count();

        $monthRevenue = ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->where('status', ServiceOrder::STATUS_DONE)
            ->whereBetween('finished_at', [now($contractor->timezone)->startOfMonth(), now($contractor->timezone)->endOfMonth()])
            ->sum('final_amount');

        $pipeline = [
            [
                'key' => 'open',
                'label' => 'Triagem',
                'qty' => ServiceOrder::query()
                    ->where('contractor_id', $contractor->id)
                    ->where('status', ServiceOrder::STATUS_OPEN)
                    ->count(),
            ],
            [
                'key' => 'in_progress',
                'label' => 'Em execução',
                'qty' => ServiceOrder::query()
                    ->where('contractor_id', $contractor->id)
                    ->where('status', ServiceOrder::STATUS_IN_PROGRESS)
                    ->count(),
            ],
            [
                'key' => 'waiting',
                'label' => 'Aguardando',
                'qty' => ServiceOrder::query()
                    ->where('contractor_id', $contractor->id)
                    ->where('status', ServiceOrder::STATUS_WAITING)
                    ->count(),
            ],
            [
                'key' => 'done',
                'label' => 'Finalizadas',
                'qty' => ServiceOrder::query()
                    ->where('contractor_id', $contractor->id)
                    ->where('status', ServiceOrder::STATUS_DONE)
                    ->count(),
            ],
        ];

        $todayAppointments = ServiceAppointment::query()
            ->where('contractor_id', $contractor->id)
            ->whereBetween('starts_at', [$todayStart, $todayEnd])
            ->with([
                'client:id,name',
                'service:id,name',
                'serviceOrder:id,code,assigned_to_name',
            ])
            ->orderBy('starts_at')
            ->limit(20)
            ->get()
            ->map(static function (ServiceAppointment $appointment): array {
                return [
                    'id' => (int) $appointment->id,
                    'service_order_code' => $appointment->serviceOrder?->code ? (string) $appointment->serviceOrder->code : '-',
                    'customer' => $appointment->client?->name ? (string) $appointment->client->name : 'Não informado',
                    'service' => $appointment->service?->name ? (string) $appointment->service->name : (string) $appointment->title,
                    'time' => optional($appointment->starts_at)?->format('H:i'),
                    'technician' => $appointment->serviceOrder?->assigned_to_name
                        ? (string) $appointment->serviceOrder->assigned_to_name
                        : '-',
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Admin/Services/Index', [
            'stats' => [
                'services' => (int) $serviceCount,
                'open' => (int) $openOrderCount,
                'today' => (int) $todayAppointmentsCount,
                'revenue' => round((float) $monthRevenue, 2),
            ],
            'pipeline' => $pipeline,
            'todayAppointments' => $todayAppointments,
        ]);
    }
}
