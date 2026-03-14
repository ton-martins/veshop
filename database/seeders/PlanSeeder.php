<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $plans = [
            [
                'niche' => Plan::NICHE_COMMERCIAL,
                'name' => 'Essencial',
                'slug' => 'start',
                'badge' => null,
                'subtitle' => 'Comércio em fase inicial',
                'summary' => 'Para lojas que estão estruturando operação, catálogo e vendas.',
                'footer_message' => 'Base para iniciar com controle de produtos, clientes e caixa.',
                'price_monthly' => 79.00,
                'max_admin_users' => 1,
                'user_limit' => 1,
                'storage_limit_gb' => 1,
                'audit_log_retention_days' => 30,
                'description' => 'Plano inicial para operação comercial com gestão prática do dia a dia.',
                'features' => [
                    ['label' => 'Produtos e categorias', 'value' => 'Cadastro completo de catálogo', 'icon' => 'Database', 'enabled' => true],
                    ['label' => 'Clientes e fornecedores', 'value' => 'Base inicial de relacionamento', 'icon' => 'Users', 'enabled' => true],
                    ['label' => 'Fluxo de vendas', 'value' => 'Pedidos e caixa operacional', 'icon' => 'Gauge', 'enabled' => true],
                ],
                'is_featured' => false,
                'show_on_landing' => true,
                'tier_rank' => 1,
                'sort_order' => 10,
            ],
            [
                'niche' => Plan::NICHE_COMMERCIAL,
                'name' => 'Profissional',
                'slug' => 'pro',
                'badge' => 'Mais escolhido',
                'subtitle' => 'Comércio em crescimento',
                'summary' => 'Para operações com maior volume, controle de estoque e rotina financeira.',
                'footer_message' => 'Equilíbrio entre escalabilidade, governança e custo operacional.',
                'price_monthly' => 159.00,
                'max_admin_users' => 1,
                'user_limit' => 1,
                'storage_limit_gb' => 1,
                'audit_log_retention_days' => 90,
                'description' => 'Plano para lojas em expansão com foco em produtividade e previsibilidade.',
                'features' => [
                    ['label' => 'Estoque avançado', 'value' => 'Movimentações e acompanhamento por item', 'icon' => 'HardDrive', 'enabled' => true],
                    ['label' => 'Financeiro', 'value' => 'Contas a pagar e a receber', 'icon' => 'Gauge', 'enabled' => true],
                    ['label' => 'Relatórios', 'value' => 'Indicadores operacionais e comerciais', 'icon' => 'CheckCircle2', 'enabled' => true],
                ],
                'is_featured' => true,
                'show_on_landing' => true,
                'tier_rank' => 2,
                'sort_order' => 20,
            ],
            [
                'niche' => Plan::NICHE_COMMERCIAL,
                'name' => 'Escala',
                'slug' => 'business',
                'badge' => 'Alta performance',
                'subtitle' => 'Comércio consolidado',
                'summary' => 'Para redes e operações com demanda elevada e múltiplas equipes.',
                'footer_message' => 'Capacidade ampliada para crescimento contínuo do varejo.',
                'price_monthly' => 799.00,
                'max_admin_users' => 2,
                'user_limit' => 10,
                'storage_limit_gb' => 5,
                'audit_log_retention_days' => 180,
                'description' => 'Plano para empresas comerciais maduras com foco em escala e governança.',
                'features' => [
                    ['label' => 'Usuários', 'value' => 'Ilimitados', 'icon' => 'Users', 'enabled' => true],
                    ['label' => 'Operação multiunidade', 'value' => 'Estrutura para expansão', 'icon' => 'Server', 'enabled' => true],
                    ['label' => 'Suporte prioritário', 'value' => 'Acompanhamento consultivo', 'icon' => 'Bell', 'enabled' => true],
                ],
                'is_featured' => false,
                'show_on_landing' => true,
                'tier_rank' => 3,
                'sort_order' => 30,
            ],
            [
                'niche' => Plan::NICHE_SERVICES,
                'name' => 'Essencial',
                'slug' => 'start',
                'badge' => null,
                'subtitle' => 'Serviços em fase inicial',
                'summary' => 'Para equipes pequenas que estão estruturando catálogo e agenda.',
                'footer_message' => 'Ideal para padronizar atendimento e execução diária.',
                'price_monthly' => 79.00,
                'max_admin_users' => 1,
                'user_limit' => 1,
                'storage_limit_gb' => 1,
                'audit_log_retention_days' => 30,
                'description' => 'Plano inicial para empresas de serviços com operação organizada.',
                'features' => [
                    ['label' => 'Catálogo de serviços', 'value' => 'Itens e categorias cadastrados', 'icon' => 'Database', 'enabled' => true],
                    ['label' => 'Agenda', 'value' => 'Gestão diária da equipe', 'icon' => 'Gauge', 'enabled' => true],
                    ['label' => 'Ordens de serviço', 'value' => 'Fluxo operacional básico', 'icon' => 'CheckCircle2', 'enabled' => true],
                ],
                'is_featured' => false,
                'show_on_landing' => true,
                'tier_rank' => 1,
                'sort_order' => 10,
            ],
            [
                'niche' => Plan::NICHE_SERVICES,
                'name' => 'Profissional',
                'slug' => 'pro',
                'badge' => 'Mais vendido',
                'subtitle' => 'Serviços em crescimento',
                'summary' => 'Para equipes com maior volume de ordens e controle de produtividade.',
                'footer_message' => 'Mais previsibilidade para atendimento e entrega.',
                'price_monthly' => 159.00,
                'max_admin_users' => 1,
                'user_limit' => 1,
                'storage_limit_gb' => 1,
                'audit_log_retention_days' => 120,
                'description' => 'Plano para empresas de serviços em expansão e com rotina intensa.',
                'features' => [
                    ['label' => 'Agenda avançada', 'value' => 'Visão de equipe e disponibilidade', 'icon' => 'Gauge', 'enabled' => true],
                    ['label' => 'Ordens com status', 'value' => 'Acompanhamento de ponta a ponta', 'icon' => 'Server', 'enabled' => true],
                    ['label' => 'Indicadores', 'value' => 'Produtividade e desempenho operacional', 'icon' => 'Star', 'enabled' => true],
                ],
                'is_featured' => true,
                'show_on_landing' => true,
                'tier_rank' => 2,
                'sort_order' => 20,
            ],
            [
                'niche' => Plan::NICHE_SERVICES,
                'name' => 'Escala',
                'slug' => 'business',
                'badge' => 'Alta capacidade',
                'subtitle' => 'Operação de serviços consolidada',
                'summary' => 'Para empresas com grande volume de atendimentos e múltiplas equipes.',
                'footer_message' => 'Estrutura robusta para governança e escala de operação.',
                'price_monthly' => 799.00,
                'max_admin_users' => 2,
                'user_limit' => 10,
                'storage_limit_gb' => 5,
                'audit_log_retention_days' => 365,
                'description' => 'Plano para operações de serviços maduras com foco em performance e SLA.',
                'features' => [
                    ['label' => 'Usuários', 'value' => 'Ilimitados', 'icon' => 'Users', 'enabled' => true],
                    ['label' => 'SLA e auditoria', 'value' => 'Retenção ampliada e governança', 'icon' => 'ShieldCheck', 'enabled' => true],
                    ['label' => 'Suporte consultivo', 'value' => 'Acompanhamento contínuo', 'icon' => 'Bell', 'enabled' => true],
                ],
                'is_featured' => false,
                'show_on_landing' => true,
                'tier_rank' => 3,
                'sort_order' => 30,
            ],
        ];

        foreach ($plans as $data) {
            Plan::query()->updateOrCreate(
                [
                    'niche' => $data['niche'],
                    'slug' => $data['slug'] ?: Str::slug($data['name']),
                ],
                [
                    'name' => $data['name'],
                    'badge' => $data['badge'],
                    'subtitle' => $data['subtitle'],
                    'summary' => $data['summary'],
                    'footer_message' => $data['footer_message'],
                    'price_monthly' => $data['price_monthly'],
                    'max_admin_users' => $data['max_admin_users'],
                    'user_limit' => $data['user_limit'],
                    'storage_limit_gb' => $data['storage_limit_gb'],
                    'audit_log_retention_days' => $data['audit_log_retention_days'],
                    'description' => $data['description'],
                    'features' => $data['features'],
                    'is_active' => true,
                    'is_featured' => $data['is_featured'],
                    'show_on_landing' => $data['show_on_landing'],
                    'tier_rank' => $data['tier_rank'],
                    'sort_order' => $data['sort_order'],
                ]
            );
        }
    }
}
