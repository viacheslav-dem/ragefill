<?php
/**
 * @var string $title
 * @var string $metaTags
 * @var string $hreflangUrl — canonical URL for hreflang
 * @var string $jsonLd
 * @var string $breadcrumbLd
 * @var string $name
 * @var string $subtitleHtml
 * @var string $categoryLabel
 * @var string $image — gallery main image HTML
 * @var string $thumbsHtml
 * @var string $stockClass — 'in' | 'out'
 * @var string $stockText
 * @var string $volumeHtml
 * @var int    $heat
 * @var string $heatLabel
 * @var string $heatBar
 * @var string $descSection
 * @var string $compSection
 * @var string $contactTg
 * @var string $ctaText
 * @var string $ctaIcon
 * @var string $relatedHtml
 */
$extraHead = $jsonLd . "\n" . $breadcrumbLd;
?>
<!DOCTYPE html>
<html lang="ru">
<?php include __DIR__ . '/partials/head.php'; ?>
<body>
    <header class="header header--catalog">
        <div class="header__inner">
            <a href="/" class="header__logo-link" aria-label="На главную"><div class="header__logo" aria-hidden="true"><span class="header__logo-rage">RAGE</span> <span class="header__logo-fill">FILL</span></div></a>
            <nav class="header__nav browser-only-link" id="main-nav">
                <a href="/" class="header__nav-link">Главная</a>
                <a href="/catalog" class="header__nav-link">Каталог</a>
                <a href="/#faq" class="header__nav-link">Частые вопросы</a>
            </nav>
            <div class="header__actions">
                <button class="theme-toggle" id="theme-toggle" aria-label="Переключить тему">
                    <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                    <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                </button>
                <button class="burger-btn browser-only-link" id="burger-btn" aria-label="Меню" aria-expanded="false">
                    <span class="burger-btn__line"></span>
                    <span class="burger-btn__line"></span>
                    <span class="burger-btn__line"></span>
                </button>
            </div>
        </div>
    </header>

    <main>
    <article class="product-page">
        <nav class="product-page__breadcrumb browser-only-link" aria-label="Навигация">
            <a href="/">Главная</a>
            <span class="product-page__breadcrumb-sep" aria-hidden="true">
                <svg width="6" height="10" viewBox="0 0 6 10" fill="none"><path d="M1 1l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <a href="/catalog">Каталог</a>
            <span class="product-page__breadcrumb-sep" aria-hidden="true">
                <svg width="6" height="10" viewBox="0 0 6 10" fill="none"><path d="M1 1l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span><?= $name ?></span>
        </nav>

        <div class="product-page__main">
            <div class="product-page__gallery">
                <div class="product-page__hero">
                    <?= $image ?>
                </div>
                <?= $thumbsHtml ?>
            </div>

            <div class="product-page__details">
                <span class="product-page__category"><?= $categoryLabel ?></span>
                <h1 class="product-page__name"><?= $name ?></h1>
                <?= $subtitleHtml ?>

                <div class="product-page__meta">
                    <div class="product-page__stock product-page__stock--<?= $stockClass ?>">
                        <span class="product-page__stock-dot"></span>
                        <?= $stockText ?>
                    </div>
                    <?= $volumeHtml ?>
                </div>

                <div class="product-page__heat-block">
                    <div class="product-page__heat-header">
                        <span class="product-page__heat-title">Острота</span>
                        <span class="product-page__heat-value"><?= $heat ?>/5 — <?= $heatLabel ?></span>
                    </div>
                    <div class="heat-bar" aria-label="Уровень остроты <?= $heat ?> из 5">
                        <?= $heatBar ?>
                    </div>
                </div>

                <div class="product-page__divider"></div>

                <?= $descSection ?>

                <?= $compSection ?>

                <div class="product-page__actions">
                    <a href="https://t.me/<?= $contactTg ?>" class="product-page__cta" target="_blank" rel="noopener">
                        <?= $ctaIcon ?>
                        <span><?= $ctaText ?></span>
                    </a>
                    <a href="/catalog" class="product-page__back">&larr; Вернуться в каталог</a>
                </div>
            </div>
        </div>
    </article>

    <?= $relatedHtml ?>

    </main>

    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script src="/js/scroll-top.js?v=<?= asset_v('js/scroll-top.js') ?>" data-cfasync="false"></script>
    <script src="/js/lightbox.js?v=<?= asset_v('js/lightbox.js') ?>" data-cfasync="false"></script>
    <script data-cfasync="false">
        const _tgRaw = window.Telegram?.WebApp;
        const tg = (_tgRaw && _tgRaw.initData) ? _tgRaw : null;
        if (tg) {
            tg.ready();
            tg.expand();
            document.body.classList.add('tg-theme', 'tg-mode');
            if (tg.colorScheme === 'dark') document.body.classList.add('tg-dark');
            tg.BackButton.show();
            tg.BackButton.onClick(() => { window.location.href = '/catalog'; });
        } else {
            document.body.classList.add('browser-mode');
        }

        // Theme toggle
        (function() {
            const saved = localStorage.getItem('ragefill-theme');
            if (saved === 'dark') document.body.classList.add('tg-dark');
            else if (saved === 'light') document.body.classList.remove('tg-dark');
            const btn = document.getElementById('theme-toggle');
            if (!btn) return;
            btn.addEventListener('click', () => {
                const isDark = document.body.classList.toggle('tg-dark');
                localStorage.setItem('ragefill-theme', isDark ? 'dark' : 'light');
            });
        })();

        // Burger menu
        (function(){
            var btn=document.getElementById('burger-btn'),nav=document.getElementById('main-nav');
            if(!btn||!nav)return;
            btn.addEventListener('click',function(){
                var open=nav.classList.toggle('open');
                btn.classList.toggle('open',open);
                btn.setAttribute('aria-expanded',String(open));
                document.body.classList.toggle('menu-open',open);
            });
            nav.querySelectorAll('a').forEach(function(a){
                a.addEventListener('click',function(){
                    nav.classList.remove('open');btn.classList.remove('open');
                    btn.setAttribute('aria-expanded','false');
                    document.body.classList.remove('menu-open');
                });
            });
        })();

        // Sticky gallery offset (match header height)
        (function() {
            var header = document.querySelector('.header');
            var gallery = document.querySelector('.product-page__gallery');
            if (!header || !gallery) return;
            function update() { gallery.style.top = (header.offsetHeight + 16) + 'px'; }
            update();
            window.addEventListener('resize', update);
        })();

        // Gallery thumbnails + Lightbox
        (function() {
            var thumbs = document.querySelectorAll('.product-page__thumb');
            var main = document.getElementById('gallery-main-img');
            var currentIdx = 0;
            if (!main) return;

            var gallery = [];
            try { gallery = JSON.parse(main.dataset.gallery || '[]'); } catch(e) {}

            // Thumbnail clicks
            thumbs.forEach(function(t) {
                t.addEventListener('click', function() {
                    currentIdx = parseInt(t.dataset.index) || 0;
                    main.style.opacity = '0';
                    setTimeout(function() { main.src = t.dataset.src; main.style.opacity = '1'; }, 150);
                    thumbs.forEach(function(x) { x.classList.remove('active'); });
                    t.classList.add('active');
                });
            });

            if (gallery.length === 0) return;

            function syncThumb(i) {
                currentIdx = i;
                main.src = gallery[i];
                thumbs.forEach(function(x) { x.classList.remove('active'); });
                if (thumbs[i]) thumbs[i].classList.add('active');
            }

            // Open lightbox on main image click
            main.addEventListener('click', function() {
                Lightbox.open({ images: gallery, startIndex: currentIdx, onNavigate: syncThumb });
            });
            main.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    Lightbox.open({ images: gallery, startIndex: currentIdx, onNavigate: syncThumb });
                }
            });
        })();
    </script>
</body>
</html>
