@extends('layouts.site')

@section('title', 'Commande '.$order->number.' — Maison Filali')

@section('content')
@php($item = $order->items->first())
<section class="thanks">
    <x-site-image src="{{ asset('images/zellige.png') }}" alt="" />
    <div class="in">
        <div class="badge-arch"><x-ico name="i-check-big" :s="44"/></div>
        <div style="display:flex;flex-direction:column;align-items:center;gap:12px">
            <span class="eyebrow">{{ $fresh ? 'Commande enregistrée' : 'Suivi de commande' }}</span>
            <h1>{{ $fresh ? 'Merci '.$order->first_name.' !' : 'Commande '.$order->number }}</h1>
            <p class="lead">
                @if ($fresh)
                    Votre commande <b>{{ $order->number }}</b> est bien reçue. Notre équipe la vérifie et la confirme sous 24 h : le statut se met à jour ici{{ $order->user_id ? ' et dans votre espace client' : '' }}.
                @else
                    Statut actuel : <span class="status st-{{ $order->status }}"><i></i>{{ $order->status_label }}</span>
                @endif
            </p>
        </div>

        <div class="box">@include('partials.order-steps')</div>

        <div class="box">
            @foreach ($order->items as $line)
                <div class="oline">
                    <x-site-image src="{{ $line->product?->image_url ?? asset('images/logo.png') }}" alt="" />
                    <div class="t"><strong>{{ $line->product_name }}</strong><small>{{ $line->variant_label }} · Qté {{ $line->quantity }}</small></div>
                    <b>{{ money($line->total) }}</b>
                </div>
            @endforeach
            <div class="facts">
                <div><span>Nom</span><p>{{ $order->customer_name }}</p></div>
                <div><span>Téléphone</span><p>{{ $order->phone }}</p></div>
                <div><span>Ville</span><p>{{ $order->city }}</p></div>
                <div><span>Paiement</span><p>À la livraison</p></div>
                <div><span>Livraison</span><p>24 – 72 h</p></div>
                <div><span>N° de commande</span><p>{{ $order->number }}</p></div>
            </div>
        </div>

        <div class="actions">
            @if ($order->user_id && auth()->id() === $order->user_id)
                <a class="btn btn-green" href="{{ route('account.orders') }}">Suivre ma commande</a>
            @else
                <a class="btn btn-green" href="{{ route('track', ['numero' => $order->number]) }}">Suivre ma commande</a>
            @endif
            <a class="btn btn-outline" href="{{ route('catalogue') }}">Continuer mes achats</a>
        </div>
    </div>
</section>
@endsection
