<?php

declare(strict_types=1);

namespace Ragefill;

class SeoHelper
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Meta tags for the catalog (main page)
     */
    public function catalogMeta(array $sauces = []): string
    {
        $title = 'Каталог RAGEFILL — острые соусы, подарочные наборы, специи | Купить в Беларуси';
        $desc = 'Каталог RAGEFILL: острые соусы, подарочные наборы, маринованные перцы, специи и арахис ручной работы. Carolina Reaper, Habanero и другие. Доставка по Беларуси.';
        $url = $this->baseUrl . '/catalog';

        // Use first product image as OG fallback
        $image = '';
        foreach ($sauces as $sauce) {
            if (!empty($sauce['image'])) {
                $image = $this->baseUrl . '/uploads/' . $sauce['image'];
                break;
            }
        }

        return $this->buildMetaTags($title, $desc, $url, $image);
    }

    /**
     * Meta tags for an individual product page
     */
    public function productMeta(array $sauce): string
    {
        $name = $sauce['name'] ?? '';
        $subtitle = $sauce['subtitle'] ?? '';
        $category = $sauce['category'] ?? 'sauce';
        $heat = (int)($sauce['heat_level'] ?? 3);
        $descText = $this->stripHtml($sauce['description'] ?? '');
        $shortDesc = mb_substr($descText, 0, 155, 'UTF-8');
        if (mb_strlen($descText, 'UTF-8') > 155) {
            $shortDesc .= '…';
        }

        // Category-aware title suffix
        $titleSuffix = match ($category) {
            'gift_set' => 'подарочный набор RAGEFILL',
            'pickled_pepper' => 'маринованные перцы RAGEFILL',
            'spicy_peanut' => 'острый арахис RAGEFILL',
            'spice' => 'специи RAGEFILL',
            default => 'острый соус RAGEFILL',
        };
        $title = "{$name} — {$titleSuffix}";

        // Fallback description: subtitle → description excerpt → generated
        $desc = $subtitle ?: $shortDesc;
        if ($desc === '') {
            $categoryLabel = match ($category) {
                'gift_set' => 'Подарочный набор',
                'pickled_pepper' => 'Маринованные перцы',
                'spicy_peanut' => 'Острый арахис',
                'spice' => 'Специи',
                default => 'Острый соус',
            };
            $desc = "{$categoryLabel} {$name} от RAGEFILL. Острота {$heat}/5. Купить в Беларуси с доставкой.";
        }

        $url = $this->baseUrl . '/sauce/' . ($sauce['slug'] ?? $sauce['id']);
        $image = !empty($sauce['image'])
            ? $this->baseUrl . '/uploads/' . $sauce['image']
            : '';

        return $this->buildMetaTags($title, $desc, $url, $image, 'product');
    }

    /**
     * JSON-LD structured data for a product
     */
    public function productJsonLd(array $sauce): string
    {
        $name = $sauce['name'];
        $desc = $this->stripHtml($sauce['description'] ?? '');
        $image = !empty($sauce['image'])
            ? $this->baseUrl . '/uploads/' . $sauce['image']
            : null;
        $url = $this->baseUrl . '/sauce/' . ($sauce['slug'] ?? $sauce['id']);
        $inStock = ($sauce['in_stock'] ?? 1) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
        $categoryMap = [
            'sauce' => 'Острые соусы',
            'gift_set' => 'Подарочные наборы',
            'pickled_pepper' => 'Маринованные перцы',
            'spicy_peanut' => 'Острый арахис',
            'spice' => 'Специи',
        ];
        $category = $categoryMap[$sauce['category'] ?? 'sauce'] ?? 'Острые соусы';

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'description' => $desc,
            'url' => $url,
            'brand' => [
                '@type' => 'Brand',
                'name' => 'RAGEFILL',
            ],
            'category' => $category,
            'offers' => [
                '@type' => 'Offer',
                'availability' => $inStock,
                'url' => $url,
            ],
        ];

        if ($image) {
            $data['image'] = $image;
        }

        return '<script type="application/ld+json">'
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            . '</script>';
    }

    /**
     * BreadcrumbList JSON-LD for product pages
     */
    public function breadcrumbJsonLd(string $productName, string $productSlug): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Каталог',
                    'item' => $this->baseUrl . '/',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $productName,
                    'item' => $this->baseUrl . '/sauce/' . $productSlug,
                ],
            ],
        ];

        return '<script type="application/ld+json">'
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</script>';
    }

    /**
     * Organization / LocalBusiness JSON-LD (reusable on any page)
     */
    public function organizationJsonLd(): string
    {
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => 'RAGEFILL',
            'url' => $this->baseUrl,
            'description' => 'Производитель сверхострых соусов ручной работы в Беларуси',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Минск',
                'addressCountry' => 'BY',
            ],
            'areaServed' => 'BY',
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'sales',
                'url' => 'https://t.me/rage_fill',
            ],
        ];

        return '<script type="application/ld+json">'
            . json_encode($org, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</script>';
    }

    /**
     * WebSite JSON-LD with SearchAction
     */
    public function websiteJsonLd(): string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => 'RAGEFILL',
            'url' => $this->baseUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $this->baseUrl . '/catalog?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];

        return '<script type="application/ld+json">'
            . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</script>';
    }

    /**
     * JSON-LD for the catalog (Organization + ItemList)
     */
    public function catalogJsonLd(array $sauces = []): string
    {
        $items = [];
        foreach ($sauces as $i => $sauce) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $this->baseUrl . '/sauce/' . ($sauce['slug'] ?? $sauce['id']),
                'name' => $sauce['name'],
            ];
        }

        $itemList = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Каталог острых соусов RAGEFILL',
            'numberOfItems' => count($sauces),
            'itemListElement' => $items,
        ];

        return $this->organizationJsonLd() . "\n"
            . '<script type="application/ld+json">'
            . json_encode($itemList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            . '</script>';
    }

    /**
     * Render a product card as static HTML (for SSR in catalog)
     */
    public function renderProductCard(array $sauce): string
    {
        $name = htmlspecialchars($sauce['name'], ENT_QUOTES, 'UTF-8');
        $subtitle = htmlspecialchars($sauce['subtitle'] ?? '', ENT_QUOTES, 'UTF-8');
        $heat = (int)($sauce['heat_level'] ?? 3);
        $inStock = ($sauce['in_stock'] ?? 1) != 0;
        $isHit = ($sauce['is_hit'] ?? 0) == 1;
        $isLowStock = ($sauce['is_low_stock'] ?? 0) == 1;
        $isNew = ($sauce['is_new'] ?? 0) == 1;
        $id = (int)$sauce['id'];

        $img = !empty($sauce['image'])
            ? '<img class="sauce-card__image" src="/uploads/' . htmlspecialchars($sauce['image'], ENT_QUOTES, 'UTF-8') . '" alt="' . $name . '" loading="lazy">'
            : '<div class="sauce-card__image-placeholder"></div>';

        $stockClass = $inStock ? '' : ' out-of-stock';
        $stockBadge = $inStock ? '' : '<span class="sauce-card__stock-badge sauce-card__stock-badge--out">Нет в наличии</span>';
        $hitBadge = $isHit ? '<span class="sauce-card__badge sauce-card__badge--hit">ХИТ</span>' : '';
        $lowStockBadge = $isLowStock ? '<span class="sauce-card__badge sauce-card__badge--low">МАЛО</span>' : '';
        $newBadge = $isNew ? '<span class="sauce-card__badge sauce-card__badge--new">НОВИНКА</span>' : '';

        $tier = $this->getHeatTier($heat);
        $peppers = $this->renderPeppers($heat);

        $subtitleHtml = $subtitle ? '<div class="sauce-card__subtitle line-clamp line-clamp-2">' . $subtitle . '</div>' : '';
        $slug = htmlspecialchars($sauce['slug'] ?? (string)$id, ENT_QUOTES, 'UTF-8');

        return <<<HTML
        <a href="/sauce/{$slug}" class="sauce-card{$stockClass}" data-id="{$id}" data-slug="{$slug}" role="listitem" aria-label="{$name}">
            <div class="sauce-card__image-wrap">
                {$img}
                {$stockBadge}
                {$hitBadge}
                {$newBadge}
                {$lowStockBadge}
            </div>
            <div class="sauce-card__content">
                <h3 class="sauce-card__name line-clamp line-clamp-2">{$name}</h3>
                {$subtitleHtml}
                <div class="sauce-card__bottom">
                    <div class="sauce-card__peppers">
                        <span class="sauce-card__pepper-icons">{$peppers}</span>
                        <span class="sauce-card__pepper-label">{$heat}/5</span>
                    </div>
                </div>
            </div>
            <div class="sauce-card__heat-accent sauce-card__heat-accent--{$tier['accent']}"></div>
        </a>
        HTML;
    }

    /**
     * Generate sitemap XML
     */
    public function generateSitemap(array $sauces): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        $xml .= $this->sitemapUrl($this->baseUrl . '/', '1.0', 'weekly');

        // Catalog
        $xml .= $this->sitemapUrl($this->baseUrl . '/catalog', '0.9', 'daily');

        // Privacy policy
        $xml .= $this->sitemapUrl($this->baseUrl . '/privacy', '0.3', 'yearly');

        // Product pages
        foreach ($sauces as $sauce) {
            $url = $this->baseUrl . '/sauce/' . ($sauce['slug'] ?? $sauce['id']);
            $lastmod = $sauce['updated_at'] ?? $sauce['created_at'] ?? null;
            $xml .= $this->sitemapUrl($url, '0.8', 'weekly', $lastmod);
        }

        $xml .= '</urlset>';
        return $xml;
    }

    /**
     * Generate robots.txt content
     */
    public function generateRobotsTxt(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /api/',
            '',
            'Sitemap: ' . $this->baseUrl . '/sitemap.xml',
            '',
        ]);
    }

    /**
     * Meta tags for the about page
     */
    public function buildAboutMeta(string $title, string $desc, string $url): string
    {
        return $this->buildMetaTags($title, $desc, $url, '');
    }

    // --- Private helpers ---

    private function buildMetaTags(string $title, string $desc, string $url, string $image, string $ogType = 'website'): string
    {
        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $d = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
        $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <meta name="description" content="{$d}">
        <link rel="canonical" href="{$u}">
        <meta property="og:type" content="{$ogType}">
        <meta property="og:title" content="{$t}">
        <meta property="og:description" content="{$d}">
        <meta property="og:url" content="{$u}">
        <meta property="og:site_name" content="RAGEFILL">
        <meta property="og:locale" content="ru_BY">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{$t}">
        <meta name="twitter:description" content="{$d}">
        HTML;

        if ($image !== '') {
            $img = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');
            $html .= "\n        <meta property=\"og:image\" content=\"{$img}\">";
            $html .= "\n        <meta name=\"twitter:image\" content=\"{$img}\">";
        }

        return $html;
    }

    private function sitemapUrl(string $loc, string $priority, string $freq, ?string $lastmod = null): string
    {
        $xml = "  <url>\n    <loc>" . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . "</loc>\n";
        if ($lastmod) {
            $date = date('Y-m-d', strtotime($lastmod));
            $xml .= "    <lastmod>{$date}</lastmod>\n";
        }
        $xml .= "    <changefreq>{$freq}</changefreq>\n";
        $xml .= "    <priority>{$priority}</priority>\n";
        $xml .= "  </url>\n";
        return $xml;
    }

    private function getHeatTier(int $level): array
    {
        if ($level >= 5) return ['label' => 'ЭКСТРЕМАЛЬНАЯ', 'accent' => 'extreme'];
        if ($level >= 4) return ['label' => 'СИЛЬНАЯ', 'accent' => 'fire'];
        if ($level >= 3) return ['label' => 'СРЕДНЯЯ', 'accent' => 'hot'];
        if ($level >= 2) return ['label' => 'УМЕРЕННАЯ', 'accent' => 'medium'];
        return ['label' => 'ЛЁГКАЯ', 'accent' => 'mild'];
    }

    private function renderPeppers(int $active, int $total = 5): string
    {
        $out = '';
        for ($i = 0; $i < $active; $i++) {
            $out .= '<span class="pepper active"><img src="/uploads/pepper.svg" alt="" width="16" height="16"></span>';
        }
        for ($i = $active; $i < $total; $i++) {
            $out .= '<span class="pepper dim"><img src="/uploads/pepper.svg" alt="" width="16" height="16"></span>';
        }
        return $out;
    }

    private function stripHtml(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }
}
