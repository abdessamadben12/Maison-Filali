{{-- Plan d'accès : carte Google Maps (Admin → Paramètres) ou image de secours --}}
@php($embed = config('shop.maps_embed'))
@if ($embed)
    <div class="map map-embed" @isset($height) style="height:{{ $height }}px;flex:none" @endisset>
        <iframe src="{{ $embed }}" title="Plan d'accès — Maison Filali" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
@else
    <a class="map" href="{{ config('shop.maps_url') }}" target="_blank" rel="noopener" aria-label="Ouvrir dans Google Maps" @isset($height) style="height:{{ $height }}px;flex:none" @endisset>
        <img src="{{ asset('images/map.png') }}" alt="" loading="lazy" @isset($height) style="height:{{ $height - 20 }}px" @endisset>
        <span class="pin" @isset($height) style="top:80px" @endisset><span><x-ico name="i-mappin" :s="30"/></span><b>Maison Filali</b></span>
    </a>
@endif
