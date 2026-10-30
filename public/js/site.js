/* Maison Filali — interactions du site (sans dépendance) */
(function () {
    'use strict';

    // Ouvrir / fermer menu mobile et recherche
    document.querySelectorAll('[data-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var el = document.querySelector(btn.getAttribute('data-toggle'));
            if (!el) return;
            el.classList.toggle('open');
            var input = el.querySelector('input');
            if (el.classList.contains('open') && input) input.focus();
        });
    });

    // Carte Google Maps : chargée à l'approche (économise ~500 Ko au chargement de la page)
    var lazyMaps = document.querySelectorAll('iframe[data-src]');
    function loadMap(f) { f.src = f.getAttribute('data-src'); f.removeAttribute('data-src'); }
    if ('IntersectionObserver' in window) {
        var mio = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) { if (e.isIntersecting) { loadMap(e.target); mio.unobserve(e.target); } });
        }, { rootMargin: '300px 0px' });
        lazyMaps.forEach(function (f) { mio.observe(f); });
    } else {
        lazyMaps.forEach(loadMap);
    }

    // En-tête : si les liens du menu ne tiennent pas à gauche du logo, passer en menu ☰
    var header = document.querySelector('.header');
    var nav = document.getElementById('nav');
    function fitNav() {
        if (!header || !nav || window.innerWidth <= 900) { header && header.classList.remove('compact'); return; }
        header.classList.remove('compact');
        nav.classList.remove('open');
        if (nav.scrollWidth > nav.clientWidth + 1) header.classList.add('compact');
    }
    fitNav();
    window.addEventListener('resize', fitNav);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fitNav);

    // Boutique mobile : panneau Filtres plein écran
    var filters = document.getElementById('filters');
    if (filters) {
        var openF = function () { filters.classList.add('open'); document.body.classList.add('filters-open'); };
        var closeF = function () { filters.classList.remove('open'); document.body.classList.remove('filters-open'); };
        document.querySelectorAll('[data-open-filters]').forEach(function (b) { b.addEventListener('click', openF); });
        filters.querySelectorAll('[data-close-filters]').forEach(function (b) { b.addEventListener('click', closeF); });
        // Sections repliables (mobile)
        filters.querySelectorAll('.fgroup h3').forEach(function (h) {
            h.addEventListener('click', function () { if (window.innerWidth <= 900) h.parentNode.classList.toggle('closed'); });
        });
        // Réglette de prix ↔ cases « min / max »
        filters.querySelectorAll('.range2').forEach(function (r) {
            var lo = r.querySelector('[data-r=min]'), hi = r.querySelector('[data-r=max]'), bar = r.querySelector('.track i');
            var nMin = filters.querySelector('input[name=prix_min]'), nMax = filters.querySelector('input[name=prix_max]');
            var min = +r.getAttribute('data-min'), max = +r.getAttribute('data-max');
            function paint() {
                var a = Math.min(+lo.value, +hi.value), b = Math.max(+lo.value, +hi.value);
                bar.style.left = ((a - min) / (max - min) * 100) + '%'; bar.style.right = (100 - (b - min) / (max - min) * 100) + '%';
            }
            function fromRange(e) {
                if (+lo.value > +hi.value) { if (e.target === lo) lo.value = hi.value; else hi.value = lo.value; }
                nMin.value = +lo.value > min ? lo.value : ''; nMax.value = +hi.value < max ? hi.value : ''; paint();
            }
            lo.addEventListener('input', fromRange); hi.addEventListener('input', fromRange);
            [nMin, nMax].forEach(function (n) { n.addEventListener('input', function () {
                lo.value = nMin.value || min; hi.value = nMax.value || max; paint();
            }); });
            paint();
        });

        // « Voir N produits » mis à jour à chaque changement
        var label = filters.querySelector('[data-count-label]');
        var timer;
        filters.addEventListener('change', function () {
            if (!label || window.innerWidth > 900) return;
            clearTimeout(timer);
            timer = setTimeout(function () {
                var q = new URLSearchParams(new FormData(filters)); q.set('_count', '1');
                fetch(filters.getAttribute('data-count-url') + '?' + q.toString(), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) { label.textContent = 'Voir ' + d.count + ' produit' + (d.count > 1 ? 's' : ''); })
                    .catch(function () {});
            }, 250);
        });
    }

    // Boutique : « Voir plus » ajoute la page suivante à la grille, sans recharger
    document.addEventListener('click', function (e) {
        var more = e.target.closest('[data-more]');
        if (!more) return;
        e.preventDefault();
        more.classList.add('loading'); more.textContent = 'Chargement…';
        fetch(more.href, { headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.text(); }).then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var grid = document.querySelector('.results .grid');
            var promo = grid.querySelector('.promo');
            doc.querySelectorAll('.results .grid > .pcard').forEach(function (card) {
                card.classList.add('appended');
                grid.insertBefore(document.importNode(card, true), promo);
            });
            var nextPromo = doc.querySelector('.results .grid > .promo');
            if (nextPromo && !promo) grid.appendChild(document.importNode(nextPromo, true));
            ['#seen', '.pager'].forEach(function (sel) {
                var cur = document.querySelector(sel), fresh = doc.querySelector(sel);
                if (cur && fresh) cur.replaceWith(document.importNode(fresh, true));
                else if (cur && !fresh) cur.remove();
            });
            history.replaceState(null, '', more.href);
        }).catch(function () { location.href = more.href; });
    });

    // Messages (« Vos informations sont enregistrées »…) : se ferment seuls après 6 s
    document.querySelectorAll('.flash').forEach(function (f) {
        var close = function () { f.classList.add('hide'); setTimeout(function () { f.remove(); }, 400); };
        var b = f.querySelector('[data-flash-close]'); if (b) b.addEventListener('click', close);
        if (f.classList.contains('flash-ok')) setTimeout(close, 6000);
    });

    // Afficher / masquer un mot de passe
    document.querySelectorAll('[data-reveal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-reveal'));
            if (input) input.type = input.type === 'password' ? 'text' : 'password';
        });
    });

    // Défilement horizontal : flèches + onglets de catégories
    function hsUpdate(track) {
        var max = track.scrollWidth - track.clientWidth - 2;
        document.querySelectorAll('[data-hs-prev="' + track.id + '"]').forEach(function (b) { b.disabled = track.scrollLeft <= 2; });
        document.querySelectorAll('[data-hs-next="' + track.id + '"]').forEach(function (b) { b.disabled = track.scrollLeft >= max; });
    }
    document.querySelectorAll('.hscroll[id]').forEach(function (track) {
        var step = function () { var c = track.firstElementChild; return c ? (c.getBoundingClientRect().width + 24) * 2 : 300; };
        document.querySelectorAll('[data-hs-prev="' + track.id + '"]').forEach(function (b) { b.addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: "smooth" }); }); });
        document.querySelectorAll('[data-hs-next="' + track.id + '"]').forEach(function (b) { b.addEventListener('click', function () { track.scrollBy({ left: step(), behavior: "smooth" }); }); });
        track.addEventListener('scroll', function () { hsUpdate(track); }, { passive: true });
        window.addEventListener('resize', function () { hsUpdate(track); });
        hsUpdate(track);
    });
    document.querySelectorAll('.ctab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var cat = tab.getAttribute('data-cat');
            document.querySelectorAll('.ctab').forEach(function (t) { t.classList.toggle('on', t === tab); });
            var track = document.getElementById('shopTrack');
            track.querySelectorAll('.slide').forEach(function (s) { s.hidden = !!cat && s.getAttribute('data-cat') !== cat; });
            track.scrollLeft = 0;
            hsUpdate(track);
        });
    });

    var money = function (n) {
        return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' DH';
    };

    // ---------- Page produit ----------
    var root = document.getElementById('product');
    if (!root) return;

    var cfg = window.FILALI_PRODUCT || { price: 0 };
    var variants = JSON.parse(root.getAttribute('data-variants') || '[]');
    var customAllowed = root.getAttribute('data-custom') === '1';
    var colorBtns = Array.prototype.slice.call(root.querySelectorAll('[data-color]'));
    var sizeBtns = Array.prototype.slice.call(root.querySelectorAll('[data-size]'));
    var qtyInput = document.getElementById('qty');
    var badge = document.getElementById('stockBadge');
    var optErr = document.getElementById('optErr');
    var state = { color: null, size: null, custom: false };

    // Galerie
    var mainPic = document.getElementById('mainPic');
    root.querySelectorAll('.thumbs button').forEach(function (b) {
        b.addEventListener('click', function () {
            mainPic.src = b.getAttribute('data-src');
            root.querySelectorAll('.thumbs button').forEach(function (x) { x.classList.remove('on'); });
            b.classList.add('on');
        });
    });

    function hasColors() { return colorBtns.length > 0; }
    function hasSizes() { return sizeBtns.some(function (b) { return b.getAttribute('data-size') !== 'custom'; }); }

    function findVariant(color, size) {
        return variants.find(function (v) {
            return (!hasColors() || v.color_id === color) && (!hasSizes() || v.size_id === size);
        }) || null;
    }
    function stockFor(color, size) {
        var v = findVariant(color, size);
        return v ? v.stock : 0;
    }
    function colorStock(color) {
        return variants.filter(function (v) { return !hasColors() || v.color_id === color; })
            .reduce(function (s, v) { return s + v.stock; }, 0);
    }
    function nameOf(btns, attr, val) {
        var b = btns.find(function (x) { return x.getAttribute(attr) === String(val); });
        return b ? b.getAttribute('data-name') : '';
    }

    function render() {
        colorBtns.forEach(function (b) {
            b.classList.toggle('on', Number(b.getAttribute('data-color')) === state.color);
        });
        sizeBtns.forEach(function (b) {
            var id = b.getAttribute('data-size');
            if (id === 'custom') {
                b.classList.toggle('on', state.custom);
                return;
            }
            var st = stockFor(state.color, Number(id));
            b.classList.toggle('off', st <= 0);
            b.disabled = st <= 0;
            b.classList.toggle('on', !state.custom && Number(id) === state.size);
        });
        var cn = document.getElementById('colorName');
        if (cn) cn.textContent = nameOf(colorBtns, 'data-color', state.color) || '—';
        var sn = document.getElementById('sizeName');
        if (sn) sn.textContent = state.custom ? 'Sur mesure' : (nameOf(sizeBtns, 'data-size', state.size) || '—');

        // Badge stock
        var text, cls = '';
        if (state.custom) {
            text = 'Sur mesure — délai confirmé par notre équipe';
        } else if (!hasSizes() || state.size) {
            var st = hasSizes() ? stockFor(state.color, state.size) : colorStock(state.color);
            var label = state.size ? ' en taille ' + nameOf(sizeBtns, 'data-size', state.size) : '';
            if (st <= 0) { text = 'Épuisé' + label; cls = 'out'; }
            else if (st <= 2) { text = 'Plus que ' + st + ' pièce' + (st > 1 ? 's' : '') + label; cls = 'low'; }
            else { text = 'En stock — ' + st + ' pièces' + label; }
        } else {
            var total = colorStock(state.color);
            if (total <= 0) { text = 'Épuisé dans cette couleur'; cls = 'out'; }
            else { text = 'En stock — choisissez votre taille'; }
        }
        badge.className = 'stock-badge ' + cls;
        badge.querySelector('span').textContent = window.innerWidth <= 900 ? text.split(' — ')[0] : text;

        var max = state.custom ? 5 : Math.max(1, hasSizes() ? stockFor(state.color, state.size) : colorStock(state.color));
        qtyInput.max = Math.min(10, max);
        if (Number(qtyInput.value) > Number(qtyInput.max)) qtyInput.value = qtyInput.max;
    }

    colorBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            state.color = Number(b.getAttribute('data-color'));
            if (!state.custom && state.size && stockFor(state.color, state.size) <= 0) state.size = null;
            render();
        });
    });
    sizeBtns.forEach(function (b) {
        b.addEventListener('click', function () {
            if (b.disabled) return;
            var id = b.getAttribute('data-size');
            if (id === 'custom') { state.custom = true; state.size = null; }
            else { state.custom = false; state.size = Number(id); }
            optErr.hidden = true;
            render();
        });
    });
    root.querySelectorAll('[data-qty]').forEach(function (b) {
        b.addEventListener('click', function () {
            var v = Number(qtyInput.value) + Number(b.getAttribute('data-qty'));
            qtyInput.value = Math.max(1, Math.min(Number(qtyInput.max) || 10, v));
        });
    });

    // État initial : couleur demandée, sinon première couleur disponible
    if (hasColors()) {
        var first = colorBtns.find(function (b) { return colorStock(Number(b.getAttribute('data-color'))) > 0; }) || colorBtns[0];
        state.color = Number(first.getAttribute('data-color'));
        if (cfg.preColor && colorBtns.some(function (b) { return Number(b.getAttribute('data-color')) === cfg.preColor; })) state.color = cfg.preColor;
    }
    if (cfg.oldVariant) {
        var ov = variants.find(function (v) { return v.id === cfg.oldVariant; });
        if (ov) { state.color = ov.color_id; state.size = ov.size_id; }
    }
    if (cfg.oldCustom) state.custom = true;
    if (cfg.oldQty) qtyInput.value = cfg.oldQty;
    render();

    // ---------- Modale de commande ----------
    var modal = document.getElementById('orderModal');
    function openModal() {
        var variant = null;
        if (!state.custom) {
            if (hasSizes() && !state.size) {
                optErr.textContent = 'Choisissez une taille avant de commander.';
                optErr.hidden = false;
                optErr.scrollIntoView({ block: 'center', behavior: 'smooth' });
                return;
            }
            variant = findVariant(state.color, state.size);
            if (!variant || variant.stock <= 0) {
                optErr.textContent = 'Cette combinaison est épuisée. Choisissez une autre couleur ou taille.';
                optErr.hidden = false;
                return;
            }
        }
        var qty = Math.max(1, Number(qtyInput.value) || 1);
        document.getElementById('fVariant').value = variant ? variant.id : '';
        document.getElementById('fCustom').value = state.custom ? 1 : 0;
        document.getElementById('fColor').value = state.color || '';
        document.getElementById('fQty').value = qty;
        var parts = [];
        var cname = nameOf(colorBtns, 'data-color', state.color);
        if (cname) parts.push(cname);
        if (state.custom) parts.push('Sur mesure');
        else if (state.size) parts.push((variant && variant.size_type === 'pointure' ? 'Pointure ' : 'Taille ') + nameOf(sizeBtns, 'data-size', state.size));
        parts.push('Qté ' + qty);
        document.getElementById('sumLine').textContent = parts.join(' · ');
        document.getElementById('sumPrice').textContent = money(cfg.price * qty);
        document.getElementById('sumTotal').textContent = money(cfg.price * qty);
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { var n = document.getElementById('name'); if (n && !n.value) n.focus(); }, 50);
    }
    function closeModal() {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
    document.getElementById('orderBtn').addEventListener('click', openModal);
    modal.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', closeModal); });
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
    var ca = document.getElementById('createAccount');
    if (ca) ca.addEventListener('change', function () { document.getElementById('acctExtra').classList.toggle('show', ca.checked); });
    if (modal.getAttribute('data-open') === '1') openModal();

    // ---------- Mobile : galerie au doigt, barre Total / Commander, accordéons fermés ----------
    document.querySelectorAll('[data-order]').forEach(function (b) { b.addEventListener('click', openModal); });
    var mTotal = document.getElementById('mTotal');
    function updTotal() { if (mTotal) mTotal.textContent = money(cfg.price * Math.max(1, Number(qtyInput.value) || 1)); }
    root.addEventListener('click', function () { setTimeout(updTotal, 0); });
    qtyInput.addEventListener('input', updTotal);
    updTotal();
    var track = document.getElementById('mTrack'), dots = document.querySelectorAll('#mDots i');
    if (track && dots.length) {
        track.addEventListener('scroll', function () {
            var i = Math.round(track.scrollLeft / track.clientWidth);
            dots.forEach(function (d, k) { d.classList.toggle('on', k === i); });
        }, { passive: true });
    }
    if (window.innerWidth <= 900) {
        root.querySelectorAll('details.acc[open]').forEach(function (d) { d.open = false; });
    }
})();
