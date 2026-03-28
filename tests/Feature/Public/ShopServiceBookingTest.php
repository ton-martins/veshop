<?php

namespace Tests\Feature\Public;

use App\Models\Contractor;
use App\Models\ServiceCatalog;
use App\Models\ServiceCategory;
use App\Models\ServiceOrder;
use App\Models\ShopCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopServiceBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_service_booking_is_blocked_when_store_is_offline(): void
    {
        $contractor = $this->createServiceContractor('servicos-offline');
        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_storefront' => [
                'store_online' => false,
                'offline_message' => 'Agenda indisponível no momento.',
            ],
        ]);
        $contractor->save();

        [$service] = $this->createServiceCatalogData($contractor);
        $customer = $this->createVerifiedShopCustomer($contractor, 'cliente-offline');

        $scheduledFor = now('America/Sao_Paulo')->addDays(1)->setTime(10, 0)->format('Y-m-d\TH:i');

        $response = $this
            ->actingAs($customer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.services.book', ['slug' => $contractor->slug]), [
                'service_catalog_id' => $service->id,
                'scheduled_for' => $scheduledFor,
                'notes' => 'Teste de indisponibilidade',
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHasErrors('booking');

        $this->assertDatabaseMissing('service_orders', [
            'contractor_id' => $contractor->id,
            'service_catalog_id' => $service->id,
        ]);
    }

    public function test_service_booking_requires_time_inside_business_hours(): void
    {
        $contractor = $this->createServiceContractor('servicos-horario');
        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_storefront' => [
                'store_online' => true,
                'business_hours' => $this->businessHoursNineToSix(),
            ],
        ]);
        $contractor->save();

        [$service] = $this->createServiceCatalogData($contractor);
        $customer = $this->createVerifiedShopCustomer($contractor, 'cliente-horario');

        $scheduledFor = now('America/Sao_Paulo')->addDays(1)->setTime(22, 0)->format('Y-m-d\TH:i');

        $response = $this
            ->actingAs($customer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.services.book', ['slug' => $contractor->slug]), [
                'service_catalog_id' => $service->id,
                'scheduled_for' => $scheduledFor,
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHasErrors('scheduled_for');

        $this->assertDatabaseMissing('service_orders', [
            'contractor_id' => $contractor->id,
            'service_catalog_id' => $service->id,
        ]);
    }

    public function test_service_booking_succeeds_when_store_is_online_and_time_is_valid(): void
    {
        $contractor = $this->createServiceContractor('servicos-agendamento-ok');
        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_storefront' => [
                'store_online' => true,
                'business_hours' => $this->alwaysOpenBusinessHours(),
            ],
        ]);
        $contractor->save();

        [$service] = $this->createServiceCatalogData($contractor);
        $customer = $this->createVerifiedShopCustomer($contractor, 'cliente-ok');

        $scheduledFor = now('America/Sao_Paulo')->addDays(1)->setTime(11, 30)->format('Y-m-d\TH:i');

        $response = $this
            ->actingAs($customer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.services.book', ['slug' => $contractor->slug]), [
                'service_catalog_id' => $service->id,
                'scheduled_for' => $scheduledFor,
                'notes' => 'Preciso confirmar no WhatsApp.',
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHas('status');

        $order = ServiceOrder::query()
            ->where('contractor_id', $contractor->id)
            ->where('service_catalog_id', $service->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($order);
        $this->assertSame(ServiceOrder::STATUS_OPEN, (string) $order->status);
    }

    public function test_service_booking_requires_time_that_fits_service_duration_inside_business_hours(): void
    {
        $contractor = $this->createServiceContractor('servicos-faixa-horaria');
        $contractor->settings = array_replace((array) $contractor->settings, [
            'shop_storefront' => [
                'store_online' => true,
                'business_hours' => $this->businessHoursNineToSix(),
            ],
        ]);
        $contractor->save();

        [$service] = $this->createServiceCatalogData($contractor);
        $service->duration_minutes = 90;
        $service->save();

        $customer = $this->createVerifiedShopCustomer($contractor, 'cliente-faixa-horaria');
        $scheduledFor = now('America/Sao_Paulo')->addDays(1)->setTime(17, 30)->format('Y-m-d\TH:i');

        $response = $this
            ->actingAs($customer, 'shop')
            ->from(route('shop.show', ['slug' => $contractor->slug]))
            ->post(route('shop.services.book', ['slug' => $contractor->slug]), [
                'service_catalog_id' => $service->id,
                'scheduled_for' => $scheduledFor,
            ]);

        $response->assertRedirect(route('shop.show', ['slug' => $contractor->slug]));
        $response->assertSessionHasErrors('scheduled_for');

        $this->assertDatabaseMissing('service_orders', [
            'contractor_id' => $contractor->id,
            'service_catalog_id' => $service->id,
        ]);
    }

    private function createServiceContractor(string $slug): Contractor
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
                'business_niche' => Contractor::NICHE_SERVICES,
                'active_plan_name' => 'Pro',
                'require_email_verification' => true,
            ],
            'is_active' => true,
        ]);
    }

    /**
     * @return array{0: ServiceCatalog, 1: ServiceCategory}
     */
    private function createServiceCatalogData(Contractor $contractor): array
    {
        $category = ServiceCategory::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Atendimento geral',
            'slug' => 'atendimento-geral',
            'description' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $service = ServiceCatalog::query()->create([
            'contractor_id' => $contractor->id,
            'service_category_id' => $category->id,
            'name' => 'Consultoria expressa',
            'code' => 'SVC-EXP-001',
            'description' => 'Atendimento rápido para ajustes operacionais.',
            'duration_minutes' => 60,
            'base_price' => 120.00,
            'is_active' => true,
        ]);

        return [$service, $category];
    }

    private function createVerifiedShopCustomer(Contractor $contractor, string $suffix): ShopCustomer
    {
        return ShopCustomer::query()->create([
            'contractor_id' => $contractor->id,
            'name' => 'Cliente '.Str::title(str_replace('-', ' ', $suffix)),
            'email' => "{$suffix}@example.com",
            'phone' => '71999990333',
            'cep' => '41810-000',
            'street' => 'Rua das Flores',
            'neighborhood' => 'Centro',
            'city' => 'Salvador',
            'state' => 'BA',
            'password' => '12345678',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return array<string, array{enabled: bool, open: string, close: string}>
     */
    private function businessHoursNineToSix(): array
    {
        return [
            'monday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
            'tuesday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
            'wednesday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
            'thursday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
            'friday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
            'saturday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
            'sunday' => ['enabled' => true, 'open' => '09:00', 'close' => '18:00'],
        ];
    }

    /**
     * @return array<string, array{enabled: bool, open: string, close: string}>
     */
    private function alwaysOpenBusinessHours(): array
    {
        return [
            'monday' => ['enabled' => true, 'open' => '00:00', 'close' => '23:59'],
            'tuesday' => ['enabled' => true, 'open' => '00:00', 'close' => '23:59'],
            'wednesday' => ['enabled' => true, 'open' => '00:00', 'close' => '23:59'],
            'thursday' => ['enabled' => true, 'open' => '00:00', 'close' => '23:59'],
            'friday' => ['enabled' => true, 'open' => '00:00', 'close' => '23:59'],
            'saturday' => ['enabled' => true, 'open' => '00:00', 'close' => '23:59'],
            'sunday' => ['enabled' => true, 'open' => '00:00', 'close' => '23:59'],
        ];
    }
}
