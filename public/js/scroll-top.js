// ============================================
// RAGEFILL ScrollTop v1.0
// Shows a "scroll to top" button after scrolling down.
// Self-initializing — just include the script.
// ============================================

(function() {
    'use strict';

    var btn = document.createElement('button');
    btn.className = 'scroll-top';
    btn.setAttribute('aria-label', 'Наверх');
    btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6"/></svg>';
    document.body.appendChild(btn);

    var visible = false;
    var threshold = 400;

    function check() {
        var show = window.scrollY > threshold;
        if (show === visible) return;
        visible = show;
        btn.classList.toggle('visible', show);
    }

    window.addEventListener('scroll', check, { passive: true });
    check();

    btn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
})();
