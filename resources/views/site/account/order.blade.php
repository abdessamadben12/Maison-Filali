@extends('site.account.layout')

@section('heading', 'Commande '.$order->number)

@section('account')
    <div class="panel">
        <div class="ph">
            <div><strong>Suivi</strong><small>{{ $order->status_message }}</small></div>
            <span class="status st-{{ $order->status }}"><i></i>{{ $order->status_label }}</span>
        </div>
        @include('partials.order-steps')
    </div>
    <div class="box">
        @foreach ($order->items as $line)
            <div class="oline">
                <img src="{{ $line->product?->thumb_urls[0] ?? asset('images/logo-160.webp') }}" alt="">
                <div class="t"><strong>{{ $line->product_name }}</strong><small>{{ $line->variant_label }} · Qté {{ $line->quantity }}</small></div>
                <b>{{ money($line->total) }}</b>
            </div>
        @endforeach
        <div class="facts">
            <div><span>Nom</span><p>{{ $order->customer_name }}</p></div>
            <div><span>Téléphone</span><p>{{ $order->phone }}</p></div>
            <div><span>Ville</span><p>{{ $order->city }}</p></div>
            <div><span>Adresse</span><p>{{ $order->address ?: '—' }}</p></div>
            <div><span>Paiement</span><p>À la livraison</p></div>
            <div><span>Date</span><p>{{ $order->created_at->translatedFormat('j F Y · H:i') }}</p></div>
        </div>
    </div>
    <div><a class="btn btn-outline btn-sm" href="{{ route('account.orders') }}">← Toutes mes commandes</a></div>
@endsection
