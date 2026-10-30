<?php

namespace App\Support;

use Illuminate\Support\Number;

/**
 * Formatage des nombres qui fonctionne même si l'extension PHP « intl » n'est pas activée
 * (cas fréquent avec XAMPP sous Windows).
 */
class Num
{
    public static function format(int|float $number): string
    {
        return extension_loaded('intl') ? (string) Number::format($number) : number_format($number, 0, ',', ' ');
    }
}
