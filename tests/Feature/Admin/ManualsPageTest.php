<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManualsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (method_exists($this, 'withoutVite')) {
            $this->withoutVite();
        }
    }

    public function test_admin_can_open_manuals_page(): void
    {
        $contractor = $this->createContractor('manuals-home');
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->get(route('admin.manuals.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Manuals/Index')
            ->where('initialTab', 'settings')
        );
    }

    public function test_manuals_page_accepts_finance_tab_query(): void
    {
        $contractor = $this->createContractor('manuals-finance');
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->get(route('admin.manuals.index', ['tab' => 'finance']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Manuals/Index')
            ->where('initialTab', 'finance')
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
            ],
        ]);
    }
}
