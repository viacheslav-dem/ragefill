<?php

/**
 * Оптимизация JPG/PNG файлов в uploads/ и всех подпапках.
 * Конвертирует в WebP, ресайзит если шире maxWidth.
 * НЕ трогает БД — только файлы на диске.
 *
 * Запуск: php optimize_uploads.php [maxWidth] [quality]
 * По умолчанию: maxWidth=800, quality=80
 */

declare(strict_types=1);

$uploadDir = __DIR__ . '/public/uploads';
$maxWidth = (int)($argv[1] ?? 800);
$quality = (int)($argv[2] ?? 80);

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
    $totalBefore += $origSize;
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
    imagewebp($src, $webpPath, $quality);
    imagedestroy($src);

    $newSize = filesize($webpPath);
    $totalAfter += $newSize;
    $optimized++;
    $pct = $origSize > 0 ? round((1 - $newSize / $origSize) * 100) : 0;

    echo " → WebP {$w}x{$h} (" . round($newSize / 1024) . " КБ) -{$pct}%\n";

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
