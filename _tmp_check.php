<?php
require __DIR__ . '/config/db.php';
$s = $pdo->query('SHOW COLUMNS FROM users');
echo "USERS: ";
foreach ($s as $r) echo $r['Field'] . ' ';
echo "\n";
// mavjud VK video URL (content/episodes dan bittasi) — reel testi uchun
$stmt = $pdo->query("SELECT video_url FROM episodes WHERE video_url LIKE '%vk%' ORDER BY id DESC LIMIT 3");
echo "EPISODES VK:\n";
foreach ($stmt as $r) echo $r['video_url'] . "\n";
$stmt2 = $pdo->query("SELECT video_url FROM content WHERE video_url LIKE '%vk%' OR video_url LIKE '%mp4%' ORDER BY id DESC LIMIT 3");
echo "CONTENT VK:\n";
foreach ($stmt2 as $r) echo $r['video_url'] . "\n";