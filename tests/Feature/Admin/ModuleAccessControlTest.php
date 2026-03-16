<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModuleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_pdv_module_is_redirected_when_accessing_pdv(): void
    {
        $contractor = $this->createContractor('module-control-pdv');
        $this->syncContractorModules($contractor, ['commercial', 'catalog']);
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAsWithTwoFactor($user)
            ->withSession(['current_contractor_id' => $contractor->id])
            ->get(route('admin.pdv.index'));

        $response->assertRedirect(route('admin.home'));
        $response->assertSessionHas('status', 'Modulo nao habilitado para o contratante ativo.');
    }

    public function test_admin_without_notifications_module_is_redirected_when_accessing_notifications(): void
    {
        $contractor = $this->createContractor('module-control-notifications');
        $this->syncContractorModules($contractor, ['commercial', 'catalog']);
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAsWithTwoFactor($user)
            ->withSession(['current_contractor_id' => $contractor->id])
            ->get(route('notifications.index'));

        $response->assertRedirect(route('admin.home'));
        $response->assertSessionHas('status', 'Modulo nao habilitado para o contratante ativo.');
    }

    /**
     * @param array<int, Contractor> $contractors
     */
    private function createAdminUser(array $contractors): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'is_active' => true,
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
            ],
            'business_type' => Contractor::BUSINESS_TYPE_STORE,
            'is_active' => true,
        ]);
    }

    /**
     * @param array<int, string> $moduleCodes
     */
    private function syncContractorModules(Contractor $contractor, array $moduleCodes): void
    {
        $moduleIds = Module::query()
            ->whereIn('code', $moduleCodes)
            ->pluck('id')
            ->all();

        $contractor->modules()->sync($moduleIds);
    }
}

