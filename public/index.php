<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Ragefill\Database;
use Ragefill\AuthMiddleware;
use Ragefill\SeoHelper;

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config.php';

$db = new Database($config['db_path']);
$auth = new AuthMiddleware($config['admin_password']);
$seo = new SeoHelper($config['base_url']);

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(
    displayErrorDetails: (bool)($config['debug'] ?? false),
    logErrors: true,
    logErrorDetails: true,
);

// CORS middleware
$app->add(function (Request $request, $handler) use ($config) {
    $response = $handler->handle($request);
    $origin = $config['base_url'] ?? '*';
    return $response
        ->withHeader('Access-Control-Allow-Origin', $origin)
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

// --- Public API ---

// Get public settings (contact info)
$app->get('/api/settings', function (Request $request, Response $response) use ($config) {
    $settings = [
        'contact_telegram' => $config['contact_telegram'] ?? 'ragefill_shop',
    ];
    $response->getBody()->write(json_encode($settings, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

// Get all active sauces
$app->get('/api/sauces', function (Request $request, Response $response) use ($db) {
    $sauces = $db->getAllSauces(true);
    $response->getBody()->write(json_encode($sauces, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

// Get single sauce
$app->get('/api/sauces/{id}', function (Request $request, Response $response, array $args) use ($db) {
    $sauce = $db->getSauceById((int)$args['id']);
    if (!$sauce) {
        $response->getBody()->write(json_encode(['error' => 'Не найдено']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
    }
    $response->getBody()->write(json_encode($sauce, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

// --- Auth ---

$app->post('/api/auth/login', function (Request $request, Response $response) use ($auth) {
    $data = $request->getParsedBody();
    $password = (string)($data['password'] ?? '');

    $token = $auth->generateToken($password);
    if (!$token) {
        $response->getBody()->write(json_encode(['error' => 'Неверный пароль']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    $response->getBody()->write(json_encode(['token' => $token]));
    return $response->withHeader('Content-Type', 'application/json');
});

// --- Admin API (protected) ---

$app->group('/api/admin', function (RouteCollectorProxy $group) use ($db, $config) {

    // Get all sauces (including inactive)
    $group->get('/sauces', function (Request $request, Response $response) use ($db) {
        $sauces = $db->getAllSauces(false);
        $response->getBody()->write(json_encode($sauces, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Create sauce
    $group->post('/sauces', function (Request $request, Response $response) use ($db, $config) {
        $data = $request->getParsedBody() ?? [];
        $files = $request->getUploadedFiles();

        $name = trim((string)($data['name'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));

        if ($name === '' || $description === '') {
            $response->getBody()->write(json_encode(['error' => 'Название и описание обязательны']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        // Handle image upload
        $imageName = null;
        if (isset($files['image']) && $files['image']->getError() === UPLOAD_ERR_OK) {
            $imageName = handleUpload($files['image'], $config);
            if ($imageName === null) {
                $response->getBody()->write(json_encode(['error' => 'Недопустимый формат изображения']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
        }

        $data['image'] = $imageName;
        $id = $db->createSauce($data);

        $sauce = $db->getSauceById($id);
        $response->getBody()->write(json_encode($sauce, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    });

    // Update sauce
    $group->post('/sauces/{id}', function (Request $request, Response $response, array $args) use ($db, $config) {
        $id = (int)$args['id'];
        $sauce = $db->getSauceById($id);
        if (!$sauce) {
            $response->getBody()->write(json_encode(['error' => 'Не найдено']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $data = $request->getParsedBody() ?? [];
        $files = $request->getUploadedFiles();
        $oldImage = $sauce['image'];

        // Determine new image state
        $hasNewUpload = isset($files['image']) && $files['image']->getError() === UPLOAD_ERR_OK;
        $wantsRemoval = isset($data['remove_image']) && $data['remove_image'] === '1';

        if ($hasNewUpload) {
            $imageName = handleUpload($files['image'], $config);
            if ($imageName === null) {
                $response->getBody()->write(json_encode(['error' => 'Недопустимый формат изображения']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            $data['image'] = $imageName;
            deleteImageFile($oldImage, $config);
        } elseif ($wantsRemoval) {
            $data['image'] = null;
            deleteImageFile($oldImage, $config);
        }

        unset($data['remove_image']);
        $db->updateSauce($id, $data);
        $updated = $db->getSauceById($id);
        $response->getBody()->write(json_encode($updated, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Delete sauce
    $group->delete('/sauces/{id}', function (Request $request, Response $response, array $args) use ($db, $config) {
        $id = (int)$args['id'];
        $sauce = $db->getSauceById($id);
        if (!$sauce) {
            $response->getBody()->write(json_encode(['error' => 'Не найдено']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        deleteImageFile($sauce['image'], $config);
        $db->deleteSauce($id);

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    });

})->add($auth);

// --- SEO: robots.txt ---

$app->get('/robots.txt', function (Request $request, Response $response) use ($seo) {
    $response->getBody()->write($seo->generateRobotsTxt());
    return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
});

// --- SEO: sitemap.xml ---

$app->get('/sitemap.xml', function (Request $request, Response $response) use ($db, $seo) {
    $sauces = $db->getAllSauces(true);
    $response->getBody()->write($seo->generateSitemap($sauces));
    return $response->withHeader('Content-Type', 'application/xml; charset=utf-8');
});

// --- Individual product page (slug-based: /sauce/garlix) ---

$app->get('/sauce/{slug:[a-z0-9][a-z0-9\-]*}', function (Request $request, Response $response, array $args) use ($db, $seo, $config) {
    $slug = $args['slug'];

    // First try slug lookup (covers cases like "18" which is a valid slug for "18+")
    $sauce = $db->getSauceBySlug($slug);

    // If not found by slug and input is numeric, try old ID-based URL and redirect
    if (!$sauce && ctype_digit($slug)) {
        $sauce = $db->getSauceById((int)$slug);
        if ($sauce && $sauce['is_active'] && !empty($sauce['slug'])) {
            return $response
                ->withHeader('Location', '/sauce/' . $sauce['slug'])
                ->withStatus(301);
        }
    }

    if (!$sauce || !$sauce['is_active']) {
        $response->getBody()->write(renderProductNotFound());
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(404);
    }

    $html = renderProductPage($sauce, $seo, $config);
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

// --- Serve catalog with SSR ---

$app->get('/', function (Request $request, Response $response) use ($db, $seo) {
    $sauces = $db->getAllSauces(true);
    $html = file_get_contents(__DIR__ . '/catalog.html');

    // Inject SEO meta tags
    $html = str_replace('{{SEO_TITLE}}', 'RAGEFILL — Каталог сверхострых соусов | Купить острый соус в Беларуси', $html);
    $html = str_replace('{{SEO_META}}', $seo->catalogMeta($sauces), $html);
    $html = str_replace('{{SEO_JSONLD}}', $seo->catalogJsonLd($sauces), $html);

    // SSR: render product cards as static HTML
    $ssrHtml = '';
    foreach ($sauces as $sauce) {
        $ssrHtml .= $seo->renderProductCard($sauce);
    }
    $html = str_replace('{{SSR_PRODUCTS}}', $ssrHtml, $html);

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->get('/admin', function (Request $request, Response $response) {
    $html = file_get_contents(__DIR__ . '/admin.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

// --- About page (benefits, FAQ, reviews) ---

$app->get('/about', function (Request $request, Response $response) use ($config, $seo) {
    $html = renderAboutPage($config, $seo);
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->run();

// --- FAQ data ---

const ABOUT_FAQ = [
    [
        'q' => 'Из чего делают соусы RAGEFILL?',
        'a' => 'Только натуральные ингредиенты: свежие острые перцы (Carolina Reaper, Trinidad Scorpion, Habanero и др.), овощи, специи, уксус. Без консервантов, красителей и усилителей вкуса.',
    ],
    [
        'q' => 'Какой соус выбрать новичку?',
        'a' => 'Начните с соусов с остротой 1–2 из 5. Они дают приятное тепло без экстремального жжения. Подробности по каждому соусу — в карточке товара.',
    ],
    [
        'q' => 'Как хранить соус после вскрытия?',
        'a' => 'В холодильнике, при температуре +2…+6 °C. Срок хранения после вскрытия — до 6 месяцев. Невскрытый соус хранится при комнатной температуре до 12 месяцев.',
    ],
    [
        'q' => 'Как оформить заказ?',
        'a' => 'Напишите нам в Telegram — мы подберём соусы под ваш вкус, ответим на вопросы и оформим доставку.',
    ],
    [
        'q' => 'Есть ли доставка по Беларуси?',
        'a' => 'Да! Доставляем по всей Беларуси почтой и курьерскими службами. По Минску возможен самовывоз.',
    ],
    [
        'q' => 'Можно ли заказать подарочный набор?',
        'a' => 'Конечно. У нас есть готовые подарочные наборы, а также можно собрать индивидуальный набор на ваш вкус.',
    ],
];

const ABOUT_BENEFITS = [
    ['icon' => '🌶️', 'title' => 'Ручная работа', 'text' => 'Каждая партия готовится вручную из свежих перцев небольшими порциями.'],
    ['icon' => '🌿', 'title' => 'Натуральный состав', 'text' => 'Без консервантов, красителей и усилителей вкуса — только настоящие ингредиенты.'],
    ['icon' => '🔥', 'title' => 'От лёгкой до экстремальной', 'text' => 'Пять уровней остроты — найдётся соус для каждого, от новичка до экстремала.'],
    ['icon' => '🎁', 'title' => 'Идеальный подарок', 'text' => 'Подарочные наборы и индивидуальная подборка соусов для любого повода.'],
    ['icon' => '🚚', 'title' => 'Доставка по Беларуси', 'text' => 'Отправляем почтой и курьером по всей стране. Самовывоз в Минске.'],
    ['icon' => '💬', 'title' => 'Личный подход', 'text' => 'Поможем подобрать соус под ваш вкус — просто напишите в Telegram.'],
];

// --- Helpers ---

function handleUpload(object $uploadedFile, array $config): ?string
{
    // Validate by actual file content, not client-provided MIME
    $tmpPath = null;
    $stream = $uploadedFile->getStream();
    $tmpPath = tempnam(sys_get_temp_dir(), 'ragefill_');
    file_put_contents($tmpPath, $stream);

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($tmpPath);

    if (!in_array($realMime, $config['allowed_types'], true)) {
        unlink($tmpPath);
        return null;
    }

    // Additional validation: must be a real image
    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        unlink($tmpPath);
        return null;
    }

    $size = filesize($tmpPath);
    if ($size > $config['max_upload_size']) {
        unlink($tmpPath);
        return null;
    }

    $uploadDir = $config['upload_dir'];
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Optimize: resize + convert to WebP
    $optimized = optimizeImage($tmpPath, $realMime, $config);

    if ($optimized) {
        unlink($tmpPath);
        $filename = bin2hex(random_bytes(16)) . '.webp';
        rename($optimized, $uploadDir . $filename);
        chmod($uploadDir . $filename, 0644);
    } else {
        // Fallback: save original if optimization fails
        $ext = match ($realMime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        rename($tmpPath, $uploadDir . $filename);
        chmod($uploadDir . $filename, 0644);
    }

    return $filename;
}

function optimizeImage(string $tmpPath, string $mime, array $config): ?string
{
    if (!function_exists('imagecreatefromjpeg')) {
        return null; // GD not available
    }

    $targetSize = $config['image_max_width'] ?? 800;
    $quality = $config['image_quality'] ?? 80;

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmpPath),
        'image/png' => @imagecreatefrompng($tmpPath),
        'image/webp' => @imagecreatefromwebp($tmpPath),
        default => false,
    };

    if (!$src) {
        return null;
    }

    $origW = imagesx($src);
    $origH = imagesy($src);

    // Center-crop to square, then resize to targetSize x targetSize
    $cropSize = min($origW, $origH);
    $cropX = (int)round(($origW - $cropSize) / 2);
    $cropY = (int)round(($origH - $cropSize) / 2);

    $dst = imagecreatetruecolor($targetSize, $targetSize);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $targetSize, $targetSize, $cropSize, $cropSize);
    imagedestroy($src);

    // Save as WebP
    $outPath = tempnam(sys_get_temp_dir(), 'ragefill_opt_');
    $result = imagewebp($dst, $outPath, $quality);
    imagedestroy($dst);

    if (!$result || !file_exists($outPath) || filesize($outPath) === 0) {
        @unlink($outPath);
        return null;
    }

    return $outPath;
}

function renderFooter(array $config): string
{
    $contactTg = htmlspecialchars($config['contact_telegram'] ?? 'rage_fill', ENT_QUOTES, 'UTF-8');
    $year = date('Y');
    return <<<HTML
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="site-footer__brand">
                <div class="site-footer__logo"><span class="site-footer__logo-rage">RAGE</span> <span class="site-footer__logo-fill">FILL</span></div>
                <div class="site-footer__text">Острые соусы ручной работы, Беларусь</div>
            </div>
            <div class="site-footer__contact">
                <h4 class="site-footer__heading">Контакты</h4>
                <nav class="site-footer__links" aria-label="Контакты">
                    <a href="https://t.me/{$contactTg}" target="_blank" rel="noopener noreferrer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.904-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.492-1.302.484-.429-.008-1.252-.242-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.141a.506.506 0 01.171.325c.016.093.036.306.02.472z"/></svg>
                        Telegram
                    </a>
                    <a href="https://instagram.com/ragefill.by" target="_blank" rel="noopener noreferrer">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        Instagram
                    </a>
                </nav>
            </div>
            <div class="site-footer__about">
                <h4 class="site-footer__heading">О продукте</h4>
                <p class="site-footer__about-text">Все соусы изготавливаются вручную из свежих перцев. Для заказа свяжитесь с нами через Telegram.</p>
            </div>
        </div>
        <div class="site-footer__bottom">
            <div class="site-footer__copy">&copy; {$year} RAGEFILL. Все права защищены.</div>
        </div>
    </footer>
    HTML;
}

function renderProductPage(array $sauce, SeoHelper $seo, array $config): string
{
    $name = htmlspecialchars($sauce['name'], ENT_QUOTES, 'UTF-8');
    $subtitle = htmlspecialchars($sauce['subtitle'] ?? '', ENT_QUOTES, 'UTF-8');
    $desc = $sauce['description'] ?? '';
    $composition = $sauce['composition'] ?? '';
    $volume = htmlspecialchars($sauce['volume'] ?? '', ENT_QUOTES, 'UTF-8');
    $heat = (int)($sauce['heat_level'] ?? 3);
    $inStock = ($sauce['in_stock'] ?? 1) != 0;
    $id = (int)$sauce['id'];

    $contactTg = htmlspecialchars($config['contact_telegram'] ?? 'rage_fill', ENT_QUOTES, 'UTF-8');

    $image = !empty($sauce['image'])
        ? '<img class="product-page__image" src="/uploads/' . htmlspecialchars($sauce['image'], ENT_QUOTES, 'UTF-8') . '" alt="' . $name . '">'
        : '';

    $subtitleHtml = $subtitle ? '<p class="product-page__subtitle">' . $subtitle . '</p>' : '';

    $stockText = $inStock ? 'В наличии' : 'Нет в наличии';
    $stockClass = $inStock ? 'in' : 'out';

    $year = date('Y');
    $sauceSlug = htmlspecialchars($sauce['slug'] ?? (string)$id, ENT_QUOTES, 'UTF-8');
    $url = 'https://ragefill.by/sauce/' . $sauceSlug;
    $metaTags = $seo->productMeta($sauce);
    $jsonLd = $seo->productJsonLd($sauce);
    $breadcrumbLd = $seo->breadcrumbJsonLd($sauce['name'], $sauceSlug);
    $title = htmlspecialchars($sauce['name'] . ' — острый соус RAGEFILL', ENT_QUOTES, 'UTF-8');

    // Clean description HTML for display (strip all attributes to prevent XSS)
    $descHtml = sanitizeHtml($desc);
    $compHtml = $composition ? sanitizeHtml($composition) : '';

    $compSection = $compHtml ? <<<HTML
        <div class="product-page__section">
            <h2 class="product-page__section-title">Состав</h2>
            <div class="product-page__text">{$compHtml}</div>
        </div>
    HTML : '';

    $volumeHtml = $volume ? '<span class="product-page__volume-pill">' . $volume . '</span>' : '';

    // Heat bar segments
    $heatBar = '';
    for ($i = 1; $i <= 5; $i++) {
        $seg = $i <= $heat ? 'active' : '';
        $heatBar .= '<div class="heat-bar__seg ' . $seg . '"></div>';
    }

    $heatLabels = [1 => 'Лёгкая', 2 => 'Умеренная', 3 => 'Средняя', 4 => 'Сильная', 5 => 'Экстремальная'];
    $heatLabel = $heatLabels[$heat] ?? '';

    // Category label
    $categoryMap = [
        'sauce' => 'Соус',
        'gift_set' => 'Подарочный набор',
        'pickled_pepper' => 'Маринованный перец',
        'spicy_peanut' => 'Острый арахис',
        'spice' => 'Специи',
    ];
    $category = $sauce['category'] ?? 'sauce';
    $categoryLabel = $categoryMap[$category] ?? 'Соус';

    $footer = renderFooter($config);

    $ctaText = $inStock ? 'Написать продавцу' : 'Узнать о наличии';
    $ctaIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.904-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.492-1.302.484-.429-.008-1.252-.242-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635.099-.002.321.023.465.141a.506.506 0 01.171.325c.016.093.036.306.02.472z"/></svg>';

    return <<<HTML
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title>{$title}</title>
        {$metaTags}
        <link rel="alternate" hreflang="ru-BY" href="{$url}">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <meta name="theme-color" content="#1C1410">
        <link rel="stylesheet" href="/css/style.css?v=3.1.0">
        <script src="https://telegram.org/js/telegram-web-app.js" data-cfasync="false"></script>
        {$jsonLd}
        {$breadcrumbLd}
    </head>
    <body>
        <header class="header">
            <div class="header__inner">
                <a href="/" class="header__logo-link">
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

        <article class="product-page">
            <nav class="product-page__breadcrumb" aria-label="Навигация">
                <a href="/">Каталог</a>
                <span class="product-page__breadcrumb-sep" aria-hidden="true">
                    <svg width="6" height="10" viewBox="0 0 6 10" fill="none"><path d="M1 1l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span>{$name}</span>
            </nav>

            <div class="product-page__main">
                <div class="product-page__gallery">
                    <div class="product-page__hero">
                        {$image}
                    </div>
                </div>

                <div class="product-page__details">
                    <span class="product-page__category">{$categoryLabel}</span>
                    <h1 class="product-page__name">{$name}</h1>
                    {$subtitleHtml}

                    <div class="product-page__meta">
                        <div class="product-page__stock product-page__stock--{$stockClass}">
                            <span class="product-page__stock-dot"></span>
                            {$stockText}
                        </div>
                        {$volumeHtml}
                    </div>

                    <div class="product-page__heat-block">
                        <div class="product-page__heat-header">
                            <span class="product-page__heat-title">Острота</span>
                            <span class="product-page__heat-value">{$heat}/5 — {$heatLabel}</span>
                        </div>
                        <div class="heat-bar" aria-label="Уровень остроты {$heat} из 5">
                            {$heatBar}
                        </div>
                    </div>

                    <div class="product-page__divider"></div>

                    <div class="product-page__section">
                        <h2 class="product-page__section-title">Описание</h2>
                        <div class="product-page__text">{$descHtml}</div>
                    </div>

                    {$compSection}

                    <div class="product-page__actions">
                        <a href="https://t.me/{$contactTg}" class="product-page__cta" target="_blank" rel="noopener">
                            {$ctaIcon}
                            <span>{$ctaText}</span>
                        </a>
                        <a href="/" class="product-page__back">← Вернуться в каталог</a>
                    </div>
                </div>
            </div>
        </article>

        {$footer}

        <script>
            const tg = window.Telegram?.WebApp;
            if (tg) {
                tg.ready();
                tg.expand();
                document.body.classList.add('tg-theme', 'tg-mode');
                if (tg.colorScheme === 'dark') document.body.classList.add('tg-dark');
                tg.BackButton.show();
                tg.BackButton.onClick(() => { window.location.href = '/'; });
            } else {
                document.body.classList.add('browser-mode');
            }

            // Theme toggle
            (function() {
                const saved = localStorage.getItem('ragefill-theme');
                if (saved === 'dark') document.body.classList.add('tg-dark');
                const btn = document.getElementById('theme-toggle');
                if (!btn) return;
                btn.addEventListener('click', () => {
                    const isDark = document.body.classList.toggle('tg-dark');
                    localStorage.setItem('ragefill-theme', isDark ? 'dark' : 'light');
                });
            })();
        </script>
    </body>
    </html>
    HTML;
}

function renderProductNotFound(): string
{
    return <<<HTML
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Товар не найден — RAGEFILL</title>
        <meta name="robots" content="noindex">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <meta name="theme-color" content="#1C1410">
        <link rel="stylesheet" href="/css/style.css?v=1.9.0">
    </head>
    <body class="browser-mode">
        <header class="header">
            <a href="/" class="header__logo-link">
                <span class="header__logo"><span class="header__logo-rage">RAGE</span> <span class="header__logo-fill">FILL</span></span>
            </a>
        </header>
        <div class="empty-state" style="padding-top: 80px;">
            <div class="empty-state__icon">🌶️</div>
            <div class="empty-state__text">Товар не найден</div>
            <div class="empty-state__hint">Возможно, он был удалён или скрыт</div>
            <a href="/" class="empty-state__btn">Вернуться в каталог</a>
        </div>
    </body>
    </html>
    HTML;
}

function sanitizeHtml(string $html): string
{
    // Step 1: strip all tags except safe formatting ones
    $clean = strip_tags($html, '<p><br><strong><em><ul><ol><li>');

    // Step 2: remove ALL attributes from remaining tags (prevents onmouseover, onclick, style, etc.)
    $clean = preg_replace('/<(\w+)\s+[^>]*>/i', '<$1>', $clean);

    return $clean;
}

function deleteImageFile(?string $image, array $config): void
{
    if ($image === null || $image === '') {
        return;
    }

    // Prevent path traversal
    $basename = basename($image);
    if ($basename !== $image) {
        return;
    }

    $path = $config['upload_dir'] . $basename;
    if (is_file($path)) {
        @unlink($path);
    }
}

// --- FAQ constants ---

const ABOUT_FAQ = [
    [
        'question' => 'Как сделать заказ?',
        'answer' => 'Напишите нам в Telegram <a href="https://t.me/rage_fill">@rage_fill</a> — поможем выбрать соус и оформим заказ. Также можно открыть каталог прямо в Telegram-боте.',
    ],
    [
        'question' => 'Какие способы доставки доступны?',
        'answer' => 'Доставляем по Минску и всей Беларуси через Белпочту и Европочту. Возможен самовывоз в Минске — уточняйте детали в Telegram.',
    ],
    [
        'question' => 'Какой срок годности у соусов?',
        'answer' => 'Срок годности наших соусов — 12 месяцев с даты изготовления. Храните в прохладном месте, после вскрытия — в холодильнике.',
    ],
    [
        'question' => 'Из чего делают соусы RAGE FILL?',
        'answer' => 'Только натуральные ингредиенты: свежие острые перцы (выращиваем сами), овощи, специи, уксус. Без консервантов, красителей и усилителей вкуса.',
    ],
    [
        'question' => 'Какой соус выбрать, если я не пробовал острое?',
        'answer' => 'Начните с соусов с уровнем остроты 1–2 (умеренная). Они дают приятное тепло без экстремального жжения. Мы поможем подобрать — напишите нам!',
    ],
    [
        'question' => 'Можно ли заказать соус в подарок?',
        'answer' => 'Да! У нас есть готовые подарочные наборы, а также можем собрать индивидуальный комплект. Отличный подарок на День рождения, 23 февраля, 8 марта и любой праздник.',
    ],
];

// --- About page renderer ---

function renderAboutPage(array $config, SeoHelper $seo): string
{
    $contactTg = htmlspecialchars($config['contact_telegram'] ?? 'rage_fill', ENT_QUOTES, 'UTF-8');

    // SEO meta
    $title = 'О нас — RAGE FILL | Острые соусы ручной работы (Минск, Беларусь)';
    $desc = 'Авторские острые соусы ручной работы RAGE FILL. Натуральные ингредиенты, собственные перцы, доставка по Минску и Беларуси. Отзывы, преимущества, FAQ.';
    $url = rtrim($config['base_url'], '/') . '/about';
    $metaTags = $seo->buildAboutMeta($title, $desc, $url);

    // FAQ JSON-LD (FAQPage schema for Google rich results)
    $faqLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [],
    ];
    foreach (ABOUT_FAQ as $item) {
        $faqLd['mainEntity'][] = [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => strip_tags($item['answer']),
            ],
        ];
    }
    $faqJsonLd = '<script type="application/ld+json">'
        . json_encode($faqLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        . '</script>';

    // Benefits
    $benefits = [
        ['icon' => '🌶', 'title' => 'Собственные перцы', 'text' => 'Мы сами выращиваем острые перцы: Carolina Reaper, Apocalypse Scorpion, Big Red Mama, 7 POT, Bhut Jolokia, Habanero, The Pain, Jalapeño и другие.'],
        ['icon' => '🌿', 'title' => 'Натуральный состав', 'text' => 'Готовим соусы из натуральных ингредиентов по собственным авторским рецептам. Без консервантов и красителей.'],
        ['icon' => '🎁', 'title' => 'Идея для подарка', 'text' => 'Отличный вариант для любого праздника — будь то День рождения, 23 февраля, 8 марта, юбилей или просто особый повод.'],
        ['icon' => '🔥', 'title' => 'Широкий выбор', 'text' => 'От лёгкой остроты до экстремальной. Яркий вкус для мяса, пиццы, бургеров и закусок.'],
        ['icon' => '🚚', 'title' => 'Доставка по Беларуси', 'text' => 'Доставляем по Минску и всей Беларуси через Белпочту и Европочту.'],
        ['icon' => '⭐', 'title' => 'Запоминающийся вкус', 'text' => 'Мы делаем соусы, которые действительно жгут и запоминаются.'],
    ];

    $benefitsHtml = '';
    foreach ($benefits as $b) {
        $benefitsHtml .= <<<HTML
            <div class="about-benefit">
                <div class="about-benefit__icon">{$b['icon']}</div>
                <h3 class="about-benefit__title">{$b['title']}</h3>
                <p class="about-benefit__text">{$b['text']}</p>
            </div>
        HTML;
    }

    // FAQ
    $faqHtml = '';
    foreach (ABOUT_FAQ as $item) {
        $q = htmlspecialchars($item['question'], ENT_QUOTES, 'UTF-8');
        $a = $item['answer'];
        $faqHtml .= <<<HTML
            <details class="about-faq__item">
                <summary class="about-faq__question">{$q}</summary>
                <div class="about-faq__answer">{$a}</div>
            </details>
        HTML;
    }

    // Reviews — scan uploads/reviews/ for images
    $reviewsDir = __DIR__ . '/uploads/reviews/';
    $reviewImages = [];
    if (is_dir($reviewsDir)) {
        $files = scandir($reviewsDir);
        foreach ($files as $f) {
            if (preg_match('/\.(jpe?g|png|webp)$/i', $f)) {
                $reviewImages[] = $f;
            }
        }
        sort($reviewImages);
    }

    $reviewsHtml = '';
    if (!empty($reviewImages)) {
        foreach ($reviewImages as $img) {
            $src = '/uploads/reviews/' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
            $reviewsHtml .= <<<HTML
                <a href="{$src}" class="about-review" target="_blank" rel="noopener">
                    <img class="about-review__img" src="{$src}" alt="Отзыв клиента RAGE FILL" loading="lazy">
                </a>
            HTML;
        }
    } else {
        $reviewsHtml = '<p class="about-reviews__empty">Скоро здесь появятся отзывы наших клиентов!</p>';
    }

    $footer = renderFooter($config);

    return <<<HTML
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title>{$title}</title>
        {$metaTags}
        <link rel="alternate" hreflang="ru-BY" href="{$url}">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <meta name="theme-color" content="#1C1410">
        <link rel="stylesheet" href="/css/style.css?v=3.2.0">
        {$faqJsonLd}
    </head>
    <body class="browser-mode about-page">

        <header class="header">
            <div class="header__inner">
                <a href="/" class="header__logo-link">
                    <div class="header__logo" aria-hidden="true"><span class="header__logo-rage">RAGE</span> <span class="header__logo-fill">FILL</span></div>
                </a>
                <div class="header__actions">
                    <a href="/" class="about-nav-link">Каталог</a>
                    <button class="theme-toggle" id="theme-toggle" aria-label="Переключить тему">
                        <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                        <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    </button>
                </div>
            </div>
        </header>

        <main class="about-main">

            <!-- Benefits -->
            <section class="about-section about-benefits" aria-label="Преимущества">
                <h1 class="about-section__title">Почему выбирают RAGE FILL</h1>
                <div class="about-benefits__grid">
                    {$benefitsHtml}
                </div>
            </section>

            <!-- Reviews -->
            <section class="about-section about-reviews" aria-label="Отзывы">
                <h2 class="about-section__title">Отзывы наших клиентов</h2>
                <p class="about-section__subtitle">Реальные отзывы из Instagram</p>
                <div class="about-reviews__grid">
                    {$reviewsHtml}
                </div>
                <a href="https://instagram.com/ragefill.by" class="about-reviews__instagram" target="_blank" rel="noopener noreferrer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    Ещё отзывы в Instagram
                </a>
            </section>

            <!-- FAQ -->
            <section class="about-section about-faq" aria-label="Частые вопросы">
                <h2 class="about-section__title">Частые вопросы</h2>
                <div class="about-faq__list">
                    {$faqHtml}
                </div>
            </section>

            <!-- CTA -->
            <section class="about-section about-cta">
                <h2 class="about-cta__title">Готовы попробовать?</h2>
                <p class="about-cta__text">Откройте каталог и выберите свой огонь!</p>
                <div class="about-cta__buttons">
                    <a href="/" class="about-cta__btn about-cta__btn--primary">Перейти в каталог</a>
                    <a href="https://t.me/{$contactTg}" class="about-cta__btn about-cta__btn--secondary" target="_blank" rel="noopener">Написать нам</a>
                </div>
            </section>

        </main>

        {$footer}

        <script>
            (function() {
                const saved = localStorage.getItem('ragefill-theme');
                if (saved === 'dark') document.body.classList.add('tg-dark');
                const btn = document.getElementById('theme-toggle');
                if (!btn) return;
                btn.addEventListener('click', () => {
                    const isDark = document.body.classList.toggle('tg-dark');
                    localStorage.setItem('ragefill-theme', isDark ? 'dark' : 'light');
                });
            })();
        </script>
    </body>
    </html>
    HTML;
}
