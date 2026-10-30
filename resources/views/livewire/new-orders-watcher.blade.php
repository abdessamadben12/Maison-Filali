{{-- Surveille les nouvelles commandes : notification + son + compteur du menu à jour --}}
<div wire:poll.10s.keep-alive="check" style="display:none"
     x-data
     x-on:mf-new-orders.window="
        // Compteur « Commandes » du menu
        document.querySelectorAll('a.fi-sidebar-item-btn[href*=&quot;/admin/commandes&quot;] .fi-badge, a[href$=&quot;/admin/commandes&quot;] .fi-badge').forEach(b => {
            const t = b.querySelector('.fi-badge-label') || b; t.textContent = $event.detail.pending;
        });
        // Petit son « ding » (sans fichier)
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            [880, 1320].forEach((f, i) => {
                const o = ctx.createOscillator(), g = ctx.createGain();
                o.type = 'sine'; o.frequency.value = f; o.connect(g); g.connect(ctx.destination);
                const t0 = ctx.currentTime + i * 0.18;
                g.gain.setValueAtTime(0.0001, t0); g.gain.exponentialRampToValueAtTime(0.25, t0 + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.35);
                o.start(t0); o.stop(t0 + 0.4);
            });
        } catch (e) {}
        // Titre de l'onglet
        document.title = '(' + $event.detail.pending + ') ' + document.title.replace(/^\(\d+\)\s*/, '');
     "></div>
