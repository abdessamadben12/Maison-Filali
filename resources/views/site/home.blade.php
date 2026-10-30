@extends('layouts.site')

@section('content')
<section class="hero">
    <img src="{{ asset('images/hero.jpg') }}" alt="Caftans Maison Filali dans un riad">
    <div class="veil"></div>
    <div class="content">
        <div class="kicker"><x-ico name="i-star" :s="16"/><span class="eyebrow">Maison Filali · Fait main</span></div>
        <h1><span class="ln"><span>L'élégance</span></span><span class="ln"><span>marocaine,</span></span><span class="ln"><span><em>cousue main</em></span></span></h1>
        <p>Caftans, takchitas, djellabas et babouches faits main. Commandez en ligne, payez à la livraison, partout au Maroc.</p>
        <div class="ctas">
            <a class="btn btn-green" href="{{ route('catalogue') }}">Découvrir la collection</a>
            <a class="btn btn-outline" href="{{ route('page', 'notre-histoire') }}">Notre histoire</a>
        </div>
    </div>
    <div class="hero-cats">
        @foreach ($categories->take(4) as $cat)
            <a href="{{ route('category', $cat) }}">
                <img class="thumb" src="{{ $cat->image_url }}" alt="">
                <span class="t"><strong>{{ $cat->name }}</strong><small>Découvrir</small></span>
                <x-ico name="i-chev" :s="18"/>
            </a>
        @endforeach
    </div>
</section>

<section class="reassure">
    <div class="wrap">
        <div class="item"><x-ico name="i-hand" :s="44"/><div><strong>Fait main</strong><small>Par nos artisans à Casablanca</small></div></div>
        <div class="item"><x-ico name="i-confirm" :s="44"/><div><strong>Commande confirmée</strong><small>Par notre équipe sous 24 h</small></div></div>
        <div class="item"><x-ico name="i-truck" :s="44"/><div><strong>Livraison Maroc</strong><small>Paiement à la livraison</small></div></div>
        <div class="item"><x-ico name="i-exchange" :s="44"/><div><strong>Échange 7 jours</strong><small>Après réception</small></div></div>
    </div>
</section>

<section class="univers">
    <div class="wrap">
        <div class="sec-head center">
            <span class="eyebrow">Nos univers</span>
            <h2 class="h-section">{{ $categories->count() === 4 ? 'Quatre savoir-faire, une maison' : 'Nos savoir-faire, une maison' }}</h2>
            <div class="divider"><span></span><x-ico name="i-star-big" :s="22"/><span></span></div>
        </div>
        <div class="hscroll-ctrl center">
            <button type="button" class="hs-btn" data-hs-prev="univ" aria-label="Précédent"><x-ico name="i-chev" :s="14"/></button>
            <button type="button" class="hs-btn" data-hs-next="univ" aria-label="Suivant"><x-ico name="i-chev" :s="14"/></button>
        </div>
        <div class="hscroll univ" id="univ">
            @foreach ($categories as $cat)
                <a class="arch-card" href="{{ route('category', $cat) }}">
                    <div class="arch"><img src="{{ $cat->image_url }}" alt="{{ $cat->name }}" loading="lazy"></div>
                    <h3>{{ $cat->name }}</h3>
                    <span class="link-gold">Découvrir</span>
                </a>
            @endforeach
        </div>
    </div>
</section>

<section class="shop">
    <div class="wrap">
        <div class="top">
            <div class="sec-head">
                <span class="eyebrow">La boutique</span>
                <h2 class="h-section">Nos créations</h2>
                <div class="divider"><span></span><x-ico name="i-star-big" :s="22"/><span></span></div>
            </div>
            <a class="link-gold" href="{{ route('catalogue') }}" style="font-size:14px;letter-spacing:1.68px">Voir toute la boutique →</a>
        </div>
        <div class="shop-bar">
            <div class="tabs-scroll" role="tablist">
                <button type="button" class="ctab on" data-cat="">Tout <small>{{ $products->count() }}</small></button>
                @foreach ($categories as $cat)
                    @php($n = $products->where('category_id', $cat->id)->count())
                    @if ($n)
                        <button type="button" class="ctab" data-cat="{{ $cat->slug }}">{{ $cat->name }} <small>{{ $n }}</small></button>
                    @endif
                @endforeach
            </div>
            <div class="hscroll-ctrl">
                <button type="button" class="hs-btn" data-hs-prev="shopTrack" aria-label="Précédent"><x-ico name="i-chev" :s="14"/></button>
                <button type="button" class="hs-btn" data-hs-next="shopTrack" aria-label="Suivant"><x-ico name="i-chev" :s="14"/></button>
            </div>
        </div>
        <div class="hscroll" id="shopTrack">
            @foreach ($products as $product)
                <div class="slide" data-cat="{{ $product->category->slug }}">@include('partials.product-card')</div>
            @endforeach
        </div>
    </div>
</section>

@if ($featured)
    @php($gallery = $featured->gallery_urls)
    <section class="feature">
        <div class="wrap">
            <div class="pics">
                <img class="pic1" src="{{ $gallery[3] ?? $gallery[0] }}" alt="" loading="lazy">
                <div class="pic2"><img src="{{ $gallery[2] ?? $gallery[0] }}" alt="" loading="lazy"></div>
            </div>
            <div class="txt">
                <span class="eyebrow">Nouveauté</span>
                @php($parts = explode(' ', $featured->name, 2))
                <h2>{{ $parts[0] }} <em class="italic-green">{{ $parts[1] ?? '' }}</em></h2>
                <div class="divider"><span></span><x-ico name="i-star-big" :s="22"/><span></span></div>
                <p>{{ $featured->tagline }}</p>
                <div class="swatches">
                    @foreach ($featured->availableColors() as $i => $c)
                        <a class="sw {{ $i === 0 ? 'on' : '' }}" href="{{ route('product', [$featured, 'couleur' => $c->id]) }}"><i style="background:{{ $c->hex }}"></i>{{ $c->name }}</a>
                    @endforeach
                </div>
                <div class="cta">
                    <a class="btn btn-green" href="{{ route('product', $featured) }}">Commander — {{ money($featured->price) }}</a>
                    <span>{{ $featured->sizes_summary }}</span>
                </div>
            </div>
        </div>
    </section>
@endif

<section class="visit" id="visite">
    <div class="wrap">
        <div class="info">
            <div class="sec-head">
                <span class="eyebrow">Atelier &amp; showroom</span>
                <h2 class="h-section">Venez nous rendre visite</h2>
                <div class="divider"><span></span><x-ico name="i-star-big" :s="22"/><span></span></div>
            </div>
            <div class="line"><x-ico name="i-pin" :s="22"/><span>{!! nl2br(e(config('shop.address'))) !!}</span></div>
            <div class="line"><x-ico name="i-phone" :s="22"/><a href="tel:{{ str_replace(' ', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a></div>
            @if (config('shop.instagram_url'))<div class="line"><x-ico name="i-insta" :s="22"/><a href="{{ config('shop.instagram_url') }}" target="_blank" rel="noopener">{{ config('shop.instagram') }}</a></div>@endif
            <div class="line"><x-ico name="i-clock" :s="22"/><span>{{ config('shop.hours') }}</span></div>
            <div style="padding-top:6px"><a class="btn btn-green" href="{{ config('shop.maps_url') }}" target="_blank" rel="noopener">Itinéraire</a></div>
        </div>
        @include('partials.map')
    </div>
</section>
@endsection
