<?php

namespace App\Support;

class ResponsiveImages
{
    private ?array $manifest = null;

    public function attributes(string $url): array
    {
        if ($this->manifest === null) {
            $file = public_path('images/optimized/manifest.json');
            $this->manifest = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
        }

        $base = rtrim(asset('/'), '/').'/';
        if (! str_starts_with($url, $base)) {
            return ['src' => $url];
        }

        $path = substr($url, strlen($base));
        $entry = $this->manifest[$path] ?? null;
        if (! $entry || ! is_file(public_path($path))
            || filemtime(public_path($path)) !== $entry['mtime']
            || filesize(public_path($path)) !== $entry['bytes']) {
            return ['src' => $url];
        }

        $sources = [];
        $src = $url;
        foreach ($entry['variants'] as $width => $variant) {
            if (! is_file(public_path($variant))) {
                return ['src' => $url];
            }
            $sources[] = asset($variant).' '.$width.'w';
            $src = asset($variant);
        }

        return [
            'src' => $src,
            'srcset' => implode(', ', $sources),
            'width' => $entry['width'],
            'height' => $entry['height'],
        ];
    }
}
