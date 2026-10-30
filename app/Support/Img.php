<?php

namespace App\Support;

use Throwable;

/**
 * Conversion automatique en WebP des photos envoyées depuis l'admin (public/uploads),
 * en qualité maximale :
 *  - PNG / GIF  → WebP sans perte (pixels identiques, transparence conservée) ;
 *  - JPEG       → WebP qualité 92 (aucune différence visible) ;
 *  - seules les photos géantes (> 2400 px) sont réduites, ce qui reste plus net que tout écran.
 * L'original est toujours conservé (Google, partage). Si la WebP n'est pas plus légère,
 * le site garde simplement l'original : la qualité n'est jamais dégradée.
 */
class Img
{
    /** Plus grand côté de la grande photo (page produit, zoom). */
    public const MAX = 2400;

    /** Miniature pour les cartes produits / catégories (≈ 2,5× la largeur affichée). */
    public const THUMB = 800;

    /** Qualité WebP des photos JPEG (92 = aucune perte visible). */
    public const QUALITY = 92;

    /** Qualité des miniatures. */
    public const THUMB_QUALITY = 88;

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
        // Suffixe « -hd » / « -thumb » : versions haute qualité (les anciennes .webp ne sont plus utilisées)
        return preg_replace('/\.[A-Za-z0-9]+$/', '', $path).($size === 'sm' ? '-thumb' : '-hd').'.webp';
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

            $lossless = in_array($info[2] ?? null, [IMAGETYPE_PNG, IMAGETYPE_GIF], true) && defined('IMG_WEBP_LOSSLESS');

            // Miniature (cartes) : largeur max 800 px, qualité 88
            $thumb = self::resize($img, self::THUMB);
            imagewebp($thumb, public_path('uploads/'.self::webpPath($path, 'sm')), self::THUMB_QUALITY);
            if ($thumb !== $img) {
                imagedestroy($thumb);
            }

            // Grande photo : PNG/GIF sans perte, JPEG qualité 92
            $large = self::resize($img, self::MAX, true);
            $ok = imagewebp($large, $dst, $lossless ? IMG_WEBP_LOSSLESS : self::QUALITY);
            // Photo enregistrée en PNG très lourde en « sans perte » : qualité 95 (invisible à l'œil)
            if ($ok && $lossless && filesize($dst) > 1.5 * 1024 * 1024) {
                $ok = imagewebp($large, $dst, 95);
            }
            if ($large !== $img) {
                imagedestroy($large);
            }
            imagedestroy($img);

            // Garder la WebP seulement si elle est plus légère (sinon l'original est servi)
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
