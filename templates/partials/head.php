<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= $title ?></title>
    <?= $metaTags ?? '' ?>
<?php if (!empty($hreflangUrl)): ?>
    <link rel="alternate" hreflang="ru-BY" href="<?= $hreflangUrl ?>">
<?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="/css/fonts.css?v=<?= asset_v('css/fonts.css') ?>">
    <meta name="theme-color" content="#0a0a0a">
    <link rel="stylesheet" href="/css/style.css?v=<?= asset_v('css/style.css') ?>">
<?= $extraCss ?? '' ?>
<?php if ($includeTgScript ?? true): ?>
    <script src="https://telegram.org/js/telegram-web-app.js" data-cfasync="false"></script>
<?php endif; ?>
<?= $extraHead ?? '' ?>
</head>
