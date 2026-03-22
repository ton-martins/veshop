<?php

namespace App\Support;

use App\Models\Contractor;

class StorefrontSettings
{
    public const TEMPLATE_COMMERCE = 'comercio';

    public const TEMPLATE_SERVICES = 'servicos';

    /**
     * @return list<string>
     */
    public static function templates(): array
    {
        return [
            self::TEMPLATE_COMMERCE,
            self::TEMPLATE_SERVICES,
        ];
    }

    /**
     * @param array<string, mixed>|mixed $raw
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

        $defaultColor = self::normalizeHex((string) ($contractor->brand_primary_color ?? ''), '#073341');

        return [
            'template' => $template,
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
     * @param array<int, mixed>|mixed $raw
     * @return array<int, array<string, string>>
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
            $ctaUrl = self::normalizeText($item['cta_url'] ?? null, '', 255);
            $backgroundColor = self::normalizeHex(
                (string) ($item['background_color'] ?? ''),
                $fallbackColor
            );

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
                'cta_url' => $ctaUrl,
                'background_color' => $backgroundColor,
            ];

            if (count($items) >= 6) {
                break;
            }
        }

        return array_values($items);
    }

    /**
     * @param array<int, mixed>|mixed $raw
     * @return array<int, int>
     */
    public static function normalizeProductIds(mixed $raw): array
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
            self::TEMPLATE_SERVICES => "Atendimento rápido, qualidade e praticidade para seu dia a dia.",
            default => 'Confira ofertas e finalize seu pedido direto pela loja virtual.',
        };
    }

    private static function defaultHeroCtaLabel(string $template): string
    {
        return match ($template) {
            self::TEMPLATE_SERVICES => "Ver serviços",
            default => 'Explorar produtos',
        };
    }

    private static function defaultPromotionsTitle(string $template): string
    {
        return $template === self::TEMPLATE_SERVICES
            ? 'Destaques da semana'
            : "Promoções da semana";
    }

    private static function defaultPromotionsSubtitle(string $template): string
    {
        return $template === self::TEMPLATE_SERVICES
            ? "Serviços selecionados para agilizar seu atendimento."
            : "Ofertas selecionadas para você comprar com economia.";
    }

    private static function defaultCatalogTitle(string $template): string
    {
        return match ($template) {
            self::TEMPLATE_SERVICES => "Catálogo de serviços",
            default => "Catálogo de produtos",
        };
    }

    private static function defaultCatalogSubtitle(string $template): string
    {
        return $template === self::TEMPLATE_SERVICES
            ? "Escolha o serviço ideal e solicite atendimento."
            : "Busque por categoria, compare preços e monte seu carrinho.";
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
}
