<?php
/**
 * Shared site header.
 *
 * Optional variables (set before include):
 *   $headerClass       — modifier class, e.g. 'header--catalog' (default: 'header--home')
 *   $headerBrowserOnly — if true, add 'browser-only-link' to nav/burger (for TG-capable pages)
 *   $headerSearchId    — search input id, e.g. 'desktop-search-input'; falsy = no search
 *   $headerTgSubtitle  — subtitle shown below logo in TG mode, e.g. 'КАТАЛОГ СВЕРХОСТРЫХ СОУСОВ'
 */
$_hClass       = $headerClass ?? 'header--home';
$_hBrowser     = !empty($headerBrowserOnly) ? ' browser-only-link' : '';
$_hSearchId    = $headerSearchId ?? null;
$_hTgSubtitle  = $headerTgSubtitle ?? null;
?>
    <header class="header <?= $_hClass ?>">
        <div class="header__inner">
            <a href="/" class="header__logo-link" aria-label="На главную"><div class="header__logo" aria-hidden="true"><span class="header__logo-rage">RAGE</span> <span class="header__logo-fill">FILL</span></div></a>
<?php if ($_hTgSubtitle): ?>
            <div class="header__tg-subtitle"><?= $_hTgSubtitle ?></div>
<?php endif; ?>
            <nav class="header__nav<?= $_hBrowser ?>" id="main-nav">
                <a href="/" class="header__nav-link header__nav-link--home">Главная</a>
                <a href="/catalog" class="header__nav-link header__nav-link--catalog">Каталог</a>
                <a href="/#benefits" class="header__nav-link">О нас</a>
                <a href="/peppers" class="header__nav-link">Наши перцы</a>
                <a href="https://t.me/<?= $contactTg ?>" class="header__nav-link header__nav-link--cta" target="_blank" rel="noopener">Написать нам</a>
            </nav>
<?php if ($_hSearchId): ?>
            <div class="header__desktop-search">
                <svg class="header__search-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/>
                    <path d="m17 17 4 4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <label for="<?= $_hSearchId ?>" class="visually-hidden">Поиск по каталогу</label>
                <input type="text" class="header__search-input" id="<?= $_hSearchId ?>" placeholder="Поиск по каталогу..." autocomplete="off">
            </div>
<?php endif; ?>
            <div class="header__actions">
                <button class="theme-toggle" id="theme-toggle" aria-label="Переключить тему">
                    <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                    <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                </button>
                <button class="burger-btn<?= $_hBrowser ?>" id="burger-btn" aria-label="Меню" aria-expanded="false">
                    <span class="burger-btn__line"></span>
                    <span class="burger-btn__line"></span>
                    <span class="burger-btn__line"></span>
                </button>
            </div>
        </div>
    </header>
