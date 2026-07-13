<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['csrf_token'] = 'test123';

$payload = [
    'message' => 'salom',
    'session_id' => 1,
    'csrf_token' => 'test123',
];

$ch = curl_init('http://localhost/uzdub/api/ai-chat.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_COOKIE => 'PHPSESSID=' . session_id(),
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP: $httpCode\n";
echo "cURL Error: " . ($curlError ?: 'none') . "\n";
echo "Response: " . substr($response, 0, 500) . "\n";
