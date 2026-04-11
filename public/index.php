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

const ABOUT_FAQ = [
    [
        'question' => 'Как сделать заказ?',
        'answer' => 'Напишите нам в Telegram <a href="https://t.me/rage_fill">@rage_fill</a> — поможем выбрать соус и оформим заказ. Также можно открыть каталог прямо в Telegram-боте и выбрать товар там. Оплата — наличными при самовывозе, переводом на карту или наложенным платежом при доставке. Обычно отправляем заказ в течение 1–2 дней.',
    ],
    [
        'question' => 'Какие способы доставки доступны?',
        'answer' => 'Доставляем по Минску и всей Беларуси через Белпочту и Европочту. Срок доставки — 2–5 дней в зависимости от региона. Возможен самовывоз в Минске — уточняйте адрес и время в Telegram. Каждый заказ упаковываем надёжно, чтобы бутылки доехали в целости.',
    ],
    [
        'question' => 'Какой срок годности у соусов?',
        'answer' => 'Срок годности наших соусов — 12 месяцев с даты изготовления. В закрытом виде храните в прохладном тёмном месте при температуре до +25°C. После вскрытия — обязательно в холодильнике, и соус сохранит вкус ещё 3–4 месяца. Дата изготовления указана на этикетке.',
    ],
    [
        'question' => 'Из чего делают соусы  RAGE FILL?',
        'answer' => 'Только натуральные ингредиенты: свежие острые перцы, которые мы выращиваем сами (Carolina Reaper, Habanero, Bhut Jolokia, Apocalypse Scorpion и другие), овощи, специи и уксус. В составе нет консервантов, красителей и усилителей вкуса. Каждая партия готовится вручную небольшими порциями — так мы контролируем качество и вкус.',
    ],
    [
        'question' => 'Какой соус выбрать, если я не пробовал острое?',
        'answer' => 'Начните с соусов с уровнем остроты 1–2 из 5. Они дают приятное тепло и раскрывают вкус блюда без экстремального жжения. Подойдут к мясу, пицце, бургерам и закускам. Если хотите попробовать разное — посмотрите наши подарочные наборы с соусами разной остроты. Не уверены в выборе? Напишите нам в Telegram — подберём под ваш вкус!',
    ],
    [
        'question' => 'Можно ли заказать соус в подарок?',
        'answer' => 'Да! У нас есть готовые подарочные наборы с соусами разной остроты — от лёгкой до экстремальной. Также можем собрать индивидуальный комплект по вашему пожеланию. Отличный подарок на День рождения, 23 февраля, 8 марта, Новый год или любой другой праздник. Каждый набор красиво упакован и готов к вручению.',
    ],
];

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
    $response = $response
        ->withHeader('Access-Control-Allow-Origin', $origin)
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');

    // Prevent search engines from indexing API responses
    $path = $request->getUri()->getPath();
    if (str_starts_with($path, '/api/')) {
        $response = $response->withHeader('X-Robots-Tag', 'noindex');
    }

    return $response;
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

        // Handle additional images upload
        $additionalImages = [];
        if (isset($files['additional_images'])) {
            $uploads = is_array($files['additional_images']) ? $files['additional_images'] : [$files['additional_images']];
            foreach (array_slice($uploads, 0, 10) as $file) {
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $imgName = handleUpload($file, $config);
                    if ($imgName !== null) {
                        $additionalImages[] = $imgName;
                    }
                }
            }
        }
        $data['images'] = json_encode($additionalImages);

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

        // Handle additional images
        $currentImages = json_decode($sauce['images'] ?? '[]', true) ?: [];
        $existingImages = isset($data['existing_images']) ? (json_decode($data['existing_images'], true) ?: []) : $currentImages;
        $deleteImgs = isset($data['delete_images']) ? (json_decode($data['delete_images'], true) ?: []) : [];

        // Delete removed images from disk
        foreach ($deleteImgs as $delImg) {
            if (in_array($delImg, $currentImages, true)) {
                deleteImageFile($delImg, $config);
            }
        }

        // Upload new additional images
        $newImages = [];
        if (isset($files['additional_images'])) {
            $uploads = is_array($files['additional_images']) ? $files['additional_images'] : [$files['additional_images']];
            foreach (array_slice($uploads, 0, 10) as $file) {
                if ($file->getError() === UPLOAD_ERR_OK) {
                    $imgName = handleUpload($file, $config);
                    if ($imgName !== null) {
                        $newImages[] = $imgName;
                    }
                }
            }
        }

        // Final list: kept existing (in order) + newly uploaded
        $keptImages = array_values(array_filter($existingImages, fn($img) => !in_array($img, $deleteImgs, true)));
        $data['images'] = json_encode(array_merge($keptImages, $newImages));
        unset($data['existing_images'], $data['delete_images']);

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
        $additionalImages = json_decode($sauce['images'] ?? '[]', true) ?: [];
        foreach ($additionalImages as $img) {
            deleteImageFile($img, $config);
        }
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

    $html = renderProductPage($sauce, $seo, $config, $db);
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

// --- Homepage ---

$app->get('/', function (Request $request, Response $response) use ($config, $seo, $db) {
    $html = renderHomePage($config, $seo, $db);
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

// --- Catalog with SSR ---

$app->get('/catalog', function (Request $request, Response $response) use ($db, $seo, $config) {
    $sauces = $db->getAllSauces(true);
    $html = file_get_contents(__DIR__ . '/catalog.html');

    $baseUrl = rtrim($config['base_url'], '/');
    $html = str_replace('{{SEO_TITLE}}', 'Каталог — RAGE FILL | Острые соусы ручной работы', $html);
    $html = str_replace('{{SEO_META}}', $seo->catalogMeta($sauces), $html);
    $html = str_replace('{{SEO_JSONLD}}', $seo->catalogJsonLd($sauces) . "\n" . $seo->websiteJsonLd(), $html);
    $html = str_replace('{{HREFLANG_URL}}', $baseUrl . '/catalog', $html);
    $contactTg = htmlspecialchars($config['contact_telegram'] ?? 'rage_fill', ENT_QUOTES, 'UTF-8');
    $html = str_replace('{{CONTACT_TG}}', $contactTg, $html);

    $ssrHtml = '';
    foreach ($sauces as $sauce) {
        $ssrHtml .= $seo->renderProductCard($sauce);
    }
    $html = str_replace('{{SSR_PRODUCTS}}', $ssrHtml, $html);

    // Inject sauce data for JS to avoid a duplicate API fetch on initial load
    $ssrJson = json_encode($sauces, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $html = str_replace('</body>', "<script>window.__SSR_SAUCES__={$ssrJson};</script>\n</body>", $html);

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->get('/admin', function (Request $request, Response $response) {
    $html = file_get_contents(__DIR__ . '/admin.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

// --- About redirects to homepage ---

// --- Privacy Policy ---

$app->get('/privacy', function (Request $request, Response $response) use ($config, $seo) {
    $html = renderPrivacyPage($config, $seo);
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->run();

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
                </nav>
            </div>
            <div class="site-footer__about">
                <h4 class="site-footer__heading">О продукте</h4>
                <p class="site-footer__about-text">Все соусы изготавливаются вручную из свежих перцев. Для заказа свяжитесь с нами через Telegram.</p>
            </div>
        </div>
        <div class="site-footer__bottom">
            <div class="site-footer__copy">&copy; {$year} RAGE FILL. Все права защищены.</div>
            <a href="/privacy" class="site-footer__privacy-link">Политика конфиденциальности</a>
        </div>
    </footer>
    HTML;
}

function renderProductPage(array $sauce, SeoHelper $seo, array $config, Database $db): string
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

    // Build gallery images: main + additional
    $additionalImgs = json_decode($sauce['images'] ?? '[]', true) ?: [];
    $allImages = [];
    if (!empty($sauce['image'])) {
        $allImages[] = $sauce['image'];
    }
    $allImages = array_merge($allImages, $additionalImgs);

    // Build lightbox data attribute
    $lightboxSrcs = [];
    if (count($allImages) > 0) {
        foreach ($allImages as $img) {
            $lightboxSrcs[] = '/uploads/' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
        }
        $mainSrc = $lightboxSrcs[0];
        $lightboxData = htmlspecialchars(json_encode($lightboxSrcs), ENT_QUOTES, 'UTF-8');
        $image = '<img class="product-page__image product-page__image--clickable" id="gallery-main-img" src="' . $mainSrc . '" alt="' . $name . '" data-gallery="' . $lightboxData . '" role="button" tabindex="0" aria-label="Открыть фото">';

        $thumbsHtml = '';
        if (count($allImages) > 1) {
            $thumbsHtml = '<div class="product-page__thumbs">';
            foreach ($allImages as $i => $img) {
                $src = $lightboxSrcs[$i];
                $activeClass = $i === 0 ? ' active' : '';
                $thumbsHtml .= '<button class="product-page__thumb' . $activeClass . '" data-src="' . $src . '" data-index="' . $i . '" aria-label="Фото ' . ($i + 1) . '">'
                    . '<img src="' . $src . '" alt="' . $name . ' — фото ' . ($i + 1) . '" loading="lazy">'
                    . '</button>';
            }
            $thumbsHtml .= '</div>';
        }
    } else {
        $image = '<div class="product-page__image-placeholder"></div>';
        $thumbsHtml = '';
    }

    $subtitleHtml = $subtitle ? '<p class="product-page__subtitle">' . $subtitle . '</p>' : '';

    $stockText = $inStock ? 'В наличии' : 'Нет в наличии';
    $stockClass = $inStock ? 'in' : 'out';

    $year = date('Y');
    $baseUrl = rtrim($config['base_url'], '/');
    $sauceSlug = htmlspecialchars($sauce['slug'] ?? (string)$id, ENT_QUOTES, 'UTF-8');
    $url = $baseUrl . '/sauce/' . $sauceSlug;
    $metaTags = $seo->productMeta($sauce);
    $jsonLd = $seo->productJsonLd($sauce);
    $breadcrumbLd = $seo->breadcrumbJsonLd($sauce['name'], $sauceSlug);
    $category = $sauce['category'] ?? 'sauce';
    $titleSuffix = match ($category) {
        'gift_set' => 'подарочный набор RAGE FILL',
        'pickled_pepper' => 'маринованные перцы RAGE FILL',
        'spicy_peanut' => 'острый арахис RAGE FILL',
        'spice' => 'специи RAGE FILL',
        default => 'острый соус RAGE FILL',
    };
    $title = htmlspecialchars($sauce['name'] . ' — ' . $titleSuffix, ENT_QUOTES, 'UTF-8');

    // Clean description HTML for display (strip all attributes to prevent XSS)
    $descHtml = sanitizeHtml($desc);
    $compHtml = $composition ? sanitizeHtml($composition) : '';

    $descSection = trim($descHtml) ? <<<HTML
        <div class="product-page__section">
            <h2 class="product-page__section-title">Описание</h2>
            <div class="product-page__text">{$descHtml}</div>
        </div>
    HTML : '';

    $compSection = $compHtml ? <<<HTML
        <div class="product-page__section product-page__section--composition">
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

    // Related products: sorted by closest heat level, excluding current, limit 4
    $allActive = $db->getAllSauces(true);
    $others = array_filter($allActive, fn($s) => $s['id'] != $id);
    usort($others, fn($a, $b) =>
        abs(($a['heat_level'] ?? 3) - $heat) <=> abs(($b['heat_level'] ?? 3) - $heat)
    );
    $related = array_slice($others, 0, 4);

    $relatedHtml = '';
    if (!empty($related)) {
        $relatedCards = '';
        foreach ($related as $rel) {
            $relatedCards .= $seo->renderProductCard($rel);
        }
        $relatedHtml = <<<HTML
        <section class="product-page__related">
            <h2 class="product-page__related-title">Вам может понравиться</h2>
            <div class="product-page__related-wrap">
                <button class="related-arrow related-arrow--left" aria-label="Назад" type="button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                </button>
                <div class="product-page__related-grid" role="list">
                    {$relatedCards}
                </div>
                <button class="related-arrow related-arrow--right" aria-label="Вперёд" type="button">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                </button>
            </div>
        </section>
        <script>
        (function(){
            const wrap = document.querySelector('.product-page__related-wrap');
            if (!wrap) return;
            const grid = wrap.querySelector('.product-page__related-grid');
            const left = wrap.querySelector('.related-arrow--left');
            const right = wrap.querySelector('.related-arrow--right');
            const scrollAmount = () => grid.clientWidth * 0.6;
            left.addEventListener('click', () => grid.scrollBy({left: -scrollAmount(), behavior: 'smooth'}));
            right.addEventListener('click', () => grid.scrollBy({left: scrollAmount(), behavior: 'smooth'}));
            function updateArrows() {
                left.style.display = grid.scrollLeft > 0 ? '' : 'none';
                right.style.display = grid.scrollLeft + grid.clientWidth < grid.scrollWidth - 2 ? '' : 'none';
            }
            grid.addEventListener('scroll', updateArrows);
            updateArrows();
            new ResizeObserver(updateArrows).observe(grid);
        })();
        </script>
        HTML;
    }

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
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="stylesheet" href="/css/fonts.css?v=1.0.0">
        <meta name="theme-color" content="#0a0a0a">
        <link rel="stylesheet" href="/css/style.css?v=4.1.0">
        <script src="https://telegram.org/js/telegram-web-app.js" data-cfasync="false"></script>
        {$jsonLd}
        {$breadcrumbLd}
    </head>
    <body>
        <header class="header header--catalog">
            <div class="header__inner">
                <a href="/" class="header__logo-link" aria-label="На главную"><div class="header__logo" aria-hidden="true"><span class="header__logo-rage">RAGE</span> <span class="header__logo-fill">FILL</span></div></a>
                <nav class="header__nav browser-only-link" id="main-nav">
                    <a href="/" class="header__nav-link">Главная</a>
                    <a href="/catalog" class="header__nav-link">Каталог</a>
                    <a href="/#faq" class="header__nav-link">Частые вопросы</a>
                </nav>
                <div class="header__actions">
                    <button class="theme-toggle" id="theme-toggle" aria-label="Переключить тему">
                        <svg class="theme-toggle__sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                        <svg class="theme-toggle__moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    </button>
                    <button class="burger-btn browser-only-link" id="burger-btn" aria-label="Меню" aria-expanded="false">
                        <span class="burger-btn__line"></span>
                        <span class="burger-btn__line"></span>
                        <span class="burger-btn__line"></span>
                    </button>
                </div>
            </div>
        </header>

        <main>
        <article class="product-page">
            <nav class="product-page__breadcrumb browser-only-link" aria-label="Навигация">
                <a href="/">Главная</a>
                <span class="product-page__breadcrumb-sep" aria-hidden="true">
                    <svg width="6" height="10" viewBox="0 0 6 10" fill="none"><path d="M1 1l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <a href="/catalog">Каталог</a>
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
                    {$thumbsHtml}
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

                    {$descSection}

                    {$compSection}

                    <div class="product-page__actions">
                        <a href="https://t.me/{$contactTg}" class="product-page__cta" target="_blank" rel="noopener">
                            {$ctaIcon}
                            <span>{$ctaText}</span>
                        </a>
                        <a href="/catalog" class="product-page__back">← Вернуться в каталог</a>
                    </div>
                </div>
            </div>
        </article>

        {$relatedHtml}

        </main>

        {$footer}

        <script src="/js/scroll-top.js?v=1.0.0" data-cfasync="false"></script>
        <script src="/js/lightbox.js?v=3.0.0" data-cfasync="false"></script>
        <script data-cfasync="false">
            const _tgRaw = window.Telegram?.WebApp;
            const tg = (_tgRaw && _tgRaw.initData) ? _tgRaw : null;
            if (tg) {
                tg.ready();
                tg.expand();
                document.body.classList.add('tg-theme', 'tg-mode');
                if (tg.colorScheme === 'dark') document.body.classList.add('tg-dark');
                tg.BackButton.show();
                tg.BackButton.onClick(() => { window.location.href = '/catalog'; });
            } else {
                document.body.classList.add('browser-mode');
            }

            // Theme toggle
            (function() {
                const saved = localStorage.getItem('ragefill-theme');
                if (saved === 'dark') document.body.classList.add('tg-dark');
                else if (saved === 'light') document.body.classList.remove('tg-dark');
                const btn = document.getElementById('theme-toggle');
                if (!btn) return;
                btn.addEventListener('click', () => {
                    const isDark = document.body.classList.toggle('tg-dark');
                    localStorage.setItem('ragefill-theme', isDark ? 'dark' : 'light');
                });
            })();

            // Burger menu
            (function(){
                var btn=document.getElementById('burger-btn'),nav=document.getElementById('main-nav');
                if(!btn||!nav)return;
                btn.addEventListener('click',function(){
                    var open=nav.classList.toggle('open');
                    btn.classList.toggle('open',open);
                    btn.setAttribute('aria-expanded',String(open));
                    document.body.classList.toggle('menu-open',open);
                });
                nav.querySelectorAll('a').forEach(function(a){
                    a.addEventListener('click',function(){
                        nav.classList.remove('open');btn.classList.remove('open');
                        btn.setAttribute('aria-expanded','false');
                        document.body.classList.remove('menu-open');
                    });
                });
            })();

            // Sticky gallery offset (match header height)
            (function() {
                var header = document.querySelector('.header');
                var gallery = document.querySelector('.product-page__gallery');
                if (!header || !gallery) return;
                function update() { gallery.style.top = (header.offsetHeight + 16) + 'px'; }
                update();
                window.addEventListener('resize', update);
            })();

            // Gallery thumbnails + Lightbox
            (function() {
                var thumbs = document.querySelectorAll('.product-page__thumb');
                var main = document.getElementById('gallery-main-img');
                var currentIdx = 0;
                if (!main) return;

                var gallery = [];
                try { gallery = JSON.parse(main.dataset.gallery || '[]'); } catch(e) {}

                // Thumbnail clicks
                thumbs.forEach(function(t) {
                    t.addEventListener('click', function() {
                        currentIdx = parseInt(t.dataset.index) || 0;
                        main.style.opacity = '0';
                        setTimeout(function() { main.src = t.dataset.src; main.style.opacity = '1'; }, 150);
                        thumbs.forEach(function(x) { x.classList.remove('active'); });
                        t.classList.add('active');
                    });
                });

                if (gallery.length === 0) return;

                function syncThumb(i) {
                    currentIdx = i;
                    main.src = gallery[i];
                    thumbs.forEach(function(x) { x.classList.remove('active'); });
                    if (thumbs[i]) thumbs[i].classList.add('active');
                }

                // Open lightbox on main image click
                main.addEventListener('click', function() {
                    Lightbox.open({ images: gallery, startIndex: currentIdx, onNavigate: syncThumb });
                });
                main.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        Lightbox.open({ images: gallery, startIndex: currentIdx, onNavigate: syncThumb });
                    }
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
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title>Товар не найден — RAGEFILL</title>
        <meta name="robots" content="noindex">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="stylesheet" href="/css/fonts.css?v=1.0.0">
        <meta name="theme-color" content="#0a0a0a">
        <link rel="stylesheet" href="/css/style.css?v=4.1.0">
        <script src="https://telegram.org/js/telegram-web-app.js" data-cfasync="false"></script>
    </head>
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
    HTML;
}

function renderPrivacyPage(array $config, SeoHelper $seo): string
{
    $baseUrl = rtrim($config['base_url'], '/');
    $title = 'Политика конфиденциальности — RAGEFILL';
    $desc = 'Политика конфиденциальности интернет-магазина острых соусов RAGEFILL.';
    $url = $baseUrl . '/privacy';
    $metaTags = $seo->buildAboutMeta($title, $desc, $url);
    $footer = renderFooter($config);
    $contactTg = htmlspecialchars($config['contact_telegram'] ?? 'rage_fill', ENT_QUOTES, 'UTF-8');

    return <<<HTML
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
        <title>{$title}</title>
        {$metaTags}
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="stylesheet" href="/css/fonts.css?v=1.0.0">
        <meta name="theme-color" content="#0a0a0a">
        <link rel="stylesheet" href="/css/style.css?v=4.1.0">
    </head>
    <body class="browser-mode">
        <header class="header">
            <div class="header__inner">
                <a href="/" class="header__logo-link" aria-label="На главную">
                    <div class="header__logo" aria-hidden="true"><span class="header__logo-rage">RAGE</span> <span class="header__logo-fill">FILL</span></div>
                </a>
                <nav class="header__nav" id="main-nav">
                    <a href="/catalog" class="header__nav-link">Каталог</a>
                    <a href="/#benefits" class="header__nav-link">О нас</a>
                    <a href="/#faq" class="header__nav-link">Частые вопросы</a>
                </nav>
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

        <nav class="catalog-breadcrumb" aria-label="Навигация">
            <a href="/">Главная</a>
            <span class="catalog-breadcrumb__sep" aria-hidden="true">
                <svg width="6" height="10" viewBox="0 0 6 10" fill="none"><path d="M1 1l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span>Политика конфиденциальности</span>
        </nav>

        <main>
            <article class="privacy-page" style="max-width: 720px; margin: 0 auto; padding: 32px 20px 40px;">
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
                <p>По вопросам конфиденциальности обращайтесь в Telegram: <a href="https://t.me/{$contactTg}">@{$contactTg}</a></p>
            </article>
        </main>

        {$footer}

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
                });
            })();
        </script>
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

// --- Homepage renderer ---

function renderHomePage(array $config, SeoHelper $seo, \Ragefill\Database $db): string
{
    $contactTg = htmlspecialchars($config['contact_telegram'] ?? 'rage_fill', ENT_QUOTES, 'UTF-8');
    $baseUrl = rtrim($config['base_url'], '/');
    $year = date('Y');
    $footer = renderFooter($config);

    // SEO
    $title = 'RAGE FILL — Острые соусы ручной работы | Минск, Беларусь';
    $desc = 'Острые соусы ручной работы RAGE FILL. Собственные перцы, от лёгкой до экстремальной остроты, натуральные ингредиенты. Каталог, доставка по Беларуси.';
    $url = $baseUrl . '/';

    $metaTags = $seo->buildAboutMeta($title, $desc, $url);

    // FAQ JSON-LD
    $faqLd = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
    foreach (ABOUT_FAQ as $item) {
        $faqLd['mainEntity'][] = [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($item['answer'])],
        ];
    }
    $faqJsonLd = '<script type="application/ld+json">'
        . json_encode($faqLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        . '</script>';
    $orgJsonLd = $seo->organizationJsonLd();
    $websiteJsonLd = $seo->websiteJsonLd();

    // Featured products (is_hit=1, limit 4)
    $allSauces = $db->getAllSauces(true);
    $featured = array_filter($allSauces, fn($s) => ($s['is_hit'] ?? 0) == 1);
    $featured = array_slice($featured, 0, 4);
    if (count($featured) < 3) {
        $featured = array_slice($allSauces, 0, 4);
    }

    $featuredHtml = '';
    foreach ($featured as $s) {
        $name = htmlspecialchars($s['name'], ENT_QUOTES, 'UTF-8');
        $subtitle = htmlspecialchars($s['subtitle'] ?? '', ENT_QUOTES, 'UTF-8');
        $slug = htmlspecialchars($s['slug'] ?? $s['id'], ENT_QUOTES, 'UTF-8');
        $heat = (int)($s['heat_level'] ?? 1);
        $img = !empty($s['image'])
            ? '<img class="home-product__img" src="/uploads/' . htmlspecialchars($s['image'], ENT_QUOTES, 'UTF-8') . '" alt="' . $name . '" loading="lazy">'
            : '<div class="home-product__img-placeholder"></div>';

        $peppers = '';
        for ($i = 0; $i < $heat; $i++) $peppers .= '<span class="pepper active"><img src="/uploads/pepper.svg" alt="" width="16" height="16"></span>';
        for ($i = $heat; $i < 5; $i++) $peppers .= '<span class="pepper dim"><img src="/uploads/pepper.svg" alt="" width="16" height="16"></span>';

        $featuredHtml .= <<<HTML
            <a href="/sauce/{$slug}" class="home-product" data-aos="fade-up" data-aos-delay="{delay}">
                <div class="home-product__image-wrap">{$img}</div>
                <div class="home-product__info">
                    <h3 class="home-product__name">{$name}</h3>
                    <div class="home-product__subtitle">{$subtitle}</div>
                    <div class="home-product__heat">{$peppers} <span class="home-product__heat-label">{$heat}/5</span></div>
                </div>
            </a>
        HTML;
    }
    // Fix delays
    $i = 0;
    $featuredHtml = preg_replace_callback('/{delay}/', function() use (&$i) {
        return (string)($i++ * 100);
    }, $featuredHtml);

    // Benefits
    $benefits = [
        ['icon' => '<img src="/uploads/pepper.svg" alt="" width="36" height="36">', 'title' => 'Собственные перцы', 'text' => 'Выращиваем острые перцы сами: Carolina Reaper, Apocalypse Scorpion, Habanero, Bhut Jolokia и другие.'],
        ['icon' => '<img src="/uploads/branch.svg" alt="" width="36" height="36">', 'title' => 'Натуральный состав', 'text' => 'Готовим по авторским рецептам из натуральных ингредиентов. Без консервантов и красителей.'],
        ['icon' => '<img src="/uploads/gift.svg" alt="" width="36" height="36">', 'title' => 'Идея для подарка', 'text' => 'Подарочные наборы на любой праздник — День рождения, 23 февраля, 8 марта, юбилей.'],
        ['icon' => '<img src="/uploads/fire.svg" alt="" width="36" height="36">', 'title' => 'Только честная острота', 'text' => 'Готовим соусы из натуральных сверхострых перцев без добавления экстракта капсаицина!'],
        ['icon' => '<img src="/uploads/box.svg" alt="" width="36" height="36">', 'title' => 'Доставка по Минску и всей Беларуси (Белпочта, Европочта)', 'text' => 'Доставляем по Минску и всей Беларуси через Белпочту и Европочту.'],
        ['icon' => '<img src="/uploads/pizza.svg" alt="" width="36" height="36">', 'title' => 'Запоминающийся вкус', 'text' => 'Соусы, которые действительно жгут и запоминаются. Яркий вкус для мяса, пиццы, бургеров.'],
    ];

    $benefitsHtml = '';
    $bIdx = 0;
    foreach ($benefits as $b) {
        $delay = $bIdx * 100;
        $benefitsHtml .= <<<HTML
            <div class="benefit-card" data-aos="fade-up" data-aos-delay="{$delay}">
                <div class="benefit-card__icon">{$b['icon']}</div>
                <h3 class="benefit-card__title">{$b['title']}</h3>
                <p class="benefit-card__text">{$b['text']}</p>
            </div>
        HTML;
        $bIdx++;
    }

    // FAQ
    $faqHtml = '';
    foreach (ABOUT_FAQ as $item) {
        $q = htmlspecialchars($item['question'], ENT_QUOTES, 'UTF-8');
        $a = $item['answer'];
        $faqHtml .= <<<HTML
            <details class="faq__item" data-aos="fade-up">
                <summary class="faq__question">{$q}</summary>
                <div class="faq__answer">{$a}</div>
            </details>
        HTML;
    }

    // Reviews
    $reviewsDir = __DIR__ . '/uploads/reviews/';
    $reviewImages = [];
    if (is_dir($reviewsDir)) {
        foreach (scandir($reviewsDir) as $f) {
            if (preg_match('/\.(jpe?g|png|webp)$/i', $f)) $reviewImages[] = $f;
        }
        natsort($reviewImages);
        $reviewImages = array_values($reviewImages);
    }

    $reviewsHtml = '';
    $reviewSrcs = [];
    if (!empty($reviewImages)) {
        foreach ($reviewImages as $idx => $img) {
            $src = '/uploads/reviews/' . htmlspecialchars($img, ENT_QUOTES, 'UTF-8');
            $reviewSrcs[] = $src;
            $delay = $idx * 80;
            $reviewNum = $idx + 1;
            $reviewsHtml .= <<<HTML
                <button type="button" class="home-review" data-review-index="{$idx}" data-aos="fade-up" data-aos-delay="{$delay}">
                    <img class="home-review__img" src="{$src}" alt="Отзыв #{$reviewNum} клиента RAGEFILL об острых соусах" loading="lazy">
                </button>
            HTML;
        }
    } else {
        $reviewsHtml = '<p class="home-reviews__empty">Скоро здесь появятся отзывы наших клиентов!</p>';
    }
    $reviewSrcsJson = htmlspecialchars(json_encode($reviewSrcs), ENT_QUOTES, 'UTF-8');

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
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="stylesheet" href="/css/fonts.css?v=1.0.0">
        <meta name="theme-color" content="#0a0a0a">
        <link rel="stylesheet" href="/css/style.css?v=4.1.0">
        <link rel="stylesheet" href="/css/aos.css?v=2.3.4">
        {$faqJsonLd}
        {$orgJsonLd}
        {$websiteJsonLd}
    </head>
    <body class="browser-mode home-page">

        <header class="header header--home">
            <div class="header__inner">
                <a href="/" class="header__logo-link" aria-label="На главную"><div class="header__logo" aria-hidden="true"><span class="header__logo-rage">RAGE</span> <span class="header__logo-fill">FILL</span></div></a>
                <nav class="header__nav" id="main-nav">
                    <a href="/catalog" class="header__nav-link">Каталог</a>
                    <a href="#benefits" class="header__nav-link">О нас</a>
                    <a href="#faq" class="header__nav-link">Частые вопросы</a>
                    <a href="https://t.me/{$contactTg}" class="header__nav-link header__nav-link--cta" target="_blank" rel="noopener">Написать нам</a>
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
                   <!-- <span class="home-hero__title-sub">Острые соусы ручной работы</span> -->
                </h1>
                <p class="home-hero__tagline" data-aos="fade-up" data-aos-delay="100">Острые соусы ручной работы, маринованные перцы, подарочные наборы и жгучие закуски.</p>
                <p class="home-hero__desc" data-aos="fade-up" data-aos-delay="200">Яркий вкус, натуральные ингредиенты и острота под любой вкус.<br>Идеальный выбор для мяса, пиццы, бургеров и закусок. <br> Доставка по Минску и Беларуси.</p>
                <div class="home-hero__buttons" data-aos="fade-up" data-aos-delay="300">
                    <a href="/catalog" class="home-hero__btn home-hero__btn--primary">Смотреть каталог</a>
                    <a href="https://t.me/{$contactTg}" class="home-hero__btn home-hero__btn--secondary" target="_blank" rel="noopener">Написать нам</a>
                </div>
            </div>
        </section>

        <!-- Featured Products -->
        <section class="home-section home-featured-section">
            <div class="home-container">
                <h2 class="home-section__title" data-aos="fade-up">Популярное</h2>
                <div class="home-featured">
                    {$featuredHtml}
                </div>
                <div class="home-featured__more" data-aos="fade-up">
                    <a href="/catalog" class="home-featured__more-link">Смотреть весь каталог →</a>
                </div>
            </div>
        </section>

        <!-- Benefits -->
        <section class="home-section home-benefits-section" id="benefits">
            <div class="home-container">
                <h2 class="home-section__title" data-aos="fade-up">Почему выбирают нас</h2>
                <div class="benefits-grid">
                    {$benefitsHtml}
                </div>
            </div>
        </section>

        <!-- About -->
        <section class="home-section home-about-section">
            <div class="home-container">
                <div class="home-about" data-aos="fade-up">
                    <p>RAGE FILL — это острые соусы ручной работы из Беларуси. Все соусы готовим небольшими партиями по авторским рецептам. Используем только натуральные ингредиенты и собственные перцы (Carolina Reaper, Apocalypse Scorpion, Big Red Mama, Big Red Mama, 7 POT, Bhut Jolokia, Habanero, The Pain, Jalapeno и другие сорта).</p>
                    <p>Помимо соусов в каталоге представлены подарочные наборы, маринованные перцы, острый арахис и специи. Широкий выбор вкусов и остроты: от легкой до экстремальной. Доставляем по Минску и всей Беларуси.</p>
                </div>
            </div>
        </section>

        <!-- Reviews -->
        <section class="home-section home-reviews-section">
            <div class="home-container">
                <h2 class="home-section__title" data-aos="fade-up">Отзывы</h2>
                <div class="home-reviews-slider" data-aos="fade-up">
                    <button class="home-reviews-slider__nav home-reviews-slider__nav--prev" id="reviews-prev" aria-label="Назад">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
                    </button>
                    <div class="home-reviews" id="home-reviews" data-gallery="{$reviewSrcsJson}">
                        {$reviewsHtml}
                    </div>
                    <button class="home-reviews-slider__nav home-reviews-slider__nav--next" id="reviews-next" aria-label="Вперёд">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 18l6-6-6-6"/></svg>
                    </button>
                </div>
                <div class="home-testimonials" data-aos="fade-up">
                    <div class="home-testimonial">
                        <blockquote class="home-testimonial__text">&laquo;Попробовал ROWAN. Интересный такой вкус. Понравилось, что очень насыщенный. Наверное, можно с любой домашней едой использовать. TORMADO я еще раньше пробовал — его оставлю на стейки, с ним лучше всего.&raquo;</blockquote>
                        <cite class="home-testimonial__author">— Anton Kavaliou</cite>
                    </div>
                    <div class="home-testimonial">
                        <blockquote class="home-testimonial__text">&laquo;Пробовали ваши соусы) все ооочень вкусные и интересные!) Но! Agonix это ад адище 🔥🔥🔥 жарче чем в преисподней) очень крут) ❤️&raquo;</blockquote>
                        <cite class="home-testimonial__author">— Наталья Голик</cite>
                    </div>
                    <div class="home-testimonial">
                        <blockquote class="home-testimonial__text">&laquo;Решила я попробовать Cheron. Грамулечку. Это просто 🔥🔥🔥 Язык пылал. Муж в восторге! Спасибо большое. Мужу реально понравилось, сказал есть вкус. Я никакого вкуса не разобрала, я, мне кажется, обожгла язык 😱&raquo;</blockquote>
                        <cite class="home-testimonial__author">— Света Комарова</cite>
                    </div>
                </div>
                <div class="home-reviews__cta" data-aos="fade-up">
                    <a href="https://www.instagram.com/stories/highlights/18073628308388969/" class="instagram-link" target="_blank" rel="noopener noreferrer">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        Ещё отзывы в Instagram
                    </a>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section class="home-section home-faq-section" id="faq">
            <div class="home-container">
                <h2 class="home-section__title" data-aos="fade-up">Частые вопросы</h2>
                <div class="faq__list">
                    {$faqHtml}
                </div>
            </div>
        </section>

        </main>

        {$footer}

        <script src="/js/scroll-top.js?v=1.0.0" data-cfasync="false"></script>
        <script src="/js/lightbox.js?v=3.0.0" data-cfasync="false"></script>
        <script src="/js/slider.js?v=1.0.0" data-cfasync="false"></script>
        <script src="/js/aos.js?v=2.3.4" data-cfasync="false"></script>
        <script>
            AOS.init({ duration: 700, once: true, offset: 50 });

            // Reviews slider + lightbox
            (function() {
                var container = document.getElementById('home-reviews');
                if (!container) return;

                Slider.init({
                    track: container,
                    prev: document.getElementById('reviews-prev'),
                    next: document.getElementById('reviews-next'),
                    autoPlay: 4000
                });

                var images = [];
                try { images = JSON.parse(container.dataset.gallery || '[]'); } catch(e) {}
                if (!images.length) return;
                container.addEventListener('click', function(e) {
                    var btn = e.target.closest('[data-review-index]');
                    if (!btn) return;
                    Lightbox.open({ images: images, startIndex: parseInt(btn.dataset.reviewIndex) || 0 });
                });
            })();

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

            // Smooth scroll for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(a => {
                a.addEventListener('click', e => {
                    const target = document.querySelector(a.getAttribute('href'));
                    if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
                });
            });

            // Burger menu
            (function(){
                var btn=document.getElementById('burger-btn'),nav=document.getElementById('main-nav');
                if(!btn||!nav)return;
                btn.addEventListener('click',function(){
                    var open=nav.classList.toggle('open');
                    btn.classList.toggle('open',open);
                    btn.setAttribute('aria-expanded',String(open));
                    document.body.classList.toggle('menu-open',open);
                });
                nav.querySelectorAll('a').forEach(function(a){
                    a.addEventListener('click',function(){
                        nav.classList.remove('open');
                        btn.classList.remove('open');
                        btn.setAttribute('aria-expanded','false');
                        document.body.classList.remove('menu-open');
                    });
                });
            })();

            // Home search → redirect to catalog
            (function(){
                var input=document.getElementById('home-search-input');
                if(!input)return;
                input.addEventListener('keydown',function(e){
                    if(e.key==='Enter'&&input.value.trim()){
                        window.location.href='/catalog?q='+encodeURIComponent(input.value.trim());
                    }
                });
            })();
        </script>
    </body>
    </html>
    HTML;
}
