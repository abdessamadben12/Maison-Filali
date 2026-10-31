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
        $json = json_encode(self::prune($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

        return new HtmlString('<script type="application/ld+json">'.$json.'</script>');
    }

    /** Retire les valeurs vides à tous les niveaux (Google signale les champs null). */
    private static function prune(array $data): array
    {
        $isList = array_is_list($data);
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $v = self::prune($v);
            }
            if ($v === null || $v === [] || $v === '') {
                unset($data[$k]);
            } else {
                $data[$k] = $v;
            }
        }

        return $isList ? array_values($data) : $data;
    }

    public static function description(?string $text, int $limit = 158): string
    {
        // Espace avant chaque balise : « <h2>Livraison</h2><p>Nous… » → « Livraison Nous… »
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', (string) $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim(preg_replace('/\s+/u', ' ', str_replace(['[', ']'], '', $text))), $limit, '…');
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
            'alternateName' => ['La Maison Filali', 'maisonfilali.com'],
            'logo' => ['@type' => 'ImageObject', 'url' => asset('images/logo.png'), 'width' => 400, 'height' => 400],
            'image' => asset('images/og-image.jpg'),
            'description' => 'Vêtements traditionnels marocains faits main : caftans, takchitas, djellabas et babouches, faits main à Fès. Livraison au Maroc et à l\'international, paiement à la livraison au Maroc.',
            'telephone' => self::intlPhone(config('shop.phone')),
            'email' => config('shop.contact_email'),
            'priceRange' => self::priceRange(),
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'contactType' => 'customer service',
                'telephone' => self::intlPhone(config('shop.whatsapp') ?: config('shop.phone')),
                'email' => config('shop.contact_email'),
                'availableLanguage' => ['French', 'Arabic'],
                'areaServed' => 'MA',
            ],
            'hasMerchantReturnPolicy' => self::returnPolicy(),
            'currenciesAccepted' => 'MAD',
            'paymentAccepted' => 'Paiement à la livraison (espèces)',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => trim(implode(', ', $address)),
                'addressLocality' => 'Casablanca',
                'addressCountry' => 'MA',
            ],
            'areaServed' => [['@type' => 'Country', 'name' => 'Maroc'], ['@type' => 'Place', 'name' => 'International']],
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
            'alternateName' => ['La Maison Filali', 'maisonfilali.com'],
            'url' => url('/').'/',
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
                'seller' => ['@type' => 'Organization', '@id' => url('/').'/#boutique', 'name' => config('shop.name')],
                'hasMerchantReturnPolicy' => self::returnPolicy(),
                'shippingDetails' => self::shipping(),
            ],
        ];
    }

    /** Échange / retour sous 7 jours (page « Livraison & retours »). */
    public static function returnPolicy(): array
    {
        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'MA',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 7,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/ReturnShippingFees',
            'merchantReturnLink' => route('page', 'livraison-retours'),
        ];
    }

    /** Livraison au Maroc en 24–72 h. Publié seulement si les frais sont renseignés (Admin → Paramètres → SEO). */
    public static function shipping(): ?array
    {
        $fee = config('shop.shipping_fee');
        if ($fee === null || $fee === '' || ! is_numeric($fee)) {
            return null;
        }

        return [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => (string) (0 + $fee), 'currency' => 'MAD'],
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'MA'],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
                'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 3, 'unitCode' => 'DAY'],
            ],
        ];
    }

    /** « 280 DH – 4 500 DH » d'après les prix du catalogue (mis en cache). */
    public static function priceRange(): ?string
    {
        try {
            [$min, $max] = \Illuminate\Support\Facades\Cache::store('file')->remember('seo.price_range', 3600, fn () => [Product::active()->min('price'), Product::active()->max('price')]);
        } catch (\Throwable) {
            return null;
        }

        return $min ? money($min).($max > $min ? ' – '.money($max) : '') : null;
    }

    /** Liste des produits d'une page catégorie / boutique (CollectionPage + ItemList). */
    public static function collection(string $name, string $description, iterable $products): array
    {
        $items = [];
        $i = 0;
        foreach ($products as $p) {
            $items[] = ['@type' => 'ListItem', 'position' => ++$i, 'url' => route('product', $p), 'name' => $p->name];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'description' => $description,
            'url' => url()->current(),
            'isPartOf' => ['@id' => url('/').'/#site'],
            'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $i, 'itemListElement' => $items],
        ];
    }
}
