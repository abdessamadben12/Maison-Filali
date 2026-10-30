<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'color_id', 'size_id', 'stock'];

    protected $casts = ['stock' => 'integer'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** « Bordeaux · M » */
    public function getLabelAttribute(): string
    {
        return collect([$this->color?->name, $this->size?->name])->filter()->implode(' · ') ?: 'Modèle unique';
    }

    /** « Caftan Velours Lalla — Bordeaux · M » */
    public function getFullLabelAttribute(): string
    {
        return $this->product?->name.' — '.$this->label;
    }

    public function getStockStatusAttribute(): string
    {
        return match (true) {
            $this->stock <= 0 => 'rupture',
            $this->stock <= config('shop.low_stock') => 'faible',
            default => 'ok',
        };
    }

    /** Ajoute (ou retire si négatif) du stock et garde une trace. */
    public function adjustStock(int $quantity, string $reason, ?string $note = null, ?int $orderId = null): void
    {
        DB::transaction(function () use ($quantity, $reason, $note, $orderId) {
            // The predicate and increment are one SQL write, including on SQLite
            // where SELECT FOR UPDATE does not lock a row.
            $updated = static::whereKey($this->id)
                ->where('stock', '>=', -$quantity)
                ->increment('stock', $quantity);
            if (! $updated) {
                throw new RuntimeException('Stock insuffisant ou variante supprimée. Actualisez la page.');
            }
            $stock = (int) static::whereKey($this->id)->value('stock');

            $this->movements()->create([
                'order_id' => $orderId,
                'user_id' => auth()->id(),
                'quantity' => $quantity,
                'stock_after' => $stock,
                'reason' => $reason,
                'note' => $note,
            ]);
        }, 3);
        $this->refresh();
    }
}
