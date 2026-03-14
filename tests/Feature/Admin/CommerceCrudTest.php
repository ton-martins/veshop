<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Contractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommerceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_admin_can_create_category_for_current_contractor(): void
    {
        $contractorA = $this->createContractor('contratante-a', Contractor::NICHE_COMMERCIAL);
        $contractorB = $this->createContractor('contratante-b', Contractor::NICHE_COMMERCIAL);
        $user = $this->createAdminUser([$contractorA, $contractorB]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractorA->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.categories.store'), [
                'name' => 'Bebidas Geladas',
                'slug' => 'bebidas-geladas',
                'description' => 'Linha de bebidas frias.',
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', [
            'contractor_id' => $contractorA->id,
            'name' => 'Bebidas Geladas',
            'slug' => 'bebidas-geladas',
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('categories', [
            'contractor_id' => $contractorB->id,
            'name' => 'Bebidas Geladas',
        ]);
    }

    public function test_admin_cannot_update_category_from_another_contractor_context(): void
    {
        $contractorA = $this->createContractor('contratante-a', Contractor::NICHE_COMMERCIAL);
        $contractorB = $this->createContractor('contratante-b', Contractor::NICHE_COMMERCIAL);

        $foreignCategory = Category::query()->create([
            'contractor_id' => $contractorB->id,
            'name' => 'Doces',
            'slug' => 'doces',
            'description' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = $this->createAdminUser([$contractorA, $contractorB]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractorA->id,
                'two_factor_passed' => true,
            ])
            ->put(route('admin.categories.update', $foreignCategory), [
                'name' => 'Doces Atualizados',
                'slug' => 'doces-atualizados',
                'description' => null,
                'is_active' => true,
            ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('categories', [
            'id' => $foreignCategory->id,
            'name' => 'Doces',
            'slug' => 'doces',
        ]);
    }

    public function test_admin_can_create_product_with_integer_quantity_and_unit(): void
    {
        $contractor = $this->createContractor('contratante-comercial', Contractor::NICHE_COMMERCIAL);
        $category = Category::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Confeitaria',
            'slug' => 'confeitaria',
            'description' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.products.store'), [
                'name' => 'Bolo Piscina',
                'sku' => 'BOL-900',
                'category_id' => $category->id,
                'description' => 'Produto de teste',
                'cost_price' => 20.5,
                'sale_price' => 35.7,
                'stock_quantity' => '7.9',
                'unit' => 'kg',
                'image_url' => 'https://example.com/bolo.jpg',
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('products', [
            'contractor_id' => $contractor->id,
            'name' => 'Bolo Piscina',
            'sku' => 'BOL-900',
            'stock_quantity' => 7,
            'unit' => 'kg',
        ]);
    }

    public function test_admin_cannot_use_category_from_another_contractor_when_creating_product(): void
    {
        $contractorA = $this->createContractor('contratante-a', Contractor::NICHE_COMMERCIAL);
        $contractorB = $this->createContractor('contratante-b', Contractor::NICHE_COMMERCIAL);
        $foreignCategory = Category::query()->create([
            'contractor_id' => $contractorB->id,
            'name' => 'Externa',
            'slug' => 'externa',
            'description' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $user = $this->createAdminUser([$contractorA, $contractorB]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractorA->id,
                'two_factor_passed' => true,
            ])
            ->from(route('admin.products.index'))
            ->post(route('admin.products.store'), [
                'name' => 'Produto inválido',
                'sku' => 'INV-001',
                'category_id' => $foreignCategory->id,
                'sale_price' => 20,
                'stock_quantity' => 1,
                'unit' => 'un',
                'is_active' => true,
            ]);

        $response->assertRedirect(route('admin.products.index'));
        $response->assertSessionHasErrors(['category_id']);
        $this->assertDatabaseMissing('products', [
            'contractor_id' => $contractorA->id,
            'sku' => 'INV-001',
        ]);
    }

    /**
     * @param array<int, \App\Models\Contractor> $contractors
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
        ]);
    }
}
