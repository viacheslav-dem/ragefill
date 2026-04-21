<?php

declare(strict_types=1);

namespace Ragefill;

use PDO;

class Database
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO("sqlite:$dbPath", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA foreign_keys=ON');

        $this->migrate();
    }

    private function migrate(): void
    {
        // --- site_settings table ---
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS site_settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL DEFAULT '',
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");

        // Seed default site_settings if table is empty
        $settingsCount = (int)$this->pdo->query("SELECT COUNT(*) FROM site_settings")->fetchColumn();
        if ($settingsCount === 0) {
            $this->seedDefaultSettings();
        }

        // Seed peppers data if not yet present (for existing databases)
        $hasPeppers = $this->pdo->prepare("SELECT 1 FROM site_settings WHERE key = 'peppers'");
        $hasPeppers->execute();
        if (!$hasPeppers->fetch()) {
            $this->seedPeppersDefaults();
        }

        // --- categories table ---
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                emoji TEXT NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        // Seed default categories if table is empty
        $catCount = (int)$this->pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        if ($catCount === 0) {
            $this->pdo->exec("
                INSERT INTO categories (slug, name, emoji, sort_order) VALUES
                ('sauce', 'Соусы', '🔥', 0),
                ('gift_set', 'Подарочные наборы', '🎁', 1),
                ('pickled_pepper', 'Маринованные перцы', '🫙', 2),
                ('spicy_peanut', 'Острый арахис', '🥜', 3),
                ('spice', 'Специи', '🌿', 4)
            ");
        }

        // Check if table already exists to avoid unnecessary DDL
        $exists = $this->pdo->query(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name='sauces'"
        )->fetch();

        if ($exists) {
            // Add missing columns
            $cols = $this->pdo->query("PRAGMA table_info(sauces)")->fetchAll();
            $colNames = array_column($cols, 'name');
            if (!in_array('in_stock', $colNames, true)) {
                $this->pdo->exec("ALTER TABLE sauces ADD COLUMN in_stock INTEGER NOT NULL DEFAULT 1 CHECK(in_stock IN (0, 1))");
            }
            if (!in_array('subtitle', $colNames, true)) {
                $this->pdo->exec("ALTER TABLE sauces ADD COLUMN subtitle TEXT NOT NULL DEFAULT ''");
            }
            if (!in_array('category', $colNames, true)) {
                $this->pdo->exec("ALTER TABLE sauces ADD COLUMN category TEXT NOT NULL DEFAULT 'sauce'");
            }
            if (!in_array('is_hit', $colNames, true)) {
                $this->pdo->exec("ALTER TABLE sauces ADD COLUMN is_hit INTEGER NOT NULL DEFAULT 0 CHECK(is_hit IN (0, 1))");
            }
            if (!in_array('is_low_stock', $colNames, true)) {
                $this->pdo->exec("ALTER TABLE sauces ADD COLUMN is_low_stock INTEGER NOT NULL DEFAULT 0 CHECK(is_low_stock IN (0, 1))");
            }
            if (!in_array('is_new', $colNames, true)) {
                $this->pdo->exec("ALTER TABLE sauces ADD COLUMN is_new INTEGER NOT NULL DEFAULT 0 CHECK(is_new IN (0, 1))");
            }
            if (!in_array('images', $colNames, true)) {
                $this->pdo->exec("ALTER TABLE sauces ADD COLUMN images TEXT NOT NULL DEFAULT '[]'");
            }
            if (!in_array('slug', $colNames, true)) {
                $this->pdo->exec("ALTER TABLE sauces ADD COLUMN slug TEXT NOT NULL DEFAULT ''");
                // Backfill slugs from existing names
                $rows = $this->pdo->query("SELECT id, name FROM sauces")->fetchAll();
                $stmt = $this->pdo->prepare("UPDATE sauces SET slug = ? WHERE id = ?");
                foreach ($rows as $row) {
                    $stmt->execute([self::generateSlug($row['name']), $row['id']]);
                }
            }
            if (!in_array('meta_title', $colNames, true)) {
                $this->pdo->exec("ALTER TABLE sauces ADD COLUMN meta_title TEXT NOT NULL DEFAULT ''");
            }
            if (!in_array('meta_description', $colNames, true)) {
                $this->pdo->exec("ALTER TABLE sauces ADD COLUMN meta_description TEXT NOT NULL DEFAULT ''");
            }
            return;
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS sauces (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                subtitle TEXT NOT NULL DEFAULT '',
                description TEXT NOT NULL,
                composition TEXT NOT NULL DEFAULT '',
                volume TEXT NOT NULL DEFAULT '',
                image TEXT DEFAULT NULL,
                heat_level INTEGER NOT NULL DEFAULT 3 CHECK(heat_level BETWEEN 1 AND 5),
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1 CHECK(is_active IN (0, 1)),
                in_stock INTEGER NOT NULL DEFAULT 1 CHECK(in_stock IN (0, 1)),
                category TEXT NOT NULL DEFAULT 'sauce',
                is_hit INTEGER NOT NULL DEFAULT 0 CHECK(is_hit IN (0, 1)),
                is_low_stock INTEGER NOT NULL DEFAULT 0 CHECK(is_low_stock IN (0, 1)),
                is_new INTEGER NOT NULL DEFAULT 0 CHECK(is_new IN (0, 1)),
                images TEXT NOT NULL DEFAULT '[]',
                slug TEXT NOT NULL DEFAULT '',
                meta_title TEXT NOT NULL DEFAULT '',
                meta_description TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
    }

    private function seedDefaultSettings(): void
    {
        $defaults = [
            'hero_tagline' => 'Острые соусы ручной работы, маринованные перцы, подарочные наборы и жгучие закуски',
            'hero_description' => 'Идеальный выбор для мяса, пиццы, бургеров и закусок <br> Доставка по Минску и Беларуси',
            'hero_btn_primary' => 'Смотреть каталог',
            'hero_btn_secondary' => 'Написать нам',
            'contact_telegram' => 'rage_fill',
            'instagram_reviews_url' => 'https://www.instagram.com/stories/highlights/18073628308388969/',
            'featured_title' => 'Популярное',
            'section_title_benefits' => 'Почему выбирают нас',
            'section_title_reviews' => 'Отзывы',
            'section_title_faq' => 'Частые вопросы',
            'footer_tagline' => 'Острые соусы ручной работы, Беларусь',
            'footer_about' => 'Все соусы изготавливаются вручную из свежих перцев. Для заказа свяжитесь с нами через Telegram.',
            'about_text' => '<p>RAGE FILL — это острые соусы ручной работы из Беларуси. Все соусы готовим небольшими партиями по авторским рецептам. Используем только натуральные ингредиенты и собственные перцы (Carolina Reaper, Apocalypse Scorpion, Big Red Mama, Big Red Mama, 7 POT, Bhut Jolokia, Habanero, The Pain, Jalapeno и другие сорта).</p><p>Помимо соусов в каталоге представлены подарочные наборы, маринованные перцы, острый арахис и специи. Широкий выбор вкусов и остроты: от легкой до экстремальной. Доставляем по Минску и всей Беларуси.</p>',
            'benefits' => json_encode([
                ['icon' => 'pepper.svg', 'title' => 'Собственные перцы', 'text' => 'Выращиваем острые перцы сами: Carolina Reaper, Apocalypse Scorpion, Habanero, Bhut Jolokia и другие.'],
                ['icon' => 'branch.svg', 'title' => 'Натуральный состав', 'text' => 'Готовим по авторским рецептам из натуральных ингредиентов. Без консервантов и красителей.'],
                ['icon' => 'gift.svg', 'title' => 'Идея для подарка', 'text' => 'Подарочные наборы на любой праздник — День рождения, 23 февраля, 8 марта, юбилей.'],
                ['icon' => 'fire.svg', 'title' => 'Только честная острота', 'text' => 'Готовим соусы из натуральных сверхострых перцев без добавления экстракта капсаицина!'],
                ['icon' => 'box.svg', 'title' => 'Доставка по Беларуси', 'text' => 'Ускоренная отправка на следующий день после заказа. Белпочта, Европочта.'],
                ['icon' => 'pizza.svg', 'title' => 'Запоминающийся вкус', 'text' => 'Соусы, которые действительно жгут и запоминаются. Яркий вкус для мяса, пиццы, бургеров.'],
            ], JSON_UNESCAPED_UNICODE),
            'testimonials' => json_encode([
                ['author' => 'Anton Kavaliou', 'text' => 'Попробовал ROWAN. Интересный такой вкус. Понравилось, что очень насыщенный. Наверное, можно с любой домашней едой использовать. TORMADO я еще раньше пробовал — его оставлю на стейки, с ним лучше всего.'],
                ['author' => 'Наталья Голик', 'text' => 'Пробовали ваши соусы) все ооочень вкусные и интересные!) Но! Agonix это ад адище 🔥🔥🔥 жарче чем в преисподней) очень крут) ❤️'],
                ['author' => 'Света Комарова', 'text' => 'Решила я попробовать Cheron. Грамулечку. Это просто 🔥🔥🔥 Язык пылал. Муж в восторге! Спасибо большое. Мужу реально понравилось, сказал есть вкус. Я никакого вкуса не разобрала, я, мне кажется, обожгла язык 😱'],
            ], JSON_UNESCAPED_UNICODE),
            'faq' => json_encode([
                ['question' => 'Как сделать заказ?', 'answer' => 'Напишите нам в Telegram <a href="https://t.me/rage_fill">@rage_fill</a> — поможем выбрать соус и оформим заказ. Также можно открыть каталог прямо в Telegram-боте и выбрать товар там. Оплата — наличными при самовывозе, переводом на карту или наложенным платежом при доставке. Обычно отправляем заказ в течение 1–2 дней.'],
                ['question' => 'Какие способы доставки доступны?', 'answer' => 'Доставляем по Минску и всей Беларуси через Белпочту и Европочту. Срок доставки — 2–5 дней в зависимости от региона. Возможен самовывоз в Минске — уточняйте адрес и время в Telegram. Каждый заказ упаковываем надёжно, чтобы бутылки доехали в целости.'],
                ['question' => 'Какой срок годности у соусов?', 'answer' => 'Срок годности наших соусов — 12 месяцев с даты изготовления. В закрытом виде храните в прохладном тёмном месте при температуре до +25°C. После вскрытия — обязательно в холодильнике, и соус сохранит вкус ещё 3–4 месяца. Дата изготовления указана на этикетке.'],
                ['question' => 'Из чего делают соусы RAGE FILL?', 'answer' => 'Только натуральные ингредиенты: свежие острые перцы, которые мы выращиваем сами (Carolina Reaper, Habanero, Bhut Jolokia, Apocalypse Scorpion и другие), овощи, специи и уксус. В составе нет консервантов, красителей и усилителей вкуса. Каждая партия готовится вручную небольшими порциями — так мы контролируем качество и вкус.'],
                ['question' => 'Какой соус выбрать, если я не пробовал острое?', 'answer' => 'Начните с соусов с уровнем остроты 1–2 из 5. Они дают приятное тепло и раскрывают вкус блюда без экстремального жжения. Подойдут к мясу, пицце, бургерам и закускам. Если хотите попробовать разное — посмотрите наши подарочные наборы с соусами разной остроты. Не уверены в выборе? Напишите нам в Telegram — подберём под ваш вкус!'],
                ['question' => 'Можно ли заказать соус в подарок?', 'answer' => 'Да! У нас есть готовые подарочные наборы с соусами разной остроты — от лёгкой до экстремальной. Также можем собрать индивидуальный комплект по вашему пожеланию. Отличный подарок на День рождения, 23 февраля, 8 марта, Новый год или любой другой праздник. Каждый набор красиво упакован и готов к вручению.'],
            ], JSON_UNESCAPED_UNICODE),
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO site_settings (key, value, updated_at) VALUES (?, ?, datetime('now'))
        ");
        foreach ($defaults as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }

    private function seedPeppersDefaults(): void
    {
        $peppers = [
            ['name' => 'Carolina Reaper', 'scoville_min' => 1400000, 'scoville_max' => 2200000, 'description' => 'Рекордсмен книги Гиннесса. Характерный хвостик «жало» и фруктовый аромат, за которым скрывается экстремальная острота. Выведен Эдом Карри в Южной Каролине.'],
            ['name' => 'Trinidad Scorpion Moruga Red', 'scoville_min' => 1200000, 'scoville_max' => 2009231, 'description' => 'Один из самых острых перцев планеты родом из Тринидада. Сладковатый фруктовый вкус быстро сменяется обжигающим жаром, который нарастает волнами.'],
            ['name' => 'Jigsaw Big Black Mama', 'scoville_min' => 1500000, 'scoville_max' => 2200000, 'description' => 'Гибрид с тёмной морщинистой кожицей и экстремальной остротой. Глубокий, почти копчёный вкус с длительным жгучим послевкусием.'],
            ['name' => 'Carolina Reaper Black', 'scoville_min' => 1400000, 'scoville_max' => 2200000, 'description' => 'Тёмная разновидность Carolina Reaper. Сохраняет фирменную остроту оригинала с более насыщенным, землистым вкусовым профилем.'],
            ['name' => 'Naga Brain Orange', 'scoville_min' => 900000, 'scoville_max' => 1300000, 'description' => 'Перец с бугристой поверхностью, напоминающей мозг. Яркий цитрусовый вкус и мощная, но не мгновенная острота — нарастает постепенно.'],
            ['name' => 'Scotch Bonnet', 'scoville_min' => 100000, 'scoville_max' => 350000, 'description' => 'Карибская классика. Сладкий тропический аромат с нотами манго и яблока. Острота ощутимая, но не экстремальная — идеален для соусов с характером.'],
            ['name' => 'Jonah 7 POT', 'scoville_min' => 800000, 'scoville_max' => 1200000, 'description' => 'Разновидность семейства 7 Pot из Тринидада. Название говорит само за себя: одного перца хватит на семь горшков еды. Фруктовый и цветочный вкус.'],
            ['name' => 'Habanero Orange', 'scoville_min' => 150000, 'scoville_max' => 350000, 'description' => 'Легенда мира острых перцев. Яркий цитрусово-фруктовый аромат и чистая, прямая острота. Универсальный перец для соусов и маринадов.'],
            ['name' => 'Carolina Reaper Apocalypse Scorpion', 'scoville_min' => 1400000, 'scoville_max' => 2200000, 'description' => 'Мощный гибрид Carolina Reaper и Apocalypse Scorpion. Сочетает фруктовую сладость с безжалостной остротой. Один из самых жгучих перцев в коллекции.'],
            ['name' => 'Big Red Mama', 'scoville_min' => 900000, 'scoville_max' => 1300000, 'description' => 'Крупный ярко-красный перец с толстыми стенками. Сладковатый вкус с ягодными нотами, переходящий в интенсивное продолжительное жжение.'],
            ['name' => 'The Pain', 'scoville_min' => 1000000, 'scoville_max' => 1400000, 'description' => 'Название не врёт. Компактный перец с мощнейшей концентрацией капсаицина. Острота приходит мгновенно и держится долго. Для опытных.'],
            ['name' => 'Trinidad Scorpion Butch T', 'scoville_min' => 800000, 'scoville_max' => 1463700, 'description' => 'Бывший мировой рекордсмен по остроте. Назван в честь Бутча Тейлора из Австралии. Сладкий фруктовый вкус с мощным, долгим жжением.'],
            ['name' => 'Bih Jolokia x Sugar Rush Red', 'scoville_min' => 800000, 'scoville_max' => 1000000, 'description' => 'Кросс индийской Bih Jolokia и сладкого Sugar Rush. Необычное сочетание: сладкий старт с фруктовыми нотами, плавно переходящий в серьёзную остроту.'],
            ['name' => 'Trinidad Hornet', 'scoville_min' => 1200000, 'scoville_max' => 1700000, 'description' => 'Перец с характерным «хвостиком-жалом». Тропический сладко-фруктовый аромат и обжигающая острота, которая приходит волнами и не отпускает.'],
        ];

        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO site_settings (key, value, updated_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute(['peppers', json_encode($peppers, JSON_UNESCAPED_UNICODE)]);
        $stmt->execute(['peppers_page_title', 'Наши перцы']);
        $stmt->execute(['peppers_page_intro', 'Мы выращиваем собственные сверхострые перцы для наших соусов. От классического Habanero до рекордсмена Carolina Reaper — каждый сорт привносит уникальный вкус и характер остроты.']);
        $stmt->execute(['catalog_page_title', 'Каталог соусов и жгучих закусок RAGE FILL']);
        $stmt->execute(['catalog_page_intro', "Острые соусы ручной работы, маринованные перцы, сверхострые специи, подарочные наборы и острый арахис.\nГотовим из собственных перцев и натуральных ингредиентов. Острота под любой вкус — от лёгкой до экстремальной.\nДоставка по Минску и Беларуси."]);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // --- Sauce CRUD ---

    public function getAllSauces(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM sauces";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id DESC";

        return $this->pdo->query($sql)->fetchAll();
    }

    public function getSauceById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sauces WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getSauceBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM sauces WHERE slug = ?");
        $stmt->execute([$slug]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function createSauce(array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $slug = self::generateSlug($name);
        // Ensure slug uniqueness
        $slug = $this->ensureUniqueSlug($slug, null);

        $stmt = $this->pdo->prepare("
            INSERT INTO sauces (name, subtitle, description, composition, volume, image, images, heat_level, sort_order, is_active, in_stock, category, is_hit, is_low_stock, is_new, slug, meta_title, meta_description)
            VALUES (:name, :subtitle, :description, :composition, :volume, :image, :images, :heat_level, :sort_order, :is_active, :in_stock, :category, :is_hit, :is_low_stock, :is_new, :slug, :meta_title, :meta_description)
        ");

        $stmt->execute([
            'name' => $name,
            'subtitle' => trim((string)($data['subtitle'] ?? '')),
            'description' => trim((string)($data['description'] ?? '')),
            'composition' => trim((string)($data['composition'] ?? '')),
            'volume' => trim((string)($data['volume'] ?? '')),
            'image' => $data['image'] ?? null,
            'images' => $data['images'] ?? '[]',
            'heat_level' => self::clampHeat($data['heat_level'] ?? 3),
            'sort_order' => max(0, (int)($data['sort_order'] ?? 0)),
            'is_active' => in_array($data['is_active'] ?? 1, [0, '0'], true) ? 0 : 1,
            'in_stock' => in_array($data['in_stock'] ?? 1, [0, '0'], true) ? 0 : 1,
            'category' => $this->validateCategory($data['category'] ?? 'sauce'),
            'is_hit' => in_array($data['is_hit'] ?? 0, [1, '1'], true) ? 1 : 0,
            'is_low_stock' => in_array($data['is_low_stock'] ?? 0, [1, '1'], true) ? 1 : 0,
            'is_new' => in_array($data['is_new'] ?? 0, [1, '1'], true) ? 1 : 0,
            'slug' => $slug,
            'meta_title' => trim((string)($data['meta_title'] ?? '')),
            'meta_description' => trim((string)($data['meta_description'] ?? '')),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateSauce(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        // Auto-update slug when name changes (skip if name is empty)
        if (array_key_exists('name', $data)) {
            $trimmedName = trim((string)$data['name']);
            if ($trimmedName === '') {
                unset($data['name']);
            } else {
                $newSlug = self::generateSlug($trimmedName);
                $data['slug'] = $this->ensureUniqueSlug($newSlug, $id);
            }
        }

        $sanitizers = [
            'name' => fn($v) => trim((string)$v),
            'slug' => fn($v) => (string)$v,
            'subtitle' => fn($v) => trim((string)$v),
            'description' => fn($v) => trim((string)$v),
            'composition' => fn($v) => trim((string)$v),
            'volume' => fn($v) => trim((string)$v),
            'image' => fn($v) => $v, // null or string
            'images' => fn($v) => $v, // JSON string
            'heat_level' => fn($v) => self::clampHeat($v),
            'sort_order' => fn($v) => max(0, (int)$v),
            'is_active' => fn($v) => in_array($v, [0, '0'], true) ? 0 : 1,
            'in_stock' => fn($v) => in_array($v, [0, '0'], true) ? 0 : 1,
            'category' => fn($v) => $this->validateCategory($v),
            'is_hit' => fn($v) => in_array($v, [1, '1'], true) ? 1 : 0,
            'is_low_stock' => fn($v) => in_array($v, [1, '1'], true) ? 1 : 0,
            'is_new' => fn($v) => in_array($v, [1, '1'], true) ? 1 : 0,
            'meta_title' => fn($v) => trim((string)$v),
            'meta_description' => fn($v) => trim((string)$v),
        ];

        foreach ($sanitizers as $field => $sanitize) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $sanitize($data[$field]);
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = "updated_at = datetime('now')";
        $sql = "UPDATE sauces SET " . implode(', ', $fields) . " WHERE id = :id";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteSauce(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM sauces WHERE id = ?");
        return $stmt->execute([$id]);
    }

    private static function clampHeat(mixed $value): int
    {
        return max(1, min(5, (int)$value));
    }

    private ?array $categorySlugsCache = null;

    private function validateCategory(mixed $value): string
    {
        if ($this->categorySlugsCache === null) {
            $this->categorySlugsCache = array_column(
                $this->pdo->query("SELECT slug FROM categories")->fetchAll(),
                'slug'
            );
        }
        $v = (string)$value;
        return in_array($v, $this->categorySlugsCache, true) ? $v : 'sauce';
    }

    // --- Category CRUD ---

    public function getAllCategories(): array
    {
        return $this->pdo->query("SELECT * FROM categories ORDER BY sort_order ASC, id ASC")->fetchAll();
    }

    public function getCategoryById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function createCategory(array $data): int
    {
        $name = trim((string)($data['name'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = self::generateSlug($name);
        }
        $emoji = trim((string)($data['emoji'] ?? ''));
        $sortOrder = max(0, (int)($data['sort_order'] ?? 0));

        $stmt = $this->pdo->prepare("
            INSERT INTO categories (slug, name, emoji, sort_order) VALUES (:slug, :name, :emoji, :sort_order)
        ");
        $stmt->execute(['slug' => $slug, 'name' => $name, 'emoji' => $emoji, 'sort_order' => $sortOrder]);
        $this->categorySlugsCache = null;
        return (int)$this->pdo->lastInsertId();
    }

    public function updateCategory(int $id, array $data): bool
    {
        // If slug is changing, update all sauces that use the old slug
        $oldCat = $this->getCategoryById($id);
        $newSlug = isset($data['slug']) ? trim((string)$data['slug']) : null;
        $oldSlug = $oldCat ? $oldCat['slug'] : null;

        $fields = [];
        $params = ['id' => $id];
        foreach (['slug', 'name', 'emoji', 'sort_order'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $field === 'sort_order' ? max(0, (int)$data[$field]) : trim((string)$data[$field]);
            }
        }
        if (empty($fields)) return false;
        $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($params);

        // Cascade slug change to sauces
        if ($result && $newSlug !== null && $oldSlug !== null && $newSlug !== $oldSlug) {
            $stmt = $this->pdo->prepare("UPDATE sauces SET category = ? WHERE category = ?");
            $stmt->execute([$newSlug, $oldSlug]);
        }

        $this->categorySlugsCache = null;
        return $result;
    }

    public function deleteCategory(int $id): bool|string
    {
        $cat = $this->getCategoryById($id);
        if (!$cat) return 'Категория не найдена';
        // Check if any products use this category
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sauces WHERE category = ?");
        $stmt->execute([$cat['slug']]);
        $count = (int)$stmt->fetchColumn();
        if ($count > 0) return "Нельзя удалить: $count товаров используют эту категорию";
        $stmt = $this->pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $this->categorySlugsCache = null;
        return true;
    }

    public static function generateSlug(string $name): string
    {
        // Transliterate Cyrillic
        $tr = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh',
            'з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
            'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts',
            'ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ё'=>'Yo','Ж'=>'Zh',
            'З'=>'Z','И'=>'I','Й'=>'J','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O',
            'П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'Ts',
            'Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch','Ъ'=>'','Ы'=>'Y','Ь'=>'','Э'=>'E','Ю'=>'Yu','Я'=>'Ya',
        ];
        $slug = strtr($name, $tr);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    private function ensureUniqueSlug(string $slug, ?int $excludeId): string
    {
        $base = $slug;
        $i = 1;
        while (true) {
            $sql = "SELECT id FROM sauces WHERE slug = ?";
            $params = [$slug];
            if ($excludeId !== null) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if (!$stmt->fetch()) {
                return $slug;
            }
            $slug = $base . '-' . (++$i);
        }
    }

    // --- Site Settings ---

    public function getSetting(string $key): ?string
    {
        $stmt = $this->pdo->prepare("SELECT value FROM site_settings WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : null;
    }

    public function getSettingJson(string $key): mixed
    {
        $val = $this->getSetting($key);
        return $val !== null ? json_decode($val, true) : null;
    }

    public function setSetting(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO site_settings (key, value, updated_at) VALUES (?, ?, datetime('now'))
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime('now')
        ");
        $stmt->execute([$key, $value]);
    }

    public function setSettingJson(string $key, mixed $value): void
    {
        $this->setSetting($key, json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    public function getAllSettings(): array
    {
        $rows = $this->pdo->query("SELECT key, value FROM site_settings ORDER BY key")->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }

    public function setMultipleSettings(array $settings): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO site_settings (key, value, updated_at) VALUES (?, ?, datetime('now'))
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime('now')
        ");
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)]);
        }
    }
}
