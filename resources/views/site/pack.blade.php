@extends('layouts.site')

@section('title', $pack->name.' — Offre | Maison Filali')
@section('description', \App\Support\Seo::description(($pack->tagline ?: $pack->name).' — '.money($pack->price).($pack->savings > 0 ? ' au lieu de '.money($pack->compare_total) : '').'. Paiement à la livraison au Maroc.'))
@section('og_type', 'product')
@section('og_image', $pack->original_image_urls[0])
@push('head')
    {{ \App\Support\Seo::jsonLd([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $pack->name,
        'url' => route('packs.show', $pack),
        'image' => $pack->original_image_urls,
        'description' => \App\Support\Seo::description($pack->description ?: $pack->tagline ?: $pack->name, 500),
        'sku' => 'PACK-'.$pack->id,
        'brand' => ['@type' => 'Brand', 'name' => config('shop.name')],
        'offers' => [
            '@type' => 'Offer',
            'url' => route('packs.show', $pack),
            'price' => (string) $pack->price,
            'priceCurrency' => 'MAD',
            'availability' => $pack->inStock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => 'https://schema.org/NewCondition',
            'priceValidUntil' => $pack->ends_at?->toDateString(),
            'seller' => ['@id' => url('/').'/#boutique'],
        ],
    ]) }}
    {{ \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumb([['Accueil', route('home')], ['Offres', route('packs.index')], [$pack->name, route('packs.show', $pack)]])) }}
@endpush

@php($gallery = $pack->gallery_urls)
@php($inStock = $pack->inStock())

@section('content')
<div class="wrap">
    <div class="crumbs">
        <a class="d-only" href="{{ route('home') }}">Accueil</a><x-ico class="d-only" name="i-crumb" :s="14"/>
        <a href="{{ route('packs.index') }}">Offres</a><x-ico name="i-crumb" :s="14"/>
        <strong>{{ $pack->name }}</strong>
    </div>
</div>

<div class="wrap product pack-page" id="pack">
    <div class="gallery">
        <div class="thumbs">
            @foreach ($gallery as $i => $url)
                <button type="button" @class(['on' => $i === 0]) data-src="{{ $url }}" aria-label="Photo {{ $i + 1 }}"><img src="{{ $pack->thumb_urls[$i] ?? $url }}" alt="" width="96" height="128" @if ($i > 0) loading="lazy" @endif></button>
            @endforeach
        </div>
        <div class="main-pic"><img id="packPic" src="{{ $gallery[0] }}" alt="{{ $pack->name }} — offre Maison Filali" fetchpriority="high" width="560" height="740"></div>
    </div>

    <div class="m-gallery m-only">
        <div class="m-track" id="mTrack">
            @foreach ($gallery as $i => $url)
                <img src="{{ $url }}" alt="{{ $i === 0 ? $pack->name : '' }}" width="350" height="460" @if ($i > 0) loading="lazy" @endif>
            @endforeach
        </div>
        @if (count($gallery) > 1)
            <div class="m-dots" id="mDots">@foreach ($gallery as $i => $url)<i @class(['on' => $i === 0])></i>@endforeach</div>
        @endif
    </div>

    <div class="pinfo">
        <span class="eyebrow">Offre · {{ $pack->items->sum('quantity') }} pièces</span>
        <h1>{{ $pack->name }}</h1>
        <div class="price-row">
            <div class="price">{{ money($pack->price) }}@if ($pack->savings > 0)<del>{{ money($pack->compare_total) }}</del>@endif</div>
            @if ($pack->savings_percent > 0)<span class="pack-save">−{{ $pack->savings_percent }} % · vous économisez {{ money($pack->savings) }}</span>@endif
        </div>
        @if ($pack->tagline)<p class="lead">{{ $pack->tagline }}</p>@endif
        @if ($pack->ends_at)<p class="pack-until"><x-ico name="i-clock" :s="16"/>Offre valable jusqu’au {{ $pack->ends_at->translatedFormat('j F Y') }}</p>@endif

        {{-- Produits du pack : choix de la couleur / taille pour chacun --}}
        <div class="pack-items">
            <div class="lbl"><span>Ce pack contient</span></div>
            @foreach ($pack->items as $item)
                @continue(! $item->product)
                @php($p = $item->product)
                @php($choices = $p->variants->filter(fn ($v) => $v->stock >= $item->quantity)->sortBy(fn ($v) => sprintf('%05d-%05d', $v->color?->position ?? 0, $v->size?->position ?? 0)))
                <div @class(['pack-item', 'invalid' => $errors->has('variants.'.$item->id)])>
                    <a href="{{ route('product', $p) }}" target="_blank" class="thumb"><img src="{{ $p->thumb_urls[0] }}" alt="" width="64" height="84" loading="lazy"></a>
                    <div class="info">
                        <strong>{{ $item->quantity > 1 ? $item->quantity.' × ' : '' }}{{ $p->name }}</strong>
                        <small>{{ $p->category?->name }} · valeur {{ money($p->price * $item->quantity) }}</small>
                        @if ($p->variants->count() > 1)
                            <select name="variants[{{ $item->id }}]" form="packForm" data-variant aria-label="Couleur / taille pour {{ $p->name }}" @disabled($choices->isEmpty())>
                                <option value="">{{ $choices->isEmpty() ? 'Épuisé' : 'Choisir couleur / taille…' }}</option>
                                @foreach ($choices as $v)
                                    <option value="{{ $v->id }}" @selected((int) old('variants.'.$item->id) === $v->id)>{{ $v->label }}</option>
                                @endforeach
                            </select>
                        @else
                            <small class="one">{{ $p->variants->first()?->label }}</small>
                        @endif
                        @error('variants.'.$item->id)<span class="err">{{ $message }}</span>@enderror
                    </div>
                </div>
            @endforeach
        </div>

        <div class="opt">
            <div class="qty-row">
                <div class="qty">
                    <button type="button" data-pqty="-1" aria-label="Moins"><x-ico name="i-minus" :s="18"/></button>
                    <input type="number" id="packQty" value="{{ old('quantity', 1) }}" min="1" max="5" aria-label="Nombre de packs">
                    <button type="button" data-pqty="1" aria-label="Plus"><x-ico name="i-plus" :s="18"/></button>
                </div>
                <button class="btn btn-green" type="button" id="packOrderBtn" @disabled(! $inStock)>{{ $inStock ? 'Commander le pack' : 'Offre épuisée' }}</button>
            </div>
            <p class="err" id="packErr" hidden></p>
            @error('pack_id')<p class="err">{{ $message }}</p>@enderror
            <div class="m-cod m-only"><x-ico name="i-truck" :s="22"/><span>Paiement à la livraison au Maroc</span></div>
        </div>

        <div class="perks d-only">
            <div><x-ico name="i-cod" :s="30"/><span>Paiement<br>à la livraison</span></div>
            <div><x-ico name="i-confirm" :s="30"/><span>Commande confirmée<br>par notre équipe</span></div>
            <div><x-ico name="i-exchange2" :s="30"/><span>Échange sous<br>7 jours</span></div>
        </div>

        @if ($pack->description)
            <details class="acc" open>
                <summary>Description <x-ico class="m" name="i-minus" :s="18"/><x-ico class="p" name="i-plus" :s="18"/></summary>
                <div class="c">{{ $pack->description }}</div>
            </details>
        @endif
    </div>
</div>

<div class="m-buy m-only">
    <div><small>Total</small><b id="mTotal">{{ money($pack->price) }}</b></div>
    <button class="btn btn-green" type="button" data-pack-order @disabled(! $inStock)>Commander</button>
</div>

@if ($others->count())
    <section class="related">
        <div class="wrap">
            <div class="sec-head">
                <span class="eyebrow">Autres offres</span>
                <h2 class="h-section">Vous aimerez aussi</h2>
                <div class="divider"><span></span><x-ico name="i-star-big" :s="22"/><span></span></div>
            </div>
            <div class="pack-grid">
                @foreach ($others as $other)
                    @include('partials.pack-card', ['pack' => $other])
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection

@push('modals')
<div class="modal" id="orderModal" @if ($errors->hasAny(['name', 'phone', 'city', 'email', 'password'])) data-open="1" @endif role="dialog" aria-modal="true" aria-labelledby="orderTitle">
    <form class="dialog" id="packForm" method="post" action="{{ route('packs.order') }}" novalidate>
        @csrf
        <input type="hidden" name="pack_id" value="{{ $pack->id }}">
        <input type="hidden" name="quantity" id="fPackQty" value="{{ old('quantity', 1) }}">
        <div class="head">
            <div><span class="eyebrow" style="font-size:11px;letter-spacing:3.08px">Commande</span><h2 id="orderTitle">Vos coordonnées</h2></div>
            <button type="button" class="x" data-close aria-label="Fermer"><x-ico name="i-close" :s="18"/></button>
        </div>
        <div class="summary">
            <img src="{{ $gallery[0] }}" alt="">
            <div>
                <strong>{{ $pack->name }}</strong>
                <small id="sumLine">Pack · Qté 1</small>
                <b id="sumPrice">{{ money($pack->price) }}</b>
            </div>
        </div>
        <div @class(['field', 'invalid' => $errors->has('name')])>
            <label for="name">Nom complet *</label>
            <input id="name" name="name" value="{{ old('name', $customer['name'] ?? '') }}" required autocomplete="name">
            @error('name')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="grid2">
            <div @class(['field', 'invalid' => $errors->has('phone')])>
                <label for="phone">Téléphone *</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone', $customer['phone'] ?? '') }}" placeholder="06 12 34 56 78" required autocomplete="tel">
                @error('phone')<span class="err">{{ $message }}</span>@enderror
            </div>
            <div @class(['field', 'invalid' => $errors->has('city')])>
                <label for="city">Ville *</label>
                <select id="city" name="city" required>
                    <option value="">Choisir…</option>
                    @foreach (config('shop.cities') as $city)
                        <option @selected(old('city', $customer['city'] ?? '') === $city)>{{ $city }}</option>
                    @endforeach
                </select>
                @error('city')<span class="err">{{ $message }}</span>@enderror
            </div>
        </div>
        <div class="field">
            <label for="address">Adresse de livraison</label>
            <input id="address" name="address" value="{{ old('address', $customer['address'] ?? '') }}" placeholder="Quartier, rue, n° (facultatif)" autocomplete="street-address">
        </div>
        @guest
            <label class="check"><input type="checkbox" name="create_account" value="1" id="createAccount" @checked(old('create_account'))><span style="font-size:14px">Créer un compte pour suivre ma commande <span style="color:var(--muted)">(facultatif)</span></span></label>
            <div @class(['acct-extra', 'show' => old('create_account')]) id="acctExtra">
                <div @class(['field', 'invalid' => $errors->has('email')])>
                    <label for="email">E-mail *</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email">
                    @error('email')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div @class(['field', 'invalid' => $errors->has('password')])>
                    <label for="password">Mot de passe * <span style="font-weight:400;color:var(--muted)">(8 caractères min.)</span></label>
                    <input id="password" name="password" type="password" autocomplete="new-password">
                    @error('password')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
        @endguest
        <div class="total"><span>Total · paiement à la livraison</span><b id="sumTotal">{{ money($pack->price) }}</b></div>
        <button class="btn btn-green btn-block" type="submit">Valider ma commande</button>
        <div class="note"><x-ico name="i-lock" :s="12" :h="16"/><span>Aucun paiement en ligne. Notre équipe vous appelle pour confirmer la commande.</span></div>
    </form>
</div>
@endpush

@push('scripts')
<script>
(function () {
    var price = {{ (int) $pack->price }};
    var money = function (n) { return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' DH'; };
    var qty = document.getElementById('packQty'), err = document.getElementById('packErr');
    var modal = document.getElementById('orderModal');
    function q() { return Math.min(5, Math.max(1, parseInt(qty.value) || 1)); }
    function upd() { var t = money(price * q()); document.getElementById('mTotal').textContent = t; }
    document.querySelectorAll('[data-pqty]').forEach(function (b) {
        b.addEventListener('click', function () { qty.value = Math.min(5, Math.max(1, q() + Number(b.getAttribute('data-pqty')))); upd(); });
    });
    qty.addEventListener('input', upd);

    function open() {
        var missing = Array.prototype.find.call(document.querySelectorAll('select[data-variant]'), function (s) { return !s.value; });
        if (missing) {
            err.textContent = 'Choisissez la couleur / taille de chaque pièce du pack.';
            err.hidden = false;
            missing.focus();
            missing.closest('.pack-item').classList.add('invalid');
            missing.scrollIntoView({ block: 'center', behavior: 'smooth' });
            return;
        }
        err.hidden = true;
        document.getElementById('fPackQty').value = q();
        document.getElementById('sumLine').textContent = 'Pack · Qté ' + q();
        document.getElementById('sumPrice').textContent = money(price * q());
        document.getElementById('sumTotal').textContent = money(price * q());
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { var n = document.getElementById('name'); if (n && !n.value) n.focus(); }, 50);
    }
    function close() { modal.classList.remove('open'); document.body.style.overflow = ''; }
    document.getElementById('packOrderBtn').addEventListener('click', open);
    document.querySelectorAll('[data-pack-order]').forEach(function (b) { b.addEventListener('click', open); });
    document.querySelectorAll('select[data-variant]').forEach(function (s) {
        s.addEventListener('change', function () { s.closest('.pack-item').classList.remove('invalid'); });
    });
    modal.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', close); });
    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    var ca = document.getElementById('createAccount');
    if (ca) ca.addEventListener('change', function () { document.getElementById('acctExtra').classList.toggle('show', ca.checked); });
    if (modal.getAttribute('data-open') === '1') open();

    // Galerie : vignettes (ordinateur) et points (mobile)
    var pic = document.getElementById('packPic');
    document.querySelectorAll('#pack .thumbs button').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('#pack .thumbs button').forEach(function (x) { x.classList.remove('on'); });
            b.classList.add('on'); pic.src = b.getAttribute('data-src');
        });
    });
    var track = document.getElementById('mTrack'), dots = document.querySelectorAll('#mDots i');
    if (track && dots.length) track.addEventListener('scroll', function () {
        var i = Math.round(track.scrollLeft / track.clientWidth);
        dots.forEach(function (d, k) { d.classList.toggle('on', k === i); });
    }, { passive: true });
    upd();
})();
</script>
@endpush
