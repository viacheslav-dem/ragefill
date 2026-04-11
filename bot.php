<?php

declare(strict_types=1);

/**
 * Telegram Bot Webhook handler for Ragefill Mini App.
 *
 * Set webhook:
 * https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://your-domain.com/bot.php
 *
 * One-time setup (run via browser or curl):
 *
 * 1) Set commands:
 * https://api.telegram.org/bot<TOKEN>/setMyCommands?commands=[
 *   {"command":"start","description":"Открыть каталог соусов"},
 *   {"command":"contact","description":"Связаться с нами"},
 *   {"command":"help","description":"Список команд"}
 * ]
 *
 * 2) Set menu button (opens Mini App directly):
 * POST https://api.telegram.org/bot<TOKEN>/setChatMenuButton
 * {"menu_button":{"type":"web_app","text":"Каталог","web_app":{"url":"https://ragefill.glosstechn.by"}}}
 *
 * 3) Set bot description (shown before user presses /start):
 * POST https://api.telegram.org/bot<TOKEN>/setMyDescription
 * {"description":"RAGE FILL — сверхострые соусы ручной работы.\n\nНатуральный состав, небольшие партии, максимальный жар. Откройте каталог и выберите свой огонь!"}
 *
 * 4) Set bot short description (shown in bot profile / search):
 * POST https://api.telegram.org/bot<TOKEN>/setMyShortDescription
 * {"short_description":"Сверхострые соусы ручной работы. Каталог, заказ, доставка."}
 */

require __DIR__ . '/vendor/autoload.php';

$config = require __DIR__ . '/config.php';
$botToken = $config['bot_token'];
$webAppUrl = $config['base_url'];
$contactTg = $config['contact_telegram'];

// One-time setup endpoint: GET /bot.php?setup=1
if (isset($_GET['setup']) && $_GET['setup'] === '1') {
    setupBot($botToken, $webAppUrl);
    exit;
}

$input = file_get_contents('php://input');
if ($input === false) {
    http_response_code(200);
    exit;
}

$update = json_decode($input, true);
if (!is_array($update)) {
    http_response_code(200);
    exit;
}

if (isset($update['message'])) {
    $chatId = (int)$update['message']['chat']['id'];
    $text = trim($update['message']['text'] ?? '');

    $catalogButton = [
        'inline_keyboard' => [
            [
                ['text' => "\xF0\x9F\x8C\xB6\xEF\xB8\x8F Открыть каталог", 'web_app' => ['url' => $webAppUrl]]
            ]
        ]
    ];

    // Reply keyboard — persists at the bottom of the chat
    $replyKeyboard = [
        'keyboard' => [
            [
                ['text' => "\xF0\x9F\x8C\xB6\xEF\xB8\x8F Каталог", 'web_app' => ['url' => $webAppUrl]],
                ['text' => "\xF0\x9F\x93\xAC Связаться"],
            ],
            [
                ['text' => "\xE2\x9D\x93 Помощь"],
            ],
        ],
        'resize_keyboard' => true,
        'is_persistent' => true,
    ];

    if ($text === '/start') {
        // First message: set reply keyboard
        sendMessage($botToken, $chatId,
            "*RAGE FILL* \xF0\x9F\x94\xA5\n"
            . "Сверхострые соусы ручной работы\n\n"
            . "\xF0\x9F\x94\xA5 Переполненные яростью\n"
            . "\xF0\x9F\x8C\xB6 Из сверхострых перцев\n"
            . "\xF0\x9F\x94\x9E Только для взрослых\n\n"
            . "Нажмите кнопку ниже, чтобы открыть каталог!",
            $replyKeyboard
        );

    } elseif ($text === '/contact' || $text === "\xF0\x9F\x93\xAC Связаться") {
        $contactButton = [
            'inline_keyboard' => [
                [
                    ['text' => "\xE2\x9C\x89\xEF\xB8\x8F Написать нам", 'url' => "https://t.me/{$contactTg}"]
                ],
                [
                    ['text' => "\xF0\x9F\x93\xB8 Instagram", 'url' => 'https://www.instagram.com/rage_fill/']
                ],
                [
                    ['text' => "\xF0\x9F\x8C\xB6\xEF\xB8\x8F Открыть каталог", 'web_app' => ['url' => $webAppUrl]]
                ]
            ]
        ];
        sendMessage($botToken, $chatId,
            "\xF0\x9F\x93\xAC *Связаться с нами*\n\n"
            . "Вопросы по соусам, доставке или хотите сделать заказ?\n"
            . "Напишите нам — мы всегда на связи!",
            $contactButton
        );

    } elseif ($text === '/help' || $text === "\xE2\x9D\x93 Помощь") {
        sendMessage($botToken, $chatId,
            "\xF0\x9F\x94\xA5 *RAGE FILL — Команды*\n\n"
            . "/start — Приветствие и каталог\n"
            . "/contact — Связаться с нами \xF0\x9F\x93\xAC\n"
            . "/help — Эта справка\n\n"
            . "Или нажмите кнопку *Каталог* в меню!",
            $catalogButton
        );

    } else {
        // Любое сообщение (текст, стикер, фото и т.д.) → направляем в мини-приложение
        sendMessage($botToken, $chatId,
            "Весь ассортимент — в нашем каталоге \xF0\x9F\x91\x87\n"
            . "Нажмите кнопку, чтобы открыть мини-приложение!",
            $catalogButton
        );
    }
}

// --- Helpers ---

function sendMessage(string $token, int $chatId, string $text, ?array $replyMarkup = null): void
{
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown',
    ];

    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }

    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function setupBot(string $token, string $webAppUrl): void
{
    header('Content-Type: text/plain; charset=utf-8');

    // 1. Set commands
    $commands = json_encode([
        ['command' => 'start', 'description' => 'Открыть каталог соусов'],
        ['command' => 'contact', 'description' => 'Связаться с нами'],
        ['command' => 'help', 'description' => 'Список команд'],
    ], JSON_UNESCAPED_UNICODE);
    $r1 = botApiCall($token, 'setMyCommands', ['commands' => $commands]);
    echo "setMyCommands: {$r1}\n";

    // 2. Set menu button → opens Mini App
    $menuButton = json_encode([
        'type' => 'web_app',
        'text' => 'Каталог',
        'web_app' => ['url' => $webAppUrl],
    ], JSON_UNESCAPED_UNICODE);
    $r2 = botApiCall($token, 'setChatMenuButton', ['menu_button' => $menuButton]);
    echo "setChatMenuButton: {$r2}\n";

    // 3. Set bot description (before /start screen)
    $description = 'RAGE FILL — сверхострые соусы ручной работы.' . "\n\n"
        . 'Натуральный состав, небольшие партии, максимальный жар. '
        . 'Откройте каталог и выберите свой огонь!';
    $r3 = botApiCall($token, 'setMyDescription', ['description' => $description]);
    echo "setMyDescription: {$r3}\n";

    // 4. Set short description (bot profile / search)
    $shortDesc = 'Сверхострые соусы ручной работы. Каталог, заказ, доставка.';
    $r4 = botApiCall($token, 'setMyShortDescription', ['short_description' => $shortDesc]);
    echo "setMyShortDescription: {$r4}\n";

    echo "\nDone!\n";
}

function botApiCall(string $token, string $method, array $data): string
{
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: 'error';
}
