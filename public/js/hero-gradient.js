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
                sx: 0.08 + Math.random() * 0.1,
                sy: 0.06 + Math.random() * 0.08,
                // Blob size (relative to canvas diagonal)
                size: 0.35 + Math.random() * 0.35,
                // Slight pulse
                pulsePhase: Math.random() * Math.PI * 2,
                pulseSpeed: 0.04 + Math.random() * 0.06,
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
        if (paused) return;
        time += 0.009;
        draw();
        animId = requestAnimationFrame(loop);
    }

    // Pause when not visible
    var observer = new IntersectionObserver(function(entries) {
        paused = !entries[0].isIntersecting;
        if (!paused) loop();
    }, { threshold: 0.05 });

    // Theme change: reinit blob colors
    var lastDark = isDark();
    var themeObserver = new MutationObserver(function() {
        var nowDark = isDark();
        if (nowDark !== lastDark) {
            lastDark = nowDark;
            // Smooth: just let the palette reference update, blobs keep orbiting
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
        resizeTimer = setTimeout(resize, 100);
    });

    // Respect reduced motion
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        // Draw once, no animation
        resize();
        initBlobs();
        time = 1; // offset so it's not at origin
        draw();
        return;
    }

    init();
})();
