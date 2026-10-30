<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Paramètres du site enregistrés depuis l'admin (page « Paramètres »).
 * Ils remplacent les valeurs par défaut de config/shop.php.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    /** Clés de config/shop.php modifiables depuis l'admin. */
    public const KEYS = [
        'phone', 'contact_email', 'whatsapp', 'hours', 'hours_short', 'address', 'address_short',
        'instagram', 'instagram_url', 'facebook_url', 'tiktok_url', 'maps_embed', 'maps_url',
    ];

    /** Charge les paramètres enregistrés dans config('shop.*'). */
    public static function applyToConfig(): void
    {
        try {
            // Mis en cache : aucune requête SQL par page (vidé à chaque enregistrement)
            $values = Cache::store('file')->rememberForever('shop.settings', fn () => static::query()->whereIn('key', self::KEYS)->pluck('value', 'key')->all());
        } catch (Throwable) {
            return; // table pas encore créée (php artisan migrate)
        }
        foreach ($values as $key => $value) {
            config(['shop.'.$key => $value === '' ? null : $value]);
        }
    }

    /** @param array<string, ?string> $values */
    public static function saveMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::store('file')->forget('shop.settings');
    }

    /** Extrait l'adresse de la carte depuis le code <iframe> copié dans Google Maps (ou l'adresse seule). */
    public static function mapsEmbedSrc(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }
        if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', $input, $m)) {
            $input = $m[1];
        }
        $input = html_entity_decode($input);

        return preg_match('#^https://(www\.)?google\.[a-z.]+/maps/embed\?#i', $input) ? $input : null;
    }

    /** Lien « Itinéraire » vers la fiche Google Maps exacte, déduit de la carte intégrée. */
    public static function mapsLinkFromEmbed(?string $src): ?string
    {
        if (! $src) {
            return null;
        }
        if (preg_match('/!1s0x[0-9a-f]+(?::|%3A)0x([0-9a-f]+)/i', $src, $m)) {
            return 'https://maps.google.com/?cid='.self::hexToDec($m[1]);
        }
        if (preg_match('/!2s([^!]+)/', $src, $m)) {
            return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode(urldecode($m[1]));
        }

        return null;
    }

    private static function hexToDec(string $hex): string
    {
        $dec = '0';
        foreach (str_split(strtolower($hex)) as $ch) {
            $carry = hexdec($ch);
            $out = '';
            for ($i = strlen($dec) - 1; $i >= 0; $i--) {
                $v = ((int) $dec[$i]) * 16 + $carry;
                $out = ($v % 10).$out;
                $carry = intdiv($v, 10);
            }
            while ($carry > 0) {
                $out = ($carry % 10).$out;
                $carry = intdiv($carry, 10);
            }
            $dec = ltrim($out, '0') ?: '0';
        }

        return $dec;
    }
}
