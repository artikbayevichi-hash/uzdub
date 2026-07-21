<?php
/* ============================================================
   api/stream.php
   UZDUB AI — token-token strimlanadigan (SSE) javob.
   ai-chat.js shu endpointga fetch + ReadableStream orqali ulanadi,
   javob Ollama'dan kelgan sari ekranga chiqib boradi (real streaming),
   to'liq javobni kutib turishning o'rniga.
   ============================================================ */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ai_secrets.php';
require_once __DIR__ . '/../includes/functions.php';

// SSE javob sarlavhalari
header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // nginx proxida buferlanishni o'chiradi
while (ob_get_level() > 0) { ob_end_flush(); }
ob_implicit_flush(true);

function sse_send($eventData) {
    echo 'data: ' . json_encode($eventData, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userMessage = isset($input['message']) ? trim($input['message']) : '';
$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : null;
$inputLang = $input['lang'] ?? '';

if ($userMessage === '') {
    sse_send(['error' => "Xabar bo'sh bo'lishi mumkin emas."]);
    exit;
}
if (!$sessionId) {
    sse_send(['error' => 'Chat session_id talab qilinadi']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;

// Foydalanuvchi tilini aniqlash (frontend yuboradi yoki sessiyadan oladi)
$userLang = $inputLang ?: ($_SESSION['lang'] ?? 'uz');
if (!in_array($userLang, ['uz', 'ru', 'en'])) $userLang = 'uz';

// IDOR himoyasi — sessiya haqiqatan shu foydalanuvchiga tegishlimi
if ($userId && $sessionId) {
    $own = $pdo->prepare("SELECT id FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
    $own->execute([$sessionId, $userId]);
    if (!$own->fetch()) {
        sse_send(['error' => 'Chat sessiyasi topilmadi']);
        exit;
    }
}

if (!validate_csrf($input['csrf_token'] ?? '')) {
    sse_send(['error' => "Xavfsizlik tokeni noto'g'ri"]);
    exit;
}

// Navbat: bir vaqtda judа ko'p so'rov Ollama'ga tushib ketmasligi uchun
if (!ai_queue_try_acquire()) {
    sse_send(['busy' => true, 'msg' => "AI hozir band, biroz kuting..."]);
    exit;
}
register_shutdown_function('ai_queue_release');

$match = findBestMatches($pdo, $userMessage, AI_MAX_RECOMMENDATIONS, $userId);
$recommendations = $match['matched'] ? ai_build_recommendations($match['rows']) : [];

$userInfo = '';
if ($userId) {
    $stmt = $pdo->prepare("SELECT username, is_premium FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u) $userInfo = $u['username'] . ' (' . ($u['is_premium'] ? 'premium' : 'oddiy') . ')';
}

$context = ai_build_context_text($match['rows']);
$systemPrompt = ai_build_system_prompt($userLang);
$userContext = ai_build_user_context($pdo, $userId, $userLang);

// Suhbat tarixi — faqat shu chat sessiyasiga tegishli (avval user_id bo'yicha olinardi,
// bu esa turli chatlar tarixini bir-biriga aralashtirib yuborardi)
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

$payload = [
    'model' => OLLAMA_MODEL,
    'messages' => $messages,
    'stream' => true,
    'options' => [
        'temperature' => 0.4,
        'num_ctx' => OLLAMA_NUM_CTX,
        'num_predict' => OLLAMA_NUM_PREDICT,
        'num_thread' => OLLAMA_NUM_THREAD,
    ],
];

$fullText = '';
$buffer = '';
$gotAnyToken = false;

$ch = curl_init(OLLAMA_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => OLLAMA_TIMEOUT,
    // Ollama har bir tokenni alohida JSON qatori (NDJSON) sifatida yuboradi;
    // shu qatorlarni kelgan zahoti o'qib, mijozga darhol uzatamiz.
    CURLOPT_WRITEFUNCTION => function ($curlHandle, $chunk) use (&$buffer, &$fullText, &$gotAnyToken) {
        $buffer .= $chunk;
        while (($nl = strpos($buffer, "\n")) !== false) {
            $line = trim(substr($buffer, 0, $nl));
            $buffer = substr($buffer, $nl + 1);
            if ($line === '') continue;

            $obj = json_decode($line, true);
            if (!is_array($obj)) continue;

            $piece = $obj['message']['content'] ?? '';
            if ($piece !== '') {
                $gotAnyToken = true;
                $fullText .= $piece;
                $clean = preg_replace(['/\*{1,2}/', '/`+/'], '', $piece);
                sse_send(['delta' => $clean]);
            }
            if (!empty($obj['done'])) {
                sse_send(['done' => true]);
            }
        }
        return strlen($chunk);
    },
]);

curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if (!$gotAnyToken) {
    error_log("UZDUB AI xatolik (stream.php): curl: " . ($curlError ?: 'javob olinmadi'));
    sse_send(['error' => "AI hozircha javob bera olmadi. Birozdan so'ng qaytadan urinib ko'ring."]);
    exit;
}

$aiText = trim(preg_replace(['/\*{1,2}/', '/`+/'], '', $fullText));

// Chat sarlavhasini yangilash (birinchi xabar uchun)
if ($userId && $sessionId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ai_chat_messages WHERE session_id = ?");
    $stmt->execute([$sessionId]);
    if ($stmt->fetchColumn() == 0) {
        $title = mb_substr($userMessage, 0, 30) . (mb_strlen($userMessage) > 30 ? '...' : '');
        $pdo->prepare("UPDATE ai_chat_sessions SET title = ? WHERE id = ? AND user_id = ?")
            ->execute([$title, $sessionId, $userId]);
    }
}

// Suhbatni saqlash
if ($userId && $sessionId) {
    $pdo->prepare("INSERT INTO ai_chat_messages (session_id, user_id, role, message) VALUES (:sid, :uid, 'user', :msg)")
        ->execute(['sid' => $sessionId, 'uid' => $userId, 'msg' => $userMessage]);
    $pdo->prepare("INSERT INTO ai_chat_messages (session_id, user_id, role, message) VALUES (:sid, :uid, 'assistant', :msg)")
        ->execute(['sid' => $sessionId, 'uid' => $userId, 'msg' => $aiText]);
} else {
    $_SESSION['ai_guest_history'][] = ['role' => 'user', 'message' => $userMessage];
    $_SESSION['ai_guest_history'][] = ['role' => 'assistant', 'message' => $aiText];
    $_SESSION['ai_guest_history'] = array_slice($_SESSION['ai_guest_history'], -AI_HISTORY_MESSAGES);
}

// Mos kontent topilgan bo'lsa — kartochka sifatida ko'rsatish uchun frontendga yuboramiz
if ($recommendations) {
    sse_send(['recommendations' => $recommendations]);
}
