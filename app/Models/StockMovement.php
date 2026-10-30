<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const REASONS = [
        'reception_atelier' => 'Réception atelier',
        'retour_client' => 'Retour client',
        'correction' => 'Correction d’inventaire',
        'commande' => 'Commande confirmée',
        'annulation' => 'Commande annulée',
    ];

    protected $fillable = ['product_variant_id', 'order_id', 'user_id', 'quantity', 'stock_after', 'reason', 'note'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
