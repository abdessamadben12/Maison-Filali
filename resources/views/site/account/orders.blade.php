@extends('site.account.layout')

@section('account')
    @if ($current)
        <div class="panel">
            <div class="ph">
                <div>
                    <strong>Commande {{ $current->number }}</strong>
                    <small>{{ $current->status_message }}</small>
                </div>
                <span class="status st-{{ $current->status }}"><i></i>{{ $current->status_label }}</span>
            </div>
            @include('partials.order-steps', ['order' => $current])
        </div>
    @endif

    <div class="panel orders">
        <div class="ph" style="padding-bottom:20px"><strong>Mes commandes</strong></div>
        @if ($orders->count())
            <div class="th"><span>Commande</span><span>Article</span><span>Total</span><span>Statut</span><span></span></div>
            @foreach ($orders as $order)
                @php($line = $order->items->first())
                <div class="tr">
                    <div><strong>{{ $order->number }}</strong><small>{{ $order->created_at->translatedFormat('d M Y') }}</small></div>
                    <div class="art">
                        <img src="{{ $line?->product?->image_url ?? asset('images/logo.png') }}" alt="">
                        <div><b>{{ $line?->product_name }}</b><small>{{ $line?->variant_label }} · ×{{ $line?->quantity }}</small></div>
                    </div>
                    <div class="tot">{{ money($order->total) }}</div>
                    <div><span class="status st-{{ $order->status }}"><i></i>{{ $order->status_label }}</span></div>
                    <div class="go"><a class="btn btn-outline" href="{{ route('account.order', $order) }}">Détails</a></div>
                </div>
            @endforeach
        @else
            <div class="empty" style="padding:40px 24px">
                <h3>Aucune commande pour l'instant</h3>
                <p><a class="link-gold" href="{{ route('catalogue') }}">Découvrir la boutique</a></p>
            </div>
        @endif
    </div>
@endsection
