<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    public function getTitle(): string|Htmlable
    {
        return 'Administration';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Administration';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Espace réservé à l’équipe Maison Filali';
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()->label('E-mail')->placeholder('admin@maisonfilali.ma');
    }
}
