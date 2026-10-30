@extends('layouts.site')

@section('title', 'Mon compte — Maison Filali')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="account">
    <div class="wrap">
        <aside class="side">
            <a href="{{ route('account.orders') }}" @class(['on' => request()->routeIs('account.orders', 'account.order')])><x-ico name="i-box" :s="19"/>Mes commandes</a>
            <a href="{{ route('account.info') }}" @class(['on' => request()->routeIs('account.info')])><x-ico name="i-user-sm" :s="19"/>Mes informations</a>
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit"><x-ico name="i-logout" :s="19"/>Déconnexion</button></form>
        </aside>
        <div class="main">
            <div>
                <span class="eyebrow">Mon compte</span>
                <h1>@yield('heading', 'Bonjour '.auth()->user()->first_name)</h1>
            </div>
            @yield('account')
        </div>
    </div>
</section>
@endsection
