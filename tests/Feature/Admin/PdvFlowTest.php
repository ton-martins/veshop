<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\Client;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SecurityAuditLog;
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

    public function test_admin_applies_payment_fee_on_pdv_sale_total(): void
    {
        $contractor = $this->createContractor('pdv-sale-fee');
        $user = $this->createAdminUser([$contractor]);

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto Taxa',
            'sku' => 'TAX-001',
            'sale_price' => 100.00,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $paymentMethod = PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => null,
            'code' => PaymentMethod::CODE_CREDIT_CARD,
            'name' => 'Cartão',
            'is_active' => true,
            'is_default' => true,
            'allows_installments' => true,
            'max_installments' => 12,
            'fee_fixed' => 2.00,
            'fee_percent' => 3.00,
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
                'opening_balance' => 100,
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
                'installments' => 2,
                'discount_amount' => 10,
                'surcharge_amount' => 5,
                'notes' => 'Venda com taxa',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales', [
            'contractor_id' => $contractor->id,
            'source' => Sale::SOURCE_PDV,
            'status' => Sale::STATUS_COMPLETED,
            'subtotal_amount' => '100.00',
            'discount_amount' => '10.00',
            'surcharge_amount' => '9.85',
            'total_amount' => '99.85',
            'paid_amount' => '99.85',
        ]);

        $sale = Sale::query()
            ->where('contractor_id', $contractor->id)
            ->where('source', Sale::SOURCE_PDV)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(5.00, (float) data_get($sale->metadata, 'charges.manual_surcharge_amount'));
        $this->assertSame(4.85, (float) data_get($sale->metadata, 'charges.payment_fee_amount'));

        $this->assertDatabaseHas('sale_payments', [
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethod->id,
            'status' => 'paid',
            'amount' => '99.85',
        ]);

        $payment = SalePayment::query()
            ->where('contractor_id', $contractor->id)
            ->where('sale_id', $sale->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(4.85, (float) data_get($payment->metadata, 'fee_snapshot.fee_amount'));
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

    public function test_admin_can_edit_pdv_sale_and_generate_audit_log(): void
    {
        $contractor = $this->createContractor('pdv-edicao');
        $user = $this->createAdminUser([$contractor]);
        $client = Client::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente PDV',
            'email' => 'cliente-pdv@example.com',
            'phone' => '71988880001',
            'is_active' => true,
        ]);

        $productA = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto A',
            'sku' => 'PDV-A',
            'sale_price' => 30,
            'stock_quantity' => 8,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $productB = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto B',
            'sku' => 'PDV-B',
            'sale_price' => 15,
            'stock_quantity' => 9,
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
            'code' => 'VDA-20260318-001',
            'source' => Sale::SOURCE_PDV,
            'status' => Sale::STATUS_COMPLETED,
            'client_id' => $client->id,
            'subtotal_amount' => 75,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 75,
            'paid_amount' => 75,
            'change_amount' => 0,
            'metadata' => [
                'customer_name' => 'Cliente PDV Original',
                'customer_contact' => '51990000000',
            ],
            'notes' => 'Observação inicial',
        ]);

        SaleItem::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'product_id' => $productA->id,
            'description' => $productA->name,
            'sku' => $productA->sku,
            'quantity' => 2,
            'unit_price' => 30,
            'discount_amount' => 0,
            'total_amount' => 60,
        ]);

        SaleItem::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'product_id' => $productB->id,
            'description' => $productB->name,
            'sku' => $productB->sku,
            'quantity' => 1,
            'unit_price' => 15,
            'discount_amount' => 0,
            'total_amount' => 15,
        ]);

        SalePayment::query()->create([
            'contractor_id' => $contractor->id,
            'sale_id' => $sale->id,
            'payment_method_id' => $paymentMethod->id,
            'payment_gateway_id' => null,
            'status' => SalePayment::STATUS_PAID,
            'amount' => 75,
            'installments' => null,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->put(route('admin.sales.update', $sale->id), [
                'client_id' => $client->id,
                'customer_name' => 'Cliente PDV Atualizado',
                'customer_contact' => 'cliente.pdv@exemplo.com',
                'discount_amount' => 2.50,
                'surcharge_amount' => 4.00,
                'items' => [
                    [
                        'product_id' => $productA->id,
                        'quantity' => 1,
                        'discount_amount' => 0,
                    ],
                    [
                        'product_id' => $productB->id,
                        'quantity' => 3,
                        'discount_amount' => 5.00,
                    ],
                ],
                'notes' => 'Ajuste manual na venda PDV.',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'subtotal_amount' => '75.00',
            'discount_amount' => '7.50',
            'surcharge_amount' => '4.00',
            'total_amount' => '71.50',
            'paid_amount' => '71.50',
            'notes' => 'Ajuste manual na venda PDV.',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $productA->id,
            'quantity' => 1,
            'discount_amount' => '0.00',
            'total_amount' => '30.00',
        ]);
        $this->assertDatabaseHas('sale_items', [
            'sale_id' => $sale->id,
            'product_id' => $productB->id,
            'quantity' => 3,
            'discount_amount' => '5.00',
            'total_amount' => '40.00',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $productA->id,
            'stock_quantity' => 9,
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $productB->id,
            'stock_quantity' => 7,
        ]);
        $this->assertDatabaseHas('sale_payments', [
            'sale_id' => $sale->id,
            'amount' => '71.50',
        ]);
        $this->assertDatabaseHas('security_audit_logs', [
            'contractor_id' => $contractor->id,
            'user_id' => $user->id,
            'event' => 'sale.updated.admin',
            'severity' => SecurityAuditLog::SEVERITY_INFO,
        ]);

        $sale->refresh();
        $this->assertSame('Cliente PDV Atualizado', (string) data_get($sale->metadata, 'customer_name'));
        $this->assertSame('cliente.pdv@exemplo.com', (string) data_get($sale->metadata, 'customer_contact'));
    }

    public function test_admin_cannot_edit_cancelled_pdv_sale(): void
    {
        $contractor = $this->createContractor('pdv-edicao-bloqueio');
        $user = $this->createAdminUser([$contractor]);
        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto Bloqueado',
            'sku' => 'PDV-BLK',
            'sale_price' => 25,
            'stock_quantity' => 10,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $sale = Sale::query()->create([
            'contractor_id' => $contractor->id,
            'code' => 'VDA-20260318-002',
            'source' => Sale::SOURCE_PDV,
            'status' => Sale::STATUS_CANCELLED,
            'subtotal_amount' => 50,
            'discount_amount' => 0,
            'surcharge_amount' => 0,
            'total_amount' => 50,
            'paid_amount' => 0,
            'change_amount' => 0,
            'metadata' => [
                'customer_name' => 'Cliente Bloqueado',
            ],
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->from(route('admin.sales.index'))
            ->put(route('admin.sales.update', $sale->id), [
                'customer_name' => 'Tentativa de alteração',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'discount_amount' => 0,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.sales.index'));
        $response->assertSessionHasErrors(['sale']);

        $sale->refresh();
        $this->assertSame('Cliente Bloqueado', (string) data_get($sale->metadata, 'customer_name'));
        $this->assertDatabaseMissing('security_audit_logs', [
            'contractor_id' => $contractor->id,
            'user_id' => $user->id,
            'event' => 'sale.updated.admin',
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
                'email_notifications_enabled' => true,
            ],
        ]);
    }
}
