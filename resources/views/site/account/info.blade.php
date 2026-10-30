@extends('site.account.layout')

@section('heading', 'Mes informations')

@section('account')
    <form class="panel form-card" method="post" action="{{ route('account.info.update') }}">
        @csrf
        @method('put')
        <div @class(['field', 'invalid' => $errors->has('name')])>
            <label for="name">Nom complet *</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div class="grid2">
            <div @class(['field', 'invalid' => $errors->has('phone')])>
                <label for="phone">Téléphone *</label>
                <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required>
                @error('phone')<span class="err">{{ $message }}</span>@enderror
            </div>
            <div class="field">
                <label for="city">Ville</label>
                <select id="city" name="city">
                    <option value="">Choisir…</option>
                    @foreach (config('shop.cities') as $city)<option @selected(old('city', $user->city) === $city)>{{ $city }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="field">
            <label for="address">Adresse de livraison</label>
            <input id="address" name="address" value="{{ old('address', $user->address) }}">
        </div>
        <div @class(['field', 'invalid' => $errors->has('email')])>
            <label for="email">E-mail *</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
            @error('email')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div @class(['field', 'invalid' => $errors->has('password')])>
            <label for="password">Nouveau mot de passe <span style="font-weight:400;color:var(--muted)">(laisser vide pour ne pas changer)</span></label>
            <input id="password" name="password" type="password" autocomplete="new-password">
            @error('password')<span class="err">{{ $message }}</span>@enderror
        </div>
        <div><button class="btn btn-green" type="submit">Enregistrer</button></div>
    </form>
@endsection
