<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    public const NICHE_COMMERCIAL = 'commercial';

    public const NICHE_SERVICES = 'services';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'niche',
        'name',
        'slug',
        'badge',
        'subtitle',
        'summary',
        'footer_message',
        'price_monthly',
        'description',
        'features',
        'max_admin_users',
        'user_limit',
        'storage_limit_gb',
        'audit_log_retention_days',
        'is_active',
        'is_featured',
        'show_on_landing',
        'tier_rank',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'show_on_landing' => 'boolean',
            'max_admin_users' => 'integer',
            'user_limit' => 'integer',
            'storage_limit_gb' => 'integer',
            'audit_log_retention_days' => 'integer',
            'tier_rank' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function contractors(): HasMany
    {
        return $this->hasMany(Contractor::class);
    }

    public function reportProfiles(): HasMany
    {
        return $this->hasMany(ReportProfile::class);
    }

    public function reportMetricSnapshots(): HasMany
    {
        return $this->hasMany(ReportMetricSnapshot::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_module')
            ->withTimestamps();
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

    public static function normalizeNiche(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::availableNiches(), true)
            ? $normalized
            : self::defaultNiche();
    }

    public static function labelForNiche(string $niche): string
    {
        return match (self::normalizeNiche($niche)) {
            self::NICHE_SERVICES => 'Serviços',
            default => 'Comércio',
        };
    }
}
