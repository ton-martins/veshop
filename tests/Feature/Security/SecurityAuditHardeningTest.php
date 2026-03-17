<?php

namespace Tests\Feature\Security;

use App\Models\Contractor;
use App\Models\Module;
use App\Models\Product;
use App\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecurityAuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_cross_contractor_route_model_access_is_blocked_and_audited(): void
    {
        $contractorA = $this->createContractor('seguranca-a');
        $contractorB = $this->createContractor('seguranca-b');

        $this->syncContractorModules($contractorA, ['commercial', 'catalog']);
        $this->syncContractorModules($contractorB, ['commercial', 'catalog']);

        $user = $this->createAdminUser([$contractorA, $contractorB]);

        $foreignProduct = Product::query()->create([
            'contractor_id' => $contractorB->id,
            'name' => 'Produto B',
            'sku' => 'SKU-B-001',
            'sale_price' => 10,
            'stock_quantity' => 5,
            'unit' => 'un',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAsWithTwoFactor($user)
            ->withSession(['current_contractor_id' => $contractorA->id])
            ->put(route('admin.products.update', ['product' => $foreignProduct->id]), []);

        $response->assertNotFound();

        $audit = SecurityAuditLog::query()
            ->where('event', 'tenant.resource_scope_violation')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(SecurityAuditLog::SEVERITY_CRITICAL, $audit->severity);
        $this->assertSame((int) $contractorA->id, (int) $audit->contractor_id);
        $this->assertSame((int) $user->id, (int) $audit->user_id);
        $this->assertSame((int) $contractorB->id, (int) ($audit->context['resource_contractor_id'] ?? 0));
        $this->assertSame((int) $contractorA->id, (int) ($audit->context['session_contractor_id'] ?? 0));
    }

    public function test_auth_shared_payload_exposes_only_safe_user_fields(): void
    {
        $contractor = $this->createContractor('seguranca-payload');
        $user = $this->createAdminUser([$contractor], [
            'name' => 'Admin Seguro',
            'email' => 'admin.seguro@example.com',
            'cpf' => '12345678900',
            'phone' => '11999999999',
            'address' => ['city' => 'Sao Paulo'],
            'preferences' => ['theme' => 'dark'],
            'two_factor_secret' => 'very-sensitive-secret',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this
            ->actingAsWithTwoFactor($user)
            ->withSession(['current_contractor_id' => $contractor->id])
            ->get(route('admin.home'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Home')
            ->where('auth.user', function (mixed $rawPayload) use ($user): bool {
                $payload = $rawPayload instanceof Collection ? $rawPayload->toArray() : (array) $rawPayload;

                $expectedKeys = [
                    'id',
                    'name',
                    'email',
                    'role',
                    'email_verified_at',
                    'avatar_url',
                ];

                foreach ($expectedKeys as $key) {
                    if (! array_key_exists($key, $payload)) {
                        return false;
                    }
                }

                if ((int) ($payload['id'] ?? 0) !== (int) $user->id) {
                    return false;
                }

                $forbiddenKeys = [
                    'password',
                    'remember_token',
                    'two_factor_secret',
                    'cpf',
                    'phone',
                    'address',
                    'preferences',
                ];

                foreach ($forbiddenKeys as $forbiddenKey) {
                    if (array_key_exists($forbiddenKey, $payload)) {
                        return false;
                    }
                }

                return true;
            })
        );
    }

    public function test_contractor_context_available_payload_is_minimized(): void
    {
        $contractorA = $this->createContractor('seguranca-ctx-a');
        $contractorB = $this->createContractor('seguranca-ctx-b');
        $user = $this->createAdminUser([$contractorA, $contractorB]);

        $response = $this
            ->actingAsWithTwoFactor($user)
            ->withSession(['current_contractor_id' => $contractorA->id])
            ->get(route('admin.home'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Home')
            ->where('contractorContext.available', function (mixed $rawItems): bool {
                $items = $rawItems instanceof Collection ? $rawItems->toArray() : (array) $rawItems;
                if ($items === []) {
                    return false;
                }

                $expectedKeys = [
                    'id',
                    'name',
                    'brand_name',
                    'brand_primary_color',
                    'brand_avatar_url',
                ];

                $forbiddenKeys = [
                    'slug',
                    'brand_logo_url',
                    'business_niche',
                    'business_niche_label',
                    'business_type',
                    'business_type_label',
                    'active_plan_name',
                    'enabled_modules',
                ];

                foreach ($items as $item) {
                    $payload = $item instanceof Collection ? $item->toArray() : (array) $item;

                    foreach ($expectedKeys as $key) {
                        if (! array_key_exists($key, $payload)) {
                            return false;
                        }
                    }

                    foreach ($forbiddenKeys as $forbiddenKey) {
                        if (array_key_exists($forbiddenKey, $payload)) {
                            return false;
                        }
                    }
                }

                return true;
            })
        );
    }

    public function test_role_denial_is_audited(): void
    {
        $contractor = $this->createContractor('seguranca-roles');
        $user = $this->createAdminUser([$contractor]);

        $response = $this
            ->actingAsWithTwoFactor($user)
            ->withSession(['current_contractor_id' => $contractor->id])
            ->get(route('master.home'));

        $response->assertForbidden();

        $this->assertDatabaseHas('security_audit_logs', [
            'event' => 'auth.role_denied',
            'severity' => SecurityAuditLog::SEVERITY_WARNING,
            'user_id' => $user->id,
            'contractor_id' => $contractor->id,
            'route_name' => 'master.home',
        ]);
    }

    /**
     * @param array<int, Contractor> $contractors
     * @param array<string, mixed> $overrides
     */
    private function createAdminUser(array $contractors, array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'is_active' => true,
        ], $overrides));

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
            'business_type' => Contractor::BUSINESS_TYPE_STORE,
            'is_active' => true,
        ]);
    }

    /**
     * @param array<int, string> $moduleCodes
     */
    private function syncContractorModules(Contractor $contractor, array $moduleCodes): void
    {
        $moduleIds = Module::query()
            ->whereIn('code', $moduleCodes)
            ->pluck('id')
            ->all();

        $contractor->modules()->sync($moduleIds);
    }
}
