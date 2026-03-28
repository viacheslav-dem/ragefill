// ============================================
// RAGEFILL Slider v1.0
// CSS scroll-snap slider with nav buttons & auto-play.
// Usage: Slider.init({ track: element, prev: btn, next: btn, autoPlay: 4000 })
// ============================================

var Slider = (function() {
    'use strict';

    function init(opts) {
        var track = opts.track;
        var prevBtn = opts.prev;
        var nextBtn = opts.next;
        var autoDelay = opts.autoPlay || 0;

        if (!track || !track.children.length) return;

        var autoTimer = null;

        function getScrollAmount() {
            var child = track.children[0];
            if (!child) return track.clientWidth;
            var style = getComputedStyle(track);
            var gap = parseFloat(style.gap) || 0;
            return child.offsetWidth + gap;
        }

        function scroll(dir) {
            track.scrollBy({ left: dir * getScrollAmount(), behavior: 'smooth' });
        }

        function updateNav() {
            if (!prevBtn || !nextBtn) return;
            var atStart = track.scrollLeft <= 2;
            var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
            prevBtn.classList.toggle('hidden', atStart);
            nextBtn.classList.toggle('hidden', atEnd);
        }

        if (prevBtn) prevBtn.addEventListener('click', function() { scroll(-1); resetAuto(); });
        if (nextBtn) nextBtn.addEventListener('click', function() { scroll(1); resetAuto(); });

        track.addEventListener('scroll', updateNav, { passive: true });
        updateNav();

        // Re-check nav on resize
        window.addEventListener('resize', updateNav);

        // Auto-play
        function startAuto() {
            if (!autoDelay) return;
            stopAuto();
            autoTimer = setInterval(function() {
                var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
                if (atEnd) {
                    track.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    scroll(1);
                }
            }, autoDelay);
        }

        function stopAuto() {
            if (autoTimer) { clearInterval(autoTimer); autoTimer = null; }
        }

        function resetAuto() {
            stopAuto();
            startAuto();
        }

        // Pause on hover/touch
        track.addEventListener('mouseenter', stopAuto);
        track.addEventListener('mouseleave', startAuto);
        track.addEventListener('touchstart', stopAuto, { passive: true });
        track.addEventListener('touchend', function() {
            // Restart after a short delay so swipe momentum finishes
            setTimeout(startAuto, 2000);
        }, { passive: true });

        startAuto();
    }

    return { init: init };
})();
