@php($colors = $product->availableColors())
<article class="pcard">
    <a class="media" href="{{ route('product', $product) }}">
        <img src="{{ $product->thumb_urls[0] }}" alt="{{ $product->name }}" loading="lazy" decoding="async" width="294" height="372" style="view-transition-name:p{{ $product->id }}">
        @if (count($product->thumb_urls) > 1)
            <img class="alt" src="{{ $product->thumb_urls[1] }}" alt="" loading="lazy" decoding="async" width="294" height="372">
        @endif
        @if (! $product->inStock())
            <span class="tag out">Épuisé</span>
        @elseif ($product->is_new)
            <span class="tag">Nouveau</span>
        @endif
    </a>
    <div class="body">
        <div class="cat">{{ $product->category->name }}</div>
        <a class="name" href="{{ route('product', $product) }}">{{ $product->name }}</a>
        <div class="meta">
            <span class="price">{{ money($product->price) }}</span>
            @if ($colors->count())
                <span class="dots">
                    @foreach ($colors->take(5) as $c)<i style="background:{{ $c->hex }}" title="{{ $c->name }}"></i>@endforeach
                    @if ($colors->count() > 1)<small>{{ $colors->count() }} coloris</small>@endif
                </span>
            @endif
        </div>
        <a class="btn btn-green btn-sm" href="{{ route('product', $product) }}">Commander</a>
    </div>
</article>
