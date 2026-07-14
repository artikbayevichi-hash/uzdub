<?php
/* ============================================================
   api/voice-command.php - Takomillashtirilgan versiya
   ============================================================ */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ai_secrets.php';
require_once __DIR__ . '/../includes/functions.php';

$input = json_decode(file_get_contents('php://input'), true);
$text = isset($input['text']) ? trim($input['text']) : '';

if ($text === '') {
    echo json_encode(['action' => 'speak', 'speak' => "Eshityapman, buyruq bering."]);
    exit;
}

if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['action' => 'speak', 'speak' => "Xavfsizlik xatosi."]);
    exit;
}

$norm = mb_strtolower($text);
$norm = preg_replace('/[.,!?;:]/u', '', $norm);
$BASE = getenv('SITE_BASE_URL') ?: '/uzdub/';

function contains($haystack, array $needles) {
    foreach ($needles as $n) {
        if (mb_strpos($haystack, $n) !== false) return true;
    }
    return false;
}

$response = null;

// Navigatsiya
if (contains($norm, ['bosh sahifa', 'asosiy'])) {
    $response = ['action' => 'navigate', 'url' => $BASE . 'index.php', 'speak' => "Bosh sahifa."];
} elseif (contains($norm, ['anime'])) {
    $response = ['action' => 'navigate', 'url' => $BASE . 'category.php?slug=anime', 'speak' => "Animelar."];
} elseif (contains($norm, ['kino'])) {
    $response = ['action' => 'navigate', 'url' => $BASE . 'category.php?slug=kino', 'speak' => "Kinolar."];
} elseif (contains($norm, ['multfilm'])) {
    $response = ['action' => 'navigate', 'url' => $BASE . 'category.php?slug=multfilm', 'speak' => "Multfilmlar."];
} elseif (contains($norm, ['profil'])) {
    $uid = is_user() ? current_user()['user_id'] : null;
    $response = $uid ? ['action' => 'navigate', 'url' => $BASE . 'profile.php?uid=' . $uid, 'speak' => "Profilingiz."] : ['action' => 'speak', 'speak' => "Tizimga kiring."];
}

// Video boshqaruvi
if (!$response) {
    if (contains($norm, ['to\'xtat', 'pauza', 'stop'])) {
        $response = ['action' => 'video', 'control' => 'pause', 'speak' => "To'xtatdim."];
    } elseif (contains($norm, ['davom', 'play', 'ishga tushir'])) {
        $response = ['action' => 'video', 'control' => 'play', 'speak' => "Boshladim."];
    }
}

// AI Fallback
if (!$response) {
    $systemPrompt = "Siz UZDUB AI ovozli yordamchisiz. Qisqa va aniq javob bering.";
    $payload = [
        'model' => OLLAMA_MODEL,
        'messages' => [['role' => 'user', 'content' => $systemPrompt . "\n\n" . $text]],
        'stream' => false,
        'options' => ['temperature' => 0.5, 'num_predict' => 50],
    ];

    $ch = curl_init(OLLAMA_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 15,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    if ($raw) {
        $data = json_decode($raw, true);
        $response = ['action' => 'speak', 'speak' => $data['message']['content'] ?? "Tushunmadim."];
    } else {
        $response = ['action' => 'speak', 'speak' => "AI aloqada emas."];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
