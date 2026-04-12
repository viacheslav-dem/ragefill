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
$extraCss = '    <link rel="stylesheet" href="/css/aos.css?v=' . asset_v('css/aos.css') . '">' . "\n";
?>
<!DOCTYPE html>
<html lang="ru">
<?php include __DIR__ . '/partials/head.php'; ?>
<body class="browser-mode peppers-page">
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
                <div class="peppers-table-wrap">
                    <table class="peppers-table">
                        <thead class="peppers-table__head">
                            <tr>
                                <th></th>
                                <th></th>
                                <th>Перец</th>
                                <th>Острота (SHU)</th>
                                <th>Шкала</th>
                                <th>Описание</th>
                                <th class="pepper-row__expand-btn"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?= $peppersHtml ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script src="/js/scroll-top.js?v=<?= asset_v('js/scroll-top.js') ?>" data-cfasync="false"></script>
    <script src="/js/lightbox.js?v=<?= asset_v('js/lightbox.js') ?>" data-cfasync="false"></script>
    <script src="/js/aos.js?v=<?= asset_v('js/aos.js') ?>" data-cfasync="false"></script>
    <script data-cfasync="false">
        (function(){
            var saved=localStorage.getItem('ragefill-theme');
            if(saved==='dark') document.body.classList.add('tg-dark');
            var btn=document.getElementById('theme-toggle');
            if(!btn) return;
            btn.addEventListener('click',function(){
                var isDark=document.body.classList.toggle('tg-dark');
                localStorage.setItem('ragefill-theme',isDark?'dark':'light');
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
        AOS.init({ duration: 600, once: true, offset: 40 });

        // Lightbox on pepper images
        document.querySelectorAll('.pepper-row__img, .pepper-detail__img-wrap .pepper-row__img').forEach(function(img) {
            img.style.cursor = 'zoom-in';
            img.addEventListener('click', function(e) {
                e.stopPropagation();
                Lightbox.open({ images: [img.src], startIndex: 0 });
            });
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
