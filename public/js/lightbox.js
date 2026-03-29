// ============================================
// RAGEFILL Lightbox v2.0 — Carousel with bottom thumbnails
// Center: large image + arrow nav. Bottom: scrollable thumbnail strip.
// Usage: Lightbox.open({ images: [...], startIndex: 0 })
// ============================================

var Lightbox = (function() {
    'use strict';

    var el, mainImg, counter, closeBtn, prevBtn, nextBtn, thumbStrip;
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
            '<div class="lightbox__stage">' +
                '<button class="lightbox__nav lightbox__nav--prev" aria-label="Предыдущее фото">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>' +
                '</button>' +
                '<div class="lightbox__main">' +
                    '<img class="lightbox__img" src="" alt="" draggable="false">' +
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
        mainImg = el.querySelector('.lightbox__img');
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

        thumbStrip.addEventListener('click', function(e) {
            var thumb = e.target.closest('.lightbox__thumb');
            if (!thumb) return;
            var newIdx = parseInt(thumb.dataset.index, 10);
            if (newIdx === idx || isNaN(newIdx)) return;
            idx = newIdx;
            showWithTransition();
            if (onNavigate) onNavigate(idx);
        });

        document.addEventListener('keydown', function(e) {
            if (!el.classList.contains('active')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowLeft') navigate(-1);
            if (e.key === 'ArrowRight') navigate(1);
        });

        var touchX = 0, touchY = 0;
        el.querySelector('.lightbox__main').addEventListener('touchstart', function(e) {
            touchX = e.touches[0].clientX;
            touchY = e.touches[0].clientY;
        }, { passive: true });
        el.querySelector('.lightbox__main').addEventListener('touchend', function(e) {
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

        buildThumbnails();
        render();
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
        showWithTransition();
        if (onNavigate) onNavigate(idx);
    }

    function showWithTransition() {
        mainImg.style.opacity = '0';
        setTimeout(function() {
            render();
            mainImg.style.opacity = '1';
        }, 100);
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

        prevBtn.style.display = single ? 'none' : '';
        nextBtn.style.display = single ? 'none' : '';
        counter.style.display = single ? 'none' : '';
        el.querySelector('.lightbox__thumbs-wrap').style.display = single ? 'none' : '';

        if (!single) {
            counter.textContent = (idx + 1) + ' / ' + images.length;
            thumbStrip.querySelectorAll('.lightbox__thumb').forEach(function(thumb, i) {
                thumb.classList.toggle('active', i === idx);
            });
            scrollThumbIntoView();
        }
    }

    function scrollThumbIntoView() {
        var activeThumb = thumbStrip.querySelector('.lightbox__thumb.active');
        if (!activeThumb) return;
        activeThumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }

    return { open: open, close: close };
})();
