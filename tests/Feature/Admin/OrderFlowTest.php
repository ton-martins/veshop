<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_admin_can_confirm_mark_paid_and_cancel_online_order(): void
    {
        $contractor = $this->createContractor('pedidos-online');
        $user = $this->createAdminUser([$contractor]);

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Tênis Esportivo',
            'sku' => 'TEN-001',
            'sale_price' => 120.00,
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

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'code' => 'PED-20260315-001',
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_PENDING_CONFIRMATION,
            'subtotal_amount' => 240,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 240,
            'paid_amount' => 0,
            'change_amount' => 0,
            'metadata' => [
                'customer_name' => 'Cliente Pedido',
            ],
        ]);

        SaleItem::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => 120,
            'discount_amount' => 0,
            'total_amount' => 240,
        ]);

        SalePayment::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_gateway_id' => null,
            'status' => SalePayment::STATUS_PENDING,
            'amount' => 240,
            'installments' => null,
            'paid_at' => null,
        ]);

        $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.orders.confirm', $sale->id))
            ->assertRedirect();

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => Sale::STATUS_AWAITING_PAYMENT,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 8,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'contractor_id' => $contractor->id,
            'product_id' => $product->id,
            'type' => 'out',
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.orders.paid', $sale->id))
            ->assertRedirect();

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => Sale::STATUS_PAID,
            'paid_amount' => '240.00',
        ]);
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'status' => SalePayment::STATUS_PAID,
        ]);

        $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.orders.cancel', $sale->id), [
                'reason' => 'Cliente desistiu da compra',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => Sale::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'status' => SalePayment::STATUS_REFUNDED,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 10,
        ]);
        $this->assertDatabaseHas('inventory_movements', [
            'contractor_id' => $contractor->id,
            'product_id' => $product->id,
            'type' => 'return',
            'quantity' => 2,
        ]);
    }

    public function test_admin_can_reject_online_order_with_reason(): void
    {
        $contractor = $this->createContractor('pedidos-rejeicao');
        $user = $this->createAdminUser([$contractor]);

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Boné',
            'sku' => 'BON-001',
            'sale_price' => 50.00,
            'stock_quantity' => 6,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'code' => 'PED-20260315-002',
            'source' => Sale::SOURCE_CATALOG,
            'status' => Sale::STATUS_PENDING_CONFIRMATION,
            'subtotal_amount' => 100,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 100,
            'paid_amount' => 0,
            'change_amount' => 0,
        ]);

        SaleItem::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'sku' => $product->sku,
            'quantity' => 2,
            'unit_price' => 50,
            'discount_amount' => 0,
            'total_amount' => 100,
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.orders.reject', $sale->id), [
                'reason' => 'Produto indisponível para entrega',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'status' => Sale::STATUS_REJECTED,
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'stock_quantity' => 6,
        ]);
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
            'is_active' => true,
        ]);
    }
}
