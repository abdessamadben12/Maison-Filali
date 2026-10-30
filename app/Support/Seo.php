<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** Données structurées (JSON-LD) lues par Google pour les résultats enrichis. */
class Seo
{
    public static function jsonLd(array $data): HtmlString
    {
        $json = json_encode(array_filter($data, fn ($v) => $v !== null && $v !== []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return new HtmlString('<script type="application/ld+json">'.$json.'</script>');
    }

    public static function description(?string $text, int $limit = 158): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $text))), $limit, '…');
    }

    /** 06 78 20 88 68 → +212678208868 */
    public static function intlPhone(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        } elseif (str_starts_with($d, '0')) {
            $d = '212'.substr($d, 1);
        }

        return '+'.$d;
    }

    public static function sameAs(): array
    {
        return array_values(array_filter([config('shop.instagram_url'), config('shop.facebook_url'), config('shop.tiktok_url')]));
    }

    /** Boutique (magasin de vêtements) + site, affichés sur l'accueil et la page Contact. */
    public static function store(): array
    {
        $address = preg_split('/\R/', (string) config('shop.address'));

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ClothingStore',
            '@id' => url('/').'/#boutique',
            'name' => config('shop.name'),
            'url' => url('/'),
            'logo' => asset('images/logo.png'),
            'image' => asset('images/og-image.jpg'),
            'description' => 'Vêtements traditionnels marocains faits main : caftans, takchitas, djellabas et babouches. Paiement à la livraison partout au Maroc.',
            'telephone' => self::intlPhone(config('shop.phone')),
            'currenciesAccepted' => 'MAD',
            'paymentAccepted' => 'Paiement à la livraison (espèces)',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => trim(implode(', ', $address)),
                'addressLocality' => 'Casablanca',
                'addressCountry' => 'MA',
            ],
            'areaServed' => ['@type' => 'Country', 'name' => 'Maroc'],
            'hasMap' => config('shop.maps_url'),
            'sameAs' => self::sameAs(),
        ];
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => url('/').'/#site',
            'name' => config('shop.name'),
            'url' => url('/'),
            'inLanguage' => 'fr-MA',
            'publisher' => ['@id' => url('/').'/#boutique'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('catalogue').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /** @param array<int, array{0:string,1:string}> $items [nom, url] */
    public static function breadcrumb(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($it, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $it[0], 'item' => $it[1],
            ])->all(),
        ];
    }

    public static function product(Product $product): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'url' => route('product', $product),
            'image' => $product->original_image_urls,
            'description' => self::description($product->description ?: $product->tagline, 500),
            'sku' => 'MF-'.$product->id,
            'brand' => ['@type' => 'Brand', 'name' => config('shop.name')],
            'category' => $product->category?->name,
            'offers' => [
                '@type' => 'Offer',
                'url' => route('product', $product),
                'price' => (string) $product->price,
                'priceCurrency' => 'MAD',
                'availability' => $product->inStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@id' => url('/').'/#boutique'],
                'hasMerchantReturnPolicy' => [
                    '@type' => 'MerchantReturnPolicy',
                    'applicableCountry' => 'MA',
                    'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
                    'merchantReturnDays' => 7,
                ],
            ],
        ];
    }
}
