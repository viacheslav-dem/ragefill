<?php

declare(strict_types=1);

/**
 * Конфигурация приложения.
 *
 * Секреты читаются из .env (файл не коммитится, см. .gitignore).
 * Несекретные настройки — здесь же, в этом файле.
 *
 * Первичная настройка:
 *   1) cp .env.example .env
 *   2) php -r "echo password_hash('НОВЫЙ_ПАРОЛЬ', PASSWORD_DEFAULT).PHP_EOL;"
 *      → подставить в ADMIN_PASSWORD_HASH
 *   3) php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
 *      → подставить в TOKEN_SECRET
 *   4) php -r "echo bin2hex(random_bytes(16)).PHP_EOL;"
 *      → подставить в WEBHOOK_SECRET (и передать в setWebhook?secret_token=…)
 */

// --- Минимальный .env-загрузчик ---
(static function (): void {
    $envPath = __DIR__ . '/.env';
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }
    $raw = @file_get_contents($envPath);
    if ($raw === false) {
        return;
    }
    // Снять UTF-8 BOM, если файл сохранён из Windows-редактора
    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Снять окружающие кавычки, если есть
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if ($key === '' || array_key_exists($key, $_ENV)) {
            continue;
        }
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
})();

/** Прочитать переменную окружения с дефолтом. */
$env = static fn(string $k, ?string $default = null): ?string =>
    $_ENV[$k] ?? (getenv($k) !== false ? getenv($k) : $default);

$adminHash = $env('ADMIN_PASSWORD_HASH');
$tokenSecret = $env('TOKEN_SECRET');

// Fail-closed: без секретов приложение не стартует
if (!$adminHash || !$tokenSecret) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    $envPath = __DIR__ . '/.env';
    echo "Сервер сконфигурирован некорректно.\n\n";
    echo "Ожидаемый путь .env: {$envPath}\n";
    echo "Файл существует: " . (is_file($envPath) ? 'да' : 'НЕТ') . "\n";
    echo "Файл читается:   " . (is_readable($envPath) ? 'да' : 'НЕТ') . "\n";
    echo "ADMIN_PASSWORD_HASH: " . ($adminHash ? 'OK' : 'ПУСТО') . "\n";
    echo "TOKEN_SECRET:        " . ($tokenSecret ? 'OK' : 'ПУСТО') . "\n";
    echo "\nПроверь: .env лежит рядом с config.php, права 644, без BOM.\n";
    exit;
}

return [
    // --- Секреты из .env ---
    'bot_token' => $env('BOT_TOKEN', ''),
    'admin_password_hash' => $adminHash,
    'token_secret' => $tokenSecret,
    'webhook_secret' => $env('WEBHOOK_SECRET', ''),

    // --- Публичные настройки ---
    'base_url' => $env('BASE_URL', 'https://ragefill.by'),
    'contact_telegram' => $env('CONTACT_TELEGRAM', 'rage_fill'),

    // --- Инфраструктура ---
    'db_path' => __DIR__ . '/database/ragefill.db',
    'upload_dir' => __DIR__ . '/public/uploads/',
    'max_upload_size' => 5 * 1024 * 1024,
    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'image_max_width' => 800,
    'image_quality' => 80,

    // --- Режим ---
    'debug' => $env('DEBUG', 'false') === 'true',
];
