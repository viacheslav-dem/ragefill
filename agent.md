# RAGEFILL — Техническая документация

> **Инструкция:** При любых изменениях в проекте (структура, API, БД, фронтенд, конфигурация, безопасность) — обновлять этот файл `agent.md`, чтобы документация всегда соответствовала актуальному состоянию кода.

## Обзор проекта

**RAGEFILL** — Telegram Mini App для каталога сверхострых соусов ручной работы.
Позволяет пользователям просматривать соусы, фильтровать по остроте и наличию, а также связываться с продавцом через Telegram. Администратор управляет каталогом через веб-панель.

**Домен:** `https://ragefill.glosstechn.by`
**Telegram контакт:** `@rage_fill`

---

## Стек технологий

| Компонент       | Технология                                 |
|-----------------|--------------------------------------------|
| Backend         | PHP 8.1+, Slim Framework 4                 |
| База данных     | SQLite3 (WAL mode)                         |
| Frontend        | Vanilla HTML/CSS/JS                        |
| WYSIWYG         | Quill.js 1.3.7                             |
| Шрифты          | Bebas Neue (display), Manrope (body)       |
| Telegram SDK    | telegram-web-app.js                        |
| Telegram Bot    | Webhook handler (bot.php)                  |
| Аутентификация  | HMAC-SHA256 Bearer tokens (24h expiry)     |
| Хостинг         | Apache + mod_rewrite                       |

---

## Структура проекта

```
tg_bot_ragefill/
├── bot.php                    # Telegram bot webhook handler
├── config.php                 # Конфигурация (токены, пути, параметры)
├── .env.example               # Шаблон переменных окружения
├── .htaccess                  # Apache rewrite для Authorization заголовков
├── composer.json               # PHP зависимости
├── composer.lock
├── agent.md                   # Этот файл
│
├── src/
│   ├── Database.php           # ORM-обёртка над SQLite (CRUD, миграции)
│   ├── AuthMiddleware.php     # PSR-15 middleware для Bearer token auth
│   └── SeoHelper.php          # SEO: мета-теги, JSON-LD, sitemap, robots.txt, SSR-карточки
│
├── public/                    # Document root
│   ├── index.php              # Slim 4 entry point (API + роутинг)
│   ├── catalog.html           # Фронтенд каталога (Telegram Mini App)
│   ├── admin.html             # Панель управления
│   ├── .htaccess              # Rewrite на index.php
│   │
│   ├── css/
│   │   ├── style.css          # Единый файл стилей (каталог + админка)
│   │   └── aos.css            # AOS (Animate On Scroll) — self-hosted v2.3.4
│   │
│   ├── js/
│   │   ├── catalog.js         # Логика каталога (фильтры, модалка, поиск)
│   │   ├── admin.js           # Логика админки (CRUD, Quill, загрузка фото)
│   │   ├── lightbox.js        # Lightbox для просмотра фото (stories-стиль)
│   │   ├── scroll-top.js      # Кнопка "наверх"
│   │   ├── slider.js          # Слайдер отзывов
│   │   └── aos.js             # AOS (Animate On Scroll) — self-hosted v2.3.4
│   │
│   └── uploads/               # Загруженные изображения соусов
│       ├── .gitkeep
│       └── .htaccess          # Запрет выполнения PHP в uploads
│
├── database/
│   └── ragefill.db            # SQLite база данных
│
├── vendor/                    # Composer зависимости
│
└── RAGEFILL_react/            # Альтернативная React-версия (Cloudflare Workers)
```

---

## База данных

### Таблица `sauces`

```sql
CREATE TABLE sauces (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT    NOT NULL,
    subtitle    TEXT    NOT NULL DEFAULT '',    -- Краткое описание (подзаголовок)
    description TEXT    NOT NULL,              -- HTML (Quill WYSIWYG)
    composition TEXT    NOT NULL DEFAULT '',    -- Состав (HTML, Quill WYSIWYG)
    volume      TEXT    NOT NULL DEFAULT '',    -- Объём ("100 мл")
    image       TEXT    DEFAULT NULL,           -- Имя файла в /uploads/
    heat_level  INTEGER NOT NULL DEFAULT 3 CHECK(heat_level BETWEEN 1 AND 5),
    sort_order  INTEGER NOT NULL DEFAULT 0,
    is_active   INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),
    in_stock    INTEGER NOT NULL DEFAULT 1 CHECK(in_stock IN (0, 1)),
    category    TEXT    NOT NULL DEFAULT 'sauce', -- 'sauce' | 'snack'
    is_hit      INTEGER NOT NULL DEFAULT 0 CHECK(is_hit IN (0, 1)),
    is_low_stock INTEGER NOT NULL DEFAULT 0 CHECK(is_low_stock IN (0, 1)),
    created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);
```

**Миграции:** автоматические при инициализации `Database.php`.
- Если таблица не существует — создаётся полностью.
- Если существует — проверяется наличие колонок `in_stock`, `subtitle`, `category`, добавляются если нет.

**Различие `is_active` и `in_stock`:**
- `is_active = 0` — соус скрыт из каталога полностью
- `in_stock = 0` — соус отображается, но помечен "Нет в наличии" (серый бейдж, grayscale фото)

---

## API

### Публичные эндпоинты

| Метод | URL                  | Описание                        |
|-------|----------------------|---------------------------------|
| GET   | `/api/settings`      | Настройки (contact_telegram)    |
| GET   | `/api/sauces`        | Все активные соусы              |
| GET   | `/api/sauces/{id}`   | Один соус по ID                 |
| POST  | `/api/auth/login`    | Авторизация (→ Bearer token)    |

### Защищённые эндпоинты (Bearer token)

| Метод  | URL                        | Описание                        |
|--------|----------------------------|---------------------------------|
| GET    | `/api/admin/sauces`        | Все соусы (включая неактивные)  |
| POST   | `/api/admin/sauces`        | Создать соус (multipart/form)   |
| POST   | `/api/admin/sauces/{id}`   | Обновить соус (multipart/form)  |
| DELETE | `/api/admin/sauces/{id}`   | Удалить соус                    |

### Формат создания/обновления соуса (FormData)

```
name          : string (обязательно)
subtitle      : string (краткое описание)
description   : string HTML (обязательно, мин. 10 символов)
composition   : string HTML
volume        : string
heat_level    : integer (1-5)
sort_order    : integer (>= 0)
is_active     : "0" | "1"
in_stock      : "0" | "1"
category      : "sauce" | "snack"
is_hit        : "0" | "1" (бейдж "ХИТ")
is_low_stock  : "0" | "1" (бейдж "МАЛО")
image         : File (JPG/PNG/WebP, макс. 5MB)
remove_image  : "0" | "1" (только при обновлении)
```

---

## Аутентификация

**Механизм:** HMAC-SHA256 Bearer tokens.

1. `POST /api/auth/login` с `{ "password": "..." }`
2. Пароль сравнивается с `config.admin_password` через `hash_equals`
3. При совпадении генерируется токен: `base64(payload).hmac_signature`
4. Payload: `{ "exp": unix_time + 86400, "iat": unix_time }`
5. Токен живёт 24 часа, хранится в `localStorage` клиента

---

## Frontend — Каталог (`catalog.html` + `catalog.js`)

### Тема
Светлая ("Light Craft") с оранжевым акцентом `#E8590C`.

### Компоненты
- **Header** — логотип RAGEFILL с перчиками, sticky
- **Hero stripe** — анимированный градиент (огненный)
- **Tab bar** — переключение между категориями: Соусы / Закуски
- **Поиск** — фильтрация по имени, описанию, составу (debounce 200ms)
- **Фильтры остроты** — чипсы: Все, ЭКСТРЕМАЛЬНАЯ, СИЛЬНАЯ, СРЕДНЯЯ, УМЕРЕННАЯ
- **Stock toggle** — сегментированный контрол: Все / В наличии / Нет в наличии
- **Сетка карточек** — 2 колонки (моб.) → 3 (768px) → 4 (1024px)
- **Модалка** — bottom sheet (моб.) / centered (десктоп)

### Система остроты (5-балльная)

| Уровень | Тир      | Цвет акцента |
|---------|----------|--------------|
| 5       | ЭКСТРЕМАЛЬНАЯ | `#991B1B`    |
| 4       | СИЛЬНАЯ       | `#DC2626`    |
| 3       | СРЕДНЯЯ       | `#E8590C`    |
| 2       | УМЕРЕННАЯ     | `#B45309`    |
| 1       | ЛЁГКАЯ        | `#B45309`    |

### Перчики-индикатор
Отображается `heat_level` ярких перцев + `(5 - heat_level)` блёклых (opacity 0.2, grayscale 80%).
Пример для остроты 3: 🌶️🌶️🌶️ + 2 бледных.

### Описание
- **Карточка** — фото → название → подзаголовок (uppercase-тег) → перчики-индикатор (без описания)
- **Модалка** — рендерится как HTML (`innerHTML`) с поддержкой `<p>`, `<strong>`, `<em>`, `<u>`, списков, ссылок
- **HTML-очистка** — при сохранении и рендере удаляются `<p><br></p>`, `<br></p>`, пустые `<p></p>`

### Telegram Mini App интеграция
- `tg.ready()`, `tg.expand()` при инициализации
- Haptic feedback на тапы (`impactOccurred`, `notificationOccurred`)
- Back button в модалке
- `tg.openTelegramLink()` для кнопки "Написать продавцу"
- Pull-to-refresh на touchstart/touchmove

---

## Frontend — Админка (`admin.html` + `admin.js`)

### Авторизация
- Экран логина с полем пароля
- Токен сохраняется в `localStorage('ragefill_token')`
- При 401 — автоматический logout

### CRUD соусов
- Список с поиском, миниатюрами, кнопками редактирования/удаления
- Форма в полноэкранной модалке
- WYSIWYG-редактор Quill для описания и состава (bold, italic, underline, списки, ссылки)
- Кнопка `</>` для переключения между WYSIWYG и HTML-кодом в каждом Quill-редакторе
- Ленивая инициализация Quill (`ensureQuillInit()`) — не блокирует загрузку страницы если CDN недоступен
- Визуальный селектор остроты (1-5 точек, заливаются до текущего уровня)
- Toggle-переключатель для is_active
- Загрузка фото с предпросмотром и удалением
- Диалог подтверждения удаления
- Диалог несохранённых изменений

---

## Telegram Bot (`bot.php`)

### Webhook handler
Устанавливается через: `https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://domain/bot.php`

### Команды

| Команда    | Действие                                          |
|------------|---------------------------------------------------|
| `/start`   | Приветствие + кнопка каталога (Web App)            |
| `/contact` | Кнопка "Написать нам" (ссылка на @rage_fill)       |
| `/help`    | Список команд                                     |

На любое сообщение (текст, стикер, фото и т.д.) — ответ с кнопкой открытия мини-приложения.

### Menu Button
Кнопка меню в чате настроена как `web_app` — при нажатии сразу открывает мини-приложение каталога.

### Описание бота (Telegram profile)
- **Description** (экран до /start): "RAGEFILL — сверхострые соусы ручной работы. Натуральный состав, небольшие партии, максимальный жар. Откройте каталог и выберите свой огонь!"
- **Short description** (профиль / поиск): "Сверхострые соусы ручной работы. Каталог, заказ, доставка."

### Одноразовая настройка
`GET /bot.php?setup=1` — устанавливает команды, menu button, description и short description через Telegram Bot API.

### Отправка сообщений
`curl` POST на `api.telegram.org/bot.../sendMessage` с Markdown parse_mode.

---

## Конфигурация (`config.php`)

```php
return [
    'bot_token'        => 'YOUR_BOT_TOKEN_HERE',
    'admin_password'   => 'ragefill123',
    'db_path'          => __DIR__ . '/database/ragefill.db',
    'upload_dir'       => __DIR__ . '/public/uploads/',
    'max_upload_size'  => 5 * 1024 * 1024,         // 5MB
    'allowed_types'    => ['image/jpeg', 'image/png', 'image/webp'],
    'image_max_width'  => 800,                       // Ресайз до ширины (px)
    'image_quality'    => 80,                        // WebP quality (1-100)
    'base_url'         => 'https://ragefill.glosstechn.by',
    'contact_telegram' => 'rage_fill',
    'debug'            => false,
];
```

---

## Загрузка изображений

1. Файл загружается во временную директорию
2. **Валидация MIME** через `finfo` (не по расширению)
3. **Валидация как изображение** через `getimagesize()`
4. **Проверка размера** (макс. 5MB)
5. **Оптимизация (GD):** ресайз до `image_max_width` (800px) + конвертация в WebP (quality 80)
6. Генерация случайного имени: `bin2hex(random_bytes(16)).webp`
7. Перемещение в `public/uploads/`
8. **`chmod(0644)`** — устанавливаются права для чтения веб-сервером
9. При обновлении — старый файл удаляется
10. Если GD недоступен — сохраняется оригинал без оптимизации (fallback)

---

## CSS архитектура (`style.css`)

### Design tokens
```css
--fire-orange: #E8590C;    /* Основной акцент */
--fire-red: #DC2626;       /* Danger / горячий */
--fire-yellow: #F59E0B;    /* Тёплый акцент */
--bg-body: #FAF7F4;        /* Фон страницы */
--bg-card: #FFFFFF;        /* Фон карточки */
--text-primary: #1C1410;   /* Основной текст */
--text-secondary: #6B5C50; /* Вторичный текст */
--text-muted: #9A8E82;     /* Приглушённый */
--success: #16A34A;        /* В наличии */
--danger: #DC2626;         /* Ошибки */
--radius: 14px;            /* Скругления */
```

### Responsive breakpoints
- **< 768px** — 2 колонки, модалка снизу (bottom sheet), scroll-фильтры
- **768px+** — 3 колонки, центрированная модалка, фильтры wrap
- **1024px+** — 4 колонки

### Единый файл
Стили каталога и админки в одном `style.css`. Админка стилизуется через `body:has(.admin-header)`.

---

## Зависимости (composer.json)

```json
{
    "require": {
        "slim/slim": "^4.0",
        "slim/psr7": "^1.6",
        "php-di/slim-bridge": "^3.4"
    }
}
```

Основные пакеты:
- **Slim 4** — микрофреймворк с PSR-7/PSR-15
- **Slim PSR7** — реализация HTTP messages
- **PHP-DI Slim Bridge** — DI-контейнер для Slim
- **FastRoute** — маршрутизация (зависимость Slim)

---

## Кеширование версий

Все CSS/JS файлы подключаются с query-параметром версии:
```html
<link rel="stylesheet" href="/css/style.css?v=1.2.0">
<script src="/js/catalog.js?v=1.1.0"></script>
<script src="/js/admin.js?v=1.2.0"></script>
```
При обновлении — менять версию для сброса кеша.

---

## Безопасность

- **Загрузки:** MIME-валидация через `finfo`, проверка `getimagesize()`, защита от path traversal, `chmod 0644`
- **Uploads/.htaccess:** разрешены только изображения, запрет выполнения PHP-файлов
- **Cloudflare:** все скрипты помечены `data-cfasync="false"` для отключения Rocket Loader
- **Auth:** HMAC-SHA256, `hash_equals` для сравнения, 24h expiry
- **XSS в карточках:** `esc()` через `textContent → innerHTML`
- **Описания:** HTML от Quill рендерится в модалке (доверенный контент из админки)
- **SQL:** prepared statements во всех запросах
- **CORS:** ограничен `base_url` из конфига
- **API X-Robots-Tag:** все `/api/*` ответы содержат `X-Robots-Tag: noindex`

---

## SEO (`SeoHelper.php`)

### Мета-теги и Open Graph
- Все страницы: `<title>`, `<meta description>`, canonical, OG tags, Twitter Card
- Продуктовые страницы: `og:type = product` (остальные — `website`)
- `hreflang="ru-BY"` на всех страницах
- apple-touch-icon (180x180) + favicon SVG + PNG 32x32

### JSON-LD Structured Data
- **Главная:** `FAQPage` + `LocalBusiness` + `WebSite` (с SearchAction)
- **Каталог:** `LocalBusiness` + `ItemList` + `WebSite`
- **Продукт:** `Product` (с Offer/availability) + `BreadcrumbList`

### Методы SeoHelper
| Метод | Назначение |
|---|---|
| `catalogMeta()` | Мета-теги для каталога |
| `productMeta()` | Мета-теги для продукта (`og:type=product`), category-aware title suffix, fallback description |
| `productJsonLd()` | Product + Offer schema |
| `breadcrumbJsonLd()` | BreadcrumbList для продуктовых страниц |
| `organizationJsonLd()` | LocalBusiness schema (адрес, контакт, регион) |
| `websiteJsonLd()` | WebSite schema с SearchAction |
| `catalogJsonLd()` | Organization + ItemList для ка��алога |
| `generateSitemap()` | XML-sitemap (/, /catalog, /privacy, все продукты) |
| `generateRobotsTxt()` | robots.txt (Disallow: /admin, /api/) |
| `renderProductCard()` | SSR HTML-карточка продукта |
| `buildAboutMeta()` | Мета-теги для статических страниц |

### Страницы
| URL | Рендеринг | Описание |
|---|---|---|
| `/` | SSR (PHP) | Главная с hero, featured, benefits, about-текст, testimonials, отзывы, FAQ |
| `/catalog` | SSR + JS hydration | Каталог с intro-текстом, фильтрами, SSR-карточки |
| `/sauce/{slug}` | SSR (PHP) | Страница продукта + блок «Вам может понравиться» |
| `/about` | 301 → `/` | Редирект на главную (about-контент встроен в homepage) |
| `/privacy` | SSR (PHP) | Политика конфиденциальности |
| `/admin` | Static HTML | Админ-панель |
| `/sitemap.xml` | Dynamic | XML-карта сайта |
| `/robots.txt` | Dynamic | Правила для роботов |

---

## Альтернативная React-версия

В директории `RAGEFILL_react/` — современная версия на:
- React 19 + TypeScript
- Hono.js (API на Cloudflare Workers)
- Tailwind CSS + Radix UI
- Cloudflare D1 (SQLite) + R2 (файлы)
- Vite 7 для сборки

Это отдельный проект, не связанный с основной PHP-версией. Может использоваться для будущей миграции.
