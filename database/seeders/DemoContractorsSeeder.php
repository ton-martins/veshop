<?php

namespace Database\Seeders;

use App\Models\AccountingDocumentRequest;
use App\Models\AccountingFeeEntry;
use App\Models\AccountingObligation;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ServiceCatalog;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoContractorsSeeder extends Seeder
{
    /**
     * Seed demo contractors and catalog data.
     */
    public function run(): void
    {
        $commercialScalePlan = Plan::query()
            ->where('niche', Plan::NICHE_COMMERCIAL)
            ->where('name', 'Escala')
            ->firstOrFail();

        $servicesScalePlan = Plan::query()
            ->where('niche', Plan::NICHE_SERVICES)
            ->where('name', 'Escala')
            ->firstOrFail();

        $admin = User::query()->updateOrCreate(
            ['email' => 'evertonjunior1015@hotmail.com'],
            [
                'name' => 'Everton Martins',
                'password' => Hash::make('@veshop_2026'),
                'role' => User::ROLE_ADMIN,
                'job_title' => 'Administrador',
                'phone' => '(11) 99777-1015',
                'email_verified_at' => now(),
                'is_active' => true,
                'password_changed_at' => now(),
            ]
        );

        $vstore = $this->upsertContractor(
            plan: $commercialScalePlan,
            payload: [
                'name' => 'VStore',
                'email' => 'contato@vstore.com.br',
                'phone' => '(11) 98888-1001',
                'cnpj' => '27865757000108',
                'slug' => 'vstore',
                'brand_name' => 'VStore',
                'brand_primary_color' => '#0F172A',
                'brand_logo_url' => $this->loremImage('store,brand', 1),
                'brand_avatar_url' => $this->loremImage('home,decor', 2),
                'business_niche' => Contractor::NICHE_COMMERCIAL,
                'business_type' => Contractor::BUSINESS_TYPE_STORE,
                'address' => [
                    'cep' => '01310-100',
                    'street' => 'Avenida Paulista',
                    'number' => '1700',
                    'complement' => 'Sala 11',
                    'neighborhood' => 'Bela Vista',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                ],
                'shop_storefront' => [
                    'template' => 'comercio',
                    'blocks' => [
                        'hero' => true,
                        'banners' => false,
                        'promotions' => true,
                        'categories' => true,
                        'catalog' => true,
                    ],
                    'hero' => [
                        'title' => 'Casa, organização e decoração em um só lugar',
                        'subtitle' => 'Produtos selecionados para deixar cada ambiente mais funcional e bonito.',
                        'cta_label' => 'Ver produtos',
                    ],
                    'promotions' => [
                        'title' => 'Destaques da semana',
                        'subtitle' => 'Itens de cozinha, mesa posta e decoração com ótimo custo-benefício.',
                        'product_ids' => [],
                    ],
                    'catalog' => [
                        'title' => 'Catálogo VStore',
                        'subtitle' => 'Escolha por categoria e compre direto na loja virtual.',
                    ],
                ],
                'shop_shipping' => [
                    'pickup_enabled' => true,
                    'delivery_enabled' => true,
                    'fixed_fee' => 14.90,
                    'free_over' => 250.00,
                    'estimated_days' => 3,
                ],
            ],
        );

        $vbarber = $this->upsertContractor(
            plan: $servicesScalePlan,
            payload: [
                'name' => 'VBarber',
                'email' => 'contato@vbarber.com.br',
                'phone' => '(11) 98888-1002',
                'cnpj' => '19131243000170',
                'slug' => 'vbarber',
                'brand_name' => 'VBarber',
                'brand_primary_color' => '#7C4A2D',
                'brand_logo_url' => $this->loremImage('barbershop,logo', 3),
                'brand_avatar_url' => $this->loremImage('barbershop,haircut', 4),
                'business_niche' => Contractor::NICHE_SERVICES,
                'business_type' => Contractor::BUSINESS_TYPE_BARBERSHOP,
                'address' => [
                    'cep' => '30130-110',
                    'street' => 'Avenida Afonso Pena',
                    'number' => '980',
                    'complement' => 'Loja 02',
                    'neighborhood' => 'Centro',
                    'city' => 'Belo Horizonte',
                    'state' => 'MG',
                ],
                'shop_storefront' => [
                    'template' => 'servicos',
                    'blocks' => [
                        'hero' => true,
                        'banners' => false,
                        'promotions' => true,
                        'categories' => true,
                        'catalog' => true,
                    ],
                    'hero' => [
                        'title' => 'Agende seu horário na VBarber',
                        'subtitle' => 'Cortes modernos, barba premium e atendimento especializado.',
                        'cta_label' => 'Agendar agora',
                    ],
                    'promotions' => [
                        'title' => 'Serviços em destaque',
                        'subtitle' => 'Pacotes completos para elevar seu visual.',
                        'product_ids' => [],
                    ],
                    'catalog' => [
                        'title' => 'Catálogo VBarber',
                        'subtitle' => 'Escolha o serviço, horário e finalize o agendamento online.',
                    ],
                ],
            ],
        );

        $vfinances = $this->upsertContractor(
            plan: $servicesScalePlan,
            payload: [
                'name' => 'Vfinances',
                'email' => 'contato@vfinances.com.br',
                'phone' => '(11) 98888-1003',
                'cnpj' => '48564079000166',
                'slug' => 'vfinances',
                'brand_name' => 'Vfinances',
                'brand_primary_color' => '#0B3B53',
                'brand_logo_url' => $this->loremImage('accounting,office', 5),
                'brand_avatar_url' => $this->loremImage('finance,business', 6),
                'business_niche' => Contractor::NICHE_SERVICES,
                'business_type' => Contractor::BUSINESS_TYPE_ACCOUNTING,
                'address' => [
                    'cep' => '80010-000',
                    'street' => 'Rua Marechal Deodoro',
                    'number' => '455',
                    'complement' => '8º andar',
                    'neighborhood' => 'Centro',
                    'city' => 'Curitiba',
                    'state' => 'PR',
                ],
                'shop_storefront' => [
                    'template' => 'servicos',
                    'blocks' => [
                        'hero' => true,
                        'banners' => false,
                        'promotions' => true,
                        'categories' => true,
                        'catalog' => true,
                    ],
                    'hero' => [
                        'title' => 'Gestão contábil para empresas em crescimento',
                        'subtitle' => 'Atendimento consultivo para fiscal, trabalhista e contábil.',
                        'cta_label' => 'Solicitar atendimento',
                    ],
                    'promotions' => [
                        'title' => 'Serviços estratégicos',
                        'subtitle' => 'Pacotes contábeis com foco em compliance e resultado.',
                        'product_ids' => [],
                    ],
                    'catalog' => [
                        'title' => 'Catálogo Vfinances',
                        'subtitle' => 'Conheça os serviços e envie sua solicitação online.',
                    ],
                ],
            ],
        );

        $admin->contractors()->syncWithoutDetaching([
            $vstore->id,
            $vbarber->id,
            $vfinances->id,
        ]);

        $this->seedVStoreCatalog($vstore);
        $this->seedVBarberCatalog($vbarber);
        $this->seedVFinancesData($vfinances);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsertContractor(Plan $plan, array $payload): Contractor
    {
        $slug = (string) ($payload['slug'] ?? '');
        $businessNiche = Contractor::normalizeNiche((string) ($payload['business_niche'] ?? Contractor::NICHE_COMMERCIAL));
        $businessType = Contractor::normalizeBusinessType((string) ($payload['business_type'] ?? ''), $businessNiche);

        $contractor = Contractor::withTrashed()->firstOrNew([
            'slug' => $slug,
        ]);

        if (! $contractor->exists) {
            $contractor->uuid = (string) Str::uuid();
        } elseif ($contractor->trashed()) {
            $contractor->restore();
        }

        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $settings['business_niche'] = $businessNiche;
        $settings['active_plan_name'] = (string) $plan->name;
        $settings['require_2fa'] = true;
        $settings['require_email_verification'] = true;
        $settings['email_notifications_enabled'] = true;
        $settings['shop_storefront'] = is_array($payload['shop_storefront'] ?? null)
            ? $payload['shop_storefront']
            : [];

        if (is_array($payload['shop_shipping'] ?? null)) {
            $settings['shop_shipping'] = $payload['shop_shipping'];
        } else {
            unset($settings['shop_shipping']);
        }

        $contractor->fill([
            'name' => (string) $payload['name'],
            'email' => strtolower(trim((string) $payload['email'])),
            'phone' => (string) ($payload['phone'] ?? ''),
            'cnpj' => (string) ($payload['cnpj'] ?? ''),
            'plan_id' => (int) $plan->id,
            'contract_starts_at' => now()->startOfMonth()->toDateString(),
            'contract_ends_at' => now()->addYear()->endOfMonth()->toDateString(),
            'timezone' => 'America/Sao_Paulo',
            'address' => is_array($payload['address'] ?? null) ? $payload['address'] : null,
            'brand_name' => (string) ($payload['brand_name'] ?? $payload['name']),
            'brand_primary_color' => (string) ($payload['brand_primary_color'] ?? '#073341'),
            'brand_logo_url' => (string) ($payload['brand_logo_url'] ?? ''),
            'brand_avatar_url' => (string) ($payload['brand_avatar_url'] ?? ''),
            'settings' => $settings,
            'business_type' => $businessType,
            'is_active' => true,
        ]);

        $contractor->save();

        $plan->loadMissing('modules:id');
        $moduleIds = $plan->modules
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $contractor->modules()->sync($moduleIds);

        return $contractor;
    }

    private function seedVStoreCatalog(Contractor $contractor): void
    {
        $categories = [
            ['slug' => 'utensilios-cozinha', 'name' => 'Utensílios de Cozinha', 'description' => 'Itens práticos para preparo e finalização de receitas.'],
            ['slug' => 'organizacao-cozinha', 'name' => 'Organização da Cozinha', 'description' => 'Soluções para otimizar espaço e rotina culinária.'],
            ['slug' => 'mesa-posta', 'name' => 'Mesa Posta', 'description' => 'Peças para compor uma mesa elegante no dia a dia ou eventos.'],
            ['slug' => 'decoracao-sala', 'name' => 'Decoração de Sala', 'description' => 'Elementos decorativos para salas modernas e acolhedoras.'],
            ['slug' => 'decoracao-quarto', 'name' => 'Decoração de Quarto', 'description' => 'Conforto e estilo para ambientes de descanso.'],
            ['slug' => 'iluminacao-decorativa', 'name' => 'Iluminação Decorativa', 'description' => 'Luminárias e soluções de iluminação para diferentes espaços.'],
            ['slug' => 'plantas-vasos', 'name' => 'Plantas e Vasos', 'description' => 'Composições verdes para valorizar ambientes internos.'],
            ['slug' => 'aromas-velas', 'name' => 'Aromas e Velas', 'description' => 'Produtos para perfumar e criar experiências sensoriais.'],
            ['slug' => 'banheiro-spa', 'name' => 'Banheiro e Spa', 'description' => 'Itens para transformar o banheiro em ambiente de bem-estar.'],
            ['slug' => 'home-office', 'name' => 'Home Office', 'description' => 'Produtos funcionais para produtividade e ergonomia.'],
        ];

        $categoryMap = [];
        foreach ($categories as $index => $item) {
            $category = Category::withTrashed()->firstOrNew([
                'contractor_id' => $contractor->id,
                'slug' => $item['slug'],
            ]);

            if ($category->exists && $category->trashed()) {
                $category->restore();
            }

            $category->fill([
                'name' => $item['name'],
                'description' => $item['description'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);

            $category->save();
            $categoryMap[$item['slug']] = (int) $category->id;
        }

        $products = [
            ['sku' => 'VST-001', 'name' => 'Kit Talheres Inox 24 Peças', 'category_slug' => 'utensilios-cozinha', 'sale_price' => 129.90, 'cost_price' => 78.50, 'stock_quantity' => 35, 'description' => 'Conjunto em aço inox com acabamento premium para uso diário.', 'image_url' => $this->loremImage('kitchen,cutlery', 101)],
            ['sku' => 'VST-002', 'name' => 'Jogo de Facas Chef 6 Peças', 'category_slug' => 'utensilios-cozinha', 'sale_price' => 189.90, 'cost_price' => 119.40, 'stock_quantity' => 28, 'description' => 'Facas de alta precisão com cabo ergonômico e suporte.', 'image_url' => $this->loremImage('kitchen,knife', 102)],
            ['sku' => 'VST-003', 'name' => 'Organizador Acrílico de Gavetas', 'category_slug' => 'organizacao-cozinha', 'sale_price' => 59.90, 'cost_price' => 29.90, 'stock_quantity' => 60, 'description' => 'Módulos transparentes para separar talheres e utensílios.', 'image_url' => $this->loremImage('kitchen,organizer', 103)],
            ['sku' => 'VST-004', 'name' => 'Porta Temperos Giratório', 'category_slug' => 'organizacao-cozinha', 'sale_price' => 79.90, 'cost_price' => 45.20, 'stock_quantity' => 42, 'description' => 'Suporte com 12 frascos e base giratória para bancada.', 'image_url' => $this->loremImage('kitchen,spices', 104)],
            ['sku' => 'VST-005', 'name' => 'Jogo Americano de Linho 4 Lugares', 'category_slug' => 'mesa-posta', 'sale_price' => 99.90, 'cost_price' => 52.70, 'stock_quantity' => 44, 'description' => 'Kit com acabamento reforçado e textura sofisticada.', 'image_url' => $this->loremImage('table,linen', 105)],
            ['sku' => 'VST-006', 'name' => 'Jogo de Taças Cristal 6 Unidades', 'category_slug' => 'mesa-posta', 'sale_price' => 169.90, 'cost_price' => 104.90, 'stock_quantity' => 24, 'description' => 'Taças de cristal para vinho e drinks especiais.', 'image_url' => $this->loremImage('table,glassware', 106)],
            ['sku' => 'VST-007', 'name' => 'Almofada Decorativa Tricot 45x45', 'category_slug' => 'decoracao-sala', 'sale_price' => 89.90, 'cost_price' => 49.30, 'stock_quantity' => 50, 'description' => 'Capa texturizada com enchimento macio e toque aconchegante.', 'image_url' => $this->loremImage('livingroom,cushion', 107)],
            ['sku' => 'VST-008', 'name' => 'Manta Decorativa Soft', 'category_slug' => 'decoracao-sala', 'sale_price' => 119.90, 'cost_price' => 65.00, 'stock_quantity' => 32, 'description' => 'Manta leve para sofá, poltrona ou cama.', 'image_url' => $this->loremImage('livingroom,blanket', 108)],
            ['sku' => 'VST-009', 'name' => 'Kit Cama Casal Minimalista', 'category_slug' => 'decoracao-quarto', 'sale_price' => 249.90, 'cost_price' => 158.40, 'stock_quantity' => 18, 'description' => 'Conjunto com colcha e fronhas em tecido respirável.', 'image_url' => $this->loremImage('bedroom,bedding', 109)],
            ['sku' => 'VST-010', 'name' => 'Abajur de Mesa Clean', 'category_slug' => 'decoracao-quarto', 'sale_price' => 139.90, 'cost_price' => 88.20, 'stock_quantity' => 22, 'description' => 'Abajur com luz suave para leitura e ambientação.', 'image_url' => $this->loremImage('bedroom,lamp', 110)],
            ['sku' => 'VST-011', 'name' => 'Luminária Pendente Industrial', 'category_slug' => 'iluminacao-decorativa', 'sale_price' => 219.90, 'cost_price' => 134.90, 'stock_quantity' => 16, 'description' => 'Peça de destaque para sala de jantar e cozinha gourmet.', 'image_url' => $this->loremImage('lighting,pendant', 111)],
            ['sku' => 'VST-012', 'name' => 'Fita LED Smart 5m', 'category_slug' => 'iluminacao-decorativa', 'sale_price' => 129.90, 'cost_price' => 75.10, 'stock_quantity' => 46, 'description' => 'Iluminação inteligente com ajuste de cor e intensidade.', 'image_url' => $this->loremImage('lighting,led', 112)],
            ['sku' => 'VST-013', 'name' => 'Vaso de Cerâmica Fosco G', 'category_slug' => 'plantas-vasos', 'sale_price' => 89.90, 'cost_price' => 44.90, 'stock_quantity' => 30, 'description' => 'Vaso decorativo para plantas naturais ou permanentes.', 'image_url' => $this->loremImage('vase,ceramic', 113)],
            ['sku' => 'VST-014', 'name' => 'Jardim Vertical Artificial', 'category_slug' => 'plantas-vasos', 'sale_price' => 199.90, 'cost_price' => 118.00, 'stock_quantity' => 14, 'description' => 'Painel verde para ambientes internos com baixa manutenção.', 'image_url' => $this->loremImage('plants,decor', 114)],
            ['sku' => 'VST-015', 'name' => 'Vela Aromática Lavanda 200g', 'category_slug' => 'aromas-velas', 'sale_price' => 49.90, 'cost_price' => 22.70, 'stock_quantity' => 65, 'description' => 'Fragrância calmante com queima uniforme e longa duração.', 'image_url' => $this->loremImage('candle,lavender', 115)],
            ['sku' => 'VST-016', 'name' => 'Difusor de Ambiente Bamboo 250ml', 'category_slug' => 'aromas-velas', 'sale_price' => 69.90, 'cost_price' => 33.90, 'stock_quantity' => 58, 'description' => 'Perfume elegante para sala, quarto e escritório.', 'image_url' => $this->loremImage('diffuser,aroma', 116)],
            ['sku' => 'VST-017', 'name' => 'Kit Toalhas Premium 5 Peças', 'category_slug' => 'banheiro-spa', 'sale_price' => 159.90, 'cost_price' => 95.00, 'stock_quantity' => 27, 'description' => 'Toalhas de alta absorção e toque macio.', 'image_url' => $this->loremImage('bath,towels', 117)],
            ['sku' => 'VST-018', 'name' => 'Porta Sabonete Líquido Mármore', 'category_slug' => 'banheiro-spa', 'sale_price' => 59.90, 'cost_price' => 31.80, 'stock_quantity' => 40, 'description' => 'Acessório sofisticado para compor o lavabo.', 'image_url' => $this->loremImage('bathroom,decor', 118)],
            ['sku' => 'VST-019', 'name' => 'Suporte para Notebook Ergonômico', 'category_slug' => 'home-office', 'sale_price' => 149.90, 'cost_price' => 82.90, 'stock_quantity' => 33, 'description' => 'Ajuste de altura para melhor postura em home office.', 'image_url' => $this->loremImage('office,laptop', 119)],
            ['sku' => 'VST-020', 'name' => 'Organizador de Mesa em Bambu', 'category_slug' => 'home-office', 'sale_price' => 79.90, 'cost_price' => 43.60, 'stock_quantity' => 48, 'description' => 'Compartimentos para canetas, papéis e acessórios.', 'image_url' => $this->loremImage('office,desk', 120)],
        ];

        foreach ($products as $index => $item) {
            $product = Product::withTrashed()->firstOrNew([
                'contractor_id' => $contractor->id,
                'sku' => $item['sku'],
            ]);

            if ($product->exists && $product->trashed()) {
                $product->restore();
            }

            $product->fill([
                'category_id' => $categoryMap[$item['category_slug']] ?? null,
                'name' => $item['name'],
                'description' => $item['description'],
                'cost_price' => $item['cost_price'],
                'sale_price' => $item['sale_price'],
                'stock_quantity' => $item['stock_quantity'],
                'unit' => 'un',
                'image_url' => $item['image_url'],
                'is_active' => true,
                'is_pdv_featured' => $index < 8,
                'pdv_featured_order' => $index < 8 ? ($index + 1) : 99,
            ]);

            $product->save();
        }
    }

    private function seedVBarberCatalog(Contractor $contractor): void
    {
        $categories = [
            ['slug' => 'cortes-classicos', 'name' => 'Cortes Clássicos', 'description' => 'Cortes tradicionais com acabamento de alto padrão.'],
            ['slug' => 'fade-degrade', 'name' => 'Fade e Degradê', 'description' => 'Técnicas modernas para transição e definição de estilo.'],
            ['slug' => 'barba-acabamento', 'name' => 'Barba e Acabamento', 'description' => 'Modelagem, hidratação e cuidados completos com a barba.'],
            ['slug' => 'tratamentos-capilares', 'name' => 'Tratamentos Capilares', 'description' => 'Saúde capilar, hidratação e recuperação dos fios.'],
            ['slug' => 'pacotes-premium', 'name' => 'Pacotes Premium', 'description' => 'Combinações exclusivas para experiência completa.'],
        ];

        $categoryMap = [];
        foreach ($categories as $index => $item) {
            $category = ServiceCategory::withTrashed()->firstOrNew([
                'contractor_id' => $contractor->id,
                'slug' => $item['slug'],
            ]);

            if ($category->exists && $category->trashed()) {
                $category->restore();
            }

            $category->fill([
                'name' => $item['name'],
                'description' => $item['description'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);

            $category->save();
            $categoryMap[$item['slug']] = (int) $category->id;
        }

        $services = [
            ['code' => 'BARB-001', 'name' => 'Corte Clássico Masculino', 'category_slug' => 'cortes-classicos', 'duration_minutes' => 45, 'base_price' => 55.00, 'description' => 'Corte social com tesoura e máquina, finalização profissional.', 'image_url' => $this->loremImage('barbershop,haircut', 201)],
            ['code' => 'BARB-002', 'name' => 'Corte Social com Navalha', 'category_slug' => 'cortes-classicos', 'duration_minutes' => 50, 'base_price' => 62.00, 'description' => 'Acabamento de nuca e laterais com navalha para maior precisão.', 'image_url' => $this->loremImage('barber,classic,hair', 202)],
            ['code' => 'BARB-003', 'name' => 'Degradê Low Fade', 'category_slug' => 'fade-degrade', 'duration_minutes' => 55, 'base_price' => 69.00, 'description' => 'Transição suave e alinhamento de contornos.', 'image_url' => $this->loremImage('haircut,fade', 203)],
            ['code' => 'BARB-004', 'name' => 'Degradê Skin Fade', 'category_slug' => 'fade-degrade', 'duration_minutes' => 60, 'base_price' => 75.00, 'description' => 'Degradê rente com acabamento detalhado.', 'image_url' => $this->loremImage('barber,skinfade', 204)],
            ['code' => 'BARB-005', 'name' => 'Barba Tradicional', 'category_slug' => 'barba-acabamento', 'duration_minutes' => 35, 'base_price' => 42.00, 'description' => 'Desenho da barba com toalha quente e finalização com balm.', 'image_url' => $this->loremImage('barber,beard', 205)],
            ['code' => 'BARB-006', 'name' => 'Barba Terapia Premium', 'category_slug' => 'barba-acabamento', 'duration_minutes' => 45, 'base_price' => 58.00, 'description' => 'Hidratação, massagem facial e acabamento de precisão.', 'image_url' => $this->loremImage('beard,shaving', 206)],
            ['code' => 'BARB-007', 'name' => 'Hidratação Capilar', 'category_slug' => 'tratamentos-capilares', 'duration_minutes' => 40, 'base_price' => 49.00, 'description' => 'Tratamento para brilho, maciez e reposição hídrica.', 'image_url' => $this->loremImage('hair,treatment', 207)],
            ['code' => 'BARB-008', 'name' => 'Reconstrução Capilar', 'category_slug' => 'tratamentos-capilares', 'duration_minutes' => 55, 'base_price' => 85.00, 'description' => 'Recuperação de fios fragilizados com protocolo profissional.', 'image_url' => $this->loremImage('hair,care', 208)],
            ['code' => 'BARB-009', 'name' => 'Pigmentação de Barba', 'category_slug' => 'barba-acabamento', 'duration_minutes' => 30, 'base_price' => 39.00, 'description' => 'Uniformização visual da barba com técnica temporária.', 'image_url' => $this->loremImage('beard,style', 209)],
            ['code' => 'BARB-010', 'name' => 'Combo Corte + Barba', 'category_slug' => 'pacotes-premium', 'duration_minutes' => 80, 'base_price' => 99.00, 'description' => 'Pacote completo com foco em visual e acabamento.', 'image_url' => $this->loremImage('barbershop,combo', 210)],
            ['code' => 'BARB-011', 'name' => 'Dia do Noivo', 'category_slug' => 'pacotes-premium', 'duration_minutes' => 120, 'base_price' => 189.00, 'description' => 'Atendimento especial com corte, barba e cuidados faciais.', 'image_url' => $this->loremImage('groom,barber', 211)],
            ['code' => 'BARB-012', 'name' => 'Design de Sobrancelha Masculina', 'category_slug' => 'pacotes-premium', 'duration_minutes' => 25, 'base_price' => 29.00, 'description' => 'Alinhamento e limpeza para realçar a expressão facial.', 'image_url' => $this->loremImage('barber,eyebrow', 212)],
        ];

        foreach ($services as $item) {
            $service = ServiceCatalog::withTrashed()->firstOrNew([
                'contractor_id' => $contractor->id,
                'code' => $item['code'],
            ]);

            if ($service->exists && $service->trashed()) {
                $service->restore();
            }

            $service->fill([
                'service_category_id' => $categoryMap[$item['category_slug']] ?? null,
                'name' => $item['name'],
                'description' => $item['description'],
                'image_url' => $item['image_url'],
                'duration_minutes' => $item['duration_minutes'],
                'base_price' => $item['base_price'],
                'is_active' => true,
            ]);

            $service->save();
        }
    }

    private function seedVFinancesData(Contractor $contractor): void
    {
        $categories = [
            ['slug' => 'abertura-regularizacao', 'name' => 'Abertura e Regularização', 'description' => 'Processos de abertura, alteração e regularização empresarial.'],
            ['slug' => 'fiscal-tributario', 'name' => 'Fiscal e Tributário', 'description' => 'Apuração, planejamento e conformidade tributária.'],
            ['slug' => 'departamento-pessoal', 'name' => 'Departamento Pessoal', 'description' => 'Rotinas trabalhistas, folha e obrigações acessórias.'],
            ['slug' => 'contabil-mensal', 'name' => 'Contábil Mensal', 'description' => 'Escrituração, demonstrativos e fechamento contábil.'],
            ['slug' => 'consultoria-planejamento', 'name' => 'Consultoria e Planejamento', 'description' => 'Análises gerenciais para tomada de decisão.'],
        ];

        $categoryMap = [];
        foreach ($categories as $index => $item) {
            $category = ServiceCategory::withTrashed()->firstOrNew([
                'contractor_id' => $contractor->id,
                'slug' => $item['slug'],
            ]);

            if ($category->exists && $category->trashed()) {
                $category->restore();
            }

            $category->fill([
                'name' => $item['name'],
                'description' => $item['description'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);

            $category->save();
            $categoryMap[$item['slug']] = (int) $category->id;
        }

        $services = [
            ['code' => 'VCNT-001', 'name' => 'Abertura de Empresa', 'category_slug' => 'abertura-regularizacao', 'duration_minutes' => 90, 'base_price' => 690.00, 'description' => 'Constituição empresarial com enquadramento tributário inicial.', 'image_url' => $this->loremImage('business,documents', 301)],
            ['code' => 'VCNT-002', 'name' => 'Regularização de CNPJ', 'category_slug' => 'abertura-regularizacao', 'duration_minutes' => 75, 'base_price' => 420.00, 'description' => 'Atualização cadastral, alvarás e pendências junto aos órgãos.', 'image_url' => $this->loremImage('company,legal', 302)],
            ['code' => 'VCNT-003', 'name' => 'Planejamento Tributário Mensal', 'category_slug' => 'fiscal-tributario', 'duration_minutes' => 70, 'base_price' => 950.00, 'description' => 'Análise tributária para redução de riscos e otimização de carga.', 'image_url' => $this->loremImage('taxes,planning', 303)],
            ['code' => 'VCNT-004', 'name' => 'Apuração de Impostos (SN)', 'category_slug' => 'fiscal-tributario', 'duration_minutes' => 60, 'base_price' => 320.00, 'description' => 'Cálculo e conferência de tributos para Simples Nacional.', 'image_url' => $this->loremImage('tax,calculator', 304)],
            ['code' => 'VCNT-005', 'name' => 'Escrituração Contábil Mensal', 'category_slug' => 'contabil-mensal', 'duration_minutes' => 80, 'base_price' => 780.00, 'description' => 'Lançamentos contábeis com conciliação e fechamento mensal.', 'image_url' => $this->loremImage('accounting,desk', 305)],
            ['code' => 'VCNT-006', 'name' => 'Balancete e DRE Gerencial', 'category_slug' => 'contabil-mensal', 'duration_minutes' => 55, 'base_price' => 560.00, 'description' => 'Demonstrativos para acompanhamento de resultados e desempenho.', 'image_url' => $this->loremImage('finance,report', 306)],
            ['code' => 'VCNT-007', 'name' => 'Folha de Pagamento Mensal', 'category_slug' => 'departamento-pessoal', 'duration_minutes' => 75, 'base_price' => 490.00, 'description' => 'Processamento de folha com encargos e envio de eventos.', 'image_url' => $this->loremImage('payroll,office', 307)],
            ['code' => 'VCNT-008', 'name' => 'eSocial e Rotinas Trabalhistas', 'category_slug' => 'departamento-pessoal', 'duration_minutes' => 65, 'base_price' => 450.00, 'description' => 'Gestão de admissões, demissões e eventos trabalhistas.', 'image_url' => $this->loremImage('humanresources,documents', 308)],
            ['code' => 'VCNT-009', 'name' => 'BPO Financeiro para PMEs', 'category_slug' => 'consultoria-planejamento', 'duration_minutes' => 90, 'base_price' => 890.00, 'description' => 'Rotina financeira com contas, fluxo de caixa e conciliações.', 'image_url' => $this->loremImage('finance,business', 309)],
            ['code' => 'VCNT-010', 'name' => 'Consultoria de Fluxo de Caixa', 'category_slug' => 'consultoria-planejamento', 'duration_minutes' => 60, 'base_price' => 520.00, 'description' => 'Diagnóstico financeiro e plano de organização de caixa.', 'image_url' => $this->loremImage('cashflow,analysis', 310)],
            ['code' => 'VCNT-011', 'name' => 'Encerramento de Empresa', 'category_slug' => 'abertura-regularizacao', 'duration_minutes' => 95, 'base_price' => 790.00, 'description' => 'Baixa de CNPJ, distrato e regularização final de obrigações.', 'image_url' => $this->loremImage('business,closure', 311)],
            ['code' => 'VCNT-012', 'name' => 'Consultoria para Investimentos', 'category_slug' => 'consultoria-planejamento', 'duration_minutes' => 50, 'base_price' => 620.00, 'description' => 'Orientação financeira para reservas, expansão e investimentos.', 'image_url' => $this->loremImage('investment,planning', 312)],
        ];

        foreach ($services as $item) {
            $service = ServiceCatalog::withTrashed()->firstOrNew([
                'contractor_id' => $contractor->id,
                'code' => $item['code'],
            ]);

            if ($service->exists && $service->trashed()) {
                $service->restore();
            }

            $service->fill([
                'service_category_id' => $categoryMap[$item['category_slug']] ?? null,
                'name' => $item['name'],
                'description' => $item['description'],
                'image_url' => $item['image_url'],
                'duration_minutes' => $item['duration_minutes'],
                'base_price' => $item['base_price'],
                'is_active' => true,
            ]);

            $service->save();
        }

        $clients = [
            [
                'name' => 'Alfa Comércio Ltda',
                'email' => 'financeiro@alfacomercio.com.br',
                'phone' => '(41) 98888-1001',
                'document' => '13456789000161',
                'cep' => '80020-310',
                'street' => 'Rua XV de Novembro',
                'number' => '214',
                'complement' => 'Conj. 301',
                'neighborhood' => 'Centro',
                'city' => 'Curitiba',
                'state' => 'PR',
            ],
            [
                'name' => 'Bella Studio Hair',
                'email' => 'contato@bellastudiohair.com.br',
                'phone' => '(41) 98888-1002',
                'document' => '25678901000182',
                'cep' => '80320-050',
                'street' => 'Avenida Iguaçu',
                'number' => '880',
                'complement' => 'Sala 4',
                'neighborhood' => 'Água Verde',
                'city' => 'Curitiba',
                'state' => 'PR',
            ],
            [
                'name' => 'Construtora Nova Era Ltda',
                'email' => 'adm@construtoranovaera.com.br',
                'phone' => '(41) 98888-1003',
                'document' => '30987654000106',
                'cep' => '80240-220',
                'street' => 'Rua Chile',
                'number' => '410',
                'complement' => 'Bloco B',
                'neighborhood' => 'Rebouças',
                'city' => 'Curitiba',
                'state' => 'PR',
            ],
            [
                'name' => 'Clínica Vida e Saúde',
                'email' => 'gestao@clinicavidaesaude.com.br',
                'phone' => '(41) 98888-1004',
                'document' => '42765432000140',
                'cep' => '80215-180',
                'street' => 'Rua Conselheiro Laurindo',
                'number' => '1200',
                'complement' => 'Andar 2',
                'neighborhood' => 'Centro',
                'city' => 'Curitiba',
                'state' => 'PR',
            ],
            [
                'name' => 'João Pedro Lima',
                'email' => 'joaopedrolima@gmail.com',
                'phone' => '(41) 98888-1005',
                'document' => '32165498700',
                'cep' => '80520-340',
                'street' => 'Rua Mateus Leme',
                'number' => '221',
                'complement' => 'Casa',
                'neighborhood' => 'São Francisco',
                'city' => 'Curitiba',
                'state' => 'PR',
            ],
            [
                'name' => 'Maria Fernanda Souza',
                'email' => 'maria.fernanda@gmail.com',
                'phone' => '(41) 98888-1006',
                'document' => '85274196320',
                'cep' => '80620-100',
                'street' => 'Rua Itupava',
                'number' => '900',
                'complement' => 'Apto 41',
                'neighborhood' => 'Alto da XV',
                'city' => 'Curitiba',
                'state' => 'PR',
            ],
        ];

        $clientIds = [];
        foreach ($clients as $item) {
            $client = Client::withTrashed()->firstOrNew([
                'contractor_id' => $contractor->id,
                'document' => $item['document'],
            ]);

            if ($client->exists && $client->trashed()) {
                $client->restore();
            }

            $client->fill([
                'name' => $item['name'],
                'email' => $item['email'],
                'phone' => $item['phone'],
                'cep' => $item['cep'],
                'street' => $item['street'],
                'number' => $item['number'],
                'complement' => $item['complement'],
                'neighborhood' => $item['neighborhood'],
                'city' => $item['city'],
                'state' => $item['state'],
                'is_active' => true,
            ]);

            $client->save();
            $clientIds[] = (int) $client->id;
        }

        $baseDate = now()->startOfMonth();

        $fees = [
            ['client_index' => 0, 'reference_label' => $baseDate->format('m/Y'), 'due_date' => $baseDate->copy()->day(5)->toDateString(), 'amount' => 1500.00, 'paid_amount' => 1500.00, 'status' => AccountingFeeEntry::STATUS_PAID, 'paid_at' => $baseDate->copy()->day(4)->setTime(10, 0, 0)],
            ['client_index' => 1, 'reference_label' => $baseDate->format('m/Y'), 'due_date' => $baseDate->copy()->day(7)->toDateString(), 'amount' => 980.00, 'paid_amount' => 980.00, 'status' => AccountingFeeEntry::STATUS_PAID, 'paid_at' => $baseDate->copy()->day(6)->setTime(14, 30, 0)],
            ['client_index' => 2, 'reference_label' => $baseDate->format('m/Y'), 'due_date' => $baseDate->copy()->day(10)->toDateString(), 'amount' => 2200.00, 'paid_amount' => 0, 'status' => AccountingFeeEntry::STATUS_PENDING, 'paid_at' => null],
            ['client_index' => 3, 'reference_label' => $baseDate->format('m/Y'), 'due_date' => $baseDate->copy()->day(12)->toDateString(), 'amount' => 1750.00, 'paid_amount' => 0, 'status' => AccountingFeeEntry::STATUS_OVERDUE, 'paid_at' => null],
            ['client_index' => 4, 'reference_label' => $baseDate->format('m/Y'), 'due_date' => $baseDate->copy()->day(15)->toDateString(), 'amount' => 620.00, 'paid_amount' => 0, 'status' => AccountingFeeEntry::STATUS_PENDING, 'paid_at' => null],
            ['client_index' => 5, 'reference_label' => $baseDate->format('m/Y'), 'due_date' => $baseDate->copy()->day(18)->toDateString(), 'amount' => 540.00, 'paid_amount' => 540.00, 'status' => AccountingFeeEntry::STATUS_PAID, 'paid_at' => $baseDate->copy()->day(17)->setTime(11, 15, 0)],
        ];

        foreach ($fees as $item) {
            $clientId = $clientIds[$item['client_index']] ?? null;
            if (! $clientId) {
                continue;
            }

            AccountingFeeEntry::query()->updateOrCreate(
                [
                    'contractor_id' => $contractor->id,
                    'client_id' => $clientId,
                    'reference_label' => $item['reference_label'],
                    'due_date' => $item['due_date'],
                ],
                [
                    'amount' => $item['amount'],
                    'paid_amount' => $item['paid_amount'],
                    'status' => $item['status'],
                    'paid_at' => $item['paid_at'],
                    'notes' => 'Lançamento gerado automaticamente para ambiente local.',
                ],
            );
        }

        $obligations = [
            ['client_index' => 0, 'title' => 'Envio EFD Contribuições', 'obligation_type' => 'SPED', 'due_date' => $baseDate->copy()->addDays(2)->toDateString(), 'status' => AccountingObligation::STATUS_PENDING, 'priority' => AccountingObligation::PRIORITY_HIGH],
            ['client_index' => 1, 'title' => 'Fechamento Folha de Pagamento', 'obligation_type' => 'DP', 'due_date' => $baseDate->copy()->addDays(4)->toDateString(), 'status' => AccountingObligation::STATUS_SENT, 'priority' => AccountingObligation::PRIORITY_NORMAL],
            ['client_index' => 2, 'title' => 'Conferência DCTFWeb', 'obligation_type' => 'Fiscal', 'due_date' => $baseDate->copy()->addDays(6)->toDateString(), 'status' => AccountingObligation::STATUS_PENDING, 'priority' => AccountingObligation::PRIORITY_CRITICAL],
            ['client_index' => 3, 'title' => 'Entrega DEFIS', 'obligation_type' => 'Anual', 'due_date' => $baseDate->copy()->addDays(8)->toDateString(), 'status' => AccountingObligation::STATUS_PENDING, 'priority' => AccountingObligation::PRIORITY_HIGH],
            ['client_index' => 4, 'title' => 'Regularização de pendência municipal', 'obligation_type' => 'Fiscal', 'due_date' => $baseDate->copy()->subDays(3)->toDateString(), 'status' => AccountingObligation::STATUS_OVERDUE, 'priority' => AccountingObligation::PRIORITY_HIGH],
            ['client_index' => 5, 'title' => 'Conciliação contábil mensal', 'obligation_type' => 'Contábil', 'due_date' => $baseDate->copy()->addDays(10)->toDateString(), 'status' => AccountingObligation::STATUS_PENDING, 'priority' => AccountingObligation::PRIORITY_NORMAL],
        ];

        foreach ($obligations as $item) {
            $clientId = $clientIds[$item['client_index']] ?? null;
            if (! $clientId) {
                continue;
            }

            AccountingObligation::query()->updateOrCreate(
                [
                    'contractor_id' => $contractor->id,
                    'client_id' => $clientId,
                    'title' => $item['title'],
                    'due_date' => $item['due_date'],
                ],
                [
                    'obligation_type' => $item['obligation_type'],
                    'competence_date' => $baseDate->toDateString(),
                    'status' => $item['status'],
                    'priority' => $item['priority'],
                    'notes' => 'Obrigação lançada para demonstração do módulo contábil.',
                ],
            );
        }

        $documents = [
            ['client_index' => 0, 'title' => 'Extratos bancários do mês', 'document_type' => 'Financeiro', 'due_date' => $baseDate->copy()->addDays(1)->toDateString(), 'status' => AccountingDocumentRequest::STATUS_PENDING],
            ['client_index' => 1, 'title' => 'Notas fiscais de entrada e saída', 'document_type' => 'Fiscal', 'due_date' => $baseDate->copy()->addDays(3)->toDateString(), 'status' => AccountingDocumentRequest::STATUS_RECEIVED],
            ['client_index' => 2, 'title' => 'Folha de ponto e admissões', 'document_type' => 'Trabalhista', 'due_date' => $baseDate->copy()->addDays(5)->toDateString(), 'status' => AccountingDocumentRequest::STATUS_PENDING],
            ['client_index' => 3, 'title' => 'Comprovantes de pró-labore', 'document_type' => 'Financeiro', 'due_date' => $baseDate->copy()->addDays(7)->toDateString(), 'status' => AccountingDocumentRequest::STATUS_VALIDATED],
            ['client_index' => 4, 'title' => 'Contrato social atualizado', 'document_type' => 'Societário', 'due_date' => $baseDate->copy()->addDays(9)->toDateString(), 'status' => AccountingDocumentRequest::STATUS_PENDING],
            ['client_index' => 5, 'title' => 'Guias de recolhimento anteriores', 'document_type' => 'Fiscal', 'due_date' => $baseDate->copy()->addDays(11)->toDateString(), 'status' => AccountingDocumentRequest::STATUS_REJECTED],
        ];

        foreach ($documents as $item) {
            $clientId = $clientIds[$item['client_index']] ?? null;
            if (! $clientId) {
                continue;
            }

            AccountingDocumentRequest::query()->updateOrCreate(
                [
                    'contractor_id' => $contractor->id,
                    'client_id' => $clientId,
                    'title' => $item['title'],
                ],
                [
                    'document_type' => $item['document_type'],
                    'due_date' => $item['due_date'],
                    'status' => $item['status'],
                    'notes' => 'Solicitação de documentos para controle mensal.',
                ],
            );
        }
    }

    private function loremImage(string $keywords, int $lock): string
    {
        return "https://loremflickr.com/1200/900/{$keywords}?lock={$lock}";
    }
}
