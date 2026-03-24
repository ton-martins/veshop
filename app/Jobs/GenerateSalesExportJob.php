<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Models\Sale;
use App\Notifications\ReportExportReadyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class GenerateSalesExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            ->with('requestedBy:id,name')
            ->find($this->reportExportId);

        if (! $export || $export->type !== ReportExport::TYPE_SALES) {
            return;
        }

        $disk = 'local';
        $directory = "exports/contractors/{$export->contractor_id}";
        $filename = sprintf(
            'sales-%d-%d-%s.csv',
            (int) $export->contractor_id,
            (int) $export->id,
            now()->format('Ymd-His')
        );
        $path = "{$directory}/{$filename}";

        $export->forceFill([
            'status' => ReportExport::STATUS_PROCESSING,
            'started_at' => now(),
            'finished_at' => null,
            'failed_at' => null,
            'error_message' => null,
            'queue_connection' => (string) $this->connection,
            'queue_name' => (string) $this->queue,
        ])->save();

        Storage::disk($disk)->makeDirectory($directory);

        $absolutePath = Storage::disk($disk)->path($path);
        $stream = fopen($absolutePath, 'wb');

        if ($stream === false) {
            throw new RuntimeException('Não foi possível criar o arquivo CSV da exportação.');
        }

        $rows = 0;

        try {
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['codigo', 'origem', 'status', 'valor_total', 'cliente', 'criado_em'], ';');

            Sale::query()
                ->where('contractor_id', $export->contractor_id)
                ->with([
                    'client:id,name',
                    'shopCustomer:id,name',
                ])
                ->orderBy('id')
                ->chunkById(200, static function ($sales) use (&$rows, $stream): void {
                    foreach ($sales as $sale) {
                        $rows++;

                        fputcsv($stream, [
                            (string) $sale->code,
                            (string) $sale->source,
                            (string) $sale->status,
                            number_format((float) $sale->total_amount, 2, '.', ''),
                            (string) ($sale->client?->name ?? $sale->shopCustomer?->name ?? 'Consumidor final'),
                            optional($sale->created_at)?->format('Y-m-d H:i:s'),
                        ], ';');
                    }
                });
        } catch (Throwable $exception) {
            fclose($stream);
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        fclose($stream);

        $export->forceFill([
            'status' => ReportExport::STATUS_COMPLETED,
            'file_disk' => $disk,
            'file_path' => $path,
            'file_name' => $filename,
            'row_count' => $rows,
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
                'error_message' => substr($exception->getMessage(), 0, 2000),
                'finished_at' => null,
            ]);
    }
}
