<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /** Rôles de l'équipe (administration). */
    public const ROLES = [
        'gerant' => 'Gérant — accès complet',
        'confirmateur' => 'Confirmateur — confirmation des commandes uniquement',
    ];

    protected $fillable = ['name', 'email', 'phone', 'city', 'address', 'password', 'is_admin', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /** Accès complet à l'administration. */
    public function isGerant(): bool
    {
        return $this->is_admin && $this->role !== 'confirmateur';
    }

    /** Société de confirmation : voit et traite uniquement les commandes à confirmer. */
    public function isConfirmateur(): bool
    {
        return $this->is_admin && $this->role === 'confirmateur';
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->isConfirmateur() ? 'Confirmateur' : 'Gérant';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Commandes confirmées / annulées par ce membre de l'équipe. */
    public function handledOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'handled_by_id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function getFirstNameAttribute(): string
    {
        return explode(' ', trim($this->name))[0];
    }
}
