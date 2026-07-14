<?php
/* ============================================================
   api/ai-chat.php - Takomillashtirilgan versiya
   ============================================================ */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ai_secrets.php';
require_once __DIR__ . '/../includes/functions.php';

$input = json_decode(file_get_contents('php://input'), true);
$userMessage = isset($input['message']) ? trim($input['message']) : '';
$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : null;

if ($userMessage === '') {
    echo json_encode(['error' => "Xabar bo'sh bo'lishi mumkin emas."]);
    exit;
}

if (!$sessionId) {
    echo json_encode(['error' => 'Chat session_id talab qilinadi']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;

// IDOR himoyasi
if ($userId && $sessionId) {
    $own = $pdo->prepare("SELECT id FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
    $own->execute([$sessionId, $userId]);
    if (!$own->fetch()) {
        echo json_encode(['error' => 'Chat sessiyasi topilmadi']);
        exit;
    }
}

if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Xavfsizlik tokeni noto\'g\'ri']);
    exit;
}

/* 1) Bazadan eng yaxshi mos keladigan kontentni topish (Optimallashtirilgan) */
function findBestMatch(PDO $pdo, string $message): ?array {
    $cleanMessage = mb_strtolower($message);
    $stmt = $pdo->prepare("SELECT c.title, cat.name AS cat_name FROM content c JOIN categories cat ON c.category_id = cat.id WHERE c.title LIKE ? OR c.description LIKE ? ORDER BY c.views DESC LIMIT 1");
    $stmt->execute(['%' . $cleanMessage . '%', '%' . $cleanMessage . '%']);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$best = findBestMatch($pdo, $userMessage);

/* 2) Prompt tayyorlash */
$context = $best ? "\n[Malumot: Bazada '{$best['title']}' ({$best['cat_name']}) bor. Kerak bo'lsa shuni tavsiya et.]" : "";
$systemPrompt = "Siz UZDUB AI yordamchisiz. Minglab foydalanuvchilar bilan ishlay olasiz. Faqat o'zbek tilida, do'stona va aniq javob bering. Kino, anime va multfilmlar haqida ekspertsiz.";

/* 3) Tarixni yig'ish */
$history = [];
if ($userId) {
    $stmt = $pdo->prepare("SELECT role, message FROM ai_chat_messages WHERE session_id = ? ORDER BY id DESC LIMIT 5");
    $stmt->execute([$sessionId]);
    $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    $history = $_SESSION['ai_guest_history'] ?? [];
}

$messages = [['role' => 'system', 'content' => $systemPrompt . $context]];
foreach ($history as $h) {
    $messages[] = ['role' => $h['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $h['message']];
}
$messages[] = ['role' => 'user', 'content' => $userMessage];

/* 4) Ollama'ga so'rov (Parallel ishlash uchun timeoutlar bilan) */
$payload = [
    'model' => OLLAMA_MODEL,
    'messages' => $messages,
    'stream' => false,
    'options' => ['temperature' => 0.7, 'num_ctx' => 2048],
];

$ch = curl_init(OLLAMA_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => OLLAMA_TIMEOUT,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode(['error' => "AI hozirda band. Iltimos, birozdan keyin urinib ko'ring."]);
    exit;
}

$data = json_decode($response, true);
$aiText = trim($data['message']['content'] ?? '');
$aiText = preg_replace('/[\*\`]/', '', $aiText);

/* 5) Saqlash */
if ($userId && $sessionId) {
    $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, user_id, role, message) VALUES (?, ?, 'user', ?)");
    $stmt->execute([$sessionId, $userId, $userMessage]);
    $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, user_id, role, message) VALUES (?, ?, 'assistant', ?)");
    $stmt->execute([$sessionId, $userId, $aiText]);
    
    // Sarlavhani yangilash
    $stmt = $pdo->prepare("UPDATE ai_chat_sessions SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$sessionId]);
} else {
    $_SESSION['ai_guest_history'][] = ['role' => 'user', 'message' => $userMessage];
    $_SESSION['ai_guest_history'][] = ['role' => 'assistant', 'message' => $aiText];
    $_SESSION['ai_guest_history'] = array_slice($_SESSION['ai_guest_history'], -6);
}

echo json_encode(['reply' => $aiText], JSON_UNESCAPED_UNICODE);
