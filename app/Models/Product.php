<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'category_id', 'name', 'slug', 'tagline', 'description', 'materials', 'price', 'compare_price',
        'images', 'is_active', 'is_new', 'is_featured', 'is_custom_possible', 'position',
    ];

    protected $casts = [
        'images' => 'array',
        'is_active' => 'boolean',
        'is_new' => 'boolean',
        'is_featured' => 'boolean',
        'is_custom_possible' => 'boolean',
        'price' => 'integer',
        'compare_price' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $p) {
            if (blank($p->slug)) {
                $base = Str::slug($p->name);
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->where('id', '!=', $p->id ?? 0)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $p->slug = $slug;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->whereHas('category', fn ($c) => $c->where('is_active', true));
    }

    public function getImageUrlAttribute(): string
    {
        return $this->gallery_urls[0];
    }

    /** @return array<int, string> */
    public function getGalleryUrlsAttribute(): array
    {
        $urls = collect($this->images ?? [])->filter()->map(fn ($p) => asset('uploads/'.$p))->values()->all();

        return $urls ?: [asset('images/logo.png')];
    }

    public function getTotalStockAttribute(): int
    {
        return (int) ($this->relationLoaded('variants') ? $this->variants->sum('stock') : $this->variants()->sum('stock'));
    }

    public function inStock(): bool
    {
        return $this->total_stock > 0;
    }

    /** Couleurs proposées pour ce produit (ordre défini dans l’admin). */
    public function availableColors(bool $withStockFirst = false): Collection
    {
        $this->loadMissing('variants.color');

        return $this->variants->pluck('color')->filter()->unique('id')->sortBy('position')->values();
    }

    public function availableSizes(): Collection
    {
        $this->loadMissing('variants.size');

        return $this->variants->pluck('size')->filter()->unique('id')->sortBy('position')->values();
    }

    /** « Tailles S à XXL · Sur mesure » */
    public function getSizesSummaryAttribute(): string
    {
        $sizes = $this->availableSizes();
        $parts = [];
        if ($sizes->count()) {
            $word = $sizes->first()->type === 'pointure' ? 'Pointures' : 'Tailles';
            $parts[] = $sizes->count() > 1 ? "$word {$sizes->first()->name} à {$sizes->last()->name}" : rtrim($word, 's').' '.$sizes->first()->name;
        }
        if ($this->is_custom_possible) {
            $parts[] = 'Sur mesure';
        }

        return implode(' · ', $parts);
    }
}
