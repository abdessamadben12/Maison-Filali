{{-- Carte d'une offre (page Offres, accueil) --}}
@php($inStock = $pack->inStock())
<article class="pack-card">
    <a class="media" href="{{ route('packs.show', $pack) }}">
        <img src="{{ $pack->thumb_urls[0] }}" alt="{{ $pack->name }}" loading="lazy" decoding="async" width="400" height="300">
        @if ($pack->savings_percent > 0)<span class="save">−{{ $pack->savings_percent }} %</span>@endif
        @if (! $inStock)<span class="out">Épuisée</span>@endif
    </a>
    <div class="body">
        <span class="eyebrow">Offre · {{ $pack->items->sum('quantity') }} pièces</span>
        <a class="name" href="{{ route('packs.show', $pack) }}">{{ $pack->name }}</a>
        <ul class="incl">
            @foreach ($pack->items as $item)
                @if ($item->product)<li>{{ $item->quantity > 1 ? $item->quantity.' × ' : '' }}{{ $item->product->name }}</li>@endif
            @endforeach
        </ul>
        <div class="prices">
            <b>{{ money($pack->price) }}</b>
            @if ($pack->savings > 0)<del>{{ money($pack->compare_total) }}</del><small>Vous économisez {{ money($pack->savings) }}</small>@endif
        </div>
        @if ($pack->ends_at)<div class="until">Jusqu’au {{ $pack->ends_at->translatedFormat('j F Y') }}</div>@endif
        <a class="btn btn-green btn-sm" href="{{ route('packs.show', $pack) }}">{{ $inStock ? 'Voir l’offre' : 'Voir le détail' }}</a>
    </div>
</article>
