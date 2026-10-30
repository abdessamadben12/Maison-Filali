<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    public const TYPES = ['vetement' => 'Vêtements', 'pointure' => 'Pointures'];

    protected $fillable = ['name', 'type', 'guide', 'position'];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
