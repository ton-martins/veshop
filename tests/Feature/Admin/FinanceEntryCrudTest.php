<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\FinancialEntry;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceEntryCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_create_financial_entry_with_document(): void
    {
        Storage::fake('public');

        $contractor = $this->createContractor('finance-create');
        $user = $this->createAdminUser([$contractor]);
        $paymentMethod = $this->createPaymentMethod($contractor);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->from(route('admin.finance.index', ['tab' => 'payables']))
            ->post(route('admin.finance.entries.store'), [
                'type' => FinancialEntry::TYPE_PAYABLE,
                'status' => FinancialEntry::STATUS_PENDING,
                'counterparty_name' => 'Fornecedor Alfa',
                'reference' => 'NF-001',
                'amount' => 159.90,
                'issue_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(7)->format('Y-m-d'),
                'notes' => 'Compra de insumos',
                'payment_method_id' => $paymentMethod->id,
                'document' => UploadedFile::fake()->create('boleto.pdf', 50, 'application/pdf'),
            ]);

        $response->assertRedirect(route('admin.finance.index', ['tab' => 'payables']));
        $response->assertSessionHas('status', 'Lançamento financeiro criado com sucesso.');

        $entry = FinancialEntry::query()->firstOrFail();

        $this->assertSame($contractor->id, $entry->contractor_id);
        $this->assertSame(FinancialEntry::TYPE_PAYABLE, $entry->type);
        $this->assertSame(FinancialEntry::STATUS_PENDING, $entry->status);
        $this->assertSame('Fornecedor Alfa', $entry->counterparty_name);
        $this->assertSame('159.90', (string) $entry->amount);
        $this->assertNull($entry->paid_at);
        $this->assertNull($entry->payment_method_id);
        $this->assertSame($user->id, $entry->created_by_id);
        $this->assertNotNull($entry->document_path);

        Storage::disk('public')->assertExists((string) $entry->document_path);
    }

    public function test_admin_can_update_financial_entry_to_paid_and_remove_document(): void
    {
        Storage::fake('public');

        $contractor = $this->createContractor('finance-update');
        $user = $this->createAdminUser([$contractor]);
        $paymentMethod = $this->createPaymentMethod($contractor, PaymentMethod::CODE_CASH, 'Dinheiro');

        $storedDocument = UploadedFile::fake()->create('conta.pdf', 25, 'application/pdf')
            ->store("contractors/{$contractor->id}/finance/documents", 'public');

        $entry = FinancialEntry::query()->create([
            'contractor_id' => $contractor->id,
            'type' => FinancialEntry::TYPE_RECEIVABLE,
            'status' => FinancialEntry::STATUS_PENDING,
            'counterparty_name' => 'Cliente Beta',
            'reference' => 'ORC-10',
            'amount' => 399.50,
            'due_date' => now()->addDays(2)->toDateString(),
            'document_path' => $storedDocument,
            'document_original_name' => 'conta.pdf',
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->from(route('admin.finance.index', ['tab' => 'receivables']))
            ->post(route('admin.finance.entries.update', $entry->id), [
                'type' => FinancialEntry::TYPE_RECEIVABLE,
                'status' => FinancialEntry::STATUS_PAID,
                'counterparty_name' => 'Cliente Beta LTDA',
                'reference' => 'ORC-10-REV1',
                'amount' => 419.90,
                'issue_date' => now()->subDays(3)->format('Y-m-d'),
                'due_date' => now()->addDays(2)->format('Y-m-d'),
                'paid_at' => now()->format('Y-m-d H:i:s'),
                'notes' => 'Recebido via caixa',
                'payment_method_id' => $paymentMethod->id,
                'remove_document' => true,
            ]);

        $response->assertRedirect(route('admin.finance.index', ['tab' => 'receivables']));
        $response->assertSessionHas('status', 'Lançamento financeiro atualizado com sucesso.');

        $entry->refresh();

        $this->assertSame(FinancialEntry::STATUS_PAID, $entry->status);
        $this->assertSame('Cliente Beta LTDA', $entry->counterparty_name);
        $this->assertSame('ORC-10-REV1', $entry->reference);
        $this->assertSame('419.90', (string) $entry->amount);
        $this->assertNotNull($entry->paid_at);
        $this->assertSame($paymentMethod->id, $entry->payment_method_id);
        $this->assertNull($entry->document_path);
        $this->assertNull($entry->document_original_name);

        Storage::disk('public')->assertMissing($storedDocument);
    }

    public function test_finance_index_returns_paginated_entries_by_tab(): void
    {
        $contractor = $this->createContractor('finance-index');
        $user = $this->createAdminUser([$contractor]);

        FinancialEntry::query()->create([
            'contractor_id' => $contractor->id,
            'type' => FinancialEntry::TYPE_RECEIVABLE,
            'status' => FinancialEntry::STATUS_PENDING,
            'counterparty_name' => 'Receita isolada',
            'amount' => 50,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        foreach (range(1, 25) as $index) {
            FinancialEntry::query()->create([
                'contractor_id' => $contractor->id,
                'type' => FinancialEntry::TYPE_PAYABLE,
                'status' => FinancialEntry::STATUS_PENDING,
                'counterparty_name' => "Despesa {$index}",
                'amount' => 100 + $index,
                'due_date' => now()->addDays($index)->toDateString(),
            ]);
        }

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->get(route('admin.finance.index', ['tab' => 'payables']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Finance/Index')
            ->where('initialTab', 'payables')
            ->has('financeEntries.data', 20)
            ->has('financeEntries.links')
        );
    }

    public function test_admin_cannot_update_financial_entry_from_another_contractor(): void
    {
        $contractorA = $this->createContractor('finance-tenant-a');
        $contractorB = $this->createContractor('finance-tenant-b');
        $user = $this->createAdminUser([$contractorA, $contractorB]);

        $entry = FinancialEntry::query()->create([
            'contractor_id' => $contractorB->id,
            'type' => FinancialEntry::TYPE_PAYABLE,
            'status' => FinancialEntry::STATUS_PENDING,
            'counterparty_name' => 'Fornecedor B',
            'amount' => 80,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $response = $this
            ->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractorA->id,
                'two_factor_passed' => true,
            ])
            ->post(route('admin.finance.entries.update', $entry->id), [
                'type' => FinancialEntry::TYPE_PAYABLE,
                'status' => FinancialEntry::STATUS_CANCELLED,
                'counterparty_name' => 'Tentativa indevida',
                'amount' => 80,
                'due_date' => now()->addDays(2)->format('Y-m-d'),
            ]);

        $response->assertNotFound();

        $entry->refresh();
        $this->assertSame(FinancialEntry::STATUS_PENDING, $entry->status);
        $this->assertSame('Fornecedor B', $entry->counterparty_name);
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

    private function createPaymentMethod(
        Contractor $contractor,
        string $code = PaymentMethod::CODE_PIX,
        string $name = 'Pix'
    ): PaymentMethod {
        return PaymentMethod::query()->create([
            'contractor_id' => $contractor->id,
            'payment_gateway_id' => null,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'is_default' => true,
            'allows_installments' => false,
            'max_installments' => null,
            'fee_fixed' => null,
            'fee_percent' => null,
            'sort_order' => 10,
            'settings' => null,
        ]);
    }
}
