<?php
/** @var string $title */
$metaTags = '<meta name="robots" content="noindex">';
?>
<!DOCTYPE html>
<html lang="ru">
<?php include __DIR__ . '/partials/head.php'; ?>
<body>
    <header class="header">
        <div class="header__inner">
            <a href="/" class="header__logo-link" aria-label="На главную">
                <div class="header__logo" aria-hidden="true"><span class="header__logo-rage">RAGE</span> <span class="header__logo-fill">FILL</span></div>
            </a>
            <div class="header__actions">
                <button class="theme-toggle" id="theme-toggle" aria-label="Переключить тему">
                    <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                    <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                </button>
            </div>
        </div>
    </header>
    <main>
        <div class="empty-state" style="padding-top: 80px;">
            <div class="empty-state__icon"><img src="/uploads/pepper.svg" alt="" width="48" height="48"></div>
            <div class="empty-state__text">Товар не найден</div>
            <div class="empty-state__hint">Возможно, он был удалён или скрыт</div>
            <a href="/catalog" class="empty-state__btn">Вернуться в каталог</a>
        </div>
    </main>
    <script data-cfasync="false">
        var tg = window.Telegram && window.Telegram.WebApp;
        if (tg && tg.initData) {
            tg.ready(); tg.expand();
            document.body.classList.add('tg-theme','tg-mode');
            if (tg.colorScheme==='dark') document.body.classList.add('tg-dark');
            tg.BackButton.show();
            tg.BackButton.onClick(function(){ window.location.href='/'; });
        } else {
            document.body.classList.add('browser-mode');
        }
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
    </script>
</body>
</html>
