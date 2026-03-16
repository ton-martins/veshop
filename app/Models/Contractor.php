<?php

namespace App\Models;

use App\Services\ContractorCapabilitiesService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Contractor extends Model
{
    use HasFactory, SoftDeletes;

    public const NICHE_COMMERCIAL = 'commercial';

    public const NICHE_SERVICES = 'services';

    public const MODULE_COMMERCIAL = 'commercial';

    public const MODULE_SERVICES = 'services';

    public const BUSINESS_TYPE_STORE = 'store';

    public const BUSINESS_TYPE_CONFECTIONERY = 'confectionery';

    public const BUSINESS_TYPE_BARBERSHOP = 'barbershop';

    public const BUSINESS_TYPE_AUTO_ELECTRIC = 'auto_electric';

    public const BUSINESS_TYPE_MECHANIC = 'mechanic';

    public const BUSINESS_TYPE_ACCOUNTING = 'accounting';

    public const BUSINESS_TYPE_GENERAL_SERVICES = 'general_services';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'cnpj',
        'slug',
        'plan_id',
        'timezone',
        'address',
        'brand_name',
        'brand_primary_color',
        'brand_logo_url',
        'brand_avatar_url',
        'settings',
        'business_type',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'contractor_module')
            ->withTimestamps();
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function shopCustomers(): HasMany
    {
        return $this->hasMany(ShopCustomer::class);
    }

    public function shopCustomerFavorites(): HasMany
    {
        return $this->hasMany(ShopCustomerFavorite::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function serviceCategories(): HasMany
    {
        return $this->hasMany(ServiceCategory::class);
    }

    public function serviceCatalogs(): HasMany
    {
        return $this->hasMany(ServiceCatalog::class);
    }

    public function paymentGateways(): HasMany
    {
        return $this->hasMany(PaymentGateway::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function cashSessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function reportExports(): HasMany
    {
        return $this->hasMany(ReportExport::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return list<string>
     */
    public static function availableNiches(): array
    {
        return [
            self::NICHE_COMMERCIAL,
            self::NICHE_SERVICES,
        ];
    }

    public static function defaultNiche(): string
    {
        return self::NICHE_COMMERCIAL;
    }

    public function niche(): string
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $rawNiche = $settings['business_niche'] ?? self::defaultNiche();

        return self::normalizeNiche($rawNiche);
    }

    public function businessType(): string
    {
        return self::normalizeBusinessType($this->business_type, $this->niche());
    }

    public function activePlanName(): string
    {
        if ($this->relationLoaded('plan') && $this->plan) {
            return $this->plan->name;
        }

        $settings = is_array($this->settings) ? $this->settings : [];
        $rawPlan = trim((string) ($settings['active_plan_name'] ?? ''));

        return $rawPlan !== '' ? $rawPlan : 'Sem plano';
    }

    public function setBusinessNiche(string $niche): self
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $settings['business_niche'] = self::normalizeNiche($niche);
        $this->settings = $settings;

        return $this;
    }

    public function setBusinessType(string $businessType): self
    {
        $this->business_type = self::normalizeBusinessType($businessType, $this->niche());

        return $this;
    }

    /**
     * @return list<string>
     */
    public static function availableModules(): array
    {
        return [
            self::MODULE_COMMERCIAL,
            self::MODULE_SERVICES,
        ];
    }

    /**
     * @return list<string>
     */
    public function enabledModules(): array
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('contractor_module')) {
            return $this->legacyEnabledModules();
        }

        return app(ContractorCapabilitiesService::class)->enabledModuleCodesForContractor($this);
    }

    public function hasModule(string $module): bool
    {
        $normalizedModule = strtolower(trim($module));
        if ($normalizedModule === '') {
            return false;
        }

        return in_array($normalizedModule, $this->enabledModules(), true);
    }

    public function requiresEmailVerification(): bool
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return (bool) ($settings['require_email_verification'] ?? true);
    }

    /**
     * @return list<string>
     */
    public static function availableBusinessTypes(?string $niche = null): array
    {
        if ($niche === null) {
            return [
                self::BUSINESS_TYPE_STORE,
                self::BUSINESS_TYPE_CONFECTIONERY,
                self::BUSINESS_TYPE_BARBERSHOP,
                self::BUSINESS_TYPE_AUTO_ELECTRIC,
                self::BUSINESS_TYPE_MECHANIC,
                self::BUSINESS_TYPE_ACCOUNTING,
                self::BUSINESS_TYPE_GENERAL_SERVICES,
            ];
        }

        return match (self::normalizeNiche($niche)) {
            self::NICHE_SERVICES => [
                self::BUSINESS_TYPE_BARBERSHOP,
                self::BUSINESS_TYPE_AUTO_ELECTRIC,
                self::BUSINESS_TYPE_MECHANIC,
                self::BUSINESS_TYPE_ACCOUNTING,
                self::BUSINESS_TYPE_GENERAL_SERVICES,
            ],
            default => [
                self::BUSINESS_TYPE_STORE,
                self::BUSINESS_TYPE_CONFECTIONERY,
            ],
        };
    }

    public static function defaultBusinessType(string $niche): string
    {
        return self::normalizeNiche($niche) === self::NICHE_SERVICES
            ? self::BUSINESS_TYPE_GENERAL_SERVICES
            : self::BUSINESS_TYPE_STORE;
    }

    public static function normalizeBusinessType(mixed $value, string $niche): string
    {
        $normalized = strtolower(trim((string) $value));
        $allowed = self::availableBusinessTypes($niche);

        return in_array($normalized, $allowed, true)
            ? $normalized
            : self::defaultBusinessType($niche);
    }

    public static function labelForBusinessType(string $businessType): string
    {
        return match (strtolower(trim($businessType))) {
            self::BUSINESS_TYPE_STORE => 'Loja',
            self::BUSINESS_TYPE_CONFECTIONERY => 'Confeitaria',
            self::BUSINESS_TYPE_BARBERSHOP => 'Barbearia',
            self::BUSINESS_TYPE_AUTO_ELECTRIC => 'Autoelétrica',
            self::BUSINESS_TYPE_MECHANIC => 'Mecânica',
            self::BUSINESS_TYPE_ACCOUNTING => 'Contabilidade',
            self::BUSINESS_TYPE_GENERAL_SERVICES => 'Serviços gerais',
            default => ucfirst(strtolower(trim($businessType))),
        };
    }

    public static function normalizeNiche(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::availableNiches(), true)
            ? $normalized
            : self::defaultNiche();
    }

    /**
     * @return list<string>
     */
    private function legacyEnabledModules(): array
    {
        return match ($this->niche()) {
            self::NICHE_SERVICES => [self::MODULE_SERVICES],
            default => [self::MODULE_COMMERCIAL],
        };
    }
}
