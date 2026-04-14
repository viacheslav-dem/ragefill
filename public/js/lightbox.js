// ============================================
// RAGEFILL Lightbox v3.0 — Stories-style carousel
// Center: large image flanked by prev/next previews.
// Bottom: scrollable thumbnail strip (tablet/desktop).
// Usage: Lightbox.open({ images: [...], startIndex: 0 })
// ============================================

var Lightbox = (function() {
    'use strict';

    var el, mainImg, prevImg, nextImg, prevSlide, nextSlide, mainSlide;
    var counter, closeBtn, prevBtn, nextBtn, thumbStrip, carousel;
    var images = [];
    var idx = 0;
    var single = false;
    var onNavigate = null;
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
            '<button class="lightbox__close" aria-label="Закрыть">&times;</button>' +
            '<div class="lightbox__carousel">' +
                '<button class="lightbox__nav lightbox__nav--prev" aria-label="Предыдущее фото">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>' +
                '</button>' +
                '<div class="lightbox__slide lightbox__slide--prev" aria-hidden="true" tabindex="-1">' +
                    '<img src="" alt="" draggable="false">' +
                '</div>' +
                '<div class="lightbox__slide lightbox__slide--main">' +
                    '<img src="" alt="" draggable="false">' +
                '</div>' +
                '<div class="lightbox__slide lightbox__slide--next" aria-hidden="true" tabindex="-1">' +
                    '<img src="" alt="" draggable="false">' +
                '</div>' +
                '<button class="lightbox__nav lightbox__nav--next" aria-label="Следующее фото">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 18l6-6-6-6"/></svg>' +
                '</button>' +
            '</div>' +
            '<div class="lightbox__thumbs-wrap">' +
                '<div class="lightbox__thumbs"></div>' +
            '</div>' +
            '<div class="lightbox__counter"></div>';

        document.body.appendChild(el);

        closeBtn = el.querySelector('.lightbox__close');
        carousel = el.querySelector('.lightbox__carousel');
        prevSlide = el.querySelector('.lightbox__slide--prev');
        mainSlide = el.querySelector('.lightbox__slide--main');
        nextSlide = el.querySelector('.lightbox__slide--next');
        prevImg = prevSlide.querySelector('img');
        mainImg = mainSlide.querySelector('img');
        nextImg = nextSlide.querySelector('img');
        prevBtn = el.querySelector('.lightbox__nav--prev');
        nextBtn = el.querySelector('.lightbox__nav--next');
        thumbStrip = el.querySelector('.lightbox__thumbs');
        counter = el.querySelector('.lightbox__counter');

        closeBtn.addEventListener('click', close);
        el.addEventListener('click', function(e) {
            if (e.target === el) close();
        });

        prevBtn.addEventListener('click', function() { navigate(-1); });
        nextBtn.addEventListener('click', function() { navigate(1); });

        // Click on side previews to navigate
        prevSlide.addEventListener('click', function() { navigate(-1); });
        nextSlide.addEventListener('click', function() { navigate(1); });

        thumbStrip.addEventListener('click', function(e) {
            var thumb = e.target.closest('.lightbox__thumb');
            if (!thumb) return;
            var newIdx = parseInt(thumb.dataset.index, 10);
            if (newIdx === idx || isNaN(newIdx)) return;
            var dir = newIdx > idx ? 1 : -1;
            idx = newIdx;
            showWithTransition(dir);
            if (onNavigate) onNavigate(idx);
        });

        document.addEventListener('keydown', function(e) {
            if (!el.classList.contains('active')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') navigate(-1);
            if (e.key === 'ArrowRight') navigate(1);
        });

        // Touch swipe with drag feedback
        var touchStartX = 0, touchStartY = 0, touchCurrentX = 0, dragging = false;
        mainSlide.addEventListener('touchstart', function(e) {
            dragging = true;
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            touchCurrentX = touchStartX;
            mainSlide.style.transition = 'none';
        }, { passive: true });

        mainSlide.addEventListener('touchmove', function(e) {
            if (!dragging || single) return;
            touchCurrentX = e.touches[0].clientX;
            var dx = touchCurrentX - touchStartX;
            var dy = e.touches[0].clientY - touchStartY;
            if (Math.abs(dx) > Math.abs(dy)) {
                var clamped = Math.max(-80, Math.min(80, dx));
                mainSlide.style.transform = 'translate3d(' + clamped + 'px, 0, 0)';
            }
        }, { passive: true });

        mainSlide.addEventListener('touchend', function() {
            if (!dragging) return;
            dragging = false;
            mainSlide.style.transition = '';
            mainSlide.style.transform = '';
            var dx = touchCurrentX - touchStartX;
            if (Math.abs(dx) > 50) {
                navigate(dx > 0 ? -1 : 1);
            }
        }, { passive: true });
    }

    var _lbClosingViaPopstate = false;

    function open(opts) {
        build();
        images = opts.images || [];
        idx = opts.startIndex || 0;
        onNavigate = opts.onNavigate || null;
        single = images.length <= 1;

        buildThumbnails();
        render();
        el.classList.add('active');
        document.body.classList.add('lightbox-open');
        history.pushState({ ragefillModal: 'lightbox' }, '');
    }

    function close() {
        if (!el.classList.contains('active')) return;
        el.classList.remove('active');
        document.body.classList.remove('lightbox-open');
        if (!_lbClosingViaPopstate) history.back();
    }

    window.addEventListener('popstate', function() {
        if (el && el.classList.contains('active')) {
            _lbClosingViaPopstate = true;
            close();
            _lbClosingViaPopstate = false;
        }
    });

    function navigate(dir) {
        if (single) return;
        idx = (idx + dir + images.length) % images.length;
        showWithTransition(dir);
        if (onNavigate) onNavigate(idx);
    }

    function isMobile() {
        return window.innerWidth < 768;
    }

    function showWithTransition(dir) {
        if (isMobile()) {
            // Mobile: slide out, then slide in from opposite side
            var dur = 150;
            mainSlide.style.transition = 'transform ' + dur + 'ms ease-in, opacity ' + dur + 'ms ease-in';
            mainSlide.style.opacity = '0';
            mainSlide.style.transform = 'translate3d(' + (dir * -30) + 'vw, 0, 0)';

            setTimeout(function() {
                render();
                mainSlide.style.transition = 'none';
                mainSlide.style.transform = 'translate3d(' + (dir * 30) + 'vw, 0, 0)';
                mainSlide.style.opacity = '0';
                mainSlide.offsetHeight;
                mainSlide.style.transition = 'transform ' + dur + 'ms ease-out, opacity ' + dur + 'ms ease-out';
                mainSlide.style.transform = '';
                mainSlide.style.opacity = '';
                setTimeout(function() { mainSlide.style.transition = ''; }, dur);
            }, dur);
            return;
        }

        // Desktop: slide old image out, then slide new image in
        var duration = 200;
        mainSlide.style.transition = 'transform ' + duration + 'ms ease-in, opacity ' + duration + 'ms ease-in';
        mainSlide.style.opacity = '0';
        mainSlide.style.transform = 'translate3d(' + (dir * -30) + 'vw, 0, 0)';

        setTimeout(function() {
            render();
            // Position new image on the opposite side (off-screen)
            mainSlide.style.transition = 'none';
            mainSlide.style.transform = 'translate3d(' + (dir * 30) + 'vw, 0, 0)';
            mainSlide.style.opacity = '0';

            // Force reflow so the position applies before animating in
            mainSlide.offsetHeight;

            mainSlide.style.transition = 'transform ' + duration + 'ms ease-out, opacity ' + duration + 'ms ease-out';
            mainSlide.style.transform = '';
            mainSlide.style.opacity = '';

            setTimeout(function() {
                mainSlide.style.transition = '';
            }, duration);
        }, duration);
    }

    function buildThumbnails() {
        if (single) {
            thumbStrip.innerHTML = '';
            return;
        }
        thumbStrip.innerHTML = images.map(function(src, i) {
            return '<button class="lightbox__thumb" data-index="' + i + '" aria-label="Фото ' + (i + 1) + '">' +
                '<img class="lightbox__thumb-img" src="' + src + '" alt="" draggable="false">' +
            '</button>';
        }).join('');
    }

    function render() {
        mainImg.src = images[idx];

        // Side previews
        if (!single && images.length > 1) {
            var prevIdx = (idx - 1 + images.length) % images.length;
            var nextIdx = (idx + 1) % images.length;
            prevImg.src = images[prevIdx];
            nextImg.src = images[nextIdx];
            prevSlide.style.visibility = '';
            nextSlide.style.visibility = '';
        } else {
            prevSlide.style.visibility = 'hidden';
            nextSlide.style.visibility = 'hidden';
        }

        prevBtn.style.display = single ? 'none' : '';
        nextBtn.style.display = single ? 'none' : '';
        counter.style.display = single ? 'none' : '';
        el.querySelector('.lightbox__thumbs-wrap').style.display = single ? 'none' : '';

        // Mobile edge gradient hints
        if (carousel) {
            carousel.classList.toggle('has-prev', !single && images.length > 1);
            carousel.classList.toggle('has-next', !single && images.length > 1);
        }

        if (!single) {
            counter.textContent = (idx + 1) + ' / ' + images.length;
            thumbStrip.querySelectorAll('.lightbox__thumb').forEach(function(thumb, i) {
                thumb.classList.toggle('active', i === idx);
            });
            scrollThumbIntoView();
            preloadAdjacent();
        }
    }

    function scrollThumbIntoView() {
        var activeThumb = thumbStrip.querySelector('.lightbox__thumb.active');
        if (!activeThumb) return;
        activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }

    function preloadAdjacent() {
        if (images.length <= 2) return;
        var next2 = (idx + 2) % images.length;
        var prev2 = (idx - 2 + images.length) % images.length;
        var img1 = new Image(); img1.src = images[next2];
        var img2 = new Image(); img2.src = images[prev2];
    }

    return { open: open, close: close };
})();
