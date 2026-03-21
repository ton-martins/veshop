<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Services\ContractorStorageQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContractorStorageQuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_limit_by_plan_uses_storage_limit_setting(): void
    {
        $contractorLow = $this->createContractor('quota-low', 1.0);
        $contractorHigh = $this->createContractor('quota-high', 2.0);
        $service = app(ContractorStorageQuotaService::class);

        $this->assertSame(3, $service->resolveProductImageLimitByPlan($contractorLow));
        $this->assertSame(5, $service->resolveProductImageLimitByPlan($contractorHigh));
    }

    public function test_assert_can_store_bytes_blocks_upload_when_quota_is_exceeded(): void
    {
        Storage::fake('public');

        $contractor = $this->createContractor('quota-block', 0.00001);
        $service = app(ContractorStorageQuotaService::class);

        Storage::disk('public')->put("contractors/{$contractor->id}/products/existente.bin", str_repeat('a', 8192));

        $this->expectException(ValidationException::class);
        $service->assertCanStoreBytes($contractor, 4096);
    }

    private function createContractor(string $slug, float $storageLimitGb): Contractor
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
                'storage_limit_gb' => $storageLimitGb,
            ],
            'is_active' => true,
        ]);
    }
}

