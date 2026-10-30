@php($reasons = \App\Models\StockMovement::REASONS)
<div style="display:flex;flex-direction:column;gap:8px">
    @forelse ($movements as $m)
        <div style="display:flex;justify-content:space-between;gap:12px;border-bottom:1px solid #ece6da;padding:8px 0;font-size:14px">
            <div>
                <strong style="color:{{ $m->quantity >= 0 ? '#1e7a45' : '#9b2c2c' }}">{{ $m->quantity >= 0 ? '+' : '' }}{{ $m->quantity }}</strong>
                · {{ $reasons[$m->reason] ?? $m->reason }}
                @if ($m->note)<span style="color:#6e6a60"> — {{ $m->note }}</span>@endif
                <div style="color:#6e6a60;font-size:12px">{{ $m->created_at->format('d/m/Y H:i') }}{{ $m->user ? ' · '.$m->user->name : '' }}</div>
            </div>
            <div style="white-space:nowrap;color:#6e6a60">Stock : <b style="color:#1f2a27">{{ $m->stock_after }}</b></div>
        </div>
    @empty
        <p style="color:#6e6a60">Aucun mouvement enregistré pour l’instant.</p>
    @endforelse
</div>
