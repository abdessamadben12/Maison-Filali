@extends('layouts.site')

@section('title', 'Page introuvable — Maison Filali')
@section('robots', 'noindex, follow')

@section('content')
<section class="page">
    <div class="in" style="text-align:center">
        <span class="eyebrow">Erreur 404</span>
        <h1>Cette page n'existe plus</h1>
        <p>Le lien est peut-être ancien ou le modèle n'est plus en vente. Découvrez nos créations du moment :</p>
        <p style="display:flex;gap:16px;justify-content:center;flex-wrap:wrap;margin-top:24px">
            <a class="btn btn-green" href="{{ route('catalogue') }}">Voir la boutique</a>
            <a class="btn btn-outline" href="{{ route('home') }}">Retour à l'accueil</a>
        </p>
    </div>
</section>
@endsection
