{{--
    Grille de stock : une ligne par couleur, une colonne par taille.
    L'état est un objet { "c{couleur}s{taille}": stock } (0 = sans couleur / taille unique).
--}}
@php
    $rows = $matrixColors ?: [0 => ['name' => 'Sans couleur', 'hex' => null]];
    $cols = $matrixSizes ?: [0 => 'Taille unique'];
    $keys = [];
    foreach ($rows as $c => $_) {
        foreach ($cols as $s => $__) {
            $keys[] = 'c'.$c.'s'.$s;
        }
    }
@endphp

<div
    x-data="{
        state: $wire.$entangle(@js($getStatePath())),
        keys: @js($keys),
        all: '',
        init() { if (! this.state || Array.isArray(this.state)) this.state = {} },
        val(k) { return this.state?.[k] ?? '' },
        put(k, v) { const s = { ...(this.state || {}) }; s[k] = v === '' ? 0 : Math.max(0, parseInt(v) || 0); this.state = s },
        fill(list, v) { if (v === '' || v === null) return; const n = Math.max(0, parseInt(v) || 0); const s = { ...(this.state || {}) }; list.forEach(k => s[k] = n); this.state = s },
        total() { return this.keys.reduce((t, k) => t + (parseInt(this.state?.[k]) || 0), 0) },
    }"
    class="mf-matrix"
>
    <style>
        .mf-matrix{--b:rgba(120,120,120,.22);font-size:14px}
        .mf-matrix .bar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:12px}
        .mf-matrix .bar input{width:90px}
        .mf-matrix .bar .tot{margin-left:auto;font-weight:600}
        .mf-matrix .wrap{overflow-x:auto;border:1px solid var(--b);border-radius:10px}
        .mf-matrix table{border-collapse:collapse;width:100%;min-width:max-content}
        .mf-matrix th,.mf-matrix td{border-bottom:1px solid var(--b);padding:6px 8px;text-align:center;white-space:nowrap}
        .mf-matrix tr:last-child td{border-bottom:0}
        .mf-matrix th{font-weight:600;background:rgba(120,120,120,.06)}
        .mf-matrix th.c,.mf-matrix td.c{text-align:left;position:sticky;left:0;background:inherit;z-index:1}
        .mf-matrix tbody td.c{background:var(--mf-row,#fff)}
        .dark .mf-matrix tbody td.c{--mf-row:#18181b}
        .mf-matrix input{width:64px;padding:5px 6px;border:1px solid var(--b);border-radius:7px;text-align:center;background:transparent;font:inherit}
        .mf-matrix input:focus{outline:2px solid #0f4a3e;outline-offset:-1px}
        .mf-matrix input.zero{color:#b91c1c}
        .mf-matrix .sw{display:inline-block;width:14px;height:14px;border-radius:50%;margin-right:7px;vertical-align:-2px;border:1px solid rgba(0,0,0,.15)}
        .mf-matrix .fill input{width:56px;border-style:dashed;font-size:12px}
        .mf-matrix .fill{color:#6b7280;font-size:12px}
        .mf-matrix .btn{padding:6px 12px;border-radius:8px;background:#0f4a3e;color:#fff;font-weight:600;font-size:13px}
        .mf-matrix .hint{color:#6b7280;font-size:12px;margin-top:8px}
    </style>

    <div class="bar">
        <span>Même stock partout :</span>
        <input type="number" min="0" x-model="all" placeholder="ex. 3" @keydown.enter.prevent="fill(keys, all)">
        <button type="button" class="btn" @click="fill(keys, all)">Appliquer à toutes les cases</button>
        <span class="tot">Total : <span x-text="total()"></span> pièce(s)</span>
    </div>

    <div class="wrap">
        <table>
            <thead>
                <tr>
                    <th class="c">Couleur \ Taille</th>
                    @foreach ($cols as $s => $sizeName)
                        <th>{{ $sizeName }}</th>
                    @endforeach
                    @if (count($cols) > 1)<th class="fill">Toute la ligne</th>@endif
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $c => $color)
                    @php $rowKeys = collect($cols)->keys()->map(fn ($s) => 'c'.$c.'s'.$s)->all(); @endphp
                    <tr>
                        <td class="c">
                            @if ($color['hex'])<span class="sw" style="background:{{ $color['hex'] }}"></span>@endif{{ $color['name'] }}
                        </td>
                        @foreach ($cols as $s => $_)
                            @php $k = 'c'.$c.'s'.$s; @endphp
                            <td>
                                <input type="number" min="0" inputmode="numeric" aria-label="Stock {{ $color['name'] }} {{ $cols[$s] }}"
                                    :value="val('{{ $k }}')" :class="{ zero: (parseInt(val('{{ $k }}')) || 0) === 0 }"
                                    @change="put('{{ $k }}', $event.target.value)" @focus="$event.target.select()">
                            </td>
                        @endforeach
                        @if (count($cols) > 1)
                            <td class="fill"><input type="number" min="0" placeholder="→" title="Même stock pour toute la ligne"
                                @change="fill(@js($rowKeys), $event.target.value); $event.target.value = ''"></td>
                        @endif
                    </tr>
                @endforeach
                @if (count($rows) > 1)
                    <tr>
                        <td class="c fill">Toute la colonne</td>
                        @foreach ($cols as $s => $_)
                            @php $colKeys = collect($rows)->keys()->map(fn ($c) => 'c'.$c.'s'.$s)->all(); @endphp
                            <td class="fill"><input type="number" min="0" placeholder="↓" title="Même stock pour toute la colonne"
                                @change="fill(@js($colKeys), $event.target.value); $event.target.value = ''"></td>
                        @endforeach
                        @if (count($cols) > 1)<td></td>@endif
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
    <p class="hint">Tapez un chiffre puis Tab pour passer à la case suivante. Une case vide = 0 (rupture). Pensez à « Enregistrer ».</p>
</div>
