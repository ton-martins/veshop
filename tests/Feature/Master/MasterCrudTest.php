<?php

namespace Tests\Feature\Master;

use App\Models\Contractor;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MasterCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_master_can_create_and_update_contractor(): void
    {
        $master = $this->createMasterUser();
        $plan = Plan::query()->create([
            'niche' => Plan::NICHE_COMMERCIAL,
            'name' => 'Pro',
            'slug' => 'pro',
            'price_monthly' => 399,
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $createResponse = $this
            ->actingAs($master)
            ->withSession(['two_factor_passed' => true])
            ->post(route('master.contractors.store'), [
                'name' => 'Contratante Norte',
                'email' => 'norte@example.com',
                'phone' => '11999990000',
                'cnpj' => '12345678000199',
                'slug' => 'contratante-norte',
                'timezone' => 'America/Sao_Paulo',
                'brand_name' => 'Norte Comercio',
                'brand_primary_color' => '#1E293B',
                'business_niche' => Contractor::NICHE_COMMERCIAL,
                'plan_id' => $plan->id,
                'is_active' => true,
            ]);

        $createResponse->assertRedirect();
        $this->assertDatabaseHas('contractors', [
            'email' => 'norte@example.com',
            'slug' => 'contratante-norte',
            'plan_id' => $plan->id,
            'is_active' => true,
        ]);

        $contractor = Contractor::query()->where('email', 'norte@example.com')->firstOrFail();

        $updateResponse = $this
            ->actingAs($master)
            ->withSession(['two_factor_passed' => true])
            ->put(route('master.contractors.update', $contractor), [
                'name' => 'Contratante Norte Atualizado',
                'email' => 'norte@example.com',
                'phone' => '11999991111',
                'cnpj' => '12345678000199',
                'slug' => 'contratante-norte-atualizado',
                'timezone' => 'America/Sao_Paulo',
                'brand_name' => 'Norte Atualizado',
                'brand_primary_color' => '#0F172A',
                'business_niche' => Contractor::NICHE_SERVICES,
                'plan_id' => null,
                'is_active' => false,
            ]);

        $updateResponse->assertRedirect();
        $contractor->refresh();

        $this->assertSame('Contratante Norte Atualizado', $contractor->name);
        $this->assertSame('contratante-norte-atualizado', $contractor->slug);
        $this->assertNull($contractor->plan_id);
        $this->assertFalse((bool) $contractor->is_active);
        $this->assertSame(Contractor::NICHE_SERVICES, $contractor->niche());
    }

    public function test_master_can_create_update_and_delete_plan(): void
    {
        $master = $this->createMasterUser();

        $createResponse = $this
            ->actingAs($master)
            ->withSession(['two_factor_passed' => true])
            ->post(route('master.plans.store'), [
                'niche' => Plan::NICHE_COMMERCIAL,
                'name' => 'Growth',
                'slug' => 'growth',
                'price_monthly' => 499.90,
                'max_admin_users' => 8,
                'description' => 'Plano de crescimento',
                'features_text' => "Relatorios\nSuporte prioritario",
                'is_active' => true,
                'sort_order' => 15,
            ]);

        $createResponse->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'niche' => Plan::NICHE_COMMERCIAL,
            'name' => 'Growth',
            'slug' => 'growth',
            'is_active' => true,
        ]);

        $plan = Plan::query()->where('slug', 'growth')->firstOrFail();
        $contractor = Contractor::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Empresa Growth',
            'email' => 'growth@empresa.com',
            'slug' => 'empresa-growth',
            'plan_id' => $plan->id,
            'timezone' => 'America/Sao_Paulo',
            'brand_name' => 'Empresa Growth',
            'brand_primary_color' => '#073341',
            'settings' => [
                'business_niche' => Contractor::NICHE_COMMERCIAL,
                'active_plan_name' => 'Growth',
            ],
        ]);

        $updateResponse = $this
            ->actingAs($master)
            ->withSession(['two_factor_passed' => true])
            ->put(route('master.plans.update', $plan), [
                'niche' => Plan::NICHE_COMMERCIAL,
                'name' => 'Growth Plus',
                'slug' => 'growth-plus',
                'price_monthly' => 599.90,
                'max_admin_users' => 12,
                'description' => 'Plano evoluido',
                'features_text' => "Relatorios BI\nSuporte prioritario",
                'is_active' => true,
                'sort_order' => 20,
            ]);

        $updateResponse->assertRedirect();
        $plan->refresh();
        $contractor->refresh();

        $this->assertSame('Growth Plus', $plan->name);
        $this->assertSame('growth-plus', $plan->slug);
        $this->assertSame('Growth Plus', $contractor->activePlanName());

        $blockedDeleteResponse = $this
            ->actingAs($master)
            ->withSession(['two_factor_passed' => true])
            ->delete(route('master.plans.destroy', $plan));

        $blockedDeleteResponse->assertSessionHasErrors(['general']);
        $this->assertDatabaseHas('plans', ['id' => $plan->id]);

        $contractor->update(['plan_id' => null]);

        $deleteResponse = $this
            ->actingAs($master)
            ->withSession(['two_factor_passed' => true])
            ->delete(route('master.plans.destroy', $plan));

        $deleteResponse->assertRedirect(route('master.plans.index'));
        $this->assertSoftDeleted('plans', ['id' => $plan->id]);
    }

    public function test_master_cannot_switch_contractor_context(): void
    {
        $master = $this->createMasterUser();

        $contractor = Contractor::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Contratante Bloqueado',
            'email' => 'bloqueado@empresa.com',
            'slug' => 'contratante-bloqueado',
            'timezone' => 'America/Sao_Paulo',
            'brand_name' => 'Contratante Bloqueado',
            'brand_primary_color' => '#073341',
            'settings' => [
                'business_niche' => Contractor::NICHE_COMMERCIAL,
            ],
        ]);

        $master->contractors()->attach($contractor->id);

        $response = $this
            ->actingAs($master)
            ->withSession(['two_factor_passed' => true])
            ->post(route('contractor.switch'), [
                'contractor_id' => $contractor->id,
            ]);

        $response->assertForbidden();
        $this->assertSame(0, (int) session('current_contractor_id', 0));
    }

    public function test_master_can_update_system_branding(): void
    {
        Storage::fake('public');
        $master = $this->createMasterUser();

        $getResponse = $this
            ->actingAs($master)
            ->withSession(['two_factor_passed' => true])
            ->get(route('master.branding.index'));

        $getResponse->assertOk();

        $updateResponse = $this
            ->actingAs($master)
            ->withSession(['two_factor_passed' => true])
            ->put(route('master.branding.update'), [
                'name' => 'Veshop Prime',
                'tagline' => 'ERP completo para operação',
                'primary_color' => '#0F172A',
                'accent_color' => '#22C55E',
                'logo' => UploadedFile::fake()->image('logo.png', 600, 200),
                'icon' => UploadedFile::fake()->image('icon.png', 256, 256),
            ]);

        $updateResponse->assertRedirect();

        $branding = SystemSetting::getValue(SystemSetting::KEY_BRANDING, []);
        $this->assertIsArray($branding);
        $this->assertSame('Veshop Prime', $branding['name'] ?? null);
        $this->assertSame('ERP completo para operação', $branding['tagline'] ?? null);
        $this->assertSame('#0F172A', $branding['primary_color'] ?? null);
        $this->assertSame('#22C55E', $branding['accent_color'] ?? null);

        $logoPath = ltrim(str_replace('/storage/', '', (string) ($branding['logo_url'] ?? '')), '/');
        $iconPath = ltrim(str_replace('/storage/', '', (string) ($branding['icon_url'] ?? '')), '/');

        Storage::disk('public')->assertExists($logoPath);
        Storage::disk('public')->assertExists($iconPath);
    }

    private function createMasterUser(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_MASTER,
            'email_verified_at' => now(),
            'two_factor_secret' => 'fake-secret',
            'two_factor_confirmed_at' => now(),
            'is_active' => true,
        ]);
    }
}
