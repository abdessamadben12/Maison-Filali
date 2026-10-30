@props(['src', 'sizes' => '100vw'])
<img {{ $attributes->merge(array_merge(app(\App\Support\ResponsiveImages::class)->attributes($src), ['sizes' => $sizes, 'decoding' => 'async'])) }}>
