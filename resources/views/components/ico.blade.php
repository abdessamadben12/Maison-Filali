@props(['name', 's' => 18, 'h' => null])
<span {{ $attributes->merge(['class' => 'ico']) }} style="width:{{ $s }}px;height:{{ $h ?? $s }}px"><img src="{{ asset('images/icons/'.$name.'.svg') }}" alt=""></span>
