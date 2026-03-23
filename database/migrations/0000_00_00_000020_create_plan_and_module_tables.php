<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('scope', 20)->default('specific');
            $table->string('niche', 30)->nullable();
            $table->json('business_types')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->index(['scope', 'niche', 'is_active']);
        });

        $now = now();
        $moduleCatalog = collect($this->moduleCatalog())
            ->map(static function (array $module) use ($now): array {
                $module['created_at'] = $now;
                $module['updated_at'] = $now;

                return $module;
            })
            ->all();

        DB::table('modules')->insert($moduleCatalog);

        Schema::create('contractor_module', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contractor_id', 'module_id']);
            $table->index(['contractor_id', 'module_id']);
        });

        Schema::create('plan_module', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained('modules')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'module_id']);
            $table->index(['plan_id', 'module_id']);
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('plan_module');
        Schema::dropIfExists('contractor_module');
        Schema::dropIfExists('modules');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function moduleCatalog(): array
    {
        return [
            [
                'code' => 'users',
                'name' => 'Usuários e perfis',
                'description' => 'Gestão de usuários internos e papéis',
                'scope' => 'global',
                'niche' => null,
                'business_types' => null,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'notifications',
                'name' => 'Notificações',
                'description' => 'Notificações internas e alertas operacionais',
                'scope' => 'global',
                'niche' => null,
                'business_types' => null,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'files',
                'name' => 'Arquivos',
                'description' => 'Upload e gerenciamento de arquivos',
                'scope' => 'global',
                'niche' => null,
                'business_types' => null,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'code' => 'reports',
                'name' => 'Relatórios',
                'description' => 'Análise e exportação de dados',
                'scope' => 'global',
                'niche' => null,
                'business_types' => null,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 40,
            ],
            [
                'code' => 'commercial',
                'name' => 'Núcleo de comércio',
                'description' => 'Módulo base para operação comercial',
                'scope' => 'niche',
                'niche' => 'commercial',
                'business_types' => null,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 50,
            ],
            [
                'code' => 'services',
                'name' => 'Núcleo de serviços',
                'description' => 'Módulo base para operação de serviços',
                'scope' => 'niche',
                'niche' => 'services',
                'business_types' => null,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 50,
            ],
            [
                'code' => 'catalog',
                'name' => 'Catálogo de produtos',
                'description' => 'Produtos e categorias para venda',
                'scope' => 'specific',
                'niche' => 'commercial',
                'business_types' => json_encode(['store', 'confectionery']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 100,
            ],
            [
                'code' => 'inventory',
                'name' => 'Estoque',
                'description' => 'Controle de estoque e movimentações',
                'scope' => 'specific',
                'niche' => 'commercial',
                'business_types' => json_encode(['store', 'confectionery']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 110,
            ],
            [
                'code' => 'pdv',
                'name' => 'PDV',
                'description' => 'Operação de ponto de venda',
                'scope' => 'specific',
                'niche' => null,
                'business_types' => json_encode(['store', 'confectionery', 'barbershop', 'auto_electric', 'mechanic', 'accounting', 'general_services']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 120,
            ],
            [
                'code' => 'orders',
                'name' => 'Pedidos',
                'description' => 'Gestão de pedidos online e internos',
                'scope' => 'specific',
                'niche' => 'commercial',
                'business_types' => json_encode(['store', 'confectionery']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 130,
            ],
            [
                'code' => 'checkout',
                'name' => 'Checkout',
                'description' => 'Checkout da loja virtual',
                'scope' => 'specific',
                'niche' => 'commercial',
                'business_types' => json_encode(['store', 'confectionery']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 140,
            ],
            [
                'code' => 'finance',
                'name' => 'Financeiro',
                'description' => 'Contas e conciliação financeira',
                'scope' => 'specific',
                'niche' => null,
                'business_types' => json_encode(['store', 'confectionery', 'barbershop', 'auto_electric', 'mechanic', 'accounting', 'general_services']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 150,
            ],
            [
                'code' => 'services_catalog',
                'name' => 'Catálogo de serviços',
                'description' => 'Catálogo de serviços prestados',
                'scope' => 'specific',
                'niche' => 'services',
                'business_types' => json_encode(['accounting', 'auto_electric', 'barbershop', 'general_services', 'mechanic']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 160,
            ],
            [
                'code' => 'services_storefront',
                'name' => 'Loja virtual de serviços',
                'description' => 'Catálogo e agendamentos online do nicho serviços',
                'scope' => 'specific',
                'niche' => 'services',
                'business_types' => json_encode(['barbershop', 'auto_electric', 'mechanic', 'accounting', 'general_services']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 165,
            ],
            [
                'code' => 'service_orders',
                'name' => 'Ordens de serviço',
                'description' => 'Abertura e acompanhamento de ordens',
                'scope' => 'specific',
                'niche' => 'services',
                'business_types' => json_encode(['barbershop', 'auto_electric', 'mechanic', 'accounting', 'general_services']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 170,
            ],
            [
                'code' => 'schedule',
                'name' => 'Agenda',
                'description' => 'Agenda operacional de serviços',
                'scope' => 'specific',
                'niche' => 'services',
                'business_types' => json_encode(['accounting', 'auto_electric', 'barbershop', 'general_services', 'mechanic']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 180,
            ],
            [
                'code' => 'workshop',
                'name' => 'Oficina',
                'description' => 'Fluxo técnico para oficina e manutenção',
                'scope' => 'specific',
                'niche' => 'services',
                'business_types' => json_encode(['auto_electric', 'mechanic']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 190,
            ],
            [
                'code' => 'tasks',
                'name' => 'Tarefas recorrentes',
                'description' => 'Gestão de tarefas por cliente',
                'scope' => 'specific',
                'niche' => 'services',
                'business_types' => json_encode(['accounting']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 200,
            ],
            [
                'code' => 'documents',
                'name' => 'Documentos',
                'description' => 'Gestão de documentos por cliente',
                'scope' => 'specific',
                'niche' => 'services',
                'business_types' => json_encode(['accounting']),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 210,
            ],
            [
                'code' => 'crm',
                'name' => 'CRM',
                'description' => 'Relacionamento e histórico de clientes',
                'scope' => 'specific',
                'niche' => null,
                'business_types' => json_encode(['store', 'confectionery', 'barbershop', 'auto_electric', 'mechanic', 'accounting', 'general_services']),
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 220,
            ],
        ];
    }
};
