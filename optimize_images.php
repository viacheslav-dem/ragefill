<?php

/**
 * Массовая оптимизация существующих изображений.
 * Запуск: php optimize_images.php
 *
 * - Конвертирует JPG/PNG в WebP
 * - Ресайзит до max_width
 * - Обновляет имена файлов в БД
 * - Удаляет старые файлы после конвертации
 */

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

$maxWidth = $config['image_max_width'] ?? 800;
$quality = $config['image_quality'] ?? 80;
$uploadDir = $config['upload_dir'];
$dbPath = $config['db_path'];

if (!function_exists('imagecreatefromjpeg')) {
    echo "ОШИБКА: расширение GD не установлено.\n";
    exit(1);
}

$pdo = new PDO("sqlite:$dbPath", null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$sauces = $pdo->query("SELECT id, image FROM sauces WHERE image IS NOT NULL AND image != ''")->fetchAll();

if (empty($sauces)) {
    echo "Нет изображений для оптимизации.\n";
    exit(0);
}

$optimized = 0;
$skipped = 0;
$errors = 0;
$savedBytes = 0;

foreach ($sauces as $sauce) {
    $filename = $sauce['image'];
    $filePath = $uploadDir . $filename;

    if (!file_exists($filePath)) {
        echo "  ПРОПУСК: {$filename} — файл не найден\n";
        $skipped++;
        continue;
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Already WebP and small — check if resize needed
    $imageInfo = @getimagesize($filePath);
    if (!$imageInfo) {
        echo "  ОШИБКА: {$filename} — не удалось прочитать как изображение\n";
        $errors++;
        continue;
    }

    $origW = $imageInfo[0];
    $origH = $imageInfo[1];
    $origSize = filesize($filePath);

    // Skip if already WebP square at target size
    if ($ext === 'webp' && $origW === $maxWidth && $origH === $maxWidth) {
        echo "  OK: {$filename} — уже оптимизирован ({$origW}x{$origH})\n";
        $skipped++;
        continue;
    }

    $mime = $imageInfo['mime'];
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($filePath),
        'image/png' => @imagecreatefrompng($filePath),
        'image/webp' => @imagecreatefromwebp($filePath),
        default => false,
    };

    if (!$src) {
        echo "  ОШИБКА: {$filename} — не удалось декодировать ({$mime})\n";
        $errors++;
        continue;
    }

    // Center-crop to square and resize to target
    $cropSize = min($origW, $origH);
    $cropX = (int)round(($origW - $cropSize) / 2);
    $cropY = (int)round(($origH - $cropSize) / 2);

    $dst = imagecreatetruecolor($maxWidth, $maxWidth);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $maxWidth, $maxWidth, $cropSize, $cropSize);
    imagedestroy($src);
    $src = $dst;
    $newDimensions = "{$maxWidth}x{$maxWidth}";

    // Save as WebP
    $newFilename = bin2hex(random_bytes(16)) . '.webp';
    $newPath = $uploadDir . $newFilename;

    if (!imagewebp($src, $newPath, $quality)) {
        imagedestroy($src);
        @unlink($newPath);
        echo "  ОШИБКА: {$filename} — не удалось сохранить WebP\n";
        $errors++;
        continue;
    }

    imagedestroy($src);
    chmod($newPath, 0644);

    $newSize = filesize($newPath);
    $saved = $origSize - $newSize;
    $savedBytes += $saved;
    $pct = $origSize > 0 ? round(($saved / $origSize) * 100) : 0;

    // Update DB
    $stmt = $pdo->prepare("UPDATE sauces SET image = :new_image, updated_at = datetime('now') WHERE id = :id");
    $stmt->execute(['new_image' => $newFilename, 'id' => $sauce['id']]);

    // Remove old file
    unlink($filePath);

    echo "  ГОТОВО: {$filename} → {$newFilename} ({$newDimensions}, -{$pct}%)\n";
    $optimized++;
}

$savedKB = round($savedBytes / 1024);
echo "\n";
echo "Результат: оптимизировано {$optimized}, пропущено {$skipped}, ошибок {$errors}\n";
echo "Сэкономлено: {$savedKB} КБ\n";
