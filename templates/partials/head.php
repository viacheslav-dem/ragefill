<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $title ?></title>
    <?= $metaTags ?? '' ?>
<?php if (!empty($hreflangUrl)): ?>
    <link rel="alternate" hreflang="ru-BY" href="<?= $hreflangUrl ?>">
    <link rel="alternate" hreflang="x-default" href="<?= $hreflangUrl ?>">
<?php endif; ?>
    <link rel="preload" href="/fonts/manrope-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/manrope-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/oswald-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/oswald-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <?= inline_css('css/fonts.css') ?>
    <meta name="theme-color" content="#0a0a0a" id="meta-theme-color">
    <?= critical_css_inline() ?>
    <?= deferred_stylesheet('css/style.css') ?>
<?= $extraCss ?? '' ?>
<?php if ($includeTgScript ?? true): ?>
    <link rel="preconnect" href="https://telegram.org">
    <script src="https://telegram.org/js/telegram-web-app.js" defer></script>
<?php endif; ?>
<?= $extraHead ?? '' ?>
</head>
