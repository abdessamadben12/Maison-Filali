<?php

namespace App\Models;

use App\Support\Img;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Offre : plusieurs produits vendus ensemble à un prix de pack. */
class Pack extends Model
{
    protected $fillable = [
        'name', 'slug', 'tagline', 'description', 'images', 'price', 'compare_price',
        'is_active', 'is_featured', 'starts_at', 'ends_at', 'position',
    ];

    protected $casts = [
        'images' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'price' => 'integer',
        'compare_price' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Pack $pack) {
            if (blank($pack->slug)) {
                $base = Str::slug($pack->name) ?: 'offre';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->whereKeyNot($pack->id)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $pack->slug = $slug;
            }
        });
        static::saved(function (Pack $pack) {
            self::forgetCache();
            foreach ($pack->images ?? [] as $path) {
                Img::optimize($path);
            }
        });
        static::deleted(fn () => self::forgetCache());
    }

    public static function forgetCache(): void
    {
        Cache::store('file')->forget('packs.has_active');
        Cache::store('file')->forget('sitemap.v2');
    }

    /** Y a-t-il au moins une offre en ligne ? (lien « Offres » du menu) */
    public static function hasActive(): bool
    {
        try {
            return Cache::store('file')->remember('packs.has_active', 600, fn () => static::active()->exists());
        } catch (\Throwable) {
            return false; // table pas encore créée (php artisan migrate)
        }
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackItem::class)->orderBy('position')->orderBy('id');
    }

    /** En ligne et dans sa période (début / fin facultatifs). */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isAvailable(): bool
    {
        return $this->is_active
            && (! $this->starts_at || $this->starts_at->isPast())
            && (! $this->ends_at || $this->ends_at->isFuture());
    }

    /** Valeur des produits achetés séparément. */
    public function getItemsValueAttribute(): int
    {
        $this->loadMissing('items.product');

        return (int) $this->items->sum(fn ($i) => ($i->product?->price ?? 0) * $i->quantity);
    }

    /** Prix barré : celui saisi, sinon la valeur des produits. */
    public function getCompareTotalAttribute(): int
    {
        return (int) ($this->compare_price ?: $this->items_value);
    }

    public function getSavingsAttribute(): int
    {
        return max(0, $this->compare_total - $this->price);
    }

    public function getSavingsPercentAttribute(): int
    {
        return $this->compare_total > 0 ? (int) round($this->savings * 100 / $this->compare_total) : 0;
    }

    /** Chaque produit du pack a au moins une variante avec assez de stock. */
    public function inStock(int $packQty = 1): bool
    {
        $this->loadMissing('items.product.variants');

        return $this->items->isNotEmpty() && $this->items->every(
            fn ($i) => $i->product && $i->product->is_active && $i->product->variants->contains(fn ($v) => $v->stock >= $i->quantity * $packQty)
        );
    }

    /** Photos du pack, sinon celles de ses produits. @return array<int, string> */
    private function imagePaths(): array
    {
        $paths = collect($this->images ?? [])->filter()->values()->all();
        if ($paths) {
            return $paths;
        }
        $this->loadMissing('items.product');

        return $this->items->map(fn ($i) => $i->product?->images[0] ?? null)->filter()->values()->all();
    }

    /** @return array<int, string> */
    public function getGalleryUrlsAttribute(): array
    {
        $urls = array_map(fn ($p) => Img::url($p), $this->imagePaths());

        return $urls ?: [asset('images/logo.png')];
    }

    /** @return array<int, string> */
    public function getThumbUrlsAttribute(): array
    {
        $urls = array_map(fn ($p) => Img::url($p, 'sm'), $this->imagePaths());

        return $urls ?: [asset('images/logo-160.webp')];
    }

    /** @return array<int, string> */
    public function getOriginalImageUrlsAttribute(): array
    {
        $urls = array_map(fn ($p) => asset('uploads/'.$p), $this->imagePaths());

        return $urls ?: [asset('images/og-image.jpg')];
    }

    public function getImageUrlAttribute(): string
    {
        return $this->gallery_urls[0];
    }
}
