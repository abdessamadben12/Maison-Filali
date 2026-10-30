<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function getImageUrlAttribute(): string
    {
        return $this->image ? asset('uploads/'.$this->image) : asset('images/logo.png');
    }
}
