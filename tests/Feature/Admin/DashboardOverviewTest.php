<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_pdv_cards_sum_business_day_sales_and_compute_average_ticket(): void
    {
        $contractor = $this->createContractor('dashboard-overview');
        $user = $this->createAdminUser([$contractor]);

        $todayStartLocal = now($contractor->timezone)->startOfDay();

        $firstCompletedAtUtc = $todayStartLocal->copy()->addHours(10)->setTimezone('UTC');
        $secondCompletedAtUtc = $todayStartLocal->copy()->addHours(23)->addMinutes(30)->setTimezone('UTC');
        $previousDayCompletedAtUtc = $todayStartLocal->copy()->subMinutes(30)->setTimezone('UTC');
        $otherSourceCompletedAtUtc = $todayStartLocal->copy()->addHours(11)->setTimezone('UTC');

        $this->createSale($contractor, 'PDV-001', Sale::SOURCE_PDV, Sale::STATUS_COMPLETED, 120.00, $firstCompletedAtUtc);
        $this->createSale($contractor, 'PDV-002', Sale::SOURCE_PDV, Sale::STATUS_COMPLETED, 80.00, $secondCompletedAtUtc);
        $this->createSale($contractor, 'PDV-003', Sale::SOURCE_PDV, Sale::STATUS_COMPLETED, 999.00, $previousDayCompletedAtUtc);
        $this->createSale($contractor, 'WEB-001', Sale::SOURCE_CATALOG, Sale::STATUS_COMPLETED, 500.00, $otherSourceCompletedAtUtc);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->get(route('admin.home'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Home')
            ->where('overview.commercial.pdv.sales_today', static fn ($value): bool => (float) $value === 200.0)
            ->where('overview.commercial.pdv.sales_count', 2)
            ->where('overview.commercial.pdv.avg_ticket', static fn ($value): bool => (float) $value === 100.0)
        );
    }

    /**
     * @param array<int, Contractor> $contractors
     */
    private function createAdminUser(array $contractors): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'two_factor_secret' => 'fake-secret',
            'two_factor_confirmed_at' => now(),
        ]);

        $user->contractors()->sync(collect($contractors)->pluck('id')->all());

        return $user;
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
                'require_2fa' => true,
                'require_email_verification' => true,
                'email_notifications_enabled' => true,
            ],
        ]);
    }

    private function createSale(
        Contractor $contractor,
        string $code,
        string $source,
        string $status,
        float $totalAmount,
        Carbon $completedAtUtc,
    ): void {
        Sale::query()->create([
            'contractor_id' => $contractor->id,
            'code' => $code,
            'source' => $source,
            'status' => $status,
            'subtotal_amount' => $totalAmount,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => $totalAmount,
            'paid_amount' => $totalAmount,
            'change_amount' => 0,
            'completed_at' => $completedAtUtc,
        ]);
    }
}
