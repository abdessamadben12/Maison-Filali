@extends('layouts.site')

@section('title', $page['title'].' — Maison Filali')

@section('content')
<section class="page">
    <div class="in">
        <span class="eyebrow">{{ $page['eyebrow'] ?? 'Maison Filali' }}</span>
        <h1>{{ $page['title'] }}</h1>
        {!! $page['html'] !!}
    </div>
</section>
@endsection
