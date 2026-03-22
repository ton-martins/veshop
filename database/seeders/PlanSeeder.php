<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the application's plans.
     */
    public function run(): void
    {
        $plans = $this->plansPayload();

        foreach ($plans as $payload) {
            $match = [
                'niche' => $payload['niche'],
                'name' => $payload['name'],
            ];

            $moduleCodes = $payload['module_codes'] ?? [];
            unset($payload['module_codes']);

            $plan = Plan::withTrashed()
                ->where($match)
                ->first();

            if (! $plan) {
                $plan = new Plan($match);
            }

            if ($plan->trashed()) {
                $plan->restore();
            }

            $plan->fill($payload)->save();

            $moduleIds = Module::query()
                ->where('is_active', true)
                ->whereIn('code', $moduleCodes)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();

            $plan->modules()->sync($moduleIds);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function plansPayload(): array
    {
        return [
            [
                'niche' => Plan::NICHE_COMMERCIAL,
                'name' => 'Essencial',
                'slug' => 'essencial',
                'badge' => 'Básico',
                'subtitle' => 'Operação de venda inicial',
                'summary' => 'Plano para iniciar as vendas online com controle do básico.',
                'footer_message' => 'Ideal para começar com baixo custo e operação simplificada.',
                'price_monthly' => 99.00,
                'max_admin_users' => 1,
                'user_limit' => 1,
                'storage_limit_gb' => 1,
                'audit_log_retention_days' => 30,
                'description' => 'Plano inicial para comércio com foco em catálogo, pedidos e checkout.',
                'features' => $this->features([
                    ['Catálogo de produtos', 'Produtos e categorias para venda'],
                    ['Pedidos online', 'Gestão completa do fluxo de pedidos'],
                    ['Checkout da loja', 'Finalização de compra na loja virtual'],
                    ['Financeiro básico', 'Contas e formas de pagamento'],
                    ['1 administrador', 'Acesso administrativo individual'],
                ]),
                'is_active' => true,
                'is_featured' => false,
                'show_on_landing' => true,
                'tier_rank' => 1,
                'sort_order' => 10,
                'module_codes' => [
                    'users',
                    'notifications',
                    'files',
                    'commercial',
                    'catalog',
                    'orders',
                    'checkout',
                    'finance',
                ],
            ],
            [
                'niche' => Plan::NICHE_COMMERCIAL,
                'name' => 'Profissional',
                'slug' => 'profissional',
                'badge' => 'Mais vendido',
                'subtitle' => 'Operação comercial completa',
                'summary' => 'Plano recomendado para integrar loja virtual, vendas e gestão.',
                'footer_message' => 'Equilíbrio ideal entre recursos e investimento mensal.',
                'price_monthly' => 199.00,
                'max_admin_users' => 1,
                'user_limit' => 1,
                'storage_limit_gb' => 3,
                'audit_log_retention_days' => 90,
                'description' => 'Plano intermediário para comércio com PDV, CRM e relatórios.',
                'features' => $this->features([
                    ['Tudo do Essencial', 'Base completa para operar com eficiência'],
                    ['PDV integrado', 'Ponto de venda no painel administrativo'],
                    ['CRM de clientes', 'Histórico e relacionamento comercial'],
                    ['Relatórios operacionais', 'Exportações e visão de desempenho'],
                    ['1 administrador', 'Controle centralizado por um responsável'],
                ]),
                'is_active' => true,
                'is_featured' => true,
                'show_on_landing' => true,
                'tier_rank' => 2,
                'sort_order' => 20,
                'module_codes' => [
                    'users',
                    'notifications',
                    'files',
                    'commercial',
                    'catalog',
                    'orders',
                    'checkout',
                    'finance',
                    'pdv',
                    'crm',
                    'reports',
                ],
            ],
            [
                'niche' => Plan::NICHE_COMMERCIAL,
                'name' => 'Escala',
                'slug' => 'escala',
                'badge' => 'Mais completo',
                'subtitle' => 'Estrutura para crescer',
                'summary' => 'Plano para operação comercial com foco em expansão.',
                'footer_message' => 'Indicado para negócio em crescimento e maior volume operacional.',
                'price_monthly' => 499.00,
                'max_admin_users' => 3,
                'user_limit' => 3,
                'storage_limit_gb' => 10,
                'audit_log_retention_days' => 365,
                'description' => 'Plano avançado para comércio, incluindo estoque e até três admins.',
                'features' => $this->features([
                    ['Tudo do Profissional', 'Conjunto completo para operação madura'],
                    ['Gestão de estoque', 'Movimentações e controle avançado'],
                    ['Até 3 administradores', 'Mais capacidade de gestão no time'],
                    ['Storage ampliado', 'Mais espaço para mídia e anexos'],
                    ['Histórico anual de auditoria', 'Rastreabilidade por 365 dias'],
                ]),
                'is_active' => true,
                'is_featured' => false,
                'show_on_landing' => true,
                'tier_rank' => 3,
                'sort_order' => 30,
                'module_codes' => [
                    'users',
                    'notifications',
                    'files',
                    'commercial',
                    'catalog',
                    'orders',
                    'checkout',
                    'finance',
                    'pdv',
                    'crm',
                    'reports',
                    'inventory',
                ],
            ],
            [
                'niche' => Plan::NICHE_SERVICES,
                'name' => 'Essencial',
                'slug' => 'essencial',
                'badge' => 'Básico',
                'subtitle' => 'Operação de serviços inicial',
                'summary' => 'Plano para iniciar a gestão de serviços com controle essencial.',
                'footer_message' => 'Plano de entrada para prestadores de serviço.',
                'price_monthly' => 99.00,
                'max_admin_users' => 1,
                'user_limit' => 1,
                'storage_limit_gb' => 1,
                'audit_log_retention_days' => 30,
                'description' => 'Plano inicial para serviços com núcleo operacional e financeiro.',
                'features' => $this->features([
                    ['Núcleo de serviços', 'Base do nicho serviços ativada'],
                    ['Financeiro básico', 'Contas e controle financeiro inicial'],
                    ['Notificações', 'Alertas operacionais no painel'],
                    ['Gestão de arquivos', 'Upload e organização de documentos'],
                    ['1 administrador', 'Acesso administrativo individual'],
                ]),
                'is_active' => true,
                'is_featured' => false,
                'show_on_landing' => true,
                'tier_rank' => 1,
                'sort_order' => 10,
                'module_codes' => [
                    'users',
                    'notifications',
                    'files',
                    'services',
                    'services_storefront',
                    'pdv',
                    'finance',
                ],
            ],
            [
                'niche' => Plan::NICHE_SERVICES,
                'name' => 'Profissional',
                'slug' => 'profissional',
                'badge' => 'Mais vendido',
                'subtitle' => 'Gestão de serviços com mais controle',
                'summary' => 'Plano recomendado para estruturar atendimento e gestão.',
                'footer_message' => 'Mais recursos para elevar a operação de serviços.',
                'price_monthly' => 199.00,
                'max_admin_users' => 1,
                'user_limit' => 1,
                'storage_limit_gb' => 3,
                'audit_log_retention_days' => 90,
                'description' => 'Plano intermediário para serviços com catálogo, CRM e relatórios.',
                'features' => $this->features([
                    ['Tudo do Essencial', 'Base consolidada para crescimento'],
                    ['Catálogo de serviços', 'Estrutura de serviços por categoria'],
                    ['CRM de clientes', 'Histórico e relacionamento'],
                    ['Relatórios operacionais', 'Métricas e exportação de dados'],
                    ['1 administrador', 'Gestão centralizada'],
                ]),
                'is_active' => true,
                'is_featured' => true,
                'show_on_landing' => true,
                'tier_rank' => 2,
                'sort_order' => 20,
                'module_codes' => [
                    'users',
                    'notifications',
                    'files',
                    'services',
                    'services_storefront',
                    'pdv',
                    'finance',
                    'services_catalog',
                    'tasks',
                    'documents',
                    'crm',
                    'reports',
                ],
            ],
            [
                'niche' => Plan::NICHE_SERVICES,
                'name' => 'Escala',
                'slug' => 'escala',
                'badge' => 'Mais completo',
                'subtitle' => 'Operação de serviços em expansão',
                'summary' => 'Plano completo para ampliar atendimento e controle operacional.',
                'footer_message' => 'Para operação de serviços com mais volume e governança.',
                'price_monthly' => 499.00,
                'max_admin_users' => 3,
                'user_limit' => 3,
                'storage_limit_gb' => 10,
                'audit_log_retention_days' => 365,
                'description' => 'Plano avançado para serviços com ordens, agenda e até três admins.',
                'features' => $this->features([
                    ['Tudo do Profissional', 'Pacote completo para escalar'],
                    ['Ordens de serviço', 'Acompanhamento de execução e status'],
                    ['Agenda operacional', 'Organização de atendimentos e compromissos'],
                    ['Até 3 administradores', 'Mais gestão para o crescimento'],
                    ['Histórico anual de auditoria', 'Rastreabilidade por 365 dias'],
                ]),
                'is_active' => true,
                'is_featured' => false,
                'show_on_landing' => true,
                'tier_rank' => 3,
                'sort_order' => 30,
                'module_codes' => [
                    'users',
                    'notifications',
                    'files',
                    'services',
                    'services_storefront',
                    'pdv',
                    'finance',
                    'services_catalog',
                    'service_orders',
                    'schedule',
                    'tasks',
                    'documents',
                    'crm',
                    'reports',
                ],
            ],
        ];
    }

    /**
     * @param array<int, array{0: string, 1: string}> $items
     * @return array<int, array{label: string, value: string, icon: string, enabled: bool}>
     */
    private function features(array $items): array
    {
        return collect($items)
            ->map(static fn (array $item): array => [
                'label' => trim((string) ($item[0] ?? '')),
                'value' => trim((string) ($item[1] ?? '')),
                'icon' => 'CheckCircle2',
                'enabled' => true,
            ])
            ->values()
            ->all();
    }
}
