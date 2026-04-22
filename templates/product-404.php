<?php
/** @var string $title */
$metaTags = '<meta name="robots" content="noindex">';
?>
<!DOCTYPE html>
<html lang="ru">
<?php include __DIR__ . '/partials/head.php'; ?>
<body>
    <?php include __DIR__ . '/partials/header.php'; ?>
    <main style="display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 80px);padding:40px 20px;text-align:center;">
        <div>
            <div style="font-size:80px;line-height:1;margin-bottom:16px;opacity:0.25;">
                <img src="/uploads/pepper.svg" alt="" width="72" height="72" style="opacity:0.5;">
            </div>
            <h1 style="font-family:var(--font-display);font-size:clamp(48px,10vw,96px);font-weight:700;letter-spacing:4px;color:var(--text-primary);margin:0 0 8px;line-height:1.1;">404</h1>
            <p style="font-size:18px;color:var(--text-secondary);margin:0 0 6px;font-weight:600;">Страница не найдена</p>
            <p style="font-size:14px;color:var(--text-muted);margin:0 0 32px;">Возможно, она была удалена или перемещена</p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <a href="/" style="display:inline-flex;align-items:center;gap:6px;padding:12px 28px;background:var(--fire-orange);color:#fff;text-decoration:none;border-radius:var(--radius-pill);font-size:15px;font-weight:700;transition:background 0.2s,transform 0.2s;" onmouseover="this.style.background='#d04a08'" onmouseout="this.style.background='var(--fire-orange)'">На главную</a>
                <a href="/catalog" style="display:inline-flex;align-items:center;gap:6px;padding:12px 28px;border:2px solid var(--fire-orange);color:var(--fire-orange);text-decoration:none;border-radius:var(--radius-pill);font-size:15px;font-weight:700;transition:background 0.2s,color 0.2s;" onmouseover="this.style.background='var(--fire-orange)';this.style.color='#fff'" onmouseout="this.style.background='transparent';this.style.color='var(--fire-orange)'">Каталог</a>
            </div>
        </div>
    </main>
    <script>
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
    </script>
</body>
</html>
