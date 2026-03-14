<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contractor extends Model
{
    use HasFactory;

    public const NICHE_COMMERCIAL = 'commercial';

    public const NICHE_SERVICES = 'services';

    public const MODULE_COMMERCIAL = 'commercial';

    public const MODULE_SERVICES = 'services';

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
        'timezone',
        'address',
        'brand_name',
        'brand_primary_color',
        'brand_logo_url',
        'brand_avatar_url',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address' => 'array',
            'settings' => 'array',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
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

        return $this->normalizeNiche($rawNiche);
    }

    public function activePlanName(): string
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $rawPlan = trim((string) ($settings['active_plan_name'] ?? ''));

        return $rawPlan !== '' ? $rawPlan : 'Sem plano';
    }

    public function setBusinessNiche(string $niche): self
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $settings['business_niche'] = $this->normalizeNiche($niche);
        $this->settings = $settings;

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
        return match ($this->niche()) {
            self::NICHE_SERVICES => [self::MODULE_SERVICES],
            default => [self::MODULE_COMMERCIAL],
        };
    }

    public function hasModule(string $module): bool
    {
        return in_array($module, $this->enabledModules(), true);
    }

    private function normalizeNiche(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::availableNiches(), true)
            ? $normalized
            : self::defaultNiche();
    }
}
