<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Jobs\GenerateSalesExportJob;
use App\Models\Contractor;
use App\Models\ReportExport;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ResolvesCurrentContractor;

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Active contractor not found.');

        $timezone = (string) ($contractor->timezone ?: config('app.timezone', 'UTC'));
        $monthStart = now($timezone)->startOfMonth()->utc();
        $monthEnd = now($timezone)->endOfMonth()->utc();

        $revenue = (float) Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('status', [Sale::STATUS_PAID, Sale::STATUS_COMPLETED])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('total_amount');

        $orders = (int) Sale::query()
            ->where('contractor_id', $contractor->id)
            ->whereNotIn('status', [Sale::STATUS_DRAFT, Sale::STATUS_CANCELLED, Sale::STATUS_REJECTED, Sale::STATUS_REFUNDED])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $exportsHistory = ReportExport::query()
            ->where('contractor_id', $contractor->id)
            ->with('requestedBy:id,name')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (ReportExport $item): array => [
                'id' => (int) $item->id,
                'file' => (string) ($item->file_name ?: strtoupper($item->type)."-{$item->id}.csv"),
                'status' => $this->statusLabel((string) $item->status),
                'status_tone' => $this->statusTone((string) $item->status),
                'status_value' => (string) $item->status,
                'by' => (string) ($item->requestedBy?->name ?? 'System'),
                'when' => optional($item->created_at)?->format('d/m/Y H:i'),
                'rows' => $item->row_count,
                'error' => $item->error_message,
                'download_url' => $item->status === ReportExport::STATUS_COMPLETED
                    ? route('admin.reports.exports.download', ['reportExport' => $item->id])
                    : null,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Reports/Index', [
            'stats' => [
                'revenue' => $revenue,
                'orders' => $orders,
                'stock_turn' => 0,
                'margin' => 0,
            ],
            'exportsHistory' => $exportsHistory,
        ]);
    }

    public function exportSales(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Active contractor not found.');

        $queueConnection = (string) config('queue.workloads.exports.connection', config('queue.default'));
        $queueName = (string) config('queue.workloads.exports.queue', 'exports');

        /** @var \App\Models\User|null $user */
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
            ],
        ]);

        GenerateSalesExportJob::dispatch((int) $export->id)
            ->onConnection($queueConnection)
            ->onQueue($queueName);

        return back()->with('status', 'Export queued. Processing in background.');
    }

    public function download(Request $request, ReportExport $reportExport): StreamedResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Active contractor not found.');

        abort_unless((int) $reportExport->contractor_id === (int) $contractor->id, 404);
        abort_unless((string) $reportExport->status === ReportExport::STATUS_COMPLETED, 404);
        abort_unless($reportExport->file_disk && $reportExport->file_path, 404);
        abort_unless(Storage::disk($reportExport->file_disk)->exists($reportExport->file_path), 404);

        return Storage::disk($reportExport->file_disk)->download(
            $reportExport->file_path,
            $reportExport->file_name ?? basename($reportExport->file_path),
        );
    }


    private function statusLabel(string $status): string
    {
        return match ($status) {
            ReportExport::STATUS_PENDING => 'Queued',
            ReportExport::STATUS_PROCESSING => 'Processing',
            ReportExport::STATUS_COMPLETED => 'Completed',
            ReportExport::STATUS_FAILED => 'Failed',
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
}


