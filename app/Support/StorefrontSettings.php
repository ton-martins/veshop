<?php

namespace App\Support;

use App\Models\Contractor;

class StorefrontSettings
{
    public const TEMPLATE_COMMERCE = 'comercio';

    public const TEMPLATE_HYBRID = 'hibrido';

    public const TEMPLATE_SERVICES = 'servicos';

    /**
     * @var array<string, string>
     */
    public const BUSINESS_HOUR_DAYS = [
        'monday' => 'Segunda',
        'tuesday' => 'Terça',
        'wednesday' => 'Quarta',
        'thursday' => 'Quinta',
        'friday' => 'Sexta',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo',
    ];

    /**
     * @return list<string>
     */
    public static function templates(): array
    {
        return [
            self::TEMPLATE_COMMERCE,
            self::TEMPLATE_HYBRID,
            self::TEMPLATE_SERVICES,
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $raw
     * @return array<string, mixed>
     */
    public static function normalize(Contractor $contractor, mixed $raw): array
    {
        $settings = is_array($raw) ? $raw : [];
        $template = self::normalizeTemplate($contractor, $settings['template'] ?? null);
        $brandName = trim((string) ($contractor->brand_name ?: $contractor->name ?: 'sua loja'));

        $rawBlocks = is_array($settings['blocks'] ?? null) ? $settings['blocks'] : [];
        $rawHero = is_array($settings['hero'] ?? null) ? $settings['hero'] : [];
        $rawPromotions = is_array($settings['promotions'] ?? null) ? $settings['promotions'] : [];
        $rawCatalog = is_array($settings['catalog'] ?? null) ? $settings['catalog'] : [];
        $rawBusinessHours = is_array($settings['business_hours'] ?? null) ? $settings['business_hours'] : [];

        $defaultColor = self::normalizeHex((string) ($contractor->brand_primary_color ?? ''), '#073341');
        $defaultMenuButtonColor = self::normalizeHex((string) ($contractor->brand_primary_color ?? ''), '#FF5C35');

        return [
            'template' => $template,
            'store_online' => (bool) ($settings['store_online'] ?? true),
            'customer_whatsapp_contact_enabled' => (bool) ($settings['customer_whatsapp_contact_enabled'] ?? false),
            'offline_message' => self::normalizeText(
                $settings['offline_message'] ?? null,
                self::defaultOfflineMessage(),
                240
            ),
            'blocks' => [
                'hero' => (bool) ($rawBlocks['hero'] ?? true),
                'banners' => (bool) ($rawBlocks['banners'] ?? true),
                'promotions' => (bool) ($rawBlocks['promotions'] ?? true),
                'categories' => (bool) ($rawBlocks['categories'] ?? true),
                'catalog' => (bool) ($rawBlocks['catalog'] ?? true),
            ],
            'hero' => [
                'title' => self::normalizeText(
                    $rawHero['title'] ?? null,
                    self::defaultHeroTitle($template, $brandName),
                    120
                ),
                'subtitle' => self::normalizeText(
                    $rawHero['subtitle'] ?? null,
                    self::defaultHeroSubtitle($template),
                    220
                ),
                'cta_label' => self::normalizeText(
                    $rawHero['cta_label'] ?? null,
                    self::defaultHeroCtaLabel($template),
                    40
                ),
            ],
            'banners' => self::normalizeBanners($settings['banners'] ?? [], $defaultColor),
            'promotions' => [
                'title' => self::normalizeText(
                    $rawPromotions['title'] ?? null,
                    self::defaultPromotionsTitle($template),
                    80
                ),
                'subtitle' => self::normalizeText(
                    $rawPromotions['subtitle'] ?? null,
                    self::defaultPromotionsSubtitle($template),
                    220
                ),
                'product_ids' => self::normalizeProductIds($rawPromotions['product_ids'] ?? []),
                'service_ids' => self::normalizeServiceIds($rawPromotions['service_ids'] ?? []),
            ],
            'catalog' => [
                'title' => self::normalizeText(
                    $rawCatalog['title'] ?? null,
                    self::defaultCatalogTitle($template),
                    80
                ),
                'subtitle' => self::normalizeText(
                    $rawCatalog['subtitle'] ?? null,
                    self::defaultCatalogSubtitle($template),
                    220
                ),
            ],
            'theme' => self::normalizeTheme($settings['theme'] ?? [], $defaultMenuButtonColor),
            'business_hours' => self::normalizeBusinessHours($rawBusinessHours),
        ];
    }

    public static function defaultTemplate(Contractor $contractor): string
    {
        return $contractor->niche() === Contractor::NICHE_SERVICES
            ? self::TEMPLATE_SERVICES
            : self::TEMPLATE_COMMERCE;
    }

    public static function normalizeTemplate(Contractor $contractor, mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, self::templates(), true)) {
            return $normalized;
        }

        return self::defaultTemplate($contractor);
    }

    /**
     * @param  array<int, mixed>|mixed  $raw
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeBanners(mixed $raw, string $fallbackColor = '#073341'): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $items = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $title = self::normalizeText($item['title'] ?? null, '', 80);
            $subtitle = self::normalizeText($item['subtitle'] ?? null, '', 160);
            $badge = self::normalizeText($item['badge'] ?? null, '', 40);
            $imageUrl = self::normalizeText($item['image_url'] ?? null, '', 255);
            $imagePath = self::normalizeStoragePath(
                self::normalizeText($item['image_path'] ?? null, '', 255),
                $imageUrl
            );
            $ctaLabel = self::normalizeText($item['cta_label'] ?? null, '', 40);
            $backgroundColor = self::normalizeHex(
                (string) ($item['background_color'] ?? ''),
                $fallbackColor
            );
            $useOriginalImageColors = (bool) ($item['use_original_image_colors'] ?? false);
            $imageOnly = (bool) ($item['image_only'] ?? false);

            if ($title === '' && $subtitle === '' && $badge === '' && $imageUrl === '' && $ctaLabel === '' && $imagePath === '') {
                continue;
            }

            $items[] = [
                'title' => $title,
                'subtitle' => $subtitle,
                'badge' => $badge,
                'image_url' => $imageUrl,
                'image_path' => $imagePath,
                'cta_label' => $ctaLabel,
                'background_color' => $backgroundColor,
                'use_original_image_colors' => $useOriginalImageColors,
                'image_only' => $imageOnly,
            ];

            if (count($items) >= 2) {
                break;
            }
        }

        return array_values($items);
    }

    /**
     * @param  array<string, mixed>|mixed  $raw
     * @return array<string, string>
     */
    public static function normalizeTheme(mixed $raw, string $fallbackMenuColor = '#FF5C35'): array
    {
        $theme = is_array($raw) ? $raw : [];

        return [
            'menu_button_color' => self::normalizeHex((string) ($theme['menu_button_color'] ?? ''), $fallbackMenuColor),
            'cart_button_color' => self::normalizeHex((string) ($theme['cart_button_color'] ?? ''), '#F58D1D'),
            'favorite_button_color' => self::normalizeHex((string) ($theme['favorite_button_color'] ?? ''), '#FF3B30'),
            'add_button_color' => self::normalizeHex((string) ($theme['add_button_color'] ?? ''), '#F59E0B'),
        ];
    }

    /**
     * @param  array<int, mixed>|mixed  $raw
     * @return array<int, int>
     */
    public static function normalizeProductIds(mixed $raw): array
    {
        return self::normalizeIntegerIds($raw);
    }

    /**
     * @param  array<int, mixed>|mixed  $raw
     * @return array<int, int>
     */
    public static function normalizeServiceIds(mixed $raw): array
    {
        return self::normalizeIntegerIds($raw);
    }

    /**
     * @param  array<string, mixed>|mixed  $raw
     * @return array<string, array{enabled: bool, open: string, close: string}>
     */
    public static function normalizeBusinessHours(mixed $raw): array
    {
        $items = is_array($raw) ? $raw : [];
        $normalized = [];

        foreach (self::businessHourDayKeys() as $day) {
            $row = is_array($items[$day] ?? null) ? $items[$day] : [];
            $open = self::normalizeHour($row['open'] ?? null, '00:00');
            $close = self::normalizeHour($row['close'] ?? null, '23:59');

            $openMinutes = self::timeToMinutes($open);
            $closeMinutes = self::timeToMinutes($close);
            if ($closeMinutes <= $openMinutes) {
                $open = '00:00';
                $close = '23:59';
            }

            $normalized[$day] = [
                'enabled' => (bool) ($row['enabled'] ?? true),
                'open' => $open,
                'close' => $close,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    public static function businessHourDayKeys(): array
    {
        return array_keys(self::BUSINESS_HOUR_DAYS);
    }

    public static function normalizeHex(string $value, string $fallback): string
    {
        $candidate = trim($value);

        if (! str_starts_with($candidate, '#')) {
            $candidate = '#'.$candidate;
        }

        if (preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $candidate) === 1) {
            return strtoupper($candidate);
        }

        return strtoupper($fallback);
    }

    private static function defaultHeroTitle(string $template, string $brandName): string
    {
        return match ($template) {
            self::TEMPLATE_SERVICES => "Agende serviços em {$brandName}",
            default => "Compre em {$brandName}",
        };
    }

    private static function defaultHeroSubtitle(string $template): string
    {
        return match ($template) {
            self::TEMPLATE_SERVICES => 'Atendimento rápido, qualidade e praticidade para seu dia a dia.',
            default => 'Confira ofertas e finalize seu pedido direto pela loja virtual.',
        };
    }

    private static function defaultHeroCtaLabel(string $template): string
    {
        return match ($template) {
            self::TEMPLATE_SERVICES => 'Ver serviços',
            default => 'Explorar produtos',
        };
    }

    private static function defaultPromotionsTitle(string $template): string
    {
        return $template === self::TEMPLATE_SERVICES
            ? 'Destaques da semana'
            : 'Promoções da semana';
    }

    private static function defaultPromotionsSubtitle(string $template): string
    {
        return $template === self::TEMPLATE_SERVICES
            ? 'Serviços selecionados para agilizar seu atendimento.'
            : 'Ofertas selecionadas para você comprar com economia.';
    }

    private static function defaultCatalogTitle(string $template): string
    {
        return match ($template) {
            self::TEMPLATE_SERVICES => 'Catálogo de serviços',
            default => 'Catálogo de produtos',
        };
    }

    private static function defaultCatalogSubtitle(string $template): string
    {
        return $template === self::TEMPLATE_SERVICES
            ? 'Escolha o serviço ideal e solicite atendimento.'
            : 'Busque por categoria, compare preços e monte seu carrinho.';
    }

    private static function defaultOfflineMessage(): string
    {
        return 'Loja temporariamente indisponível. Tente novamente mais tarde.';
    }

    private static function normalizeText(mixed $value, string $fallback = '', int $maxLength = 255): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return $fallback;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maxLength);
        }

        return substr($text, 0, $maxLength);
    }

    private static function normalizeStoragePath(string $path, string $url): string
    {
        $candidate = trim($path);
        if ($candidate !== '') {
            return ltrim($candidate, '/');
        }

        $urlPath = parse_url($url, PHP_URL_PATH);
        if (! is_string($urlPath) || $urlPath === '') {
            return '';
        }

        if (str_starts_with($urlPath, '/storage/')) {
            return ltrim(substr($urlPath, strlen('/storage/')), '/');
        }

        if (str_starts_with($urlPath, 'storage/')) {
            return ltrim(substr($urlPath, strlen('storage/')), '/');
        }

        return '';
    }

    /**
     * @param  array<int, mixed>|mixed  $raw
     * @return array<int, int>
     */
    private static function normalizeIntegerIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id <= 0) {
                continue;
            }

            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    private static function normalizeHour(mixed $value, string $fallback): string
    {
        $hour = trim((string) ($value ?? ''));
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hour) !== 1) {
            return $fallback;
        }

        return $hour;
    }

    private static function timeToMinutes(string $time): int
    {
        [$hour, $minute] = explode(':', $time) + [0, 0];

        return ((int) $hour * 60) + (int) $minute;
    }
}
