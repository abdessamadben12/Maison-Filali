{{-- Plan d'accès : carte Google Maps (Admin → Paramètres) ou image de secours --}}
@php($embed = config('shop.maps_embed'))
@if ($embed)
    <div class="map map-embed" @isset($height) style="height:{{ $height }}px;flex:none" @endisset>
        {{-- La carte (≈ 500 Ko de Google) se charge seulement quand on arrive dessus --}}
        <img src="{{ asset('images/map.png') }}" alt="" loading="lazy" decoding="async" width="620" height="380">
        <iframe data-src="{{ $embed }}" title="Plan d'accès — Maison Filali" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        <noscript><iframe src="{{ $embed }}" title="Plan d'accès — Maison Filali" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></noscript>
    </div>
@else
    <a class="map" href="{{ config('shop.maps_url') }}" target="_blank" rel="noopener" aria-label="Ouvrir dans Google Maps" @isset($height) style="height:{{ $height }}px;flex:none" @endisset>
        <img src="{{ asset('images/map.png') }}" alt="" loading="lazy" @isset($height) style="height:{{ $height - 20 }}px" @endisset>
        <span class="pin" @isset($height) style="top:80px" @endisset><span><x-ico name="i-mappin" :s="30"/></span><b>Maison Filali</b></span>
    </a>
@endif
