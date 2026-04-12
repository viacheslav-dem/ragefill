<?php
/**
 * @var string $title
 * @var string $metaTags
 * @var string $contactTg
 */
$includeTgScript = false;
?>
<!DOCTYPE html>
<html lang="ru">
<?php include __DIR__ . '/partials/head.php'; ?>
<body class="browser-mode">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <nav class="catalog-breadcrumb" aria-label="Навигация">
        <a href="/">Главная</a>
        <span class="catalog-breadcrumb__sep" aria-hidden="true">
            <svg width="6" height="10" viewBox="0 0 6 10" fill="none"><path d="M1 1l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span>Политика конфиденциальности</span>
    </nav>

    <main style="min-height: calc(100vh - 200px);">
        <article class="privacy-page" style="max-width: 720px; margin: 0 auto; padding: 32px 20px 60px;">
            <h1 style="font-family: var(--font-display); font-size: 2rem; margin-bottom: 24px;">Политика конфиденциальности</h1>

            <p>Настоящая политика конфиденциальности описывает, как RAGE FILL обрабатывает информацию при использовании нашего сайта и Telegram-бота.</p>

            <h2 style="font-family: var(--font-display); font-size: 1.4rem; margin: 24px 0 12px;">Какие данные мы собираем</h2>
            <ul style="padding-left: 20px; margin-bottom: 16px;">
                <li>Имя пользователя Telegram при оформлении заказа через бота</li>
                <li>Адрес доставки, указанный вами при заказе</li>
                <li>Техническая информация (IP-адрес, тип браузера) при посещении сайта</li>
            </ul>

            <h2 style="font-family: var(--font-display); font-size: 1.4rem; margin: 24px 0 12px;">Как мы используем данные</h2>
            <p>Данные используются исключительно для обработки и доставки заказов, а также для связи с вами по вопросам заказа. Мы не передаём ваши данные третьим лицам и не используем их в рекламных целях.</p>

            <h2 style="font-family: var(--font-display); font-size: 1.4rem; margin: 24px 0 12px;">Контакты</h2>
            <p>По вопросам конфиденциальности обращайтесь в Telegram: <a href="https://t.me/<?= $contactTg ?>">@<?= $contactTg ?></a></p>
        </article>
    </main>

    <?php include __DIR__ . '/partials/footer.php'; ?>

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
    </script>
</body>
</html>
