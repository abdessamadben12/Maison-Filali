@extends('layouts.site')

@section('title', 'Offres & packs — caftans, djellabas et babouches | Maison Filali')
@section('description', 'Les offres Maison Filali : packs de caftans, djellabas et babouches faits main à prix réduit. Livraison au Maroc et à l’international, paiement à la livraison.')
@if ($packs->isEmpty())
    @section('robots', 'noindex, follow')
@endif
@push('head')
    {{ \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumb([['Accueil', route('home')], ['Offres', route('packs.index')]])) }}
@endpush

@section('content')
<section class="offers-hero">
    <div class="wrap">
        <div class="crumbs"><a href="{{ route('home') }}">Accueil</a><x-ico name="i-crumb" :s="14"/><strong>Offres</strong></div>
        <span class="eyebrow">Maison Filali · Offres</span>
        <h1>Nos <em class="italic-green">offres</em> &amp; packs</h1>
        <p>Des ensembles pensés pour vous : plusieurs pièces faites main réunies à un prix doux. Paiement à la livraison au Maroc.</p>
    </div>
</section>

<section class="offers">
    <div class="wrap">
        @if ($packs->isEmpty())
            <div class="empty">
                <h2>Aucune offre en ce moment</h2>
                <p>Revenez bientôt, ou découvrez toute la boutique.</p>
                <a class="btn btn-green" href="{{ route('catalogue') }}">Voir la boutique</a>
            </div>
        @else
            <div class="pack-grid">
                @foreach ($packs as $pack)
                    @include('partials.pack-card', ['pack' => $pack])
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
