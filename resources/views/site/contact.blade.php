@extends('layouts.site')

@section('title', 'Contact — Maison Filali')
@section('description', 'Contactez Maison Filali : atelier & showroom à Casablanca, téléphone, WhatsApp et formulaire de contact.')

@section('content')
<section class="cat-hero contact-hero">
    <img src="{{ asset('images/catalogue-banner.png') }}" alt="">
    <div class="txt">
        <div class="crumbs"><a href="{{ route('home') }}">Accueil</a><x-ico name="i-crumb" :s="14"/><strong>Contact</strong></div>
        <h1>Nous <em class="italic-green">contacter</em></h1>
        <p>Une question sur un modèle, une taille ou une pièce sur mesure ? Écrivez-nous, nous répondons sous 24 h.</p>
    </div>
</section>

<section class="contact-page">
    <div class="wrap">
        <div class="contact-info">
            <div class="sec-head">
                <span class="eyebrow">Atelier &amp; showroom</span>
                <h2 class="h-section">Maison Filali</h2>
                <div class="divider"><span></span><x-ico name="i-star-big" :s="22"/><span></span></div>
            </div>
            <div class="visit"><div class="info" style="width:auto;gap:18px">
                <div class="line"><x-ico name="i-pin" :s="22"/><span>{!! nl2br(e(config('shop.address'))) !!}</span></div>
                <div class="line"><x-ico name="i-phone" :s="22"/><a href="tel:{{ str_replace(' ', '', config('shop.phone')) }}">{{ config('shop.phone') }}</a></div>
                @if (config('shop.instagram_url'))<div class="line"><x-ico name="i-insta" :s="22"/><a href="{{ config('shop.instagram_url') }}" target="_blank" rel="noopener">{{ config('shop.instagram') }}</a></div>@endif
                <div class="line"><x-ico name="i-clock" :s="22"/><span>{{ config('shop.hours') }}</span></div>
            </div></div>
            <div class="contact-ctas">
                <a class="btn btn-green" href="https://wa.me/{{ config('shop.whatsapp') }}" target="_blank" rel="noopener">Écrire sur WhatsApp</a>
                <a class="btn btn-outline" href="{{ config('shop.maps_url') }}" target="_blank" rel="noopener">Itinéraire</a>
            </div>
            @include('partials.map', ['height' => 300])
        </div>

        <form class="contact-form" method="post" action="{{ route('contact.send') }}" novalidate>
            @csrf
            <div>
                <span class="eyebrow">Formulaire</span>
                <h2 class="h-section" style="font-size:40px;margin-top:8px">Écrivez-nous</h2>
            </div>
            <input type="text" name="website" class="sr" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="grid2">
                <div @class(['field', 'invalid' => $errors->has('name')])>
                    <label for="c-name">Nom complet *</label>
                    <input id="c-name" name="name" value="{{ old('name', auth()->user()?->name) }}" required autocomplete="name">
                    @error('name')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div @class(['field', 'invalid' => $errors->has('phone')])>
                    <label for="c-phone">Téléphone *</label>
                    <input id="c-phone" name="phone" type="tel" value="{{ old('phone', auth()->user()?->phone ? format_phone(auth()->user()->phone) : '') }}" placeholder="06 12 34 56 78" required autocomplete="tel">
                    @error('phone')<span class="err">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="grid2">
                <div @class(['field', 'invalid' => $errors->has('email')])>
                    <label for="c-email">E-mail <span style="font-weight:400;color:var(--muted)">(facultatif)</span></label>
                    <input id="c-email" name="email" type="email" value="{{ old('email', auth()->user()?->email) }}" autocomplete="email">
                    @error('email')<span class="err">{{ $message }}</span>@enderror
                </div>
                <div @class(['field', 'invalid' => $errors->has('subject')])>
                    <label for="c-subject">Sujet *</label>
                    <select id="c-subject" name="subject" required>
                        @foreach (\App\Models\ContactMessage::SUBJECTS as $key => $label)
                            <option value="{{ $key }}" @selected(old('subject', request('sujet')) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div @class(['field', 'invalid' => $errors->has('message')])>
                <label for="c-message">Message *</label>
                <textarea id="c-message" name="message" rows="6" required placeholder="Votre question, le modèle qui vous intéresse, vos mesures…">{{ old('message') }}</textarea>
                @error('message')<span class="err">{{ $message }}</span>@enderror
            </div>
            <button class="btn btn-green btn-block" type="submit">Envoyer le message</button>
            <p class="note"><span>Vos coordonnées servent uniquement à vous répondre. Pour une réponse immédiate, écrivez-nous sur WhatsApp.</span></p>
        </form>
    </div>
</section>
@endsection
