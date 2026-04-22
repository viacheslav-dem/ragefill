<?php
/**
 * @var string $title
 * @var string $metaTags
 * @var string $extraHead
 * @var string $hreflangUrl
 * @var string $contactTg
 * @var string $pageTitle
 * @var string $pageIntro
 * @var string $peppersHtml
 * @var string $footerTagline
 * @var string $footerAbout
 */
$includeTgScript = false;
$headerClass = 'header--peppers';
$headerSearchId = 'desktop-search-input';
$extraCss = '    ' . inline_css('css/aos.css') . "\n";
?>
<!DOCTYPE html>
<html lang="ru">
<?php include __DIR__ . '/partials/head.php'; ?>
<body class="browser-mode peppers-page">
    <script>if(window.Telegram&&window.Telegram.WebApp&&window.Telegram.WebApp.initData){window.location.replace('/catalog');}</script>
    <?php include __DIR__ . '/partials/header.php'; ?>

    <nav class="catalog-breadcrumb" aria-label="Навигация">
        <a href="/">Главная</a>
        <span class="catalog-breadcrumb__sep" aria-hidden="true">
            <svg width="6" height="10" viewBox="0 0 6 10" fill="none"><path d="M1 1l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span>Наши перцы</span>
    </nav>

    <main>
        <section class="home-section peppers-section">
            <div class="home-container">
                <h1 class="home-section__title" data-aos="fade-up"><?= $pageTitle ?></h1>
<?php if ($pageIntro): ?>
                <p class="peppers-intro" data-aos="fade-up" data-aos-delay="100"><?= $pageIntro ?></p>
<?php endif; ?>
<?php if ($peppersHtml): ?>
                <div class="peppers-table-wrap">
                    <table class="peppers-table">
                        <thead class="peppers-table__head">
                            <tr>
                                <th><span class="visually-hidden">Уровень</span></th>
                                <th><span class="visually-hidden">Фото</span></th>
                                <th>Перец</th>
                                <th>Острота (SHU)</th>
                                <th>Описание</th>
                                <th class="pepper-row__expand-btn"><span class="visually-hidden">Действия</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?= $peppersHtml ?>
                        </tbody>
                    </table>
                </div>
                <div class="peppers-instagram-cta" data-aos="fade-up">
                    <a href="https://instagram.com/rage_fill" class="home-featured__more-link" rel="noopener noreferrer">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        Больше перцев в нашем Instagram
                    </a>
                </div>
<?php else: ?>
                <div class="peppers-empty">
                    <svg class="peppers-empty__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2C9.5 2 7.5 4 7.5 6.5c0 1.5.7 2.8 1.8 3.7L8 22h8l-1.3-11.8c1.1-.9 1.8-2.2 1.8-3.7C16.5 4 14.5 2 12 2z"/></svg>
                    <p class="peppers-empty__text">Информация о перцах скоро появится</p>
                    <a href="/catalog" class="peppers-empty__link">Перейти в каталог соусов</a>
                </div>
<?php endif; ?>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script src="/js/scroll-top.js?v=<?= asset_v('js/scroll-top.js') ?>" defer></script>
    <script src="/js/lightbox.js?v=<?= asset_v('js/lightbox.js') ?>" defer></script>
    <script src="/js/aos.js?v=<?= asset_v('js/aos.js') ?>" defer></script>
    <script>
        (function(){
            var saved=localStorage.getItem('ragefill-theme');
            if(saved==='dark') document.body.classList.add('tg-dark');
            else if(saved==='light') document.body.classList.remove('tg-dark');
            else if(window.matchMedia('(prefers-color-scheme: dark)').matches) document.body.classList.add('tg-dark');
            var meta=document.getElementById('meta-theme-color');
            function syncThemeColor(){ if(meta) meta.content=document.body.classList.contains('tg-dark')?'#161210':'#0a0a0a'; }
            syncThemeColor();
            var btn=document.getElementById('theme-toggle');
            if(!btn) return;
            btn.addEventListener('click',function(){
                var isDark=document.body.classList.toggle('tg-dark');
                localStorage.setItem('ragefill-theme',isDark?'dark':'light');
                syncThemeColor();
            });
        })();
        (function(){
            var btn=document.getElementById('burger-btn'),nav=document.getElementById('main-nav');
            if(!btn||!nav)return;
            btn.addEventListener('click',function(){
                var open=nav.classList.toggle('open');
                btn.classList.toggle('open',open);
                btn.setAttribute('aria-expanded',String(open));
                document.body.classList.toggle('menu-open',open);
            });
        })();
        (function(){
            var input=document.getElementById('desktop-search-input');
            if(!input)return;
            input.addEventListener('keydown',function(e){
                if(e.key==='Enter'&&input.value.trim()) window.location.href='/catalog?q='+encodeURIComponent(input.value.trim());
            });
        })();
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof AOS !== 'undefined') AOS.init({ duration: 600, once: true, offset: 40 });
        });

        // BFCache fix — force all data-aos elements visible on back-navigation
        // so none stay stuck at opacity:0 if the page is restored from cache.
        window.addEventListener('pageshow', function(e) {
            if (!e.persisted) return;
            document.querySelectorAll('[data-aos]').forEach(function(el) {
                el.classList.add('aos-animate');
            });
        });

        // Lightbox on pepper images
        document.querySelectorAll('.pepper-row__img, .pepper-detail__img-wrap .pepper-row__img').forEach(function(img) {
            img.style.cursor = 'zoom-in';
            img.setAttribute('tabindex', '0');
            img.setAttribute('role', 'button');
            function openLb(e) { e.stopPropagation(); Lightbox.open({ images: [img.src], startIndex: 0 }); }
            img.addEventListener('click', openLb);
            img.addEventListener('keydown', function(e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openLb(e); } });
        });

        // Expand/collapse pepper detail rows
        document.querySelectorAll('.pepper-expand').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var row = btn.closest('.pepper-row');
                var detail = row.nextElementSibling;
                if (!detail || !detail.classList.contains('pepper-row__detail')) return;
                var open = detail.classList.toggle('open');
                row.classList.toggle('pepper-row--expanded', open);
                detail.setAttribute('aria-hidden', String(!open));
                btn.setAttribute('aria-expanded', String(open));
            });
        });

        // On mobile: clicking the row itself expands
        document.querySelectorAll('.pepper-row[id]').forEach(function(row) {
            row.addEventListener('click', function(e) {
                if (window.innerWidth >= 960) return;
                if (e.target.closest('a, button')) return;
                var btn = row.querySelector('.pepper-expand');
                if (btn) btn.click();
            });
        });
    </script>
</body>
</html>
