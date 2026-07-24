<?php
/* ============================================================
   api/ai-chat.php
   UZDUB AI — tarix, foydalanuvchi ma'lumotlari, o'rganish
   ============================================================ */

session_set_cookie_params(['httponly' => true, 'secure' => isset($_SERVER['HTTPS']), 'samesite' => 'Strict']);
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

// Server bir vaqtda judа ko'p Ollama so'rovi bilan yuklanib qolmasligi uchun oddiy navbat
if (!ai_queue_try_acquire()) {
    echo json_encode(['error' => 'busy', 'busy' => true]);
    exit;
}
register_shutdown_function('ai_queue_release');

/* ------------------------------------------------------------
   1) Bazadan so'rovga mos keladigan bir nechta kontentni topish
   ------------------------------------------------------------ */
// Foydalanuvchi tilini aniqlash (frontend yuborgan yoki sessiyadan)
$inputLang = isset($input['lang']) ? trim($input['lang']) : '';
$userLang = $inputLang ?: ($_SESSION['lang'] ?? 'uz');
if (!in_array($userLang, ['uz', 'ru', 'en'])) $userLang = 'uz';

$match = findBestMatches($pdo, $userMessage, AI_MAX_RECOMMENDATIONS, $userId);
$recommendations = $match['matched'] ? ai_build_recommendations($match['rows']) : [];

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
    3) Prompt tayyorlash — ko'p tilli system prompt + shaxsiy kontekst
    ------------------------------------------------------------ */
$systemPrompt = ai_build_system_prompt($userLang);
$context = ai_build_context_text($match['rows']);
$userContext = ai_build_user_context($pdo, $userId, $userLang);

/* ------------------------------------------------------------
    4) Suhbat tarixi — MUHIM: faqat shu chat sessiyasiga tegishli xabarlar olinadi
    (avval user_id bo'yicha olinardi, bu esa turli chatlar tarixini aralashtirib yuborardi)
    ------------------------------------------------------------ */
$history = [];
if ($userId) {
    $stmt = $pdo->prepare("SELECT role, message FROM ai_chat_messages WHERE user_id = :uid AND session_id = :sid ORDER BY id DESC LIMIT :lim");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':sid', $sessionId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', AI_HISTORY_MESSAGES, PDO::PARAM_INT);
    $stmt->execute();
    $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    // Mehmon: agar frontend yangi (boshqa) session_id yuborsa — bu yangi suhbat, tarixni tozalaymiz
    if (!isset($_SESSION['ai_guest_session_id']) || $_SESSION['ai_guest_session_id'] != $sessionId) {
        $_SESSION['ai_guest_session_id'] = $sessionId;
        $_SESSION['ai_guest_history'] = [];
    }
    $_SESSION['ai_guest_history'] = $_SESSION['ai_guest_history'] ?? [];
    $history = array_slice($_SESSION['ai_guest_history'], -AI_HISTORY_MESSAGES);
}

$messages = [];
$messages[] = ['role' => 'system', 'content' => $systemPrompt];
foreach ($history as $h) {
    $messages[] = ['role' => $h['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $h['message']];
}
$messages[] = ['role' => 'user', 'content' => $userMessage . $context . $userContext];

/* ------------------------------------------------------------
   5) Ollama'ga so'rov
   ------------------------------------------------------------ */
$payload = [
    'model' => OLLAMA_MODEL,
    'messages' => $messages,
    'stream' => false,
    'options' => [
        'temperature' => 0.4,
        'num_ctx' => OLLAMA_NUM_CTX,
        'num_predict' => OLLAMA_NUM_PREDICT,
        'num_thread' => OLLAMA_NUM_THREAD,
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
    error_log("UZDUB AI xatolik (ai-chat.php): HTTP $httpCode, curl: $curlError");
    echo json_encode(['error' => "AI hozircha javob bera olmadi. Birozdan so'ng qaytadan urinib ko'ring."]);
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
    $_SESSION['ai_guest_history'] = array_slice($_SESSION['ai_guest_history'], -AI_HISTORY_MESSAGES);
}

echo json_encode(['reply' => $aiText, 'recommendations' => $recommendations], JSON_UNESCAPED_UNICODE);
