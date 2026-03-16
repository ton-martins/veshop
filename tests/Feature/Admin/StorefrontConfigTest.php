<?php

namespace Tests\Feature\Admin;

use App\Models\Contractor;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
