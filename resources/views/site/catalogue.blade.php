@extends('layouts.site')

@php
    $isFiltered = request()->hasAny(['q', 'categories', 'couleurs', 'tailles', 'prix_min', 'prix_max', 'stock', 'sur_mesure', 'tri']);
    $pageNo = $products->currentPage();
@endphp
@section('title', ($category ? $category->name.' faits main' : 'Boutique — caftans, takchitas, djellabas & babouches').($pageNo > 1 ? ' (page '.$pageNo.')' : '').' | Maison Filali')
@section('description', \App\Support\Seo::description($category
    ? ($category->description ?: $category->name.' faits main par Maison Filali.').' Livraison partout au Maroc, paiement à la livraison.'
    : 'Toute la boutique Maison Filali : caftans, takchitas, djellabas et babouches faits main. Livraison partout au Maroc, paiement à la livraison.'))
{{-- Pages filtrées / recherche : non indexées (évite le contenu dupliqué), liens suivis --}}
@section('canonical', $isFiltered ? url()->current() : url()->current().($pageNo > 1 ? '?page='.$pageNo : ''))
@if ($isFiltered)
    @section('robots', 'noindex, follow')
@endif
@push('head')
    {{ \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumb(array_filter([
        ['Accueil', route('home')],
        ['Boutique', route('catalogue')],
        $category ? [$category->name, route('category', $category)] : null,
    ]))) }}
@endpush

@section('content')
<section class="cat-hero">
    <img src="{{ asset('images/catalogue-banner.png') }}" alt="" width="1440" height="300">
    <div class="a2"><img src="{{ asset('images/cat-takchitas.webp') }}" alt="" width="550" height="760"></div>
    <div class="a1"><img src="{{ asset('images/hero-800.webp') }}" alt="" width="800" height="457"></div>
    <div class="txt">
        <div class="crumbs">
            <a href="{{ route('home') }}">Accueil</a><x-ico name="i-crumb" :s="14"/>
            @if ($category)
                <a href="{{ route('catalogue') }}">Boutique</a><x-ico name="i-crumb" :s="14"/><strong>{{ $category->name }}</strong>
            @else
                <strong>Boutique</strong>
            @endif
        </div>
        @if ($category)
            <h1>{{ $category->name }}</h1>
            <p class="d-only">{{ $category->description }}</p>
        @else
            <h1>Notre <em class="italic-green">boutique</em></h1>
            <p class="d-only">Caftans, takchitas, djellabas et babouches — toutes nos créations faites main, livrées partout au Maroc.</p>
        @endif
        <p class="m-only cat-count">{{ $products->total() }} création{{ $products->total() > 1 ? 's' : '' }} faite{{ $products->total() > 1 ? 's' : '' }} main</p>
    </div>
</section>

<div class="chips">
    <div class="wrap">
        <a @class(['chip', 'on' => ! $category]) href="{{ route('catalogue') }}">
            <x-ico name="i-all" :s="34"/>Tout <small>{{ $totalCount }}</small>
        </a>
        @foreach ($categories as $cat)
            <a @class(['chip', 'on' => $category?->is($cat)]) href="{{ route('category', $cat) }}">
                <img src="{{ $cat->thumb_url }}" alt="" width="34" height="34">{{ $cat->name }} <small>{{ $cat->products_count }}</small>
            </a>
        @endforeach
    </div>
</div>

{{-- Mobile : boutons Filtres + Trier --}}
<div class="wrap m-bar m-only">
    <button type="button" class="m-fbtn" data-open-filters><x-ico name="i-filter" :s="16"/>Filtres @if (count($activePills))<b>{{ count($activePills) }}</b>@endif</button>
    <label class="m-sort">
        <span>Trier</span>
        <select aria-label="Trier" onchange="document.querySelectorAll('select[name=tri]').forEach(s => s.value = this.value); var h = document.querySelector('#filters input[name=tri]'); if (h) h.value = this.value; document.getElementById('filters').submit()">
            <option value="nouveautes" @selected($filters['tri'] === 'nouveautes')>Nouveautés</option>
            <option value="prix_asc" @selected($filters['tri'] === 'prix_asc')>Prix croissant</option>
            <option value="prix_desc" @selected($filters['tri'] === 'prix_desc')>Prix décroissant</option>
            <option value="nom" @selected($filters['tri'] === 'nom')>Nom A → Z</option>
        </select>
    </label>
</div>

<div class="wrap catalog">
    <form class="filters" method="get" action="{{ $category ? route('category', $category) : route('catalogue') }}" id="filters" data-count-url="{{ $category ? route('category', $category) : route('catalogue') }}">
        {{-- En-tête du panneau mobile --}}
        <div class="m-fhead m-only">
            <h2>Filtres</h2>
            <button type="button" data-close-filters aria-label="Fermer"><x-ico name="i-close" :s="18"/></button>
        </div>
        <div class="m-factive m-only">
            <span>{{ count($activePills) }} filtre{{ count($activePills) > 1 ? 's' : '' }} actif{{ count($activePills) > 1 ? 's' : '' }}</span>
            <a href="{{ $category ? route('category', $category) : route('catalogue') }}">Tout effacer</a>
        </div>
        <div class="fh d-only"><h2>Filtrer</h2><a href="{{ $category ? route('category', $category) : route('catalogue') }}">Tout effacer</a></div>
        @if (request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
        @if (! $category)
            <div class="fgroup">
                <h3>Catégorie</h3>
                @foreach ($categories as $cat)
                    <label class="check"><input type="checkbox" name="categories[]" value="{{ $cat->slug }}" @checked(in_array($cat->slug, $filters['categories']))><span>{{ $cat->name }}</span><small>{{ $cat->products_count }}</small></label>
                @endforeach
            </div>
        @endif
        <div class="fgroup">
            <h3>Prix</h3>
            {{-- Réglette à deux curseurs, reliée aux deux cases --}}
            <div class="range2" data-min="{{ $priceRange[0] }}" data-max="{{ max($priceRange[1], $priceRange[0] + 50) }}">
                <div class="track"><i></i></div>
                <input type="range" aria-label="Prix minimum" min="{{ $priceRange[0] }}" max="{{ max($priceRange[1], $priceRange[0] + 50) }}" step="10" value="{{ $filters['prix_min'] ?? $priceRange[0] }}" data-r="min">
                <input type="range" aria-label="Prix maximum" min="{{ $priceRange[0] }}" max="{{ max($priceRange[1], $priceRange[0] + 50) }}" step="10" value="{{ $filters['prix_max'] ?? $priceRange[1] }}" data-r="max">
            </div>
            <div class="prices">
                <input type="number" name="prix_min" min="0" step="50" placeholder="{{ $priceRange[0] }} DH" value="{{ $filters['prix_min'] }}">
                <span>—</span>
                <input type="number" name="prix_max" min="0" step="50" placeholder="{{ $priceRange[1] }} DH" value="{{ $filters['prix_max'] }}">
            </div>
        </div>
        @if ($colors->count())
            <div class="fgroup">
                <h3>Couleur</h3>
                <div class="fcolors">
                    @foreach ($colors as $c)
                        <label><input type="checkbox" name="couleurs[]" value="{{ $c->id }}" @checked(in_array($c->id, $filters['couleurs']))><i style="background:{{ $c->hex }}"></i>{{ $c->name }}</label>
                    @endforeach
                </div>
            </div>
        @endif
        @if ($sizes->count())
            <div class="fgroup">
                <h3>Taille</h3>
                <div class="fsizes">
                    @foreach ($sizes->where('type', 'vetement') as $s)
                        <label><input type="checkbox" name="tailles[]" value="{{ $s->id }}" @checked(in_array($s->id, $filters['tailles']))><span class="size-btn">{{ $s->name }}</span></label>
                    @endforeach
                </div>
                @if ($sizes->where('type', 'pointure')->count())
                    <div class="fsizes">
                        @foreach ($sizes->where('type', 'pointure') as $s)
                            <label><input type="checkbox" name="tailles[]" value="{{ $s->id }}" @checked(in_array($s->id, $filters['tailles']))><span class="size-btn">{{ $s->name }}</span></label>
                        @endforeach
                    </div>
                    <p class="hint">Pointures babouches : {{ $sizes->where('type', 'pointure')->first()->name }} à {{ $sizes->where('type', 'pointure')->last()->name }}</p>
                @endif
            </div>
        @endif
        <div class="fgroup">
            <h3>Disponibilité</h3>
            <label class="check"><input type="checkbox" name="stock" value="1" @checked($filters['stock'])><span>En stock uniquement</span></label>
            <label class="check"><input type="checkbox" name="sur_mesure" value="1" @checked($filters['sur_mesure'])><span>Sur mesure possible</span></label>
            <button class="btn btn-green btn-sm apply d-only" type="submit">Appliquer</button>
        </div>
        <input type="hidden" name="tri" value="{{ $filters['tri'] }}">
        {{-- Pied du panneau mobile --}}
        <div class="m-ffoot m-only">
            <a class="btn btn-outline" href="{{ $category ? route('category', $category) : route('catalogue') }}">Effacer</a>
            <button class="btn btn-green" type="submit" data-count-label>Voir {{ $products->total() }} produit{{ $products->total() > 1 ? 's' : '' }}</button>
        </div>
    </form>

    <div class="results">
        <div class="bar">
            <div class="pills">
                <span class="count">{{ $products->total() }} <span>produit{{ $products->total() > 1 ? 's' : '' }}</span></span>
                @foreach ($activePills as $pill)
                    <a class="pill" href="{{ $pill['remove'] }}">{{ $pill['label'] }} <x-ico name="i-x" :s="12"/></a>
                @endforeach
            </div>
            <label class="sort">Trier :
                <select name="tri" form="filters" onchange="this.form.submit()">
                    <option value="nouveautes" @selected($filters['tri'] === 'nouveautes')>Nouveautés</option>
                    <option value="prix_asc" @selected($filters['tri'] === 'prix_asc')>Prix croissant</option>
                    <option value="prix_desc" @selected($filters['tri'] === 'prix_desc')>Prix décroissant</option>
                    <option value="nom" @selected($filters['tri'] === 'nom')>Nom A → Z</option>
                </select>
            </label>
        </div>

        @if ($products->count())
            <div class="grid">
                @foreach ($products as $product)
                    @include('partials.product-card')
                @endforeach
                @if ($products->onLastPage())
                    <div class="promo d-only">
                        <img src="{{ asset('images/sur-mesure.png') }}" alt="">
                        <div class="in">
                            <x-ico name="i-needle" :s="56"/>
                            <span class="k">Atelier</span>
                            <h3>Une pièce<br><em>sur mesure</em> ?</h3>
                            <p>Choisissez le tissu, la couleur et la broderie : nos maâlems créent votre caftan à vos mesures.</p>
                            <a class="btn btn-gold btn-sm" href="https://wa.me/{{ config('shop.whatsapp') }}?text={{ rawurlencode('Bonjour, je souhaite prendre rendez-vous pour une pièce sur mesure.') }}" target="_blank" rel="noopener">Prendre rendez-vous</a>
                        </div>
                    </div>
                @endif
            </div>
            <div class="seen" id="seen">
                <span>Vous avez vu <b data-seen>{{ $products->lastItem() }}</b> produits sur {{ $products->total() }}</span>
                <i><b data-seen-bar style="width:{{ round($products->lastItem() / max(1, $products->total()) * 100) }}%"></b></i>
                @if ($products->hasMorePages())
                    {{-- « Voir plus » ajoute les produits suivants sans changer de page --}}
                    <a class="btn btn-outline btn-sm" href="{{ $products->nextPageUrl() }}" data-more>Voir plus</a>
                @endif
            </div>
            @if ($products->lastPage() > 1)
                {{-- Pagination numérotée (utile pour Google et pour revenir à une page) --}}
                <nav class="pager" aria-label="Pages">
                    @if ($products->onFirstPage())
                        <span class="off" aria-hidden="true">‹</span>
                    @else
                        <a href="{{ $products->previousPageUrl() }}" rel="prev" aria-label="Page précédente">‹</a>
                    @endif
                    @foreach ($products->getUrlRange(1, $products->lastPage()) as $page => $url)
                        @if ($page === $products->currentPage())
                            <span class="on" aria-current="page">{{ $page }}</span>
                        @elseif ($page === 1 || $page === $products->lastPage() || abs($page - $products->currentPage()) <= 1)
                            <a href="{{ $url }}">{{ $page }}</a>
                        @elseif (abs($page - $products->currentPage()) === 2)
                            <span class="gap">…</span>
                        @endif
                    @endforeach
                    @if ($products->hasMorePages())
                        <a href="{{ $products->nextPageUrl() }}" rel="next" aria-label="Page suivante">›</a>
                    @else
                        <span class="off" aria-hidden="true">›</span>
                    @endif
                </nav>
            @endif
        @else
            <div class="empty">
                <h3>Aucun produit ne correspond</h3>
                <p>Essayez de retirer un filtre ou <a class="link-gold" href="{{ route('catalogue') }}">voir toute la boutique</a>.</p>
            </div>
        @endif
    </div>
</div>
@endsection
