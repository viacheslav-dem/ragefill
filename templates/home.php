<?php
/**
 * @var string $title
 * @var string $metaTags
 * @var string $hreflangUrl
 * @var string $faqJsonLd
 * @var string $orgJsonLd
 * @var string $websiteJsonLd
 * @var string $contactTg
 * @var string $featuredHtml
 * @var string $featuredJson
 * @var string $benefitsHtml
 * @var string $faqHtml
 * @var string $reviewsHtml
 * @var string $reviewSrcsJson
 * @var string $heroTagline
 * @var string $heroDesc
 * @var string $heroBtnPrimary
 * @var string $heroBtnSecondary
 * @var string $aboutText
 * @var string $testimonialsHtml
 * @var string $instagramReviewsUrl
 */
$extraCss = '    <link rel="stylesheet" href="/css/aos.css?v=' . asset_v('css/aos.css') . '">' . "\n";
$extraHead = $faqJsonLd . "\n" . $orgJsonLd . "\n" . $websiteJsonLd;
$includeTgScript = false;
?>
<!DOCTYPE html>
<html lang="ru">
<?php include __DIR__ . '/partials/head.php'; ?>
<body class="browser-mode home-page">

    <header class="header header--home">
        <div class="header__inner">
            <a href="/" class="header__logo-link" aria-label="На главную"><div class="header__logo" aria-hidden="true"><span class="header__logo-rage">RAGE</span> <span class="header__logo-fill">FILL</span></div></a>
            <nav class="header__nav" id="main-nav">
                <a href="/catalog" class="header__nav-link">Каталог</a>
                <a href="#benefits" class="header__nav-link">О нас</a>
                <a href="#faq" class="header__nav-link">Частые вопросы</a>
                <a href="https://t.me/<?= $contactTg ?>" class="header__nav-link header__nav-link--cta" target="_blank" rel="noopener">Написать нам</a>
            </nav>
            <div class="header__desktop-search">
                <svg class="header__search-icon" viewBox="0 0 24 24" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/>
                    <path d="m17 17 4 4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <label for="home-search-input" class="visually-hidden">Поиск по каталогу</label>
                <input type="text" class="header__search-input" id="home-search-input" placeholder="Поиск по каталогу..." autocomplete="off">
            </div>
            <div class="header__actions">
                <button class="theme-toggle" id="theme-toggle" aria-label="Переключить тему">
                    <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                    <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                </button>
                <button class="burger-btn" id="burger-btn" aria-label="Меню" aria-expanded="false">
                    <span class="burger-btn__line"></span>
                    <span class="burger-btn__line"></span>
                    <span class="burger-btn__line"></span>
                </button>
            </div>
        </div>
    </header>

    <main>

    <!-- Hero -->
    <section class="home-hero">
        <div class="hero-liquid hero-liquid--left" aria-hidden="true"></div>
        <div class="hero-liquid hero-liquid--right" aria-hidden="true"></div>
        <div class="home-hero__inner">
            <h1 class="home-hero__title" data-aos="fade-up">
                <span class="home-hero__title-rage">RAGE</span><span class="home-hero__title-fill">FILL</span>
            </h1>
            <p class="home-hero__tagline" data-aos="fade-up" data-aos-delay="100"><?= $heroTagline ?></p>
            <p class="home-hero__desc" data-aos="fade-up" data-aos-delay="200"><?= $heroDesc ?></p>
            <div class="home-hero__buttons" data-aos="fade-up" data-aos-delay="300">
                <a href="/catalog" class="home-hero__btn home-hero__btn--primary"><?= $heroBtnPrimary ?></a>
                <a href="https://t.me/<?= $contactTg ?>" class="home-hero__btn home-hero__btn--secondary" target="_blank" rel="noopener"><?= $heroBtnSecondary ?></a>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section class="home-section home-featured-section">
        <div class="home-container">
            <h2 class="home-section__title" data-aos="fade-up"><?= $featuredTitle ?></h2>
            <div class="home-featured">
                <?= $featuredHtml ?>
            </div>
            <div class="home-featured__more" data-aos="fade-up">
                <a href="/catalog" class="home-featured__more-link">Смотреть весь каталог &rarr;</a>
            </div>
        </div>
    </section>

    <!-- Benefits -->
    <section class="home-section home-benefits-section" id="benefits">
        <div class="home-container">
            <h2 class="home-section__title" data-aos="fade-up"><?= $sectionTitleBenefits ?></h2>
            <div class="benefits-grid">
                <?= $benefitsHtml ?>
            </div>
        </div>
    </section>

    <!-- Reviews -->
    <section class="home-section home-reviews-section">
        <div class="home-container">
            <h2 class="home-section__title" data-aos="fade-up"><?= $sectionTitleReviews ?></h2>
            <div class="home-reviews-slider" data-aos="fade-up">
                <button class="home-reviews-slider__nav home-reviews-slider__nav--prev" id="reviews-prev" aria-label="Назад">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                <div class="home-reviews" id="home-reviews" data-gallery="<?= $reviewSrcsJson ?>">
                    <?= $reviewsHtml ?>
                </div>
                <button class="home-reviews-slider__nav home-reviews-slider__nav--next" id="reviews-next" aria-label="Вперёд">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>
            <div class="home-testimonials" data-aos="fade-up">
                <?= $testimonialsHtml ?>
            </div>
            <div class="home-reviews__cta" data-aos="fade-up">
                <a href="<?= htmlspecialchars($instagramReviewsUrl, ENT_QUOTES, 'UTF-8') ?>" class="instagram-link" target="_blank" rel="noopener noreferrer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    Ещё отзывы в Instagram
                </a>
            </div>
        </div>
    </section>

    <!-- About -->
    <section class="home-section home-about-section">
        <div class="home-container">
            <div class="home-about" data-aos="fade-up">
                <?= $aboutText ?>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="home-section home-faq-section" id="faq">
        <div class="home-container">
            <h2 class="home-section__title" data-aos="fade-up"><?= $sectionTitleFaq ?></h2>
            <div class="faq__list">
                <?= $faqHtml ?>
            </div>
        </div>
    </section>

    </main>

    <!-- Product modal (for featured cards on mobile) -->
    <div id="home-modal-overlay" class="modal-overlay">
        <div class="modal" id="home-modal" role="dialog" aria-modal="true">
            <div class="modal__top-bar">
                <div class="modal__handle"></div>
                <button class="modal__close" id="home-modal-close" aria-label="Закрыть">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="modal__layout">
                <div id="home-modal-image" class="modal__image-wrapper"></div>
                <div class="modal__content">
                    <h2 id="home-modal-name" class="modal__name"></h2>
                    <div id="home-modal-subtitle" class="modal__subtitle"></div>
                    <div id="home-modal-stock" class="modal__stock-badge modal__stock-badge--in">В наличии</div>
                    <div id="home-modal-peppers" class="modal__peppers">
                        <span class="modal__pepper-icons"></span>
                        <span class="modal__pepper-label"></span>
                    </div>
                    <div id="home-modal-description" class="modal__description collapsed"></div>
                    <button id="home-modal-read-more" class="modal__read-more" style="display:none">Читать далее</button>
                    <div id="home-modal-composition" class="modal__info-block" style="display:none">
                        <div class="modal__info-label">Состав</div>
                        <div id="home-modal-composition-value" class="modal__info-value"></div>
                    </div>
                    <div id="home-modal-volume" style="display:none">
                        <span id="home-modal-volume-value" class="modal__volume-pill"></span>
                    </div>
                    <button class="modal__contact-btn" id="home-modal-contact">Написать продавцу</button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/partials/footer.php'; ?>

    <script src="/js/scroll-top.js?v=<?= asset_v('js/scroll-top.js') ?>" data-cfasync="false"></script>
    <script src="/js/lightbox.js?v=<?= asset_v('js/lightbox.js') ?>" data-cfasync="false"></script>
    <script src="/js/slider.js?v=<?= asset_v('js/slider.js') ?>" data-cfasync="false"></script>
    <script src="/js/aos.js?v=<?= asset_v('js/aos.js') ?>" data-cfasync="false"></script>
    <script>window.__HOME_DATA__={sauces:<?= $featuredJson ?>,contactTg:'<?= $contactTg ?>'};</script>
    <script src="/js/home.js?v=<?= asset_v('js/home.js') ?>" data-cfasync="false"></script>
    <script src="/js/hero-gradient.js?v=<?= asset_v('js/hero-gradient.js') ?>" data-cfasync="false"></script>
</body>
</html>
