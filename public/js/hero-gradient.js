// ============================================
// RAGEFILL Hero Mesh Gradient v1.0
// Organic drifting color blobs on canvas
// ============================================

(function() {
    'use strict';

    var hero = document.querySelector('.home-hero');
    if (!hero) return;

    var canvas = document.createElement('canvas');
    canvas.className = 'hero-gradient-canvas';
    hero.insertBefore(canvas, hero.firstChild);

    var ctx = canvas.getContext('2d');
    var width, height, dpr;
    var animId;
    var time = 0;
    var paused = false;
    var finished = false;
    // Seconds of animated "bloom" before we freeze the canvas. The gradient
    // drifts slowly enough that a frozen frame is visually indistinguishable,
    // and freezing keeps the main thread idle after the initial paint.
    var ANIMATION_DURATION = 4;

    // Color palettes — light & dark
    var palettes = {
        light: [
            { r: 245, g: 215, b: 195, a: 0.60 }, // warm cream
            { r: 240, g: 200, b: 175, a: 0.45 }, // soft sand
            { r: 250, g: 230, b: 218, a: 0.55 }, // pale blush
            { r: 235, g: 180, b: 150, a: 0.20 }, // muted peach
            { r: 248, g: 235, b: 225, a: 0.50 }, // ivory
            { r: 242, g: 210, b: 190, a: 0.35 }, // linen
        ],
        dark: [
            { r: 140, g:  35, b:   0, a: 0.45 }, // deep ember
            { r: 160, g:  45, b:   8, a: 0.30 }, // dark flame
            { r:  90, g:  22, b:   5, a: 0.40 }, // burnt coal
            { r: 180, g:  70, b:  15, a: 0.15 }, // amber glow
            { r:  50, g:  16, b:   8, a: 0.50 }, // charcoal
            { r: 110, g:  28, b:   0, a: 0.25 }, // smolder
        ]
    };

    var baseBg = {
        light: { r: 250, g: 245, b: 240 },
        dark:  { r:  22, g:  18, b:  15 }
    };

    // Blob definitions — each has its own orbit, speed, size
    var blobs = [];

    function isDark() {
        return document.body.classList.contains('tg-dark');
    }

    function getPalette() {
        return isDark() ? palettes.dark : palettes.light;
    }

    function getBg() {
        return isDark() ? baseBg.dark : baseBg.light;
    }

    function initBlobs() {
        var palette = getPalette();
        blobs = [];
        for (var i = 0; i < palette.length; i++) {
            blobs.push({
                color: palette[i],
                // Orbit center (relative 0-1)
                cx: 0.15 + Math.random() * 0.7,
                cy: 0.15 + Math.random() * 0.7,
                // Orbit radii (relative)
                rx: 0.08 + Math.random() * 0.25,
                ry: 0.08 + Math.random() * 0.2,
                // Phase offsets
                px: Math.random() * Math.PI * 2,
                py: Math.random() * Math.PI * 2,
                // Speed multipliers
                sx: 0.15 + Math.random() * 0.12,
                sy: 0.12 + Math.random() * 0.1,
                // Blob size (relative to canvas diagonal)
                size: 0.35 + Math.random() * 0.35,
                // Slight pulse
                pulsePhase: Math.random() * Math.PI * 2,
                pulseSpeed: 0.07 + Math.random() * 0.06,
                pulseAmp: 0.05 + Math.random() * 0.06,
            });
        }
    }

    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        var rect = hero.getBoundingClientRect();
        width = rect.width;
        height = rect.height;
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        canvas.style.width = width + 'px';
        canvas.style.height = height + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function draw() {
        var bg = getBg();
        ctx.fillStyle = 'rgb(' + bg.r + ',' + bg.g + ',' + bg.b + ')';
        ctx.fillRect(0, 0, width, height);

        var palette = getPalette();
        var diag = Math.sqrt(width * width + height * height);

        for (var i = 0; i < blobs.length; i++) {
            var b = blobs[i];
            var c = palette[i] || b.color;

            // Position: orbit path
            var x = (b.cx + Math.sin(time * b.sx + b.px) * b.rx) * width;
            var y = (b.cy + Math.cos(time * b.sy + b.py) * b.ry) * height;

            // Pulsing radius
            var pulse = 1 + Math.sin(time * b.pulseSpeed + b.pulsePhase) * b.pulseAmp;
            var r = b.size * diag * 0.5 * pulse;

            var grad = ctx.createRadialGradient(x, y, 0, x, y, r);
            grad.addColorStop(0, 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',' + c.a + ')');
            grad.addColorStop(0.3, 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',' + (c.a * 0.7) + ')');
            grad.addColorStop(0.6, 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',' + (c.a * 0.35) + ')');
            grad.addColorStop(1, 'rgba(' + c.r + ',' + c.g + ',' + c.b + ',0)');

            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, width, height);
        }
    }

    function loop() {
        if (paused || finished) {
            animId = null;
            return;
        }
        time += 0.016;
        draw();
        if (time >= ANIMATION_DURATION) {
            finished = true;
            animId = null;
            return;
        }
        animId = requestAnimationFrame(loop);
    }

    // Pause when not visible. Guard on animId so the initial IntersectionObserver
    // callback (fires right after .observe()) doesn't start a second concurrent
    // rAF chain on top of the one init() already kicked off.
    var observer = new IntersectionObserver(function(entries) {
        paused = !entries[0].isIntersecting;
        if (!paused && !finished && animId === null) {
            animId = requestAnimationFrame(loop);
        }
    }, { threshold: 0.05 });

    // Theme change: reinit blob colors. While the loop is running, the next
    // draw() picks up the new palette automatically. After freeze (or in
    // static mode), we need one explicit redraw so the canvas reflects the
    // new theme.
    var lastDark = isDark();
    var themeObserver = new MutationObserver(function() {
        var nowDark = isDark();
        if (nowDark !== lastDark) {
            lastDark = nowDark;
            if (finished) draw();
        }
    });

    function init() {
        resize();
        initBlobs();
        observer.observe(hero);
        themeObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        loop();
    }

    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            resize();
            // Changing canvas dimensions wipes its contents. If the animation
            // loop has already frozen (or we're in static mode), nothing else
            // will redraw — do it explicitly.
            if (finished) draw();
        }, 100);
    });

    // Skip the animated loop entirely on mobile/narrow viewports and when the
    // user prefers reduced motion. On those devices the drifting gradient is
    // barely perceptible but costs tens of ms per frame on the main thread,
    // which dominates TBT on PageSpeed mobile audits. One static frame is
    // indistinguishable visually and takes near-zero CPU.
    var staticOnly = !window.matchMedia('(min-width: 768px)').matches
        || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (staticOnly) {
        resize();
        initBlobs();
        time = 1; // offset so blobs aren't clustered at origin
        draw();
        finished = true;
        themeObserver.observe(document.body, { attributes: true, attributeFilter: ['class'] });
        return;
    }

    init();
})();
