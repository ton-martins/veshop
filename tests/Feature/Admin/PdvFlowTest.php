<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PdvFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_admin_can_open_cash_session_for_current_contractor(): void
    {
        $contractor = $this->createContractor('pdv-open-cash');
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.pdv.cash.open'), [
                'opening_balance' => 150.35,
                'notes' => 'Abertura da manhã',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cash_sessions', [
            'contractor_id' => $contractor->id,
            'status' => 'open',
            'opening_balance' => '150.35',
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'contractor_id' => $contractor->id,
            'type' => 'opening_balance',
            'direction' => 'in',
            'amount' => '150.35',
        ]);
    }

    public function test_admin_can_finalize_pdv_sale_and_reduce_stock(): void
    {
        $contractor = $this->createContractor('pdv-sale-flow');
        $user = $this->createAdminUser([$contractor]);

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Café Especial',
            'sku' => 'CAF-001',
            'sale_price' => 12.50,
            'stock_quantity' => 8,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $paymentMethod = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => null,
            'code' => PaymentMethod::CODE_CASH,
            'name' => 'Dinheiro',
            'is_active' => true,
            'is_default' => true,
            'allows_installments' => false,
            'max_installments' => null,
            'fee_fixed' => null,
            'fee_percent' => null,
            'sort_order' => 10,
            'settings' => null,
        ]);

        $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.pdv.cash.open'), [
                'opening_balance' => 50,
            ])
            ->assertRedirect();

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.pdv.sales.store'), [
                'client_id' => null,
                'payment_method_id' => $paymentMethod->id,
                'discount_amount' => 0,
                'surcharge_amount' => 0,
                'notes' => 'Venda balcão',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales', [
            'contractor_id' => $contractor->id,
            'source' => 'pdv',
            'status' => 'completed',
            'subtotal_amount' => '25.00',
            'total_amount' => '25.00',
            'paid_amount' => '25.00',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'contractor_id' => $contractor->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'total_amount' => '25.00',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 6,
        ]);
        $this->assertDatabaseHas('sale_payments', [
            'contractor_id' => $contractor->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => 'paid',
            'amount' => '25.00',
        ]);
        $this->assertDatabaseHas('cash_movements', [
            'contractor_id' => $contractor->id,
            'type' => 'sale_payment',
            'direction' => 'in',
            'amount' => '25.00',
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'contractor_id' => $contractor->id,
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 2,
        ]);
    }

    public function test_admin_cannot_finalize_sale_without_open_cash_session(): void
    {
        $contractor = $this->createContractor('pdv-without-open-cash');
        $user = $this->createAdminUser([$contractor]);

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Água Mineral',
            'sku' => 'AGU-001',
            'sale_price' => 5.00,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $paymentMethod = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => null,
            'code' => PaymentMethod::CODE_PIX,
            'name' => 'Pix',
            'is_active' => true,
            'is_default' => true,
            'allows_installments' => false,
            'max_installments' => null,
            'fee_fixed' => null,
            'fee_percent' => null,
            'sort_order' => 10,
            'settings' => null,
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->from(route('admin.pdv.index'))
            ->post(route('admin.pdv.sales.store'), [
                'payment_method_id' => $paymentMethod->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect(route('admin.pdv.index'));
        $response->assertSessionHasErrors(['cash_session']);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_admin_can_define_featured_products_for_pdv(): void
    {
        $contractor = $this->createContractor('pdv-featured-products');
        $user = $this->createAdminUser([$contractor]);

        $first = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto A',
            'sku' => 'PDV-A',
            'sale_price' => 10,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $second = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto B',
            'sku' => 'PDV-B',
            'sale_price' => 20,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $third = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto C',
            'sku' => 'PDV-C',
            'sale_price' => 30,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->put(route('admin.pdv.products.featured.update'), [
                'product_ids' => [$second->id, $first->id],
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $second->id,
            'is_pdv_featured' => 1,
            'pdv_featured_order' => 1,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $first->id,
            'is_pdv_featured' => 1,
            'pdv_featured_order' => 2,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $third->id,
            'is_pdv_featured' => 0,
            'pdv_featured_order' => null,
        ]);
    }

    public function test_admin_can_create_client_from_pdv(): void
    {
        $contractor = $this->createContractor('pdv-quick-client');
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.pdv.clients.store'), [
                'name' => 'Cliente PDV',
                'email' => 'cliente-pdv@example.com',
                'phone' => '71999990000',
                'city' => 'Salvador',
                'state' => 'ba',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('pdv_new_client_id');

        $this->assertDatabaseHas('clients', [
            'contractor_id' => $contractor->id,
            'name' => 'Cliente PDV',
            'email' => 'cliente-pdv@example.com',
            'state' => 'BA',
            'is_active' => 1,
        ]);
        $this->assertSame(1, Client::query()->where('contractor_id', $contractor->id)->count());
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
}
