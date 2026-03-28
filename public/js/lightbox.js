// ============================================
// RAGEFILL Lightbox v1.0
// Standalone module — creates its own DOM, no markup needed.
// Usage: Lightbox.init({ images: ['/uploads/a.webp', ...'], startIndex: 0 })
// ============================================

var Lightbox = (function() {
    'use strict';

    var el, img, counter, prevBtn, nextBtn;
    var images = [];
    var idx = 0;
    var single = false;
    var onNavigate = null; // callback(index)
    var built = false;

    function build() {
        if (built) return;
        built = true;

        el = document.createElement('div');
        el.className = 'lightbox';
        el.id = 'lightbox';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', 'Просмотр фото');

        el.innerHTML =
            '<div class="lightbox__content">' +
                '<button class="lightbox__close" aria-label="Закрыть">&times;</button>' +
                '<button class="lightbox__nav lightbox__nav--prev" aria-label="Предыдущее фото">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>' +
                '</button>' +
                '<button class="lightbox__nav lightbox__nav--next" aria-label="Следующее фото">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 18l6-6-6-6"/></svg>' +
                '</button>' +
                '<img class="lightbox__img" src="" alt="">' +
                '<div class="lightbox__counter"></div>' +
            '</div>';

        document.body.appendChild(el);

        img = el.querySelector('.lightbox__img');
        counter = el.querySelector('.lightbox__counter');
        prevBtn = el.querySelector('.lightbox__nav--prev');
        nextBtn = el.querySelector('.lightbox__nav--next');

        // Close on backdrop click (not on image or controls)
        el.querySelector('.lightbox__close').addEventListener('click', close);
        el.addEventListener('click', function(e) {
            if (e.target === el) close();
        });

        // Nav
        prevBtn.addEventListener('click', function() { navigate(-1); });
        nextBtn.addEventListener('click', function() { navigate(1); });

        // Keyboard
        document.addEventListener('keydown', function(e) {
            if (!el.classList.contains('active')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') navigate(-1);
            if (e.key === 'ArrowRight') navigate(1);
        });

        // Touch swipe
        var touchX = 0, touchY = 0;
        el.addEventListener('touchstart', function(e) {
            touchX = e.touches[0].clientX;
            touchY = e.touches[0].clientY;
        }, { passive: true });
        el.addEventListener('touchend', function(e) {
            var dx = e.changedTouches[0].clientX - touchX;
            var dy = e.changedTouches[0].clientY - touchY;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
                navigate(dx > 0 ? -1 : 1);
            }
        }, { passive: true });
    }

    function open(opts) {
        build();
        images = opts.images || [];
        idx = opts.startIndex || 0;
        onNavigate = opts.onNavigate || null;
        single = images.length <= 1;

        prevBtn.style.display = single ? 'none' : '';
        nextBtn.style.display = single ? 'none' : '';
        counter.style.display = single ? 'none' : '';

        show();
        el.classList.add('active');
        document.body.classList.add('lightbox-open');
    }

    function close() {
        el.classList.remove('active');
        document.body.classList.remove('lightbox-open');
    }

    function navigate(dir) {
        if (single) return;
        idx = (idx + dir + images.length) % images.length;
        img.style.opacity = '0';
        setTimeout(function() {
            img.src = images[idx];
            img.style.opacity = '1';
        }, 120);
        counter.textContent = (idx + 1) + ' / ' + images.length;
        if (onNavigate) onNavigate(idx);
    }

    function show() {
        img.src = images[idx];
        img.style.opacity = '1';
        if (!single) counter.textContent = (idx + 1) + ' / ' + images.length;
    }

    return { open: open, close: close };
})();
