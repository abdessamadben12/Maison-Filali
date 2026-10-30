<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Closure;
use RuntimeException;

class Order extends Model
{
    public const STATUSES = [
        'en_attente' => 'En attente',
        'confirmee' => 'Confirmée',
        'expediee' => 'Expédiée',
        'livree' => 'Livrée',
        'annulee' => 'Annulée',
    ];

    public const STATUS_COLORS = [
        'en_attente' => 'warning',
        'confirmee' => 'success',
        'expediee' => 'info',
        'livree' => 'gray',
        'annulee' => 'danger',
    ];

    protected $fillable = [
        'number', 'customer_id', 'user_id', 'customer_name', 'phone', 'city', 'address', 'status', 'total',
        'admin_note', 'stock_deducted', 'confirmed_at', 'shipped_at', 'delivered_at', 'cancelled_at',
    ];

    protected $casts = [
        'stock_deducted' => 'boolean',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if (! $order->number) {
                $order->number = sprintf('MF-%s-%04d', $order->created_at->format('Y'), $order->id);
                $order->saveQuietly();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return $q->where('status', $status);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusMessageAttribute(): string
    {
        return match ($this->status) {
            'en_attente' => 'En cours de vérification par notre équipe — confirmation sous 24 h.',
            'confirmee' => 'Confirmée : votre commande est en préparation à l’atelier.',
            'expediee' => 'Expédiée : le livreur vous appellera avant de passer.',
            'livree' => 'Livrée. Merci pour votre confiance !',
            'annulee' => 'Cette commande a été annulée.',
            default => '',
        };
    }

    public function getFirstNameAttribute(): string
    {
        return explode(' ', trim($this->customer_name))[0];
    }

    public function getSummaryAttribute(): string
    {
        $first = $this->items->first();
        if (! $first) {
            return '—';
        }

        return $first->product_name.($this->items->count() > 1 ? ' + '.($this->items->count() - 1) : '');
    }

    // ---------- Changements de statut ----------

    /** Vérifie que le stock suffit pour confirmer (renvoie la liste des problèmes). */
    public function stockProblems(): array
    {
        $this->loadMissing('items.variant');
        $problems = [];
        foreach ($this->items as $item) {
            if ($item->is_custom) {
                continue;
            }
            if (! $item->variant) {
                $problems[] = "{$item->product_name} : variante supprimée";
                continue;
            }
            if ($item->variant->stock < $item->quantity) {
                $problems[] = "{$item->product_name} ({$item->variant_label}) : {$item->variant->stock} en stock pour {$item->quantity} demandé(s)";
            }
        }

        return $problems;
    }

    public function confirm(): void
    {
        $this->transition(function (Order $order) {
            if ($order->status !== 'en_attente') {
                throw new RuntimeException('Seule une commande en attente peut être confirmée.');
            }
            if ($problems = $order->stockProblems()) {
                throw new RuntimeException('Stock insuffisant : '.implode(' ; ', $problems));
            }
            foreach ($order->items->sortBy('product_variant_id') as $item) {
                if (! $item->is_custom && $item->variant) {
                    $item->variant->adjustStock(-$item->quantity, 'commande', $order->number, $order->id);
                }
            }
            $order->update(['status' => 'confirmee', 'confirmed_at' => now(), 'stock_deducted' => true]);
        });
    }

    public function ship(): void
    {
        $this->transition(function (Order $order) {
            if ($order->status !== 'confirmee') {
                throw new RuntimeException('La commande doit être confirmée avant d’être expédiée.');
            }
            $order->update(['status' => 'expediee', 'shipped_at' => now()]);
        });
    }

    public function deliver(): void
    {
        $this->transition(function (Order $order) {
            if ($order->status !== 'expediee') {
                throw new RuntimeException('La commande doit être expédiée avant d’être livrée.');
            }
            $order->update(['status' => 'livree', 'delivered_at' => now()]);
        });
    }

    public function cancel(): void
    {
        $this->transition(function (Order $order) {
            if (in_array($order->status, ['livree', 'annulee'], true)) {
                throw new RuntimeException('Cette commande ne peut plus être annulée.');
            }
            if ($order->stock_deducted) {
                $order->loadMissing('items.variant');
                foreach ($order->items->sortBy('product_variant_id') as $item) {
                    if (! $item->is_custom && $item->variant) {
                        $item->variant->adjustStock($item->quantity, 'annulation', $order->number, $order->id);
                    }
                }
            }
            $order->update(['status' => 'annulee', 'cancelled_at' => now(), 'stock_deducted' => false]);
        });
    }

    private function transition(Closure $change): void
    {
        DB::transaction(function () use ($change) {
            // Acquire the SQLite write lock before reading the current status.
            // Other drivers use the row lock below.
            if (DB::getDriverName() === 'sqlite') {
                static::whereKey($this->id)->update(['status' => DB::raw('status')]);
            }
            $order = static::query()->lockForUpdate()->findOrFail($this->id);
            $change($order);
        }, 3);
        $this->refresh();
    }
}
