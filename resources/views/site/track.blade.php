@extends('layouts.site')

@section('title', 'Suivre ma commande — Maison Filali')

@section('content')
<section class="thanks">
    <x-site-image src="{{ asset('images/zellige.png') }}" alt="" />
    <div class="in">
        <div style="display:flex;flex-direction:column;align-items:center;gap:12px">
            <span class="eyebrow">Suivi</span>
            <h1>Suivre ma commande</h1>
            <p class="lead">Entrez votre numéro de commande et le téléphone utilisé lors de la commande.</p>
        </div>
        <form class="track-form" method="post" action="{{ route('track.find') }}">
            @csrf
            <div @class(['field', 'invalid' => $errors->has('numero')])>
                <label for="numero">N° de commande</label>
                <input id="numero" name="numero" value="{{ old('numero', request('numero')) }}" placeholder="MF-2026-0145" required>
                @error('numero')<span class="err">{{ $message }}</span>@enderror
            </div>
            <div @class(['field', 'invalid' => $errors->has('phone')])>
                <label for="phone">Téléphone</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="06 12 34 56 78" required>
                @error('phone')<span class="err">{{ $message }}</span>@enderror
            </div>
            <button class="btn btn-green btn-block" type="submit">Voir le statut</button>
            <p class="hint" style="font-size:13px;color:var(--muted);text-align:center">Vous avez un compte ? <a class="link-gold" style="font-size:13px;letter-spacing:0;text-transform:none" href="{{ route('login') }}">Connectez-vous</a></p>
        </form>
    </div>
</section>
@endsection
