// ============================================
// RAGEFILL Homepage v1.0
// Reads data from window.__HOME_DATA__
// ============================================

(function() {
    'use strict';

    // AOS
    if (typeof AOS !== 'undefined') {
        AOS.init({ duration: 700, once: true, offset: 50 });
    }

    // Reviews slider + lightbox
    (function() {
        var container = document.getElementById('home-reviews');
        if (!container) return;

        Slider.init({
            track: container,
            prev: document.getElementById('reviews-prev'),
            next: document.getElementById('reviews-next'),
            autoPlay: 4000
        });

        var images = [];
        try { images = JSON.parse(container.dataset.gallery || '[]'); } catch(e) {}
        if (!images.length) return;
        container.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-review-index]');
            if (!btn) return;
            Lightbox.open({ images: images, startIndex: parseInt(btn.dataset.reviewIndex) || 0 });
        });
    })();

    // Theme toggle
    (function() {
        var saved = localStorage.getItem('ragefill-theme');
        if (saved === 'dark') document.body.classList.add('tg-dark');
        else if (saved === 'light') document.body.classList.remove('tg-dark');
        else if (window.matchMedia('(prefers-color-scheme: dark)').matches) document.body.classList.add('tg-dark');
        var meta = document.getElementById('meta-theme-color');
        function syncThemeColor() { if (meta) meta.content = document.body.classList.contains('tg-dark') ? '#161210' : '#0a0a0a'; }
        syncThemeColor();
        var btn = document.getElementById('theme-toggle');
        if (!btn) return;
        btn.addEventListener('click', function() {
            var isDark = document.body.classList.toggle('tg-dark');
            localStorage.setItem('ragefill-theme', isDark ? 'dark' : 'light');
            syncThemeColor();
        });
    })();

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(function(a) {
        a.addEventListener('click', function(e) {
            var target = document.querySelector(a.getAttribute('href'));
            if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
        });
    });

    // Burger menu
    (function() {
        var btn = document.getElementById('burger-btn');
        var nav = document.getElementById('main-nav');
        if (!btn || !nav) return;
        btn.addEventListener('click', function() {
            var open = nav.classList.toggle('open');
            btn.classList.toggle('open', open);
            btn.setAttribute('aria-expanded', String(open));
            document.body.classList.toggle('menu-open', open);
        });
        nav.querySelectorAll('a').forEach(function(a) {
            a.addEventListener('click', function() {
                nav.classList.remove('open');
                btn.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('menu-open');
            });
        });
    })();

    // Home search → redirect to catalog
    (function() {
        ['home-search-input', 'desktop-search-input'].forEach(function(id) {
            var input = document.getElementById(id);
            if (!input) return;
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && input.value.trim()) {
                    window.location.href = '/catalog?q=' + encodeURIComponent(input.value.trim());
                }
            });
        });
    })();

    // Featured products modal (mobile only)
    (function() {
        var data = window.__HOME_DATA__;
        if (!data) return;

        var MOBILE_BP = 1024;
        var sauces = data.sauces || [];
        var contactTg = data.contactTg || '';

        function esc(text) {
            return (text || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function sanitizeHtml(html) {
            var div = document.createElement('div');
            div.innerHTML = html || '';
            div.querySelectorAll('script,style,iframe,object,embed,link').forEach(function(el) { el.remove(); });
            div.querySelectorAll('*').forEach(function(el) {
                for (var i = el.attributes.length - 1; i >= 0; i--) {
                    var name = el.attributes[i].name.toLowerCase();
                    if (name.startsWith('on') || name === 'style' || name === 'srcset') el.removeAttribute(name);
                    if ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(el.getAttribute(name))) el.removeAttribute(name);
                }
            });
            return div.innerHTML;
        }

        var overlay = document.getElementById('home-modal-overlay');
        var modal = document.getElementById('home-modal');
        if (!overlay || !modal) return;

        var closeBtn = document.getElementById('home-modal-close');
        var contactBtn = document.getElementById('home-modal-contact');
        var readMoreBtn = document.getElementById('home-modal-read-more');
        var descEl = document.getElementById('home-modal-description');

        function openModal(sauce) {
            document.getElementById('home-modal-name').textContent = sauce.name;
            document.getElementById('home-modal-subtitle').textContent = sauce.subtitle || '';

            var imgWrap = document.getElementById('home-modal-image');
            if (sauce.image) {
                imgWrap.innerHTML = '<img class="modal__image" src="/uploads/' + esc(sauce.image) + '" alt="' + esc(sauce.name) + '">';
            } else {
                imgWrap.innerHTML = '<div class="modal__image-placeholder"></div>';
            }

            var stockEl = document.getElementById('home-modal-stock');
            if (sauce.in_stock == 0) {
                stockEl.className = 'modal__stock-badge modal__stock-badge--out';
                stockEl.textContent = 'Нет в наличии';
            } else {
                stockEl.className = 'modal__stock-badge modal__stock-badge--in';
                stockEl.textContent = 'В наличии';
            }

            var heat = parseInt(sauce.heat_level) || 0;
            var peppersEl = document.getElementById('home-modal-peppers');
            var iconsHtml = '';
            for (var i = 0; i < heat; i++) iconsHtml += '<span class="pepper active"><img src="/uploads/pepper.svg" alt="" width="18" height="18"></span>';
            for (var i = heat; i < 5; i++) iconsHtml += '<span class="pepper dim"><img src="/uploads/pepper.svg" alt="" width="18" height="18"></span>';
            peppersEl.querySelector('.modal__pepper-icons').innerHTML = iconsHtml;
            peppersEl.querySelector('.modal__pepper-label').textContent = 'Острота ' + heat + ' из 5';

            descEl.innerHTML = sanitizeHtml(sauce.description);
            descEl.classList.add('collapsed');
            readMoreBtn.style.display = sauce.description ? '' : 'none';
            readMoreBtn.textContent = 'Читать далее';

            var compEl = document.getElementById('home-modal-composition');
            var compVal = document.getElementById('home-modal-composition-value');
            if (sauce.composition) {
                compEl.style.display = '';
                compVal.innerHTML = sanitizeHtml(sauce.composition);
            } else {
                compEl.style.display = 'none';
            }

            var volEl = document.getElementById('home-modal-volume');
            var volVal = document.getElementById('home-modal-volume-value');
            if (sauce.volume) {
                volEl.style.display = '';
                volVal.textContent = sauce.volume;
            } else {
                volEl.style.display = 'none';
            }

            overlay.classList.add('active');
            document.body.classList.add('modal-open');
            history.pushState({ ragefillModal: 'home' }, '');
            modal.scrollTop = 0;
        }

        var _homeClosingViaPopstate = false;
        function closeModal() {
            if (!overlay.classList.contains('active')) return;
            overlay.classList.remove('active');
            document.body.classList.remove('modal-open');
            if (!_homeClosingViaPopstate) history.back();
        }
        window.addEventListener('popstate', function() {
            if (overlay.classList.contains('active')) {
                _homeClosingViaPopstate = true;
                closeModal();
                _homeClosingViaPopstate = false;
            }
        });

        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) closeModal(); });

        readMoreBtn.addEventListener('click', function() {
            var collapsed = descEl.classList.toggle('collapsed');
            readMoreBtn.textContent = collapsed ? 'Читать далее' : 'Свернуть';
        });

        contactBtn.addEventListener('click', function() {
            window.open('https://t.me/' + contactTg, '_blank');
        });

        // Drag to close from handle/image
        var touchStartY = 0, dragging = false;
        var handle = modal.querySelector('.modal__top-bar');
        var imgArea = modal.querySelector('.modal__image-wrapper');
        [handle, imgArea].forEach(function(el) {
            if (el) el.addEventListener('touchstart', function(e) {
                touchStartY = e.touches[0].clientY; dragging = true;
            }, { passive: true });
        });
        modal.addEventListener('touchmove', function(e) {
            if (dragging && e.touches[0].clientY - touchStartY > 80) { dragging = false; closeModal(); }
        }, { passive: true });
        modal.addEventListener('touchend', function() { dragging = false; }, { passive: true });

        // Intercept clicks on featured cards
        document.querySelectorAll('.home-product[data-id]').forEach(function(card) {
            card.addEventListener('click', function(e) {
                if (window.innerWidth >= MOBILE_BP) return;
                e.preventDefault();
                var sauce = sauces.find(function(s) { return s.id == card.dataset.id; });
                if (sauce) openModal(sauce);
            });
        });
    })();
})();
