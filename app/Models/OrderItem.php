<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'product_name', 'color_name', 'size_name', 'size_type',
        'is_custom', 'quantity', 'unit_price', 'total',
    ];

    protected $casts = ['is_custom' => 'boolean'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** « Bordeaux · Taille M » */
    public function getVariantLabelAttribute(): string
    {
        $size = $this->is_custom ? 'Sur mesure'
            : ($this->size_name ? ($this->size_type === 'pointure' ? 'Pointure ' : 'Taille ').$this->size_name : null);

        return collect([$this->color_name, $size])->filter()->implode(' · ') ?: 'Modèle unique';
    }
}
