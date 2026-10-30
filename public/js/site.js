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
        badge.querySelector('span').textContent = text;

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
})();
