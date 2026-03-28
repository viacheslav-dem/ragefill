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
        // Check if table already exists to avoid unnecessary DDL
        $exists = $this->pdo->query(
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name='sauces'"
        )->fetch();

        if ($exists) {
            // Add in_stock column if missing
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
                images TEXT NOT NULL DEFAULT '[]',
                slug TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
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
            INSERT INTO sauces (name, subtitle, description, composition, volume, image, images, heat_level, sort_order, is_active, in_stock, category, is_hit, is_low_stock, slug)
            VALUES (:name, :subtitle, :description, :composition, :volume, :image, :images, :heat_level, :sort_order, :is_active, :in_stock, :category, :is_hit, :is_low_stock, :slug)
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
            'category' => self::validateCategory($data['category'] ?? 'sauce'),
            'is_hit' => in_array($data['is_hit'] ?? 0, [1, '1'], true) ? 1 : 0,
            'is_low_stock' => in_array($data['is_low_stock'] ?? 0, [1, '1'], true) ? 1 : 0,
            'slug' => $slug,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function updateSauce(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        // Auto-update slug when name changes
        if (array_key_exists('name', $data)) {
            $newSlug = self::generateSlug(trim((string)$data['name']));
            $data['slug'] = $this->ensureUniqueSlug($newSlug, $id);
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
            'category' => fn($v) => self::validateCategory($v),
            'is_hit' => fn($v) => in_array($v, [1, '1'], true) ? 1 : 0,
            'is_low_stock' => fn($v) => in_array($v, [1, '1'], true) ? 1 : 0,
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

    private static function validateCategory(mixed $value): string
    {
        $allowed = ['sauce', 'gift_set', 'pickled_pepper', 'spicy_peanut', 'spice'];
        $v = (string)$value;
        return in_array($v, $allowed, true) ? $v : 'sauce';
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
}
