@extends('layouts.site')

@section('title', $page['title'].' — Maison Filali')
@section('description', \App\Support\Seo::description($page['html']))
@push('head')
    {{ \App\Support\Seo::jsonLd(\App\Support\Seo::breadcrumb([['Accueil', route('home')], [$page['title'], url()->current()]])) }}
@endpush

@section('content')
<section class="page">
    <div class="in">
        <span class="eyebrow">{{ $page['eyebrow'] ?? 'Maison Filali' }}</span>
        <h1>{{ $page['title'] }}</h1>
        {!! $page['html'] !!}
    </div>
</section>
@endsection
