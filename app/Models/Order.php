<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
        'handled_by_id', 'call_attempts', 'last_call_at',
    ];

    protected $casts = [
        'stock_deducted' => 'boolean',
        'confirmed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_call_at' => 'datetime',
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

    /** Membre de l'équipe qui a confirmé ou annulé la commande. */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_id');
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
        $problems = [];
        foreach ($this->items as $item) {
            if ($item->is_custom || ! $item->variant) {
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
        if ($this->status !== 'en_attente') {
            throw new RuntimeException('Seule une commande en attente peut être confirmée.');
        }
        if ($problems = $this->stockProblems()) {
            throw new RuntimeException('Stock insuffisant : '.implode(' ; ', $problems));
        }

        DB::transaction(function () {
            foreach ($this->items as $item) {
                if (! $item->is_custom && $item->variant) {
                    $item->variant->adjustStock(-$item->quantity, 'commande', $this->number, $this->id);
                }
            }
            $this->update(['status' => 'confirmee', 'confirmed_at' => now(), 'stock_deducted' => true, 'handled_by_id' => auth()->id() ?? $this->handled_by_id]);
        });
    }

    /** Appel sans réponse : la commande reste « en attente », la tentative est notée. */
    public function noAnswer(?string $note = null): void
    {
        if ($this->status !== 'en_attente') {
            throw new RuntimeException('Seule une commande en attente peut être rappelée.');
        }
        $line = now()->format('d/m H:i').' · '.(auth()->user()?->name ?? 'Équipe').' : injoignable'.($note ? ' — '.$note : '');
        $this->update([
            'call_attempts' => $this->call_attempts + 1,
            'last_call_at' => now(),
            'admin_note' => trim(($this->admin_note ? $this->admin_note."\n" : '').$line),
        ]);
    }

    public function ship(): void
    {
        if ($this->status !== 'confirmee') {
            throw new RuntimeException('La commande doit être confirmée avant d’être expédiée.');
        }
        $this->update(['status' => 'expediee', 'shipped_at' => now()]);
    }

    public function deliver(): void
    {
        if ($this->status !== 'expediee') {
            throw new RuntimeException('La commande doit être expédiée avant d’être livrée.');
        }
        $this->update(['status' => 'livree', 'delivered_at' => now()]);
    }

    public function cancel(): void
    {
        if (in_array($this->status, ['livree', 'annulee'], true)) {
            throw new RuntimeException('Cette commande ne peut plus être annulée.');
        }
        if (auth()->user()?->isConfirmateur() && $this->status !== 'en_attente') {
            throw new RuntimeException('Seul le gérant peut annuler une commande déjà confirmée.');
        }

        DB::transaction(function () {
            if ($this->stock_deducted) {
                foreach ($this->items as $item) {
                    if (! $item->is_custom && $item->variant) {
                        $item->variant->adjustStock($item->quantity, 'annulation', $this->number, $this->id);
                    }
                }
            }
            $this->update(['status' => 'annulee', 'cancelled_at' => now(), 'stock_deducted' => false, 'handled_by_id' => auth()->id() ?? $this->handled_by_id]);
        });
    }
}
