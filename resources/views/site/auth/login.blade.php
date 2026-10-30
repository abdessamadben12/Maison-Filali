@extends('layouts.site')

@section('title', ($mode === 'register' ? 'Créer un compte' : 'Connexion').' — Maison Filali')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="auth">
    <div class="left">
        <img class="pattern" src="{{ asset('images/zellige-tile.png') }}" alt="">
        <div class="frame"><img src="{{ asset('images/login-photo.webp') }}" alt=""></div>
        <blockquote>« L'élégance marocaine,<br>cousue main. »</blockquote>
    </div>
    <div class="right">
        <div class="form">
            <div class="hd">
                <img src="{{ asset('images/logo-160.webp') }}" alt="" width="64" height="64">
                <h1>Bienvenue</h1>
                <p>{{ $mode === 'register' ? 'Créez votre compte pour suivre vos commandes' : 'Connectez-vous pour suivre vos commandes' }}</p>
            </div>
            <div class="tabs">
                <a @class(['on' => $mode === 'login']) href="{{ route('login') }}">Connexion</a>
                <a @class(['on' => $mode === 'register']) href="{{ route('register') }}">Créer un compte</a>
            </div>

            @if ($mode === 'login')
                <form method="post" action="{{ route('login.attempt') }}" style="display:flex;flex-direction:column;gap:22px">
                    @csrf
                    <div @class(['field', 'invalid' => $errors->has('login')])>
                        <label for="login">E-mail ou téléphone</label>
                        <input id="login" name="login" value="{{ old('login') }}" required autocomplete="username">
                        @error('login')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div class="field">
                        <label for="password">Mot de passe</label>
                        <div class="pw">
                            <input id="password" name="password" type="password" required autocomplete="current-password">
                            <button type="button" data-reveal="password" aria-label="Afficher le mot de passe"><img src="{{ asset('images/icons/i-eye.svg') }}" alt=""></button>
                        </div>
                    </div>
                    <div class="row">
                        <label class="check"><input type="checkbox" name="remember" value="1" checked><span>Se souvenir de moi</span></label>
                        <a href="https://wa.me/{{ config('shop.whatsapp') }}?text={{ rawurlencode('Bonjour, j’ai oublié le mot de passe de mon compte Maison Filali.') }}" target="_blank" rel="noopener">Mot de passe oublié ?</a>
                    </div>
                    <button class="btn btn-green btn-block" type="submit">Se connecter</button>
                </form>
            @else
                <form method="post" action="{{ route('register.store') }}" style="display:flex;flex-direction:column;gap:18px">
                    @csrf
                    <div @class(['field', 'invalid' => $errors->has('name')])>
                        <label for="name">Nom complet *</label>
                        <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">
                        @error('name')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div class="grid2">
                        <div @class(['field', 'invalid' => $errors->has('phone')])>
                            <label for="phone">Téléphone *</label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required autocomplete="tel">
                            @error('phone')<span class="err">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="city">Ville</label>
                            <select id="city" name="city">
                                <option value="">Choisir…</option>
                                @foreach (config('shop.cities') as $city)<option @selected(old('city') === $city)>{{ $city }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div @class(['field', 'invalid' => $errors->has('email')])>
                        <label for="email">E-mail *</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                        @error('email')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <div @class(['field', 'invalid' => $errors->has('password')])>
                        <label for="password">Mot de passe * <span style="font-weight:400;color:var(--muted)">(8 caractères min.)</span></label>
                        <div class="pw">
                            <input id="password" name="password" type="password" required autocomplete="new-password">
                            <button type="button" data-reveal="password" aria-label="Afficher le mot de passe"><img src="{{ asset('images/icons/i-eye.svg') }}" alt=""></button>
                        </div>
                        @error('password')<span class="err">{{ $message }}</span>@enderror
                    </div>
                    <button class="btn btn-green btn-block" type="submit">Créer mon compte</button>
                </form>
            @endif

        </div>
    </div>
</section>
@endsection
