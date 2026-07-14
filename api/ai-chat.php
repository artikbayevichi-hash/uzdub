<?php
/* ============================================================
   api/ai-chat.php
   UZDUB AI — tarix, foydalanuvchi ma'lumotlari, o'rganish
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

// Sessiya haqiqatan shu foydalanuvchiga tegishli ekanini tekshirish (IDOR himoyasi)
if ($userId && $sessionId) {
    $own = $pdo->prepare("SELECT id FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
    $own->execute([$sessionId, $userId]);
    if (!$own->fetch()) {
        echo json_encode(['error' => 'Chat sessiyasi topilmadi']);
        exit;
    }
}

if (!validate_csrf($input['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    echo json_encode(['error' => 'Xavfsizlik tokeni noto\'g\'ri']);
    exit;
}

/* ------------------------------------------------------------
   1) Bazadan 1 ta eng yaxshi mos keladigan kontentni topish
   ------------------------------------------------------------ */
function findBestMatch(PDO $pdo, string $message): ?array {
    $words = preg_split('/\s+/u', mb_strtolower($message));
    $words = array_values(array_filter($words, fn($w) => mb_strlen($w) >= 3));

    if (empty($words)) {
        $stmt = $pdo->query("SELECT title, name AS cat_name FROM content c JOIN categories cat ON c.category_id = cat.id ORDER BY views DESC LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $conditions = [];
    $params = [];
    foreach ($words as $i => $w) {
        $conditions[] = "(c.title LIKE :w{$i})";
        $params[":w{$i}"] = '%' . $w . '%';
    }

    $sql = "SELECT c.title, cat.name AS cat_name
            FROM content c
            JOIN categories cat ON c.category_id = cat.id
            WHERE " . implode(' OR ', $conditions) . "
            ORDER BY c.views DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$best = findBestMatch($pdo, $userMessage);

/* ------------------------------------------------------------
    2) Foydalanuvchi haqida ma'lumot yig'ish
    ------------------------------------------------------------ */
$userInfo = '';
if ($userId) {
    $stmt = $pdo->prepare("SELECT username, is_premium FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($u) {
        $userInfo = $u['username'] . ' (' . ($u['is_premium'] ? 'premium' : 'oddiy') . ')';
    }
}

/* ------------------------------------------------------------
    3) Prompt tayyorlash
    ------------------------------------------------------------ */
$context = '';
if ($best) {
    $context = "\n[Malumot: Bazada '{$best['title']}' ({$best['cat_name']}) bor. Kerak bo'lsa shuni tavsiya et.]";
}

$systemPrompt = "Siz UZDUB AI yordamchisiz. Siz faqat o'zbek tilida javob berasiz. Har qanday savolga o'zbek tilida, tushunarli va qisqa javob bering. Kino, anime va multfilmlar haqida savollarga javob berasiz.";

$history = [];
if ($userId) {
    $stmt = $pdo->prepare("SELECT role, message FROM ai_chat_messages WHERE user_id = :uid ORDER BY id DESC LIMIT 2");
    $stmt->execute(['uid' => $userId]);
    $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    $_SESSION['ai_guest_history'] = $_SESSION['ai_guest_history'] ?? [];
    $history = $_SESSION['ai_guest_history'];
}

$messages = [];
if (!empty($history)) {
    foreach ($history as $h) {
        $messages[] = ['role' => $h['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $h['message']];
    }
}
$messages[] = ['role' => 'user', 'content' => $systemPrompt . "\n\n" . $userMessage . $context];

/* ------------------------------------------------------------
   5) Ollama'ga so'rov
   ------------------------------------------------------------ */
$payload = [
    'model' => OLLAMA_MODEL,
    'messages' => $messages,
    'stream' => false,
    'options' => [
        'temperature' => 0.8,
        'num_ctx' => 1024,
        'num_predict' => 200,
    ],
];

$ch = curl_init(OLLAMA_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => OLLAMA_TIMEOUT,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode(['error' => "AI xatolik: $curlError (HTTP $httpCode)"]);
    exit;
}

$data = json_decode($response, true);
$aiText = trim($data['message']['content'] ?? '');

if (!$aiText) {
    echo json_encode(['error' => "Javob bo'sh."]);
    exit;
}

$aiText = preg_replace('/\*{1,2}/', '', $aiText);
$aiText = preg_replace('/`+/', '', $aiText);
$aiText = trim($aiText);

/* ------------------------------------------------------------
   5) Chat sarlavhasini yangilash (birinchi xabar uchun)
   ------------------------------------------------------------ */
if ($userId && $sessionId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_messages WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    $msgCount = $stmt->fetchColumn();
    
    if ($msgCount == 0) {
        $title = mb_substr($userMessage, 0, 30) . (mb_strlen($userMessage) > 30 ? '...' : '');
        $stmt = $pdo->prepare("UPDATE ai_chat_sessions SET title = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$title, $sessionId, $userId]);
    }
}

/* ------------------------------------------------------------
   6) Suhbatni saqlash
   ------------------------------------------------------------ */
if ($userId && $sessionId) {
    $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, user_id, role, message) VALUES (:sid, :uid, 'user', :msg)");
    $stmt->execute(['sid' => $sessionId, 'uid' => $userId, 'msg' => $userMessage]);
    $stmt = $pdo->prepare("INSERT INTO ai_chat_messages (session_id, user_id, role, message) VALUES (:sid, :uid, 'assistant', :msg)");
    $stmt->execute(['sid' => $sessionId, 'uid' => $userId, 'msg' => $aiText]);
} else {
    $_SESSION['ai_guest_history'][] = ['role' => 'user', 'message' => $userMessage];
    $_SESSION['ai_guest_history'][] = ['role' => 'assistant', 'message' => $aiText];
    $_SESSION['ai_guest_history'] = array_slice($_SESSION['ai_guest_history'], -4);
}

echo json_encode(['reply' => $aiText], JSON_UNESCAPED_UNICODE);
