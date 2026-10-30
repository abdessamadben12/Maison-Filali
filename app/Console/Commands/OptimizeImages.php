<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OptimizeImages extends Command
{
    protected $signature = 'shop:optimize-images';

    protected $description = 'Génère des images WebP adaptées aux écrans sans modifier les originaux';

    public function handle(): int
    {
        if (! function_exists('imagewebp')) {
            $this->error('Activez GD avec WebP dans PHP pour optimiser les images.');
            return self::FAILURE;
        }

        $directory = public_path('images/optimized');
        File::ensureDirectoryExists($directory);
        $manifestFile = $directory.'/manifest.json';
        $old = is_file($manifestFile) ? (json_decode(file_get_contents($manifestFile), true) ?: []) : [];
        $manifest = [];
        $originalBytes = $optimizedBytes = 0;

        foreach (['images', 'uploads'] as $folder) {
            if (! is_dir(public_path($folder))) {
                continue;
            }
            foreach (File::allFiles(public_path($folder)) as $file) {
                $path = $folder.'/'.str_replace('\\', '/', $file->getRelativePathname());
                if (str_starts_with($path, 'images/optimized/') || ! in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }
                $source = $file->getPathname();
                $info = @getimagesize($source);
                // Bound decoded memory, and leave unsupported originals usable.
                if (! $info || $info[0] * $info[1] > 8000000) {
                    $this->warn('Image ignorée (dimensions trop grandes ou fichier invalide) : '.$path);
                    continue;
                }
                if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($source);
                    if (($exif['Orientation'] ?? 1) !== 1) {
                        $this->warn('Image conservée avec son orientation EXIF : '.$path);
                        continue;
                    }
                }
                $fingerprint = hash_file('sha256', $source);
                $entry = $old[$path] ?? null;
                if (! $entry || ($entry['hash'] ?? '') !== $fingerprint
                    || collect($entry['variants'])->contains(fn ($variant) => ! is_file(public_path($variant)))) {
                    $image = match ($info[2]) {
                        IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
                        IMAGETYPE_PNG => @imagecreatefrompng($source),
                        IMAGETYPE_WEBP => @imagecreatefromwebp($source),
                        default => false,
                    };
                    if (! $image) {
                        $this->warn('Image illisible : '.$path);
                        continue;
                    }
                    $variants = [];
                    $widths = array_unique(array_map(fn ($width) => min($width, $info[0]), [128, 480, 960, 1600]));
                    try {
                        foreach ($widths as $width) {
                            $height = max(1, (int) round($info[1] * $width / $info[0]));
                            $resized = imagecreatetruecolor($width, $height);
                            imagealphablending($resized, false);
                            imagesavealpha($resized, true);
                            imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
                            $output = 'images/optimized/'.substr(hash('sha256', $path.$fingerprint), 0, 24).'-'.$width.'.webp';
                            $temporary = tempnam($directory, 'webp-');
                            try {
                                if (! imagewebp($resized, $temporary, 82) || filesize($temporary) === 0) {
                                    throw new \RuntimeException('Échec de conversion : '.$path);
                                }
                                File::move($temporary, public_path($output));
                            } finally {
                                imagedestroy($resized);
                                if (is_file($temporary)) {
                                    File::delete($temporary);
                                }
                            }
                            $variants[$width] = $output;
                        }
                    } finally {
                        imagedestroy($image);
                    }
                    $entry = ['width' => $info[0], 'height' => $info[1], 'hash' => $fingerprint, 'variants' => $variants];
                }
                $entry['mtime'] = filemtime($source);
                $entry['bytes'] = filesize($source);
                $manifest[$path] = $entry;
                $originalBytes += $entry['bytes'];
                $optimizedBytes += filesize(public_path(end($entry['variants'])));
            }
        }

        File::replace($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $this->info(sprintf('%d images : %.2f Mo originaux → %.2f Mo WebP (plus grandes tailles).', count($manifest), $originalBytes / 1048576, $optimizedBytes / 1048576));

        return self::SUCCESS;
    }
}
