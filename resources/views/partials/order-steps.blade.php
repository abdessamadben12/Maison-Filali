@php
    $steps = [
        ['label' => 'Commande reçue', 'date' => $order->created_at, 'empty' => ''],
        ['label' => 'Confirmée par Maison Filali', 'date' => $order->confirmed_at, 'empty' => 'Sous 24 h'],
        ['label' => 'Expédiée', 'date' => $order->shipped_at, 'empty' => '—'],
        ['label' => 'Livrée', 'date' => $order->delivered_at, 'empty' => '—'],
    ];
@endphp
<div class="steps">
    <div class="line"></div>
    <div class="row">
        @foreach ($steps as $step)
            <div @class(['step', 'done' => $step['date']])>
                <span class="dot"><x-ico :name="$step['date'] ? 'i-check-w' : 'i-check-g'" :s="16"/></span>
                <strong>{{ $step['label'] }}</strong>
                <small>{{ $step['date'] ? $step['date']->translatedFormat('j M · H:i') : $step['empty'] }}</small>
            </div>
        @endforeach
    </div>
    @if ($order->status === 'annulee')
        <p class="err" style="text-align:center;margin-top:18px">Cette commande a été annulée{{ $order->cancelled_at ? ' le '.$order->cancelled_at->translatedFormat('j F Y') : '' }}. Pour toute question : {{ config('shop.phone') }}.</p>
    @endif
</div>
