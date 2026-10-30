<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        // Les @section(…) sont déjà échappées : on décode avant de réafficher (évite « &amp;#039; »)
        $seo = fn (string $name, string $default) => html_entity_decode(trim($__env->yieldContent($name)), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?: $default;
        $seoTitle = $seo('title', 'Maison Filali — Caftans, takchitas & babouches faits main au Maroc');
        $seoDesc = $seo('description', 'Maison Filali : vêtements traditionnels marocains faits main — caftans, takchitas, djellabas et babouches. Commandez en ligne, payez à la livraison partout au Maroc.');
        $seoCanonical = $seo('canonical', url()->current());
        $seoImage = $seo('og_image', asset('images/og-image.jpg'));
    @endphp
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDesc }}">
    <meta name="robots" content="@yield('robots', 'index, follow, max-image-preview:large')">
    <link rel="canonical" href="{{ $seoCanonical }}">

    {{-- Partage sur les réseaux sociaux (WhatsApp, Facebook, Instagram…) --}}
    <meta property="og:site_name" content="Maison Filali">
    <meta property="og:locale" content="fr_MA">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDesc }}">
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta name="twitter:card" content="summary_large_image">
    @stack('meta')

    <meta name="theme-color" content="#0f4a3e">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
    <link rel="icon" href="{{ asset('images/favicon-48.png') }}" type="image/png" sizes="48x48">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="sitemap" type="application/xml" href="{{ url('sitemap.xml') }}">

    {{-- Polices principales préchargées (hébergées sur le site) --}}
    <link rel="preload" href="{{ asset('fonts/jost-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/cormorant-garamond-latin-500-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @stack('preload')
    <script>if (!matchMedia('(prefers-reduced-motion: reduce)').matches) document.documentElement.classList.add('anim');</script>
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ config('shop.assets_version') }}">
    <link rel="stylesheet" href="{{ asset('css/anim.css') }}?v={{ config('shop.assets_version') }}">
    @stack('head')
</head>
<body>
<div class="topbar">
    <span>Livraison partout au Maroc</span>
    <x-ico name="i-dot" :s="12"/>
    <span>Paiement à la livraison</span>
    <x-ico name="i-dot" :s="12"/>
    <span>Vêtements traditionnels faits main</span>
</div>

<header class="header">
    <div class="inner">
        <button class="burger" type="button" data-toggle="#nav" aria-label="Menu"><i></i></button>
        <nav class="nav" id="nav">
            <a href="{{ route('home') }}" @class(['active' => request()->routeIs('home')])>Accueil</a>
            <a href="{{ route('catalogue') }}" @class(['active' => request()->routeIs('catalogue') && ! request()->route('category')])>Boutique</a>
            @foreach ($navCategories as $navCat)
                <a href="{{ route('category', $navCat) }}" @class(['active' => ($activeCategory ?? null) === $navCat->slug])>{{ $navCat->name }}</a>
            @endforeach
            <a href="{{ route('page', 'notre-histoire') }}" @class(['active' => request()->is('maison/notre-histoire')])>Notre histoire</a>
            <a class="nav-m" href="{{ route('contact') }}" @class(['active' => request()->routeIs('contact')])>Contact</a>
        </nav>
        <a class="brand" href="{{ route('home') }}">
            <img src="{{ asset('images/logo-160.webp') }}" alt="Maison Filali — accueil" width="52" height="52">
            <span>MAISON FILALI</span>
        </a>
        <div class="header-actions">
            <a class="account-link contact-link" href="{{ route('contact') }}"><span>Contact</span></a>
            <button type="button" data-toggle="#search" aria-label="Rechercher"><x-ico name="i-search" :s="21"/></button>
            <a class="account-link" href="{{ auth()->check() && ! auth()->user()->is_admin ? route('account.orders') : route('login') }}" aria-label="Mon compte">
                <x-ico name="i-user" :s="21"/><span>Mon compte</span>
            </a>
            <a class="phone-pill" href="https://wa.me/{{ config('shop.whatsapp') }}" target="_blank" rel="noopener">
                <x-ico name="i-whatsapp" :s="16"/>{{ config('shop.phone') }}
            </a>
        </div>
    </div>
</header>
<div class="search-bar" id="search">
    <div class="wrap">
        <form action="{{ route('catalogue') }}" method="get">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher un caftan, des babouches…">
            <button class="btn btn-green btn-sm" type="submit">Rechercher</button>
        </form>
    </div>
</div>

@if (session('success'))
    <div class="flash flash-ok">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="flash flash-err">{{ session('error') }}</div>
@endif

@yield('content')

<footer class="footer">
    <div class="cols">
        <div class="about">
            <div class="logo">
                <img src="{{ asset('images/logo-160.webp') }}" alt="" width="64" height="64" loading="lazy">
                <div><strong>MAISON FILALI</strong><small>Vêtements traditionnels</small></div>
            </div>
            <p>Caftans, takchitas, djellabas et babouches faits main, livrés partout au Maroc. Commandez en ligne, payez à la livraison.</p>
            <div class="socials">
                @if (config('shop.instagram_url'))<a href="{{ config('shop.instagram_url') }}" target="_blank" rel="noopener" aria-label="Instagram"><x-ico name="i-s-insta" :s="18"/></a>@endif
                @if (config('shop.facebook_url'))<a href="{{ config('shop.facebook_url') }}" target="_blank" rel="noopener" aria-label="Facebook"><x-ico name="i-s-fb" :s="18"/></a>@endif
                @if (config('shop.tiktok_url'))<a href="{{ config('shop.tiktok_url') }}" target="_blank" rel="noopener" aria-label="TikTok"><x-ico name="i-s-tiktok" :s="16"/></a>@endif
                <a href="https://wa.me/{{ config('shop.whatsapp') }}" target="_blank" rel="noopener" aria-label="WhatsApp"><x-ico name="i-s-wa" :s="18"/></a>
            </div>
        </div>
        <div>
            <ul>
                <li class="eyebrow">Boutique</li>
                @foreach ($navCategories as $navCat)
                    <li><a href="{{ route('category', $navCat) }}">{{ $navCat->name }}</a></li>
                @endforeach
                <li><a href="{{ route('catalogue', ['tri' => 'nouveautes']) }}">Nouveautés</a></li>
            </ul>
        </div>
        <div>
            <ul>
                <li class="eyebrow">Aide</li>
                <li><a href="{{ route('page', 'comment-commander') }}">Comment commander</a></li>
                <li><a href="{{ route('track') }}">Suivre ma commande</a></li>
                <li><a href="{{ route('page', 'guide-des-tailles') }}">Guide des tailles</a></li>
                <li><a href="{{ route('page', 'livraison-retours') }}">Livraison &amp; retours</a></li>
                <li><a href="{{ route('contact') }}">Nous contacter</a></li>
            </ul>
        </div>
        <div class="contact">
            <ul>
                <li class="eyebrow">Contact</li>
                <li><x-ico name="i-f-pin" :s="18"/><span>{!! nl2br(e(config('shop.address_short'))) !!}</span></li>
                <li><x-ico name="i-f-phone" :s="18"/><a href="tel:{{ str_replace(' ', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a></li>
                @if (config('shop.instagram_url'))<li><x-ico name="i-f-insta" :s="18"/><a href="{{ config('shop.instagram_url') }}" target="_blank" rel="noopener">{{ config('shop.instagram') }}</a></li>@endif
                <li><x-ico name="i-f-clock" :s="18"/><span>{{ config('shop.hours_short') }}</span></li>
            </ul>
        </div>
    </div>
    <div class="bottom">
        <div>
            <span>© {{ date('Y') }} Maison Filali — Tous droits réservés</span>
            <span><a href="{{ route('page', 'mentions-legales') }}">Mentions légales</a> · <a href="{{ route('page', 'confidentialite') }}">Confidentialité</a> · <a href="{{ route('page', 'cgv') }}">Conditions générales</a></span>
        </div>
    </div>
</footer>

@stack('modals')
@stack('scripts')
<script src="{{ asset('js/site.js') }}?v={{ config('shop.assets_version') }}" defer></script>
<script src="{{ asset('js/anim.js') }}?v={{ config('shop.assets_version') }}" defer></script>
</body>
</html>
