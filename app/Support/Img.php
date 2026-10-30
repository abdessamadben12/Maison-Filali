<?php

namespace App\Support;

use Throwable;

/**
 * Optimisation des photos envoyées depuis l'admin (dossier public/uploads) :
 * une copie WebP redimensionnée (max 1400 px) est créée à côté de l'original
 * et servie sur le site. Si le serveur ne sait pas créer de WebP, l'original est utilisé.
 */
class Img
{
    public const MAX = 1400;

    /** Miniature pour les cartes produits / catégories (2× la largeur affichée). */
    public const THUMB = 600;

    /** @param 'lg'|'sm' $size */
    public static function url(?string $path, string $size = 'lg'): ?string
    {
        if (! $path) {
            return null;
        }
        foreach ($size === 'sm' ? [self::webpPath($path, 'sm'), self::webpPath($path)] : [self::webpPath($path)] as $candidate) {
            if (is_file(public_path('uploads/'.$candidate))) {
                return asset('uploads/'.$candidate);
            }
        }

        return asset('uploads/'.$path);
    }

    public static function webpPath(string $path, string $size = 'lg'): string
    {
        return preg_replace('/\.[A-Za-z0-9]+$/', '', $path).($size === 'sm' ? '-sm' : '').'.webp';
    }

    /** Redimensionne (sans agrandir) : par la largeur, ou par le plus grand côté si $longSide. */
    private static function resize(\GdImage $img, int $max, bool $longSide = false): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $ref = $longSide ? max($w, $h) : $w;
        if ($ref <= $max) {
            imagealphablending($img, false);
            imagesavealpha($img, true);

            return $img;
        }
        $ratio = $max / $ref;
        $out = imagecreatetruecolor((int) round($w * $ratio), (int) round($h * $ratio));
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $img, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);

        return $out;
    }

    /** Crée (ou met à jour) la version WebP. Retourne true si elle existe après l'appel. */
    public static function optimize(string $path, bool $force = false): bool
    {
        $src = public_path('uploads/'.$path);
        $dst = public_path('uploads/'.self::webpPath($path));
        if (! is_file($src) || ! function_exists('imagewebp') || str_ends_with(strtolower($path), '.webp')) {
            return is_file($dst);
        }
        if (! $force && is_file($dst) && filemtime($dst) >= filemtime($src)) {
            return true;
        }

        try {
            $info = @getimagesize($src);
            $img = match ($info[2] ?? null) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($src),
                IMAGETYPE_PNG => @imagecreatefrompng($src),
                IMAGETYPE_GIF => @imagecreatefromgif($src),
                default => false,
            };
            if (! $img) {
                return false;
            }

            // Photos de téléphone : appliquer l'orientation EXIF
            if (($info[2] ?? null) === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $o = (int) (@exif_read_data($src)['Orientation'] ?? 1);
                $img = match ($o) {
                    3 => imagerotate($img, 180, 0),
                    6 => imagerotate($img, -90, 0),
                    8 => imagerotate($img, 90, 0),
                    default => $img,
                };
            }

            imagepalettetotruecolor($img);
            $w = imagesx($img);
            $h = imagesy($img);

            // Miniature (cartes) : largeur max 600 px
            $thumb = self::resize($img, self::THUMB);
            imagewebp($thumb, public_path('uploads/'.self::webpPath($path, 'sm')), 78);
            if ($thumb !== $img) {
                imagedestroy($thumb);
            }

            // Grande photo (page produit) : max 1400 px
            $large = self::resize($img, self::MAX, true);
            $ok = imagewebp($large, $dst, 80);
            if ($large !== $img) {
                imagedestroy($large);
            }
            imagedestroy($img);

            // Garder la WebP seulement si elle est plus légère
            if ($ok && filesize($dst) >= filesize($src) && max($w, $h) <= self::MAX) {
                @unlink($dst);

                return false;
            }

            return $ok;
        } catch (Throwable) {
            return false;
        }
    }
}
