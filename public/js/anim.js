/* Maison Filali — animations (apparition au défilement, parallaxe, effets) */
(function () {
    'use strict';
    var html = document.documentElement;
    if (!html.classList.contains('anim')) return; // « réduire les animations » activé

    // 1. Éléments qui apparaissent au défilement : [sélecteur, effet]
    var groups = [
        ['.sec-head', ''],
        ['.reassure .item', ''],
        ['.univ .arch-card', 'zoom'],
        ['.pcard', ''],
        ['.feature .pics', 'left'],
        ['.feature .txt', 'right'],
        ['.visit .info', 'left'],
        ['.visit > .wrap > .map', 'right'],
        ['.contact-info', 'left'],
        ['.contact-form', 'right'],
        ['.filters, .results-head', ''],
        ['.page-body > *', ''],
        ['.account-card, .order-card, .track-card, .form-card', ''],
        ['.footer .cols > *', '']
    ];
    var targets = [];
    groups.forEach(function (g) {
        document.querySelectorAll(g[0]).forEach(function (el) {
            if (el.hasAttribute('data-anim') || el.closest('[data-anim]') || el.closest('.modal')) return;
            el.setAttribute('data-anim', g[1]);
            targets.push(el);
        });
    });

    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            var i = 0;
            entries.forEach(function (e) {
                if (!e.isIntersecting) return;
                e.target.style.setProperty('--d', Math.min(i++ * 0.09, 0.45) + 's');
                e.target.classList.add('in');
                io.unobserve(e.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        targets.forEach(function (el) { io.observe(el); });
    } else {
        targets.forEach(function (el) { el.classList.add('in'); });
    }

    // 2. En-tête avec ombre + parallaxe légère (hero, mise en avant)
    var header = document.querySelector('.header');
    var heroImg = document.querySelector('.hero > img');
    var pic1 = document.querySelector('.feature .pic1');
    var pic2 = document.querySelector('.feature .pic2');
    var ticking = false;
    function onScroll() {
        var y = window.scrollY;
        if (header) header.classList.toggle('scrolled', y > 10);
        if (heroImg && y < 900) heroImg.style.translate = '0 ' + (y * 0.25).toFixed(1) + 'px';
        if (pic1 && pic2) {
            var r = pic1.parentNode.getBoundingClientRect();
            var p = (r.top + r.height / 2 - window.innerHeight / 2) / window.innerHeight; // -1 … 1
            if (Math.abs(p) < 1.5) {
                pic1.style.translate = '0 ' + (p * 30).toFixed(1) + 'px';
                pic2.style.translate = '0 ' + (p * -40).toFixed(1) + 'px';
            }
        }
        ticking = false;
    }
    window.addEventListener('scroll', function () {
        if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
    }, { passive: true });
    onScroll();

    // 3. Onglets « Nos créations » : les cartes réapparaissent en cascade
    document.querySelectorAll('.ctab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var i = 0;
            document.querySelectorAll('#shopTrack .slide').forEach(function (s) {
                s.classList.remove('pop');
                if (s.hidden) return;
                var card = s.querySelector('[data-anim]');
                if (card) card.classList.add('in');
                void s.offsetWidth; // relance l'animation
                s.style.setProperty('--d', Math.min(i++ * 0.07, 0.42) + 's');
                s.classList.add('pop');
            });
        });
    });

    // 4. Page produit : fondu au changement de photo
    var mainPic = document.getElementById('mainPic');
    if (mainPic) {
        document.querySelectorAll('.thumbs button').forEach(function (b) {
            b.addEventListener('click', function () {
                mainPic.classList.remove('swap');
                void mainPic.offsetWidth;
                mainPic.classList.add('swap');
            });
        });
    }
})();
