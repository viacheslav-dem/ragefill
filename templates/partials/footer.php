<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__brand">
            <a href="/" class="site-footer__logo"><span class="site-footer__logo-rage">RAGE</span> <span class="site-footer__logo-fill">FILL</span></a>
            <div class="site-footer__text"><?= $footerTagline ?? 'Острые соусы ручной работы, Беларусь' ?></div>
        </div>
        <div class="site-footer__contact">
            <h4 class="site-footer__heading">Контакты</h4>
            <nav class="site-footer__links" aria-label="Контакты">
                <a href="https://t.me/<?= $contactTg ?>" target="_blank" rel="noopener noreferrer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.904-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.492-1.302.484-.429-.008-1.252-.242-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.141a.506.506 0 01.171.325c.016.093.036.306.02.472z"/></svg>
                    Telegram
                </a>
                <a href="https://instagram.com/rage_fill" target="_blank" rel="noopener noreferrer">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    Instagram
                </a>
            </nav>
        </div>
        <div class="site-footer__nav">
            <h4 class="site-footer__heading">Навигация</h4>
            <nav class="site-footer__links" aria-label="Навигация">
                <a href="/">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                    Главная
                </a>
                <a href="/catalog">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
                    Каталог
                </a>
                <a href="/peppers">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C9.5 2 7.5 4 7.5 6.5c0 1.5.7 2.8 1.8 3.7L8 22h8l-1.3-11.8c1.1-.9 1.8-2.2 1.8-3.7C16.5 4 14.5 2 12 2z"/></svg>
                    Наши перцы
                </a>
            </nav>
        </div>
        <div class="site-footer__about">
            <h4 class="site-footer__heading">О продукте</h4>
            <p class="site-footer__about-text"><?= $footerAbout ?? 'Все соусы изготавливаются вручную из свежих перцев. Для заказа свяжитесь с нами через Telegram.' ?></p>
        </div>
    </div>
    <div class="site-footer__bottom">
        <div class="site-footer__copy">&copy; <?= date('Y') ?> RAGE FILL. Все права защищены.</div>
        <a href="/privacy" class="site-footer__privacy-link">Политика конфиденциальности</a>
    </div>
</footer>
