<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentSettingsSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $commercialContractors = Contractor::query()
            ->get()
            ->filter(static fn (Contractor $contractor): bool => $contractor->hasModule(Contractor::MODULE_COMMERCIAL))
            ->values();

        foreach ($commercialContractors as $contractor) {
            $manualGateway = PaymentGateway::query()->updateOrCreate(
                [
                    'contractor_id' => $contractor->id,
                    'provider' => PaymentGateway::PROVIDER_MANUAL,
                ],
                [
                    'name' => 'Operação manual',
                    'is_active' => true,
                    'is_default' => true,
                    'is_sandbox' => true,
                    'credentials' => null,
                    'settings' => [
                        'note' => 'Gateway interno para operações sem integração externa.',
                    ],
                    'last_health_check_at' => now(),
                ]
            );

            PaymentGateway::query()->updateOrCreate(
                [
                    'contractor_id' => $contractor->id,
                    'provider' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                ],
                [
                    'name' => 'Mercado Pago',
                    'is_active' => false,
                    'is_default' => false,
                    'is_sandbox' => true,
                    'credentials' => null,
                    'settings' => [
                        'integration_status' => 'pending_configuration',
                    ],
                    'last_health_check_at' => null,
                ]
            );

            $methods = [
                [
                    'code' => PaymentMethod::CODE_CASH,
                    'name' => 'Dinheiro',
                    'is_default' => true,
                    'allows_installments' => false,
                    'max_installments' => null,
                    'sort_order' => 10,
                    'settings' => ['requires_gateway' => false],
                ],
                [
                    'code' => PaymentMethod::CODE_PIX,
                    'name' => 'Pix',
                    'is_default' => false,
                    'allows_installments' => false,
                    'max_installments' => null,
                    'sort_order' => 20,
                    'settings' => ['requires_gateway' => false],
                ],
                [
                    'code' => PaymentMethod::CODE_DEBIT_CARD,
                    'name' => 'Cartão de débito',
                    'is_default' => false,
                    'allows_installments' => false,
                    'max_installments' => null,
                    'sort_order' => 30,
                    'settings' => ['requires_gateway' => true],
                ],
                [
                    'code' => PaymentMethod::CODE_CREDIT_CARD,
                    'name' => 'Cartão de crédito',
                    'is_default' => false,
                    'allows_installments' => false,
                    'max_installments' => null,
                    'sort_order' => 40,
                    'settings' => ['requires_gateway' => true],
                ],
                [
                    'code' => PaymentMethod::CODE_INSTALLMENT,
                    'name' => 'A prazo',
                    'is_default' => false,
                    'allows_installments' => true,
                    'max_installments' => 12,
                    'sort_order' => 50,
                    'settings' => ['requires_gateway' => false],
                ],
            ];

            foreach ($methods as $method) {
                PaymentMethod::query()->updateOrCreate(
                    [
                        'contractor_id' => $contractor->id,
                        'code' => $method['code'],
                    ],
                    [
                        'payment_gateway_id' => $manualGateway->id,
                        'name' => $method['name'],
                        'is_active' => true,
                        'is_default' => $method['is_default'],
                        'allows_installments' => $method['allows_installments'],
                        'max_installments' => $method['max_installments'],
                        'fee_fixed' => null,
                        'fee_percent' => null,
                        'sort_order' => $method['sort_order'],
                        'settings' => $method['settings'],
                    ]
                );
            }
        }
    }
}
