<?php

namespace Tests\Feature\Admin;

use App\Models\AddressState;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_admin_can_update_storefront_settings_for_current_contractor(): void
    {
        $contractor = $this->createContractor('storefront-admin');
        $user = $this->createAdminUser([$contractor]);

        $product = Product::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Produto destaque',
            'sku' => 'PROD-001',
            'sale_price' => 129.90,
            'stock_quantity' => 20,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->put(route('admin.storefront.update'), [
                'template' => 'hibrido',
                'hero_enabled' => true,
                'hero_title' => 'Compre e contrate em um so lugar',
                'hero_subtitle' => 'Texto de teste para a vitrine principal.',
                'hero_cta_label' => 'Explorar vitrine',
                'banners_enabled' => true,
                'banners' => [
                    [
                        'title' => 'Semana especial',
                        'subtitle' => 'Ate 20% em itens selecionados',
                        'badge' => 'Oferta',
                        'image_url' => 'https://example.com/banner.jpg',
                        'cta_label' => 'Ver ofertas',
                        'cta_url' => '#categorias',
                        'background_color' => '#112233',
                    ],
                ],
                'promotions_enabled' => true,
                'promotions_title' => 'Destaques da semana',
                'promotions_subtitle' => 'Itens selecionados para promocao.',
                'promotion_product_ids' => [$product->id],
                'categories_enabled' => true,
                'catalog_enabled' => true,
                'catalog_title' => 'Nosso catalogo',
                'catalog_subtitle' => 'Tudo organizado para sua compra.',
            ]);

        $response->assertRedirect();

        $contractor->refresh();

        $this->assertIsArray($contractor->settings);
        $this->assertIsArray($contractor->settings['shop_storefront'] ?? null);

        $storefront = $contractor->settings['shop_storefront'];

        $this->assertSame('hibrido', $storefront['template'] ?? null);
        $this->assertSame('Compre e contrate em um so lugar', $storefront['hero']['title'] ?? null);
        $this->assertSame([$product->id], $storefront['promotions']['product_ids'] ?? []);
        $this->assertCount(1, $storefront['banners'] ?? []);
    }

    public function test_admin_cannot_use_promotion_products_from_another_contractor(): void
    {
        $contractorA = $this->createContractor('storefront-a');
        $contractorB = $this->createContractor('storefront-b');
        $user = $this->createAdminUser([$contractorA]);

        $foreignProduct = Product::query()->create([
            'contractor_id' => $contractorB->id,
            'name' => 'Produto externo',
            'sku' => 'EXT-001',
            'sale_price' => 89.90,
            'stock_quantity' => 12,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractorA->id,
                'two_factor_passed' => true,
            ])
            ->from(route('admin.storefront.index'))
            ->put(route('admin.storefront.update'), [
                'template' => 'comercio',
                'hero_enabled' => true,
                'hero_title' => 'Titulo',
                'hero_subtitle' => 'Subtitulo',
                'hero_cta_label' => 'Ver itens',
                'banners_enabled' => true,
                'banners' => [],
                'promotions_enabled' => true,
                'promotions_title' => 'Promocoes',
                'promotions_subtitle' => 'Destaques',
                'promotion_product_ids' => [$foreignProduct->id],
                'categories_enabled' => true,
                'catalog_enabled' => true,
                'catalog_title' => 'Catalogo',
                'catalog_subtitle' => 'Lista completa',
            ]);

        $response->assertRedirect(route('admin.storefront.index'));
        $response->assertSessionHasErrors(['promotion_product_ids']);

        $contractorA->refresh();

        $this->assertFalse(
            isset($contractorA->settings['shop_storefront']['promotions']['product_ids'])
            && count((array) $contractorA->settings['shop_storefront']['promotions']['product_ids']) > 0
        );
    }

    public function test_admin_can_replace_banner_image_and_old_file_is_removed_from_storage(): void
    {
        Storage::fake('public');

        $contractor = $this->createContractor('storefront-upload');
        $user = $this->createAdminUser([$contractor]);

        $firstUpload = UploadedFile::fake()->image('banner-1.jpg');
        $firstResponse = $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->put(route('admin.storefront.update'), [
                'template' => 'comercio',
                'hero_enabled' => true,
                'hero_title' => 'Título',
                'hero_subtitle' => 'Subtítulo',
                'hero_cta_label' => 'Ver vitrine',
                'banners_enabled' => true,
                'banners' => [
                    [
                        'title' => 'Banner 1',
                        'subtitle' => 'Sub 1',
                        'badge' => 'Oferta',
                        'image_file' => $firstUpload,
                        'image_url' => '',
                        'existing_image_path' => '',
                        'remove_image' => false,
                        'cta_label' => 'Comprar',
                        'cta_url' => '#categorias',
                        'background_color' => '#112233',
                    ],
                ],
                'promotions_enabled' => false,
                'promotions_title' => '',
                'promotions_subtitle' => '',
                'promotion_product_ids' => [],
                'categories_enabled' => true,
                'catalog_enabled' => true,
                'catalog_title' => '',
                'catalog_subtitle' => '',
            ]);

        $firstResponse->assertRedirect();

        $contractor->refresh();
        $firstPath = (string) ($contractor->settings['shop_storefront']['banners'][0]['image_path'] ?? '');
        $this->assertNotSame('', $firstPath);
        Storage::disk('public')->assertExists($firstPath);

        $secondUpload = UploadedFile::fake()->image('banner-2.jpg');
        $secondResponse = $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->put(route('admin.storefront.update'), [
                'template' => 'comercio',
                'hero_enabled' => true,
                'hero_title' => 'Título',
                'hero_subtitle' => 'Subtítulo',
                'hero_cta_label' => 'Ver vitrine',
                'banners_enabled' => true,
                'banners' => [
                    [
                        'title' => 'Banner 2',
                        'subtitle' => 'Sub 2',
                        'badge' => 'Oferta',
                        'image_file' => $secondUpload,
                        'image_url' => '',
                        'existing_image_path' => $firstPath,
                        'remove_image' => false,
                        'cta_label' => 'Comprar',
                        'cta_url' => '#categorias',
                        'background_color' => '#112233',
                    ],
                ],
                'promotions_enabled' => false,
                'promotions_title' => '',
                'promotions_subtitle' => '',
                'promotion_product_ids' => [],
                'categories_enabled' => true,
                'catalog_enabled' => true,
                'catalog_title' => '',
                'catalog_subtitle' => '',
            ]);

        $secondResponse->assertRedirect();

        $contractor->refresh();
        $secondPath = (string) ($contractor->settings['shop_storefront']['banners'][0]['image_path'] ?? '');

        $this->assertNotSame('', $secondPath);
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertExists($secondPath);
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_admin_location_cities_endpoint_syncs_cities_from_ibge_when_state_is_not_local(): void
    {
        $contractor = $this->createContractor('storefront-local-cities');
        $user = $this->createAdminUser([$contractor]);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/RS/municipios' => Http::response([
                ['nome' => 'Porto Alegre', 'id' => '4314902'],
                ['nome' => 'Caxias do Sul', 'id' => '4305108'],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->getJson(route('admin.storefront.location.cities', ['state' => 'RS']));

        $response->assertOk()
            ->assertJsonPath('cities.0', 'Caxias do Sul')
            ->assertJsonPath('cities.1', 'Porto Alegre');

        $this->assertDatabaseHas('address_states', [
            'code' => 'RS',
            'name' => 'Rio Grande do Sul',
        ]);

        $this->assertDatabaseHas('address_cities', [
            'name' => 'Porto Alegre',
            'normalized_name' => 'porto alegre',
            'ibge_code' => '4314902',
        ]);
    }

    public function test_admin_shipping_update_rejects_city_that_does_not_exist_for_selected_state(): void
    {
        $contractor = $this->createContractor('storefront-city-validation');
        $user = $this->createAdminUser([$contractor]);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/RS/municipios' => Http::response([
                ['nome' => 'Porto Alegre', 'id' => '4314902'],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->from(route('admin.storefront.index'))
            ->put(route('admin.storefront.update'), [
                'section' => 'shipping',
                'shipping_pickup_enabled' => true,
                'shipping_delivery_enabled' => true,
                'shipping_nationwide_enabled' => false,
                'shipping_nationwide_fee' => 0,
                'shipping_nationwide_free_over' => 0,
                'shipping_statewide_enabled' => false,
                'shipping_statewide_state' => '',
                'shipping_statewide_fee' => 0,
                'shipping_statewide_free_over' => 0,
                'shipping_estimated_days' => 2,
                'shipping_city_rates' => [
                    [
                        'state' => 'RS',
                        'city' => 'Cidade Inexistente',
                        'fee' => 12.5,
                        'free_over' => 0,
                        'estimated_days' => 2,
                        'active' => true,
                        'is_free' => false,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.storefront.index'));
        $response->assertSessionHasErrors(['shipping_city_rates.0.city']);
    }

    public function test_admin_shipping_update_normalizes_city_name_to_canonical_directory_value(): void
    {
        $contractor = $this->createContractor('storefront-city-canonical');
        $user = $this->createAdminUser([$contractor]);

        AddressState::query()->create([
            'code' => 'RS',
            'name' => 'Rio Grande do Sul',
        ]);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/localidades/estados/RS/municipios' => Http::response([
                ['nome' => 'Porto Alegre', 'id' => '4314902'],
            ], 200),
            '*' => Http::response([], 200),
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'current_contractor_id' => $contractor->id,
                'two_factor_passed' => true,
            ])
            ->put(route('admin.storefront.update'), [
                'section' => 'shipping',
                'shipping_pickup_enabled' => true,
                'shipping_delivery_enabled' => true,
                'shipping_nationwide_enabled' => false,
                'shipping_nationwide_fee' => 0,
                'shipping_nationwide_free_over' => 0,
                'shipping_statewide_enabled' => false,
                'shipping_statewide_state' => '',
                'shipping_statewide_fee' => 0,
                'shipping_statewide_free_over' => 0,
                'shipping_estimated_days' => 2,
                'shipping_city_rates' => [
                    [
                        'state' => 'RS',
                        'city' => 'porto alegre',
                        'fee' => 9.9,
                        'free_over' => 0,
                        'estimated_days' => 2,
                        'active' => true,
                        'is_free' => false,
                    ],
                ],
            ]);

        $response->assertRedirect();

        $contractor->refresh();
        $cityRate = data_get($contractor->settings, 'shop_shipping.city_rates.0');

        $this->assertSame('RS', (string) data_get($cityRate, 'state'));
        $this->assertSame('Porto Alegre', (string) data_get($cityRate, 'city'));
        $this->assertDatabaseHas('address_cities', [
            'name' => 'Porto Alegre',
            'normalized_name' => 'porto alegre',
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
            ],
            'is_active' => true,
        ]);
    }
}
