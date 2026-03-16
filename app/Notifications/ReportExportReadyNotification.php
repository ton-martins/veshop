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
        return [
            'title' => 'Export ready',
            'message' => 'Your sales export is complete and available for download.',
            'target_url' => '/app/reports',
            'export_id' => (int) $this->reportExport->id,
            'download_url' => "/app/reports/exports/{$this->reportExport->id}/download",
            'created_at' => now()->toIso8601String(),
        ];
    }
}
