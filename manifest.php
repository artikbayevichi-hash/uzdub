<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'name' => 'UZDUB PLATFORM',
    'short_name' => 'UZDUB',
    'description' => "Kino, Anime va Multfilmlar o'zbek tilida",
    'start_url' => ROOT_URL . '/index.php',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#0b0f19',
    'theme_color' => '#2196f3',
    'icons' => [
        [
            'src' => ROOT_URL . '/favicon.svg',
            'sizes' => 'any',
            'type' => 'image/svg+xml',
            'purpose' => 'any maskable',
        ],
    ],
], JSON_UNESCAPED_SLASHES);
