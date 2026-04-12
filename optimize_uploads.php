<?php

/**
 * Оптимизация JPG/PNG файлов в uploads/ и всех подпапках.
 * Конвертирует в WebP, ресайзит если шире maxWidth.
 * Обновляет ссылки в БД (таблица sauces) при переименовании файлов.
 *
 * Запуск: php optimize_uploads.php [maxWidth] [quality]
 * По умолчанию: maxWidth=800, quality=80
 */

declare(strict_types=1);

$uploadDir = __DIR__ . '/public/uploads';
$maxWidth = max(1, (int)($argv[1] ?? 800));
$quality = max(1, min(100, (int)($argv[2] ?? 80)));

// Connect to DB to update image references
$dbPath = __DIR__ . '/database/ragefill.db';
$pdo = null;
if (is_file($dbPath)) {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} else {
    echo "ПРЕДУПРЕЖДЕНИЕ: БД не найдена ({$dbPath}), ссылки на изображения не будут обновлены.\n";
}

if (!function_exists('imagecreatefromjpeg')) {
    echo "ОШИБКА: расширение GD не установлено.\n";
    exit(1);
}

$optimized = 0;
$totalBefore = 0;
$totalAfter = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    $path = $file->getPathname();
    $ext = strtolower($file->getExtension());

    if (!in_array($ext, ['jpg', 'jpeg', 'png'])) continue;

    $info = @getimagesize($path);
    if (!$info) {
        echo "  ПРОПУСК: " . basename($path) . " — не удалось прочитать\n";
        continue;
    }

    $w = $info[0];
    $h = $info[1];
    $origSize = filesize($path);
    $rel = str_replace(str_replace('\\', '/', $uploadDir) . '/', '', str_replace('\\', '/', $path));

    echo "  {$rel}: {$w}x{$h} (" . round($origSize / 1024) . " КБ)";

    $src = match ($ext) {
        'png' => @imagecreatefrompng($path),
        default => @imagecreatefromjpeg($path),
    };

    if (!$src) {
        echo " — ОШИБКА декодирования\n";
        continue;
    }

    $totalBefore += $origSize;

    if ($w > $maxWidth) {
        $newH = (int)($h * $maxWidth / $w);
        $dst = imagecreatetruecolor($maxWidth, $newH);
        if ($ext === 'png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, $newH, $w, $h);
        imagedestroy($src);
        $src = $dst;
        $w = $maxWidth;
        $h = $newH;
    }

    $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
    $ok = imagewebp($src, $webpPath, $quality);
    imagedestroy($src);

    if (!$ok || !is_file($webpPath) || filesize($webpPath) === 0) {
        echo " — ОШИБКА записи WebP\n";
        if (is_file($webpPath)) @unlink($webpPath);
        continue;
    }

    $newSize = filesize($webpPath);
    $totalAfter += $newSize;
    $optimized++;
    $pct = $origSize > 0 ? round((1 - $newSize / $origSize) * 100) : 0;

    echo " → WebP {$w}x{$h} (" . round($newSize / 1024) . " КБ) -{$pct}%\n";

    // Update DB references if the filename changed (jpg/png → webp)
    $oldBasename = basename($path);
    $newBasename = basename($webpPath);
    if ($pdo && $oldBasename !== $newBasename) {
        $stmt = $pdo->prepare('UPDATE sauces SET image = :new WHERE image = :old');
        $stmt->execute([':new' => $newBasename, ':old' => $oldBasename]);
        if ($stmt->rowCount() > 0) {
            echo "    БД: {$oldBasename} → {$newBasename}\n";
        }
    }

    unlink($path);
}

echo "\n";
if ($optimized > 0) {
    echo "Оптимизировано: {$optimized} файлов\n";
    echo "До:  " . round($totalBefore / 1024) . " КБ\n";
    echo "После: " . round($totalAfter / 1024) . " КБ\n";
    echo "Сэкономлено: " . round(($totalBefore - $totalAfter) / 1024) . " КБ (" . round((1 - $totalAfter / $totalBefore) * 100) . "%)\n";
} else {
    echo "Нет JPG/PNG файлов для оптимизации.\n";
}
