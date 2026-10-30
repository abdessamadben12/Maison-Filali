<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    public const SUBJECTS = [
        'commande' => 'Une commande',
        'sur_mesure' => 'Pièce sur mesure',
        'tailles' => 'Tailles / conseils',
        'echange' => 'Échange / retour',
        'autre' => 'Autre question',
    ];

    protected $fillable = ['name', 'phone', 'email', 'subject', 'message', 'is_read'];

    protected $casts = ['is_read' => 'boolean'];

    public function getSubjectLabelAttribute(): string
    {
        return self::SUBJECTS[$this->subject] ?? $this->subject;
    }
}
