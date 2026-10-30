@extends('layouts.site')

@section('title', $product->name.' — Maison Filali')
@section('description', $product->tagline)

@php($gallery = $product->gallery_urls)
@php($colors = $product->availableColors(true))
@php($sizes = $product->availableSizes())

@section('content')
<div class="wrap">
    <div class="crumbs">
        <a href="{{ route('home') }}">Accueil</a><x-ico name="i-crumb" :s="14"/>
        <a href="{{ route('category', $product->category) }}">{{ $product->category->name }}</a><x-ico name="i-crumb" :s="14"/>
        <strong>{{ $product->name }}</strong>
    </div>
</div>

<div class="wrap product" id="product" data-variants='@json($variantsJson)' data-custom="{{ $product->is_custom_possible ? 1 : 0 }}">
    <div class="gallery">
        <div class="thumbs">
            @foreach ($gallery as $i => $url)
                <button type="button" @class(['on' => $i === 0]) data-src="{{ $url }}"><img src="{{ $url }}" alt=""></button>
            @endforeach
        </div>
        <div class="main-pic"><img id="mainPic" src="{{ $gallery[0] }}" alt="{{ $product->name }}" style="view-transition-name:p{{ $product->id }}"></div>
    </div>

    <div class="pinfo">
        <span class="eyebrow">{{ $product->category->name }}{{ $product->is_new ? ' · Nouveauté' : '' }}</span>
        <h1>{{ $product->name }}</h1>
        <div class="price">{{ money($product->price) }}@if ($product->compare_price && $product->compare_price > $product->price)<del>{{ money($product->compare_price) }}</del>@endif</div>
        <div><span class="stock-badge" id="stockBadge"><i></i><span>—</span></span></div>
        @if ($product->tagline)<p class="lead">{{ $product->tagline }}</p>@endif

        @if ($colors->count())
            <div class="opt" style="padding-top:6px">
                <div class="lbl"><span>Couleur : <b id="colorName">{{ $colors->first()->name }}</b></span></div>
                <div class="colors">
                    @foreach ($colors as $c)
                        <button type="button" data-color="{{ $c->id }}" data-name="{{ $c->name }}" style="background:{{ $c->hex }}" title="{{ $c->name }}" aria-label="{{ $c->name }}"></button>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($sizes->count() || $product->is_custom_possible)
            <div class="opt">
                <div class="lbl">
                    <span>{{ $sizes->first()?->type === 'pointure' ? 'Pointure' : 'Taille' }} : <b id="sizeName">—</b></span>
                    <a class="guide" href="{{ route('page', 'guide-des-tailles') }}" target="_blank"><x-ico name="i-ruler" :s="15"/>Guide des tailles</a>
                </div>
                <div class="sizes">
                    @foreach ($sizes as $s)
                        <button type="button" data-size="{{ $s->id }}" data-name="{{ $s->name }}">{{ $s->name }}</button>
                    @endforeach
                    @if ($product->is_custom_possible)
                        <button type="button" class="wide" data-size="custom" data-name="Sur mesure">Sur mesure</button>
                    @endif
                </div>
            </div>
        @endif

        <div class="opt">
            <div class="lbl"><span>Quantité</span></div>
            <div class="qty-row">
                <div class="qty">
                    <button type="button" data-qty="-1" aria-label="Moins"><x-ico name="i-minus" :s="18"/></button>
                    <input type="number" id="qty" value="1" min="1" max="10" aria-label="Quantité">
                    <button type="button" data-qty="1" aria-label="Plus"><x-ico name="i-plus" :s="18"/></button>
                </div>
                <button class="btn btn-green" type="button" id="orderBtn">Commander</button>
            </div>
            <p class="err" id="optErr" hidden></p>
            <div class="ask">Une question sur ce modèle ?
                <a href="https://wa.me/{{ config('shop.whatsapp') }}?text={{ rawurlencode('Bonjour, une question sur « '.$product->name.' » : ') }}" target="_blank" rel="noopener"><x-ico name="i-wa-sm" :s="14"/>Écrivez-nous</a>
            </div>
        </div>

        <div class="perks">
            <div><x-ico name="i-cod" :s="30"/><span>Paiement<br>à la livraison</span></div>
            <div><x-ico name="i-confirm" :s="30"/><span>Commande confirmée<br>par notre équipe</span></div>
            <div><x-ico name="i-exchange2" :s="30"/><span>Échange sous<br>7 jours</span></div>
        </div>

        <div>
            <details class="acc" open>
                <summary>Description <x-ico class="m" name="i-minus" :s="18"/><x-ico class="p" name="i-plus" :s="18"/></summary>
                <div class="c">{{ $product->description }}</div>
            </details>
            @if ($product->materials)
                <details class="acc">
                    <summary>Matières &amp; entretien <x-ico class="m" name="i-minus" :s="18"/><x-ico class="p" name="i-plus" :s="18"/></summary>
                    <div class="c">{{ $product->materials }}</div>
                </details>
            @endif
            <details class="acc">
                <summary>Livraison &amp; retours <x-ico class="m" name="i-minus" :s="18"/><x-ico class="p" name="i-plus" :s="18"/></summary>
                <div class="c">Livraison partout au Maroc en 24 à 72 h. Paiement en espèces à la livraison.
Échange possible sous 7 jours après réception (article non porté, avec son emballage).</div>
            </details>
        </div>
    </div>
</div>

@if ($related->count())
    <section class="related">
        <div class="wrap">
            <div class="sec-head">
                <span class="eyebrow">À associer</span>
                <h2 class="h-section">Complétez votre tenue</h2>
                <div class="divider"><span></span><x-ico name="i-star-big" :s="22"/><span></span></div>
            </div>
            <div class="grid">
                @foreach ($related as $product_r)
                    @include('partials.product-card', ['product' => $product_r])
                @endforeach
            </div>
        </div>
    </section>
@endif
@endsection

@push('modals')
<div class="modal" id="orderModal" @if ($errors->any()) data-open="1" @endif role="dialog" aria-modal="true" aria-labelledby="orderTitle">
    <form class="dialog" method="post" action="{{ route('order.store') }}" novalidate>
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <input type="hidden" name="variant_id" id="fVariant" value="{{ old('variant_id') }}">
        <input type="hidden" name="custom" id="fCustom" value="{{ old('custom', 0) }}">
        <input type="hidden" name="color_id" id="fColor" value="{{ old('color_id') }}">
        <input type="hidden" name="quantity" id="fQty" value="{{ old('quantity', 1) }}">
        <div class="head">
            <div><span class="eyebrow" style="font-size:11px;letter-spacing:3.08px">Commande</span><h2 id="orderTitle">Vos coordonnées</h2></div>
            <button type="button" class="x" data-close aria-label="Fermer"><x-ico name="i-close" :s="18"/></button>
        </div>
        <div class="summary">
            <img src="{{ $gallery[0] }}" alt="">
            <div>
                <strong>{{ $product->name }}</strong>
                <small id="sumLine">—</small>
                <b id="sumPrice">{{ money($product->price) }}</b>
            </div>
        </div>
        @error('variant_id')<p class="err">{{ $message }}</p>@enderror
        @error('quantity')<p class="err">{{ $message }}</p>@enderror
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
        <div class="total"><span>Total · paiement à la livraison</span><b id="sumTotal">{{ money($product->price) }}</b></div>
        <button class="btn btn-green btn-block" type="submit">Valider ma commande</button>
        <div class="note"><x-ico name="i-lock" :s="12" :h="16"/><span>Aucun paiement en ligne. Votre commande est enregistrée, notre équipe la confirme et vous suivez son statut sur le site.</span></div>
    </form>
</div>
@endpush

@push('scripts')
<script>
    window.FILALI_PRODUCT = {
        price: {{ (int) $product->price }},
        preColor: {{ (int) (old('variant_id') ? 0 : request('couleur', 0)) }},
        oldVariant: {{ (int) old('variant_id', 0) }},
        oldCustom: {{ (int) old('custom', 0) }},
        oldQty: {{ (int) old('quantity', 1) }}
    };
</script>
@endpush
