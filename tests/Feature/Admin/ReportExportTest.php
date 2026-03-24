<?php

namespace Tests\Feature\Admin;

use App\Jobs\GenerateReportExportJob;
use App\Jobs\GenerateSalesExportJob;
use App\Models\Contractor;
use App\Models\ReportExport;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_dashboard_export_with_selected_modules_and_format(): void
    {
        Queue::fake();

        $contractor = $this->createContractor('queue-report-store');
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $user->contractors()->attach($contractor->id);

        $response = $this->actingAsWithTwoFactor($user)
            ->from(route('admin.reports.index'))
            ->post(route('admin.reports.exports'), [
                'format' => 'pdf',
                'module_codes' => ['commercial', 'finance'],
                'include_details' => true,
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
            ]);

        $response->assertRedirect(route('admin.reports.index'));
        $response->assertSessionHas('status', 'Exportação enfileirada. O processamento seguirá em segundo plano.');

        $export = ReportExport::query()->first();

        $this->assertNotNull($export);
        $this->assertSame((int) $contractor->id, (int) $export->contractor_id);
        $this->assertSame((int) $user->id, (int) $export->requested_by_user_id);
        $this->assertSame(ReportExport::TYPE_DASHBOARD, (string) $export->type);
        $this->assertSame(ReportExport::STATUS_PENDING, (string) $export->status);
        $this->assertSame('pdf', strtolower((string) ($export->filters['format'] ?? '')));
        $this->assertSame(['commercial', 'finance'], $export->filters['module_codes'] ?? []);

        Queue::assertPushed(GenerateReportExportJob::class, function (GenerateReportExportJob $job) use ($export): bool {
            return (int) $job->reportExportId === (int) $export->id
                && (string) $job->queue === (string) config('queue.workloads.exports.queue')
                && (string) $job->connection === (string) config('queue.workloads.exports.connection');
        });
    }

    public function test_admin_can_still_queue_legacy_sales_export_route(): void
    {
        Queue::fake();

        $contractor = $this->createContractor('queue-legacy-sales-export');
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $user->contractors()->attach($contractor->id);

        $response = $this->actingAsWithTwoFactor($user)
            ->from(route('admin.reports.index'))
            ->post(route('admin.reports.exports.sales'));

        $response->assertRedirect(route('admin.reports.index'));
        $response->assertSessionHas('status', 'Exportação enfileirada. O processamento seguirá em segundo plano.');

        $export = ReportExport::query()->first();

        $this->assertNotNull($export);
        $this->assertSame(ReportExport::TYPE_SALES, (string) $export->type);
        $this->assertSame('csv', strtolower((string) ($export->filters['format'] ?? '')));

        Queue::assertPushed(GenerateSalesExportJob::class, function (GenerateSalesExportJob $job) use ($export): bool {
            return (int) $job->reportExportId === (int) $export->id;
        });
    }

    public function test_sales_export_job_generates_csv_and_marks_export_as_completed(): void
    {
        Storage::fake('local');

        $contractor = $this->createContractor('job-report-store');
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $user->contractors()->attach($contractor->id);

        Sale::query()->create([
            'contractor_id' => $contractor->id,
            'code' => 'S-1001',
            'source' => Sale::SOURCE_ORDER,
            'status' => Sale::STATUS_PAID,
            'total_amount' => 120.50,
        ]);

        Sale::query()->create([
            'contractor_id' => $contractor->id,
            'code' => 'S-1002',
            'source' => Sale::SOURCE_PDV,
            'status' => Sale::STATUS_COMPLETED,
            'total_amount' => 45.00,
        ]);

        $export = ReportExport::query()->create([
            'contractor_id' => $contractor->id,
            'requested_by_user_id' => $user->id,
            'type' => ReportExport::TYPE_SALES,
            'status' => ReportExport::STATUS_PENDING,
        ]);

        (new GenerateSalesExportJob((int) $export->id))->handle();

        $export->refresh();

        $this->assertSame(ReportExport::STATUS_COMPLETED, (string) $export->status);
        $this->assertNotNull($export->file_disk);
        $this->assertNotNull($export->file_path);
        $this->assertNotNull($export->finished_at);
        $this->assertSame(2, (int) $export->row_count);
        $this->assertTrue(Storage::disk((string) $export->file_disk)->exists((string) $export->file_path));

        $content = Storage::disk((string) $export->file_disk)->get((string) $export->file_path);
        $this->assertStringContainsString('S-1001', $content);
        $this->assertStringContainsString('S-1002', $content);

        $notification = $user->notifications()->latest()->first();
        $this->assertNotNull($notification);
        $this->assertSame((int) $export->id, (int) ($notification->data['export_id'] ?? 0));
    }

    private function createContractor(string $slug): Contractor
    {
        return Contractor::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::title(str_replace('-', ' ', $slug)),
            'email' => "{$slug}@example.com",
            'slug' => $slug,
            'timezone' => 'America/Sao_Paulo',
            'brand_name' => Str::title(str_replace('-', ' ', $slug)),
            'brand_primary_color' => '#073341',
            'settings' => [
                'business_niche' => Contractor::NICHE_COMMERCIAL,
                'active_plan_name' => 'Pro',
            ],
            'is_active' => true,
        ]);
    }
}
