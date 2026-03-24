<?php

namespace App\Application\Reports\Services;

use App\Models\Contractor;

class ReportMetricCatalog
{
    /**
     * @return array<int, array<string, string>>
     */
    public function definitionsForNiche(string $niche): array
    {
        return match (Contractor::normalizeNiche($niche)) {
            Contractor::NICHE_SERVICES => $this->servicesDefinitions(),
            default => $this->commercialDefinitions(),
        };
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function commercialDefinitions(): array
    {
        return [
            [
                'key' => 'commercial_revenue',
                'label' => 'Faturamento no período',
                'description' => 'Pedidos pagos e concluídos',
                'format' => 'currency',
                'tone' => 'bg-emerald-100 text-emerald-700',
            ],
            [
                'key' => 'commercial_orders',
                'label' => 'Pedidos processados',
                'description' => 'Pedidos válidos no período',
                'format' => 'integer',
                'tone' => 'bg-blue-100 text-blue-700',
            ],
            [
                'key' => 'commercial_average_ticket',
                'label' => 'Ticket médio',
                'description' => 'Média por pedido faturado',
                'format' => 'currency',
                'tone' => 'bg-amber-100 text-amber-700',
            ],
            [
                'key' => 'commercial_active_products',
                'label' => 'Produtos ativos',
                'description' => 'Catálogo disponível para venda',
                'format' => 'integer',
                'tone' => 'bg-slate-100 text-slate-700',
            ],
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function servicesDefinitions(): array
    {
        return [
            [
                'key' => 'services_revenue',
                'label' => 'Faturamento no período',
                'description' => 'Ordens finalizadas e faturadas',
                'format' => 'currency',
                'tone' => 'bg-emerald-100 text-emerald-700',
            ],
            [
                'key' => 'services_completed_orders',
                'label' => 'Ordens concluídas',
                'description' => 'Ordens encerradas no período',
                'format' => 'integer',
                'tone' => 'bg-blue-100 text-blue-700',
            ],
            [
                'key' => 'services_appointments',
                'label' => 'Atendimentos agendados',
                'description' => 'Agenda ativa no período',
                'format' => 'integer',
                'tone' => 'bg-amber-100 text-amber-700',
            ],
            [
                'key' => 'services_active_catalog',
                'label' => 'Serviços ativos',
                'description' => 'Itens disponíveis para agendamento',
                'format' => 'integer',
                'tone' => 'bg-slate-100 text-slate-700',
            ],
        ];
    }
}
