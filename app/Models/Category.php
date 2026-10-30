<?php

namespace App\Models;

use App\Support\Img;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'image', 'in_menu', 'is_active', 'position'];

    protected $casts = ['in_menu' => 'boolean', 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (Category $c) {
            if (blank($c->slug)) {
                $c->slug = Str::slug($c->name);
            }
        });
        // Vider le cache du menu et du plan du site
        static::saved(function (Category $c) {
            static::flushCache();
            if ($c->image) {
                Img::optimize($c->image);
            }
        });
        static::deleted(fn () => static::flushCache());
    }

    /** Le menu du haut a-t-il encore de la place ? */
    public static function menuIsFull(?Category $except = null): bool
    {
        return static::where('in_menu', true)->when($except?->exists, fn ($q) => $q->whereKeyNot($except->getKey()))->count()
            >= (int) config('shop.menu_max', 4);
    }

    public static function flushCache(): void
    {
        Cache::store('file')->forget('nav.categories');
        Cache::store('file')->forget('nav.categories.v2');
        Cache::store('file')->forget('footer.categories');
        Cache::store('file')->forget('sitemap.v2');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('position')->orderBy('name');
    }

    public function getThumbUrlAttribute(): string
    {
        return $this->image ? Img::url($this->image, 'sm') : asset('images/logo-160.webp');
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image ? Img::url($this->image) : asset('images/logo.png');
    }
}
