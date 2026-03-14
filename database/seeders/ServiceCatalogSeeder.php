<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\ServiceCatalog;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceCatalogSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $serviceContractors = Contractor::query()
            ->get()
            ->filter(static fn (Contractor $contractor): bool => $contractor->hasModule(Contractor::MODULE_SERVICES))
            ->values();

        foreach ($serviceContractors as $contractor) {
            $data = $this->resolveCatalog();
            $categories = $this->seedCategories($contractor, $data['categories']);
            $this->seedServices($contractor, $categories, $data['services']);
        }
    }

    /**
     * @return array{
     *   categories: array<int, array{name: string, description: string}>,
     *   services: array<int, array{
     *     code: string,
     *     name: string,
     *     category_slug: string,
     *     description: string,
     *     duration_minutes: int,
     *     base_price: float
     *   }>
     * }
     */
    private function resolveCatalog(): array
    {
        return [
            'categories' => [
                ['name' => 'Diagnóstico', 'description' => 'Avaliação técnica e análise inicial'],
                ['name' => 'Instalação', 'description' => 'Implantação e configuração'],
                ['name' => 'Manutenção', 'description' => 'Preventiva e corretiva'],
                ['name' => 'Suporte', 'description' => 'Atendimento remoto e presencial'],
                ['name' => 'Consultoria', 'description' => 'Planejamento e melhoria de processos'],
            ],
            'services' => [
                [
                    'code' => 'SER-001',
                    'name' => 'Diagnóstico técnico completo',
                    'category_slug' => 'diagnostico',
                    'description' => 'Mapeamento de falhas e plano de ação.',
                    'duration_minutes' => 90,
                    'base_price' => 220.00,
                ],
                [
                    'code' => 'SER-002',
                    'name' => 'Instalação de equipamento',
                    'category_slug' => 'instalacao',
                    'description' => 'Configuração inicial e validação operacional.',
                    'duration_minutes' => 120,
                    'base_price' => 320.00,
                ],
                [
                    'code' => 'SER-003',
                    'name' => 'Manutenção preventiva mensal',
                    'category_slug' => 'manutencao',
                    'description' => 'Checklist técnico e ajustes preventivos.',
                    'duration_minutes' => 180,
                    'base_price' => 540.00,
                ],
                [
                    'code' => 'SER-004',
                    'name' => 'Suporte emergencial',
                    'category_slug' => 'suporte',
                    'description' => 'Atendimento prioritário para incidentes críticos.',
                    'duration_minutes' => 60,
                    'base_price' => 180.00,
                ],
                [
                    'code' => 'SER-005',
                    'name' => 'Consultoria operacional',
                    'category_slug' => 'consultoria',
                    'description' => 'Revisão de fluxo e recomendações de eficiência.',
                    'duration_minutes' => 150,
                    'base_price' => 650.00,
                ],
            ],
        ];
    }

    /**
     * @param array<int, array{name: string, description: string}> $seedCategories
     * @return array<string, \App\Models\ServiceCategory>
     */
    private function seedCategories(Contractor $contractor, array $seedCategories): array
    {
        $map = [];

        foreach ($seedCategories as $index => $entry) {
            $slug = Str::slug($entry['name']);

            $category = ServiceCategory::query()->updateOrCreate(
                [
                    'contractor_id' => $contractor->id,
                    'slug' => $slug,
                ],
                [
                    'name' => $entry['name'],
                    'description' => $entry['description'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ]
            );

            $map[$slug] = $category;
        }

        return $map;
    }

    /**
     * @param array<string, \App\Models\ServiceCategory> $categories
     * @param array<int, array{
     *   code: string,
     *   name: string,
     *   category_slug: string,
     *   description: string,
     *   duration_minutes: int,
     *   base_price: float
     * }> $seedServices
     */
    private function seedServices(Contractor $contractor, array $categories, array $seedServices): void
    {
        foreach ($seedServices as $entry) {
            $category = $categories[$entry['category_slug']] ?? null;

            ServiceCatalog::query()->updateOrCreate(
                [
                    'contractor_id' => $contractor->id,
                    'code' => $entry['code'],
                ],
                [
                    'service_category_id' => $category?->id,
                    'name' => $entry['name'],
                    'description' => $entry['description'],
                    'duration_minutes' => $entry['duration_minutes'],
                    'base_price' => $entry['base_price'],
                    'is_active' => true,
                ]
            );
        }
    }
}

