<?php

namespace App\Notifications;

use App\Models\ReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportExportReadyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ReportExport $reportExport,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(mixed $notifiable): array
    {
        $filters = is_array($this->reportExport->filters) ? $this->reportExport->filters : [];
        $format = strtolower(trim((string) ($filters['format'] ?? 'csv')));
        $formatLabel = match ($format) {
            'pdf' => 'PDF',
            'excel', 'xls', 'xlsx' => 'Excel',
            default => 'CSV',
        };

        return [
            'title' => 'Exportação pronta',
            'message' => "Sua exportação de relatórios em {$formatLabel} está concluída e disponível para download.",
            'contractor_id' => (int) ($this->reportExport->contractor_id ?? 0),
            'target_url' => '/app/reports',
            'export_id' => (int) $this->reportExport->id,
            'download_url' => "/app/reports/exports/{$this->reportExport->id}/download",
            'created_at' => now()->toIso8601String(),
        ];
    }
}
