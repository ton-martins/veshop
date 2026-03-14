<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CommerceCatalogSeeder extends Seeder
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
            $catalog = $this->resolveCatalogBySlug($contractor->slug);
            $categories = $this->seedCategories($contractor, $catalog['categories']);
            $this->seedProducts($contractor, $categories, $catalog['products']);
            $this->seedClients($contractor, $catalog['clients']);
            $this->seedSuppliers($contractor, $catalog['suppliers']);
        }
    }

    /**
     * @return array{
     *   categories: array<int, array{name: string, description: string}>,
     *   products: array<int, array{
     *     sku: string,
     *     name: string,
     *     category_slug: string,
     *     sale_price: float,
     *     cost_price: float,
     *     stock_quantity: int,
     *     unit: string,
     *     image_url: string
     *   }>,
     *   clients: array<int, array{
     *     name: string,
     *     email: string,
     *     phone: string,
     *     document: string,
     *     city: string,
     *     state: string
     *   }>,
     *   suppliers: array<int, array{
     *     name: string,
     *     email: string,
     *     phone: string,
     *     document: string,
     *     category: string,
     *     lead_time_days: int
     *   }>
     * }
     */
    private function resolveCatalogBySlug(string $slug): array
    {
        return match ($slug) {
            'veshop-store' => [
                'categories' => [
                    ['name' => 'Camisetas', 'description' => 'Modelos básicos e premium'],
                    ['name' => 'Calças', 'description' => 'Jeans, sarja e alfaiataria'],
                    ['name' => 'Vestidos', 'description' => 'Casual, festa e social'],
                    ['name' => 'Acessórios', 'description' => 'Cintos, bolsas e bonés'],
                    ['name' => 'Calçados', 'description' => 'Tênis, sandálias e botas'],
                ],
                'products' => [
                    ['sku' => 'ROP-001', 'name' => 'Camiseta Básica Algodão', 'category_slug' => 'camisetas', 'sale_price' => 59.90, 'cost_price' => 27.00, 'stock_quantity' => 40, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/rop001/64/64'],
                    ['sku' => 'ROP-002', 'name' => 'Camiseta Estampada Street', 'category_slug' => 'camisetas', 'sale_price' => 79.90, 'cost_price' => 38.00, 'stock_quantity' => 26, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/rop002/64/64'],
                    ['sku' => 'ROP-003', 'name' => 'Calça Jeans Slim', 'category_slug' => 'calcas', 'sale_price' => 149.90, 'cost_price' => 72.00, 'stock_quantity' => 18, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/rop003/64/64'],
                    ['sku' => 'ROP-004', 'name' => 'Calça Sarja Chino', 'category_slug' => 'calcas', 'sale_price' => 169.90, 'cost_price' => 84.00, 'stock_quantity' => 12, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/rop004/64/64'],
                    ['sku' => 'ROP-005', 'name' => 'Vestido Midi Floral', 'category_slug' => 'vestidos', 'sale_price' => 189.90, 'cost_price' => 95.00, 'stock_quantity' => 10, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/rop005/64/64'],
                    ['sku' => 'ROP-006', 'name' => 'Vestido Tubinho Preto', 'category_slug' => 'vestidos', 'sale_price' => 209.90, 'cost_price' => 102.00, 'stock_quantity' => 9, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/rop006/64/64'],
                    ['sku' => 'ROP-007', 'name' => 'Bolsa Transversal', 'category_slug' => 'acessorios', 'sale_price' => 129.90, 'cost_price' => 58.00, 'stock_quantity' => 16, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/rop007/64/64'],
                    ['sku' => 'ROP-008', 'name' => 'Cinto Couro Sintético', 'category_slug' => 'acessorios', 'sale_price' => 69.90, 'cost_price' => 30.00, 'stock_quantity' => 24, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/rop008/64/64'],
                    ['sku' => 'ROP-009', 'name' => 'Tênis Casual Branco', 'category_slug' => 'calcados', 'sale_price' => 219.90, 'cost_price' => 110.00, 'stock_quantity' => 14, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/rop009/64/64'],
                    ['sku' => 'ROP-010', 'name' => 'Sandália Salto Médio', 'category_slug' => 'calcados', 'sale_price' => 179.90, 'cost_price' => 88.00, 'stock_quantity' => 11, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/rop010/64/64'],
                ],
                'clients' => [
                    ['name' => 'Mariana Souza', 'email' => 'mariana.souza@cliente.com.br', 'phone' => '71999990001', 'document' => '12345678901', 'city' => 'Salvador', 'state' => 'BA'],
                    ['name' => 'José Carvalho', 'email' => 'jose.carvalho@cliente.com.br', 'phone' => '71999990002', 'document' => '23456789012', 'city' => 'Lauro de Freitas', 'state' => 'BA'],
                    ['name' => 'Boutique Vila Nova', 'email' => 'compras@boutiquevilanova.com.br', 'phone' => '71999990003', 'document' => '34567890000189', 'city' => 'Feira de Santana', 'state' => 'BA'],
                ],
                'suppliers' => [
                    ['name' => 'Têxtil Nordeste', 'email' => 'vendas@textilnordeste.com.br', 'phone' => '7133331001', 'document' => '11111111000101', 'category' => 'Tecidos', 'lead_time_days' => 5],
                    ['name' => 'Acessórios Premium', 'email' => 'comercial@acessoriospremium.com.br', 'phone' => '7133331002', 'document' => '22222222000102', 'category' => 'Acessórios', 'lead_time_days' => 3],
                    ['name' => 'Calçados Bahia Atacado', 'email' => 'pedidos@calcadosbahia.com.br', 'phone' => '7133331003', 'document' => '33333333000103', 'category' => 'Calçados', 'lead_time_days' => 7],
                ],
            ],
            default => [
                'categories' => [
                    ['name' => 'Utilidades Domésticas', 'description' => 'Itens para cozinha e organização'],
                    ['name' => 'Papelaria', 'description' => 'Cadernos, agendas e escritório'],
                    ['name' => 'Decoração', 'description' => 'Objetos decorativos para casa'],
                    ['name' => 'Presentes', 'description' => 'Kits e itens para presentear'],
                    ['name' => 'Brinquedos', 'description' => 'Itens infantis e educativos'],
                ],
                'products' => [
                    ['sku' => 'BZR-001', 'name' => 'Caneca Cerâmica 350ml', 'category_slug' => 'utilidades-domesticas', 'sale_price' => 29.90, 'cost_price' => 14.00, 'stock_quantity' => 22, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/bzr001/64/64'],
                    ['sku' => 'BZR-002', 'name' => 'Pote Hermético 1L', 'category_slug' => 'utilidades-domesticas', 'sale_price' => 19.90, 'cost_price' => 9.00, 'stock_quantity' => 30, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/bzr002/64/64'],
                    ['sku' => 'BZR-003', 'name' => 'Caderno Capa Dura A5', 'category_slug' => 'papelaria', 'sale_price' => 24.90, 'cost_price' => 12.00, 'stock_quantity' => 35, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/bzr003/64/64'],
                    ['sku' => 'BZR-004', 'name' => 'Kit Canetas Coloridas', 'category_slug' => 'papelaria', 'sale_price' => 18.90, 'cost_price' => 8.00, 'stock_quantity' => 42, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/bzr004/64/64'],
                    ['sku' => 'BZR-005', 'name' => 'Vaso Decorativo Minimalista', 'category_slug' => 'decoracao', 'sale_price' => 49.90, 'cost_price' => 23.00, 'stock_quantity' => 11, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/bzr005/64/64'],
                    ['sku' => 'BZR-006', 'name' => 'Porta Retrato Madeira', 'category_slug' => 'decoracao', 'sale_price' => 34.90, 'cost_price' => 16.00, 'stock_quantity' => 18, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/bzr006/64/64'],
                    ['sku' => 'BZR-007', 'name' => 'Kit Presente Aromático', 'category_slug' => 'presentes', 'sale_price' => 79.90, 'cost_price' => 40.00, 'stock_quantity' => 6, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/bzr007/64/64'],
                    ['sku' => 'BZR-008', 'name' => 'Sacola Presente Luxo', 'category_slug' => 'presentes', 'sale_price' => 12.90, 'cost_price' => 5.50, 'stock_quantity' => 60, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/bzr008/64/64'],
                    ['sku' => 'BZR-009', 'name' => 'Quebra-cabeça 500 peças', 'category_slug' => 'brinquedos', 'sale_price' => 59.90, 'cost_price' => 28.00, 'stock_quantity' => 20, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/bzr009/64/64'],
                    ['sku' => 'BZR-010', 'name' => 'Jogo Educativo Infantil', 'category_slug' => 'brinquedos', 'sale_price' => 69.90, 'cost_price' => 34.00, 'stock_quantity' => 14, 'unit' => 'un', 'image_url' => 'https://picsum.photos/seed/bzr010/64/64'],
                ],
                'clients' => [
                    ['name' => 'Cristina Silva', 'email' => 'cristina.silva@cliente.com.br', 'phone' => '71988880001', 'document' => '45678901234', 'city' => 'Salvador', 'state' => 'BA'],
                    ['name' => 'Paulo Mendes', 'email' => 'paulo.mendes@cliente.com.br', 'phone' => '71988880002', 'document' => '56789012345', 'city' => 'Camaçari', 'state' => 'BA'],
                    ['name' => 'João Vitor', 'email' => 'joao.vitor@cliente.com.br', 'phone' => '71988880003', 'document' => '67890123000190', 'city' => 'Lauro de Freitas', 'state' => 'BA'],
                ],
                'suppliers' => [
                    ['name' => 'Distribuidora Bahia Sul', 'email' => 'comercial@bahiasul.com.br', 'phone' => '7132222001', 'document' => '44444444000104', 'category' => 'Utilidades', 'lead_time_days' => 4],
                    ['name' => 'Embalagens Prime', 'email' => 'vendas@embalagensprime.com.br', 'phone' => '7132222002', 'document' => '55555555000105', 'category' => 'Embalagens', 'lead_time_days' => 2],
                    ['name' => 'Laticínios Central', 'email' => 'pedidos@laticinioscentral.com.br', 'phone' => '7132222003', 'document' => '66666666000106', 'category' => 'Alimentos', 'lead_time_days' => 3],
                ],
            ],
        };
    }

    /**
     * @param array<int, array{name: string, description: string}> $seedCategories
     * @return array<string, \App\Models\Category>
     */
    private function seedCategories(Contractor $contractor, array $seedCategories): array
    {
        $map = [];

        foreach ($seedCategories as $index => $entry) {
            $slug = Str::slug($entry['name']);

            $category = Category::query()->updateOrCreate(
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
     * @param array<string, \App\Models\Category> $categories
     * @param array<int, array{
     *   sku: string,
     *   name: string,
     *   category_slug: string,
     *   sale_price: float,
     *   cost_price: float,
     *   stock_quantity: int,
     *   unit: string,
     *   image_url: string
     * }> $seedProducts
     */
    private function seedProducts(Contractor $contractor, array $categories, array $seedProducts): void
    {
        foreach ($seedProducts as $entry) {
            $category = $categories[$entry['category_slug']] ?? null;

            Product::query()->updateOrCreate(
                [
                    'contractor_id' => $contractor->id,
                    'sku' => $entry['sku'],
                ],
                [
                    'category_id' => $category?->id,
                    'name' => $entry['name'],
                    'description' => null,
                    'cost_price' => $entry['cost_price'],
                    'sale_price' => $entry['sale_price'],
                    'stock_quantity' => $entry['stock_quantity'],
                    'unit' => $entry['unit'],
                    'image_url' => $entry['image_url'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @param array<int, array{name: string, email: string, phone: string, document: string, city: string, state: string}> $seedClients
     */
    private function seedClients(Contractor $contractor, array $seedClients): void
    {
        foreach ($seedClients as $entry) {
            Client::query()->updateOrCreate(
                [
                    'contractor_id' => $contractor->id,
                    'document' => $entry['document'],
                ],
                [
                    'name' => $entry['name'],
                    'email' => $entry['email'],
                    'phone' => $entry['phone'],
                    'city' => $entry['city'],
                    'state' => $entry['state'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * @param array<int, array{name: string, email: string, phone: string, document: string, category: string, lead_time_days: int}> $seedSuppliers
     */
    private function seedSuppliers(Contractor $contractor, array $seedSuppliers): void
    {
        foreach ($seedSuppliers as $entry) {
            Supplier::query()->updateOrCreate(
                [
                    'contractor_id' => $contractor->id,
                    'document' => $entry['document'],
                ],
                [
                    'name' => $entry['name'],
                    'email' => $entry['email'],
                    'phone' => $entry['phone'],
                    'category' => $entry['category'],
                    'lead_time_days' => $entry['lead_time_days'],
                    'is_active' => true,
                ]
            );
        }
    }
}
