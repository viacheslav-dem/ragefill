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
$auth = new AuthMiddleware($config['admin_password_hash'], $config['token_secret']);
$seo = new SeoHelper($config['base_url']);

/** Return filemtime of a public asset as cache-buster string. */
function asset_v(string $file): string {
    $path = __DIR__ . '/' . ltrim($file, '/');
    return file_exists($path) ? (string) filemtime($path) : '0';
}

/** Replace {{V_*}} asset version placeholders in HTML templates (catalog.html, admin.html). */
function replace_asset_versions(string $html): string {
    return str_replace(
        ['{{V_FONTS}}', '{{V_STYLE}}', '{{V_CATALOG}}', '{{V_ADMIN}}', '{{V_SCROLL}}'],
        [asset_v('css/fonts.css'), asset_v('css/style.css'), asset_v('js/catalog.js'), asset_v('js/admin.js'), asset_v('js/scroll-top.js')],
        $html
    );
}

/** Render a PHP template with extracted variables. */
function render(string $template, array $vars = []): string {
    extract($vars, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/../templates/' . $template;
    return ob_get_clean();
}

const DEFAULT_FAQ = [
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

const DEFAULT_BENEFITS = [
    ['icon' => 'pepper.svg', 'title' => 'Собственные перцы', 'text' => 'Выращиваем острые перцы сами: Carolina Reaper, Apocalypse Scorpion, Habanero, Bhut Jolokia и другие.'],
    ['icon' => 'branch.svg', 'title' => 'Натуральный состав', 'text' => 'Готовим по авторским рецептам из натуральных ингредиентов. Без консервантов и красителей.'],
    ['icon' => 'gift.svg', 'title' => 'Идея для подарка', 'text' => 'Подарочные наборы на любой праздник — День рождения, 23 февраля, 8 марта, юбилей.'],
    ['icon' => 'fire.svg', 'title' => 'Только честная острота', 'text' => 'Готовим соусы из натуральных сверхострых перцев без добавления экстракта капсаицина!'],
    ['icon' => 'box.svg', 'title' => 'Доставка по Беларуси', 'text' => ' Ускоренная отправка на следующий день после заказа. Белпочта, Европочта.'],
    ['icon' => 'pizza.svg', 'title' => 'Запоминающийся вкус', 'text' => 'Соусы, которые действительно жгут и запоминаются. Яркий вкус для мяса, пиццы, бургеров.'],
];

const DEFAULT_TESTIMONIALS = [
    ['author' => 'Anton Kavaliou', 'text' => 'Попробовал ROWAN. Интересный такой вкус. Понравилось, что очень насыщенный. Наверное, можно с любой домашней едой использовать. TORMADO я еще раньше пробовал — его оставлю на стейки, с ним лучше всего.'],
    ['author' => 'Наталья Голик', 'text' => 'Пробовали ваши соусы) все ооочень вкусные и интересные!) Но! Agonix это ад адище 🔥🔥🔥 жарче чем в преисподней) очень крут) ❤️'],
    ['author' => 'Света Комарова', 'text' => 'Решила я попробовать Cheron. Грамулечку. Это просто 🔥🔥🔥 Язык пылал. Муж в восторге! Спасибо большое. Мужу реально понравилось, сказал есть вкус. Я никакого вкуса не разобрала, я, мне кажется, обожгла язык 😱'],
];

const DEFAULT_ABOUT = '<p>RAGE FILL — это острые соусы ручной работы из Беларуси. Все соусы готовим небольшими партиями по авторским рецептам. Используем только натуральные ингредиенты и собственные перцы (Carolina Reaper, Apocalypse Scorpion, Big Red Mama, Big Red Mama, 7 POT, Bhut Jolokia, Habanero, The Pain, Jalapeno и другие сорта).</p><p>Помимо соусов в каталоге представлены подарочные наборы, маринованные перцы, острый арахис и специи. Широкий выбор вкусов и остроты: от легкой до экстремальной. Доставляем по Минску и всей Беларуси.</p>';

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(
    displayErrorDetails: (bool)($config['debug'] ?? false),
    logErrors: true,
    logErrorDetails: true,
);

// Custom 404/405 handler — render friendly page
$errorMiddleware->setErrorHandler(
    \Slim\Exception\HttpNotFoundException::class,
    function (Request $request, \Throwable $exception, bool $displayErrorDetails) use ($config) {
        $response = new \Slim\Psr7\Response();
        $title = 'Страница не найдена — RAGE FILL';
        $contactTg = htmlspecialchars($config['contact_telegram'] ?? '', ENT_QUOTES, 'UTF-8');
        ob_start();
        include __DIR__ . '/../templates/product-404.php';
        $html = ob_get_clean();
        $response->getBody()->write($html);
        return $response->withStatus(404);
    }
);
$errorMiddleware->setErrorHandler(
    \Slim\Exception\HttpMethodNotAllowedException::class,
    function (Request $request, \Throwable $exception, bool $displayErrorDetails) use ($config) {
        $response = new \Slim\Psr7\Response();
        $title = 'Страница не найдена — RAGE FILL';
        $contactTg = htmlspecialchars($config['contact_telegram'] ?? '', ENT_QUOTES, 'UTF-8');
        ob_start();
        include __DIR__ . '/../templates/product-404.php';
        $html = ob_get_clean();
        $response->getBody()->write($html);
        return $response->withStatus(404);
    }
);

// CORS middleware — жёсткий allowlist.
// Публичное API (/api/sauces, /api/settings, /api/categories) отражает только свой origin.
// /api/admin/* CORS вообще не отдаёт — запросы должны идти только с нашей same-origin админки.
$app->add(function (Request $request, $handler) use ($config) {
    $response = $handler->handle($request);

    $path = $request->getUri()->getPath();
    $origin = $request->getHeaderLine('Origin');
    $allowedOrigin = rtrim($config['base_url'] ?? '', '/');

    $isAdminApi = str_starts_with($path, '/api/admin/');
    $isPublicApi = str_starts_with($path, '/api/') && !$isAdminApi;

    if ($isPublicApi && $origin !== '' && $origin === $allowedOrigin) {
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
            ->withHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
    }
    // admin-эндпоинты: никаких CORS-заголовков → браузер заблокирует кросс-оригин.

    // Prevent search engines from indexing API responses
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

// Get all categories (public)
$app->get('/api/categories', function (Request $request, Response $response) use ($db) {
    $categories = $db->getAllCategories();
    $response->getBody()->write(json_encode($categories, JSON_UNESCAPED_UNICODE));
    return $response->withHeader('Content-Type', 'application/json');
});

// --- Auth ---

$app->post('/api/auth/login', function (Request $request, Response $response) use ($auth, $config) {
    // Rate limiting: max 5 attempts per IP per 15 minutes
    $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitDir = dirname($config['db_path']) . '/rate_limit';
    if (!is_dir($rateLimitDir)) @mkdir($rateLimitDir, 0755, true);
    $rateLimitFile = $rateLimitDir . '/' . md5($ip) . '.json';
    $maxAttempts = 5;
    $windowSeconds = 900;
    $attempts = [];
    if (is_file($rateLimitFile)) {
        $attempts = json_decode(file_get_contents($rateLimitFile), true) ?: [];
        $attempts = array_values(array_filter($attempts, fn($t) => $t > time() - $windowSeconds));
    }
    if (count($attempts) >= $maxAttempts) {
        $response->getBody()->write(json_encode(['error' => 'Слишком много попыток. Попробуйте через 15 минут.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(429);
    }

    $data = $request->getParsedBody();
    $password = (string)($data['password'] ?? '');

    $token = $auth->generateToken($password);
    if (!$token) {
        $attempts[] = time();
        file_put_contents($rateLimitFile, json_encode($attempts));
        $response->getBody()->write(json_encode(['error' => 'Неверный пароль']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
    }

    // Clear attempts on successful login
    if (is_file($rateLimitFile)) @unlink($rateLimitFile);

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

    // Bulk operations (must be before {id} route)
    $group->post('/sauces/bulk', function (Request $request, Response $response) use ($db, $config) {
        $data = $request->getParsedBody() ?? [];
        $ids = $data['ids'] ?? [];
        $action = (string)($data['action'] ?? '');

        if (empty($ids) || !is_array($ids)) {
            $response->getBody()->write(json_encode(['error' => 'Не выбраны товары']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $pdo = $db->getPdo();
        $pdo->beginTransaction();
        try {
            foreach ($ids as $id) {
                $id = (int)$id;
                switch ($action) {
                    case 'activate':
                        $db->updateSauce($id, ['is_active' => 1]);
                        break;
                    case 'deactivate':
                        $db->updateSauce($id, ['is_active' => 0]);
                        break;
                    case 'in_stock':
                        $db->updateSauce($id, ['in_stock' => 1]);
                        break;
                    case 'out_stock':
                        $db->updateSauce($id, ['in_stock' => 0]);
                        break;
                    case 'delete':
                        $sauce = $db->getSauceById($id);
                        if ($sauce) {
                            deleteImageFile($sauce['image'], $config);
                            $additionalImages = json_decode($sauce['images'] ?? '[]', true) ?: [];
                            foreach ($additionalImages as $img) {
                                deleteImageFile($img, $config);
                            }
                            $db->deleteSauce($id);
                        }
                        break;
                }
            }
            $pdo->commit();
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $pdo->rollBack();
            $response->getBody()->write(json_encode(['error' => 'Ошибка при обработке']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Reorder sauces (must be before {id} route)
    $group->post('/sauces/reorder', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody() ?? [];
        $order = $data['order'] ?? [];
        if (is_array($order)) {
            $pdo = $db->getPdo();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE sauces SET sort_order = ? WHERE id = ?");
            foreach ($order as $i => $id) {
                $stmt->execute([$i, (int)$id]);
            }
            $pdo->commit();
        }
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
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

    // --- Site Settings ---

    // Get all site settings
    $group->get('/site-settings', function (Request $request, Response $response) use ($db) {
        $settings = $db->getAllSettings();
        // Decode JSON values for the client
        $decoded = [];
        foreach ($settings as $k => $v) {
            $json = json_decode($v, true);
            $decoded[$k] = $json !== null ? $json : $v;
        }
        $response->getBody()->write(json_encode($decoded, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Update site settings (batch)
    $group->post('/site-settings', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody() ?? [];
        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'Нет данных']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $allowedKeys = [
            'hero_title', 'hero_tagline', 'hero_description',
            'hero_btn_primary', 'hero_btn_secondary',
            'benefits', 'about_text', 'testimonials', 'faq',
            'contact_telegram', 'instagram_reviews_url',
            'featured_title', 'featured_product_ids',
            'section_title_benefits', 'section_title_reviews', 'section_title_faq',
            'footer_tagline', 'footer_about',
            'peppers', 'peppers_page_title', 'peppers_page_intro',
            'catalog_page_title', 'catalog_page_intro',
        ];

        $toSave = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, $allowedKeys, true)) {
                continue;
            }
            $toSave[$key] = is_array($value)
                ? json_encode($value, JSON_UNESCAPED_UNICODE)
                : (string)$value;
        }

        if (!empty($toSave)) {
            $db->setMultipleSettings($toSave);
        }

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // --- Categories CRUD ---

    $group->get('/categories', function (Request $request, Response $response) use ($db) {
        $categories = $db->getAllCategories();
        $response->getBody()->write(json_encode($categories, JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->post('/categories', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody() ?? [];
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            $response->getBody()->write(json_encode(['error' => 'Название обязательно']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
        try {
            $id = $db->createCategory($data);
            $cat = $db->getCategoryById($id);
            $response->getBody()->write(json_encode($cat, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode(['error' => 'Slug уже существует']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    });

    // Reorder categories (must be before {id} route)
    $group->post('/categories/reorder', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody() ?? [];
        $order = $data['order'] ?? [];
        if (is_array($order)) {
            $pdo = $db->getPdo();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
            foreach ($order as $i => $id) {
                $stmt->execute([$i, (int)$id]);
            }
            $pdo->commit();
        }
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->post('/categories/{id}', function (Request $request, Response $response, array $args) use ($db) {
        $id = (int)$args['id'];
        $data = $request->getParsedBody() ?? [];
        try {
            $db->updateCategory($id, $data);
            $cat = $db->getCategoryById($id);
            $response->getBody()->write(json_encode($cat, JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            $response->getBody()->write(json_encode(['error' => 'Slug уже существует']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }
    });

    $group->delete('/categories/{id}', function (Request $request, Response $response, array $args) use ($db) {
        $id = (int)$args['id'];
        $result = $db->deleteCategory($id);
        if ($result === true) {
            $response->getBody()->write(json_encode(['success' => true]));
            return $response->withHeader('Content-Type', 'application/json');
        }
        $response->getBody()->write(json_encode(['error' => $result]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    });

    // --- Review images ---

    $group->get('/reviews', function (Request $request, Response $response) use ($db) {
        $stored = $db->getSettingJson('review_images');
        if (is_array($stored)) {
            $response->getBody()->write(json_encode($stored, JSON_UNESCAPED_UNICODE));
        } else {
            // Fallback: scan directory
            $reviewsDir = __DIR__ . '/uploads/reviews/';
            $images = [];
            if (is_dir($reviewsDir)) {
                foreach (scandir($reviewsDir) as $f) {
                    if (preg_match('/\.(jpe?g|png|webp)$/i', $f)) $images[] = $f;
                }
                natsort($images);
                $images = array_values($images);
            }
            $response->getBody()->write(json_encode($images, JSON_UNESCAPED_UNICODE));
        }
        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->post('/reviews', function (Request $request, Response $response) use ($db, $config) {
        $files = $request->getUploadedFiles();
        $uploaded = [];
        $reviewFiles = $files['images'] ?? [];
        if (!is_array($reviewFiles)) $reviewFiles = [$reviewFiles];

        $reviewsDir = __DIR__ . '/uploads/reviews/';
        if (!is_dir($reviewsDir)) mkdir($reviewsDir, 0755, true);

        foreach (array_slice($reviewFiles, 0, 20) as $file) {
            if ($file->getError() !== UPLOAD_ERR_OK) continue;

            $stream = $file->getStream();
            $tmpPath = tempnam(sys_get_temp_dir(), 'ragefill_rev_');
            file_put_contents($tmpPath, $stream);

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $realMime = $finfo->file($tmpPath);
            if (!in_array($realMime, $config['allowed_types'], true)) {
                unlink($tmpPath);
                continue;
            }
            $imageInfo = @getimagesize($tmpPath);
            if ($imageInfo === false) { unlink($tmpPath); continue; }

            $filename = bin2hex(random_bytes(16)) . '.webp';

            // Convert to WebP without square crop
            $src = match ($realMime) {
                'image/jpeg' => @imagecreatefromjpeg($tmpPath),
                'image/png' => @imagecreatefrompng($tmpPath),
                'image/webp' => @imagecreatefromwebp($tmpPath),
                default => false,
            };
            if ($src) {
                $w = imagesx($src);
                $h = imagesy($src);
                $maxW = 1200;
                if ($w > $maxW) {
                    $newH = (int)round($h * $maxW / $w);
                    $dst = imagecreatetruecolor($maxW, $newH);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $newH, $w, $h);
                    imagedestroy($src);
                    $src = $dst;
                }
                imagewebp($src, $reviewsDir . $filename, 85);
                imagedestroy($src);
                unlink($tmpPath);
            } else {
                rename($tmpPath, $reviewsDir . $filename);
            }
            chmod($reviewsDir . $filename, 0644);
            $uploaded[] = $filename;
        }

        // Update stored list
        $stored = $db->getSettingJson('review_images') ?? [];
        if (!is_array($stored)) {
            // First time: migrate from directory scan
            $stored = [];
            if (is_dir($reviewsDir)) {
                foreach (scandir($reviewsDir) as $f) {
                    if (preg_match('/\.(jpe?g|png|webp)$/i', $f) && !in_array($f, $uploaded)) $stored[] = $f;
                }
                natsort($stored);
                $stored = array_values($stored);
            }
        }
        $stored = array_merge($stored, $uploaded);
        $db->setSettingJson('review_images', $stored);

        $response->getBody()->write(json_encode(['success' => true, 'images' => $stored], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->delete('/reviews/{filename}', function (Request $request, Response $response, array $args) use ($db) {
        $filename = basename($args['filename']);
        $path = __DIR__ . '/uploads/reviews/' . $filename;
        if (is_file($path)) @unlink($path);

        $stored = $db->getSettingJson('review_images') ?? [];
        $stored = array_values(array_filter($stored, fn($f) => $f !== $filename));
        $db->setSettingJson('review_images', $stored);

        $response->getBody()->write(json_encode(['success' => true, 'images' => $stored], JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $group->post('/reviews/reorder', function (Request $request, Response $response) use ($db) {
        $data = $request->getParsedBody() ?? [];
        $order = $data['order'] ?? [];
        if (is_array($order)) {
            $db->setSettingJson('review_images', array_values($order));
        }
        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Upload pepper image
    $group->post('/peppers/upload', function (Request $request, Response $response) use ($config) {
        $files = $request->getUploadedFiles();
        $file = $files['image'] ?? null;
        if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
            $response->getBody()->write(json_encode(['error' => 'No file']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $peppersDir = __DIR__ . '/uploads/peppers/';
        if (!is_dir($peppersDir)) mkdir($peppersDir, 0755, true);

        $stream = $file->getStream();
        $tmpPath = tempnam(sys_get_temp_dir(), 'ragefill_pep_');
        file_put_contents($tmpPath, $stream);

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($tmpPath);
        if (!in_array($realMime, $config['allowed_types'], true)) {
            unlink($tmpPath);
            $response->getBody()->write(json_encode(['error' => 'Invalid type']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $filename = bin2hex(random_bytes(16)) . '.webp';

        $src = match ($realMime) {
            'image/jpeg' => @imagecreatefromjpeg($tmpPath),
            'image/png' => @imagecreatefrompng($tmpPath),
            'image/webp' => @imagecreatefromwebp($tmpPath),
            default => false,
        };
        if ($src) {
            $w = imagesx($src);
            $h = imagesy($src);
            $maxW = 400;
            if ($w > $maxW) {
                $newH = (int)round($h * $maxW / $w);
                $dst = imagecreatetruecolor($maxW, $newH);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxW, $newH, $w, $h);
                imagedestroy($src);
                $src = $dst;
            }
            imagewebp($src, $peppersDir . $filename, 85);
            imagedestroy($src);
            unlink($tmpPath);
        } else {
            rename($tmpPath, $peppersDir . $filename);
        }
        @chmod($peppersDir . $filename, 0644);

        $response->getBody()->write(json_encode(['filename' => $filename]));
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

    // Catalog page title & intro (editable from admin)
    $catalogSettings = $db->getAllSettings();
    $catalogTitle = htmlspecialchars($catalogSettings['catalog_page_title'] ?? 'Каталог соусов и жгучих закусок RAGE FILL', ENT_QUOTES, 'UTF-8');
    $catalogIntro = htmlspecialchars($catalogSettings['catalog_page_intro'] ?? '', ENT_QUOTES, 'UTF-8');
    $html = str_replace('{{CATALOG_TITLE}}', $catalogTitle, $html);
    $html = str_replace('{{CATALOG_INTRO}}', nl2br($catalogIntro), $html);

    // Render shared header partial
    $headerClass = 'header--catalog';
    $headerBrowserOnly = true;
    $headerSearchId = 'desktop-search-input';
    ob_start();
    include __DIR__ . '/../templates/partials/header.php';
    $headerHtml = ob_get_clean();
    $html = str_replace('{{HEADER}}', $headerHtml, $html);

    // Render shared footer partial
    $footerVars = getFooterVars($db);
    $footerTagline = $footerVars['footerTagline'];
    $footerAbout = $footerVars['footerAbout'];
    ob_start();
    include __DIR__ . '/../templates/partials/footer.php';
    $footerHtml = ob_get_clean();
    $html = str_replace('{{FOOTER}}', $footerHtml, $html);

    // Dynamic category chips/sidebar from DB
    $cats = $db->getAllCategories();
    $categoryChips = '';
    $categorySidebar = '';
    foreach ($cats as $cat) {
        $catSlug = htmlspecialchars($cat['slug'], ENT_QUOTES, 'UTF-8');
        $catName = htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8');
        $categoryChips .= '<button class="toolbar__chip" data-category="' . $catSlug . '" role="radio" aria-checked="false">' . $catName . '</button>' . "\n";
        $categorySidebar .= '<button class="catalog-sidebar__option" data-category="' . $catSlug . '" role="radio" aria-checked="false"><span class="catalog-sidebar__radio"></span>' . $catName . '</button>' . "\n";
    }
    $html = str_replace('{{CATEGORY_CHIPS}}', $categoryChips, $html);
    $html = str_replace('{{CATEGORY_SIDEBAR}}', $categorySidebar, $html);

    $ssrHtml = '';
    foreach ($sauces as $sauce) {
        $ssrHtml .= $seo->renderProductCard($sauce);
    }
    $html = str_replace('{{SSR_PRODUCTS}}', $ssrHtml, $html);

    // Inject sauce data for JS to avoid a duplicate API fetch on initial load
    $ssrJson = json_encode($sauces, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    $html = str_replace('</body>', "<script>window.__SSR_SAUCES__={$ssrJson};</script>\n</body>", $html);
    $html = replace_asset_versions($html);

    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->get('/admin', function (Request $request, Response $response) {
    $html = file_get_contents(__DIR__ . '/admin.html');
    $html = replace_asset_versions($html);
    $response->getBody()->write($html);
    return $response
        ->withHeader('Content-Type', 'text/html; charset=utf-8')
        ->withHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

// --- About redirects to homepage ---

// --- Privacy Policy ---

$app->get('/privacy', function (Request $request, Response $response) use ($config, $seo, $db) {
    $html = renderPrivacyPage($config, $seo, $db);
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->get('/peppers', function (Request $request, Response $response) use ($config, $seo, $db) {
    $html = renderPeppersPage($config, $seo, $db);
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
});

$app->run();

// --- Helpers ---

function handleUpload(object $uploadedFile, array $config): ?string
{
    // Check reported size before writing to disk (early rejection of huge uploads)
    $reportedSize = $uploadedFile->getSize();
    if ($reportedSize !== null && $reportedSize > $config['max_upload_size']) {
        return null;
    }

    // Validate by actual file content, not client-provided MIME
    $stream = $uploadedFile->getStream();
    $tmpPath = tempnam(sys_get_temp_dir(), 'ragefill_');
    file_put_contents($tmpPath, $stream);

    $size = filesize($tmpPath);
    if ($size > $config['max_upload_size']) {
        unlink($tmpPath);
        return null;
    }

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

    // Category label from DB (needed for title)
    $category = $sauce['category'] ?? 'sauce';
    $allCategories = $db->getAllCategories();
    $categoryLabel = 'Соус';
    foreach ($allCategories as $cat) {
        if ($cat['slug'] === $category) { $categoryLabel = $cat['name']; break; }
    }

    $stockText = $inStock ? 'В наличии' : 'Нет в наличии';
    $stockClass = $inStock ? 'in' : 'out';

    $baseUrl = rtrim($config['base_url'], '/');
    $sauceSlug = htmlspecialchars($sauce['slug'] ?? (string)$id, ENT_QUOTES, 'UTF-8');
    $url = $baseUrl . '/sauce/' . $sauceSlug;
    $metaTags = $seo->productMeta($sauce);
    $jsonLd = $seo->productJsonLd($sauce);
    $breadcrumbLd = $seo->breadcrumbJsonLd($sauce['name'], $sauceSlug);
    $titleSuffix = mb_strtolower($categoryLabel, 'UTF-8') . ' RAGE FILL';
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

    // Use custom SEO title if set
    $customTitle = trim($sauce['meta_title'] ?? '');
    if ($customTitle !== '') {
        $title = htmlspecialchars($customTitle, ENT_QUOTES, 'UTF-8');
    }

    // Footer vars
    $footerVars = getFooterVars($db);
    $footerTagline = $footerVars['footerTagline'];
    $footerAbout = $footerVars['footerAbout'];

    $hreflangUrl = $url;
    return render('product.php', compact(
        'title', 'metaTags', 'hreflangUrl', 'jsonLd', 'breadcrumbLd',
        'name', 'subtitleHtml', 'categoryLabel', 'image', 'thumbsHtml',
        'stockClass', 'stockText', 'volumeHtml', 'heat', 'heatLabel', 'heatBar',
        'descSection', 'compSection', 'contactTg', 'ctaText', 'ctaIcon', 'relatedHtml',
        'footerTagline', 'footerAbout'
    ));
}

function renderProductNotFound(): string
{
    return render('product-404.php', ['title' => 'Товар не найден — RAGEFILL']);
}

function renderPrivacyPage(array $config, SeoHelper $seo, Database $db): string
{
    $baseUrl = rtrim($config['base_url'], '/');
    $title = 'Политика конфиденциальности — RAGE FILL';
    $desc = 'Политика конфиденциальности интернет-магазина острых соусов RAGE FILL.';
    $url = $baseUrl . '/privacy';
    $metaTags = $seo->buildAboutMeta($title, $desc, $url);
    $contactTg = htmlspecialchars($config['contact_telegram'] ?? 'rage_fill', ENT_QUOTES, 'UTF-8');

    $footerVars = getFooterVars($db);
    $footerTagline = $footerVars['footerTagline'];
    $footerAbout = $footerVars['footerAbout'];

    return render('privacy.php', compact('title', 'metaTags', 'contactTg', 'footerTagline', 'footerAbout'));
}

function renderPeppersPage(array $config, SeoHelper $seo, Database $db): string
{
    $baseUrl = rtrim($config['base_url'], '/');
    $title = 'Сорта перцев для соусов — RAGE FILL | Шкала Сковилла';
    $desc = 'Перцы RAGE FILL: Carolina Reaper, Trinidad Scorpion, Habanero и другие. Уровни остроты по шкале Сковилла, описания и характеристики сверхострых перцев.';
    $url = $baseUrl . '/peppers';
    $metaTags = $seo->buildAboutMeta($title, $desc, $url);
    $hreflangUrl = $url;
    $contactTg = htmlspecialchars($config['contact_telegram'] ?? 'rage_fill', ENT_QUOTES, 'UTF-8');

    $settings = $db->getAllSettings();
    $peppersRaw = $settings['peppers'] ?? null;
    $peppers = $peppersRaw !== null ? (json_decode($peppersRaw, true) ?? []) : [];

    usort($peppers, fn($a, $b) => ($b['scoville_max'] ?? 0) <=> ($a['scoville_max'] ?? 0));

    $pageTitle = htmlspecialchars($settings['peppers_page_title'] ?? 'Наши перцы', ENT_QUOTES, 'UTF-8');
    $pageIntro = htmlspecialchars($settings['peppers_page_intro'] ?? '', ENT_QUOTES, 'UTF-8');

    $maxScoville = 1;
    foreach ($peppers as $p) {
        $s = (int)($p['scoville_max'] ?? 0);
        if ($s > $maxScoville) $maxScoville = $s;
    }

    $peppersHtml = '';
    $jsonLdItems = [];
    foreach ($peppers as $idx => $p) {
        $name = htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $sMin = (int)($p['scoville_min'] ?? 0);
        $sMax = (int)($p['scoville_max'] ?? 0);
        $descText = htmlspecialchars($p['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $pct = round($sMax / $maxScoville * 100);
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($p['name'] ?? ''));
        $slug = trim($slug, '-') ?: 'pepper-' . ($idx + 1);
        $image = $p['image'] ?? '';
        $imageSafe = htmlspecialchars($image, ENT_QUOTES, 'UTF-8');

        $tier = match (true) {
            $sMax >= 2000000 => 'extreme',
            $sMax >= 1500000 => 'fire',
            $sMax >= 1000000 => 'hot',
            $sMax >= 500000 => 'medium',
            default => 'mild',
        };

        $sMinFmt = number_format($sMin, 0, '', ' ');
        $sMaxFmt = number_format($sMax, 0, '', ' ');
        $imgHtml = $image
            ? '<img class="pepper-row__img" src="/uploads/peppers/' . $imageSafe . '" alt="' . $name . '" width="80" height="80" loading="lazy">'
            : '<div class="pepper-row__img-placeholder"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C9.5 2 7.5 4 7.5 6.5c0 1.5.7 2.8 1.8 3.7L8 22h8l-1.3-11.8c1.1-.9 1.8-2.2 1.8-3.7C16.5 4 14.5 2 12 2z"/></svg></div>';

        $peppersHtml .= <<<HTML
            <tr class="pepper-row pepper-row--{$tier}" id="{$slug}">
                <td class="pepper-row__accent-cell"><div class="pepper-row__accent"></div></td>
                <td class="pepper-row__photo">{$imgHtml}</td>
                <td class="pepper-row__name"><h2>{$name}</h2></td>
                <td class="pepper-row__shu"><data value="{$sMax}">{$sMinFmt} — {$sMaxFmt}</data><span class="pepper-row__shu-unit">SHU</span><div class="pepper-row__shu-bar"><div class="pepper-row__bar-track"><div class="pepper-row__bar-fill pepper-row__bar-fill--{$tier}" style="width:{$pct}%"></div></div></div></td>
                <td class="pepper-row__desc">{$descText}</td>
                <td class="pepper-row__expand-btn"><button type="button" class="pepper-expand" aria-label="Подробнее" aria-expanded="false"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg></button></td>
                <td class="pepper-row__heat-bottom"><div class="pepper-row__heat-bottom-fill"></div></td>
            </tr>
            <tr class="pepper-row__detail pepper-row__detail--{$tier}" aria-hidden="true">
                <td colspan="7">
                    <div class="pepper-detail__inner">
                        <div class="pepper-detail__img-wrap">{$imgHtml}</div>
                        <div class="pepper-detail__meta">
                            <div class="pepper-detail__shu"><data value="{$sMax}">{$sMinFmt} — {$sMaxFmt}</data> <span class="pepper-row__shu-unit">SHU</span></div>
                            <div class="pepper-detail__bar"><div class="pepper-row__bar-track"><div class="pepper-row__bar-fill pepper-row__bar-fill--{$tier}" style="width:{$pct}%"></div></div></div>
                        </div>
                        <p class="pepper-detail__text">{$descText}</p>
                        <a href="/catalog?q={$name}" class="pepper-detail__catalog-link">Найти продукцию с этим перцем</a>
                    </div>
                </td>
            </tr>
        HTML;

        $jsonLdItems[] = [
            '@type' => 'ListItem',
            'position' => $idx + 1,
            'name' => $p['name'] ?? '',
            'url' => $url . '#' . $slug,
        ];
    }

    // JSON-LD: BreadcrumbList
    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => $baseUrl . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Наши перцы', 'item' => $url],
        ],
    ];

    // JSON-LD: ItemList
    $itemListLd = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => 'Сорта перцев RAGE FILL',
        'numberOfItems' => count($peppers),
        'itemListElement' => $jsonLdItems,
    ];

    $jsonLdFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
    $extraHead = '<script type="application/ld+json">' . json_encode($breadcrumbLd, $jsonLdFlags) . '</script>' . "\n"
        . '    <script type="application/ld+json">' . json_encode($itemListLd, $jsonLdFlags) . '</script>';

    $footerVars = getFooterVars($db);
    $footerTagline = $footerVars['footerTagline'];
    $footerAbout = $footerVars['footerAbout'];

    return render('peppers.php', compact(
        'title', 'metaTags', 'extraHead', 'hreflangUrl', 'contactTg',
        'pageTitle', 'pageIntro', 'peppersHtml',
        'footerTagline', 'footerAbout'
    ));
}

/**
 * Санитайзер HTML на DOMDocument-allowlist.
 * Всё, что не входит в $allowedTags, распаковывается (контент сохраняется как текст).
 * Атрибуты не в $allowedAttrs удаляются. В <a> разрешены только http(s)/mailto.
 */
function sanitizeHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowedTags  = ['p', 'br', 'strong', 'em', 'u', 'b', 'i', 'ul', 'ol', 'li', 'a'];
    $allowedAttrs = ['a' => ['href']];

    $dom = new \DOMDocument('1.0', 'UTF-8');
    $prevErrors = libxml_use_internal_errors(true);
    // XML-декларация + обёртка принуждают libxml трактовать ввод как UTF-8 без добавления <html>/<body>
    $wrapped = '<?xml encoding="UTF-8"?><div id="__sanitize_root__">' . $html . '</div>';
    $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prevErrors);

    $root = $dom->getElementById('__sanitize_root__');
    if (!$root) {
        return '';
    }

    $filter = static function (\DOMNode $node) use (&$filter, $allowedTags, $allowedAttrs): void {
        // Снимок детей — живой NodeList ломается при удалении/перемещении
        $children = iterator_to_array($node->childNodes);
        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                // Post-order: сначала чистим детей, потом решаем про сам элемент
                $filter($child);

                $tag = strtolower($child->nodeName);
                if (!in_array($tag, $allowedTags, true)) {
                    // Распаковка: переносим уже-очищенных детей в родителя, удаляем обёртку
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                // Чистим атрибуты
                $allow = $allowedAttrs[$tag] ?? [];
                $toRemove = [];
                foreach ($child->attributes as $attr) {
                    if (!in_array(strtolower($attr->nodeName), $allow, true)) {
                        $toRemove[] = $attr->nodeName;
                    }
                }
                foreach ($toRemove as $name) {
                    $child->removeAttribute($name);
                }

                // Валидация href у <a>: только http(s) / mailto
                if ($tag === 'a' && $child->hasAttribute('href')) {
                    $href = trim($child->getAttribute('href'));
                    if (!preg_match('#^(https?://|mailto:)#i', $href)) {
                        $child->removeAttribute('href');
                    } else {
                        $child->setAttribute('target', '_blank');
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }
                }
            } elseif ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);
            }
            // DOMText и прочее — оставляем
        }
    };
    $filter($root);

    $result = '';
    foreach ($root->childNodes as $child) {
        $result .= $dom->saveHTML($child);
    }
    return $result;
}

function getFooterVars(Database $db): array
{
    $settings = $db->getAllSettings();
    return [
        'footerTagline' => htmlspecialchars($settings['footer_tagline'] ?? 'Острые соусы ручной работы, Беларусь', ENT_QUOTES, 'UTF-8'),
        'footerAbout' => htmlspecialchars($settings['footer_about'] ?? 'Все соусы изготавливаются вручную из свежих перцев. Для заказа свяжитесь с нами через Telegram.', ENT_QUOTES, 'UTF-8'),
    ];
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
    // Load site settings from DB (with fallbacks)
    $siteSettings = $db->getAllSettings();

    $contactTgRaw = $siteSettings['contact_telegram'] ?? $config['contact_telegram'] ?? 'rage_fill';
    $contactTg = htmlspecialchars($contactTgRaw, ENT_QUOTES, 'UTF-8');
    $baseUrl = rtrim($config['base_url'], '/');
    // SEO
    $title = 'RAGE FILL — Острые соусы ручной работы | Минск, Беларусь';
    $desc = 'Острые соусы ручной работы RAGE FILL. Собственные перцы, от лёгкой до экстремальной остроты, натуральные ингредиенты. Каталог, доставка по Беларуси.';
    $url = $baseUrl . '/';

    $metaTags = $seo->buildAboutMeta($title, $desc, $url);

    // FAQ — from DB or default
    $faqRaw = $siteSettings['faq'] ?? null;
    $faqItems = $faqRaw !== null ? (json_decode($faqRaw, true) ?? DEFAULT_FAQ) : DEFAULT_FAQ;
    $faqLd = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => []];
    foreach ($faqItems as $item) {
        $faqLd['mainEntity'][] = [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($item['answer'])],
        ];
    }
    $faqJsonLd = '<script type="application/ld+json">'
        . json_encode($faqLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_PRETTY_PRINT)
        . '</script>';
    $orgJsonLd = $seo->organizationJsonLd();
    $websiteJsonLd = $seo->websiteJsonLd();

    // Featured products — manual selection or auto (is_hit=1)
    $allSauces = $db->getAllSauces(true);
    $featuredIds = $siteSettings['featured_product_ids'] ?? null;
    if ($featuredIds !== null) {
        $featuredIds = json_decode($featuredIds, true);
    }
    if (is_array($featuredIds) && !empty($featuredIds)) {
        $sauceMap = [];
        foreach ($allSauces as $s) $sauceMap[$s['id']] = $s;
        $featured = [];
        foreach ($featuredIds as $fid) {
            if (isset($sauceMap[$fid])) $featured[] = $sauceMap[$fid];
        }
    } else {
        $featured = array_filter($allSauces, fn($s) => ($s['is_hit'] ?? 0) == 1);
        $featured = array_slice($featured, 0, 4);
        if (count($featured) < 3) {
            $featured = array_slice($allSauces, 0, 4);
        }
    }
    $featuredTitle = htmlspecialchars($siteSettings['featured_title'] ?? 'Популярное', ENT_QUOTES, 'UTF-8');

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

        $id = (int)$s['id'];
        $featuredHtml .= <<<HTML
            <a href="/sauce/{$slug}" class="home-product" data-id="{$id}" data-aos="fade-up" data-aos-delay="{delay}">
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

    $featuredJson = json_encode(array_values($featured), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);

    // Benefits — from DB or default
    $benefitsRaw = $siteSettings['benefits'] ?? null;
    $benefits = $benefitsRaw !== null ? (json_decode($benefitsRaw, true) ?? DEFAULT_BENEFITS) : DEFAULT_BENEFITS;

    $benefitsHtml = '';
    $bIdx = 0;
    foreach ($benefits as $b) {
        $delay = $bIdx * 100;
        $iconFile = htmlspecialchars($b['icon'] ?? 'pepper.svg', ENT_QUOTES, 'UTF-8');
        $icon = '<img src="/uploads/' . $iconFile . '" alt="" width="36" height="36">';
        $bTitle = htmlspecialchars($b['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $bText = htmlspecialchars($b['text'] ?? '', ENT_QUOTES, 'UTF-8');
        $benefitsHtml .= <<<HTML
            <div class="benefit-card" data-aos="fade-up" data-aos-delay="{$delay}">
                <div class="benefit-card__icon">{$icon}</div>
                <h3 class="benefit-card__title">{$bTitle}</h3>
                <p class="benefit-card__text">{$bText}</p>
            </div>
        HTML;
        $bIdx++;
    }

    // FAQ
    $faqHtml = '';
    foreach ($faqItems as $item) {
        $q = htmlspecialchars($item['question'], ENT_QUOTES, 'UTF-8');
        $a = sanitizeHtml($item['answer']);
        $faqHtml .= <<<HTML
            <details class="faq__item" data-aos="fade-up">
                <summary class="faq__question">{$q}</summary>
                <div class="faq__answer">{$a}</div>
            </details>
        HTML;
    }

    // Reviews — from DB setting or directory scan fallback
    $storedReviews = $db->getSettingJson('review_images');
    $reviewsDir = __DIR__ . '/uploads/reviews/';
    if (is_array($storedReviews) && !empty($storedReviews)) {
        // Filter to only existing files
        $reviewImages = array_values(array_filter($storedReviews, fn($f) => is_file($reviewsDir . basename($f))));
    } else {
        $reviewImages = [];
        if (is_dir($reviewsDir)) {
            foreach (scandir($reviewsDir) as $f) {
                if (preg_match('/\.(jpe?g|png|webp)$/i', $f)) $reviewImages[] = $f;
            }
            natsort($reviewImages);
            $reviewImages = array_values($reviewImages);
        }
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

    // Hero content — from DB or defaults
    $heroTagline = htmlspecialchars($siteSettings['hero_tagline'] ?? 'Острые соусы ручной работы, маринованные перцы, подарочные наборы и жгучие закуски', ENT_QUOTES, 'UTF-8');
    $heroDesc = htmlspecialchars($siteSettings['hero_description'] ?? 'Идеальный выбор для мяса, пиццы, бургеров и закусок <br> Доставка по Минску и Беларуси', ENT_QUOTES, 'UTF-8');
    // For description, allow <br> tag
    $heroDesc = str_replace('&lt;br&gt;', '<br>', $heroDesc);
    $heroBtnPrimary = htmlspecialchars($siteSettings['hero_btn_primary'] ?? 'Смотреть каталог', ENT_QUOTES, 'UTF-8');
    $heroBtnSecondary = htmlspecialchars($siteSettings['hero_btn_secondary'] ?? 'Написать нам', ENT_QUOTES, 'UTF-8');

    // About text — from DB or default (sanitize to prevent stored XSS)
    $aboutText = sanitizeHtml($siteSettings['about_text'] ?? DEFAULT_ABOUT);

    // Testimonials — from DB or default
    $testimonialsRaw = $siteSettings['testimonials'] ?? null;
    $testimonials = $testimonialsRaw !== null ? (json_decode($testimonialsRaw, true) ?? DEFAULT_TESTIMONIALS) : DEFAULT_TESTIMONIALS;
    $testimonialsHtml = '';
    foreach ($testimonials as $t) {
        $tText = htmlspecialchars($t['text'] ?? '', ENT_QUOTES, 'UTF-8');
        $tAuthor = htmlspecialchars($t['author'] ?? '', ENT_QUOTES, 'UTF-8');
        $testimonialsHtml .= <<<HTML
            <div class="home-testimonial">
                <blockquote class="home-testimonial__text">&laquo;{$tText}&raquo;</blockquote>
                <cite class="home-testimonial__author">— {$tAuthor}</cite>
            </div>
        HTML;
    }

    $instagramReviewsUrl = $siteSettings['instagram_reviews_url'] ?? 'https://www.instagram.com/stories/highlights/18073628308388969/';

    // Section titles
    $sectionTitleBenefits = htmlspecialchars($siteSettings['section_title_benefits'] ?? 'Почему выбирают нас', ENT_QUOTES, 'UTF-8');
    $sectionTitleReviews = htmlspecialchars($siteSettings['section_title_reviews'] ?? 'Отзывы', ENT_QUOTES, 'UTF-8');
    $sectionTitleFaq = htmlspecialchars($siteSettings['section_title_faq'] ?? 'Частые вопросы', ENT_QUOTES, 'UTF-8');

    // Footer vars
    $footerTagline = htmlspecialchars($siteSettings['footer_tagline'] ?? 'Острые соусы ручной работы, Беларусь', ENT_QUOTES, 'UTF-8');
    $footerAbout = htmlspecialchars($siteSettings['footer_about'] ?? 'Все соусы изготавливаются вручную из свежих перцев. Для заказа свяжитесь с нами через Telegram.', ENT_QUOTES, 'UTF-8');

    $hreflangUrl = $url;
    return render('home.php', compact(
        'title', 'metaTags', 'hreflangUrl', 'faqJsonLd', 'orgJsonLd', 'websiteJsonLd',
        'contactTg', 'contactTgRaw', 'featuredHtml', 'featuredJson', 'featuredTitle', 'benefitsHtml',
        'faqHtml', 'reviewsHtml', 'reviewSrcsJson',
        'heroTagline', 'heroDesc', 'heroBtnPrimary', 'heroBtnSecondary',
        'aboutText', 'testimonialsHtml', 'instagramReviewsUrl',
        'sectionTitleBenefits', 'sectionTitleReviews', 'sectionTitleFaq',
        'footerTagline', 'footerAbout'
    ));
}

