<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ServiceAppointment;
use App\Models\ServiceCatalog;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_index_returns_commercial_metrics_with_modular_payload(): void
    {
        $contractor = $this->createContractor('report-overview-commerce', Contractor::NICHE_COMMERCIAL);
        $user = $this->createAdminUser($contractor);

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto A',
            'sku' => 'PROD-A',
            'sale_price' => 120,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'code' => 'V-1000',
            'source' => Sale::SOURCE_ORDER,
            'status' => Sale::STATUS_COMPLETED,
            'total_amount' => 150.00,
            'completed_at' => now()->subHour(),
        ]);

        SaleItem::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 2,
            'unit_price' => 75,
            'total_amount' => 150,
        ]);

        $response = $this->actingAsWithTwoFactor($user)
            ->withSession(['current_contractor_id' => $contractor->id])
            ->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('reportContext.niche', Contractor::NICHE_COMMERCIAL)
            ->where('topItems.kind', 'products')
            ->where('filters.period', 'month')
            ->where('metricCards', static function ($cards): bool {
                $collection = collect($cards);
                $revenueCard = $collection->firstWhere('key', 'commercial_revenue');
                $ordersCard = $collection->firstWhere('key', 'commercial_orders');

                return is_array($revenueCard)
                    && is_array($ordersCard)
                    && (float) ($revenueCard['value'] ?? 0) === 150.0
                    && (int) ($ordersCard['value'] ?? 0) === 1;
            })
        );
    }

    public function test_reports_index_returns_services_metrics_with_modular_payload(): void
    {
        $contractor = $this->createContractor('report-overview-services', Contractor::NICHE_SERVICES);
        $user = $this->createAdminUser($contractor);

        $service = ServiceCatalog::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Instalação elétrica',
            'duration_minutes' => 90,
            'base_price' => 250,
            'is_active' => true,
        ]);

        ServiceOrder::query()->create([
            'contractor_id' => $contractor->id,
            'service_catalog_id' => $service->id,
            'code' => 'OS-900',
            'title' => 'Instalação elétrica',
            'status' => ServiceOrder::STATUS_DONE,
            'final_amount' => 340,
            'finished_at' => now()->subMinutes(30),
        ]);

        ServiceAppointment::query()->create([
            'contractor_id' => $contractor->id,
            'service_catalog_id' => $service->id,
            'title' => 'Visita técnica',
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'status' => ServiceAppointment::STATUS_CONFIRMED,
        ]);

        $response = $this->actingAsWithTwoFactor($user)
            ->withSession(['current_contractor_id' => $contractor->id])
            ->get(route('admin.reports.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index')
            ->where('reportContext.niche', Contractor::NICHE_SERVICES)
            ->where('topItems.kind', 'services')
            ->where('metricCards', static function ($cards): bool {
                $collection = collect($cards);
                $revenueCard = $collection->firstWhere('key', 'services_revenue');
                $ordersCard = $collection->firstWhere('key', 'services_completed_orders');
                $appointmentsCard = $collection->firstWhere('key', 'services_appointments');

                return is_array($revenueCard)
                    && is_array($ordersCard)
                    && is_array($appointmentsCard)
                    && (float) ($revenueCard['value'] ?? 0) === 340.0
                    && (int) ($ordersCard['value'] ?? 0) === 1
                    && (int) ($appointmentsCard['value'] ?? 0) === 1;
            })
        );
    }

    private function createContractor(string $slug, string $niche): Contractor
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
                'business_niche' => $niche,
                'active_plan_name' => 'Pro',
                'require_2fa' => true,
                'require_email_verification' => true,
                'email_notifications_enabled' => true,
            ],
            'business_type' => $niche === Contractor::NICHE_SERVICES
                ? Contractor::BUSINESS_TYPE_GENERAL_SERVICES
                : Contractor::BUSINESS_TYPE_STORE,
            'is_active' => true,
        ]);
    }

    private function createAdminUser(Contractor $contractor): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
        $user->contractors()->attach($contractor->id);

        return $user;
    }
}
