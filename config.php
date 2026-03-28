<?php

declare(strict_types=1);

return [
    // Telegram Bot Token
    'bot_token' => 'YOUR_BOT_TOKEN_HERE',

    // Admin password for admin panel
    'admin_password' => 'ragefill123',

    // Database path
    'db_path' => __DIR__ . '/database/ragefill.db',

    // Upload directory
    'upload_dir' => __DIR__ . '/public/uploads/',

    // Max upload size (5MB)
    'max_upload_size' => 5 * 1024 * 1024,

    // Allowed image types
    'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],

    // Image optimization (GD)
    'image_max_width' => 800,   // Max width in px (height scales proportionally)
    'image_quality' => 80,      // WebP quality (1-100)

    // Base URL (set to your domain)
    'base_url' => 'https://ragefill.by',

    // Contact Telegram username (without @) for "Write to seller" button
    'contact_telegram' => 'rage_fill',

    // Debug mode
    'debug' => true,
];
