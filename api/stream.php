<?php
/* ============================================================
   api/stream.php
   UZDUB AI — provider fallback: Groq → Cerebras → Ollama
   Birinchi provider ishlamasa (limit/xato), keyingisiga avtomatik o'tadi.
   ============================================================ */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ai_secrets.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
while (ob_get_level() > 0) { ob_end_flush(); }
ob_implicit_flush(true);

function sse_send($data) {
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userMessage = isset($input['message']) ? trim($input['message']) : '';
$sessionId = isset($input['session_id']) ? (int)$input['session_id'] : null;
$inputLang = $input['lang'] ?? '';

if ($userMessage === '') { sse_send(['error' => "Xabar bo'sh bo'lishi mumkin emas."]); exit; }
if (!$sessionId) { sse_send(['error' => 'Chat session_id talab qilinadi']); exit; }

$userId = $_SESSION['user_id'] ?? null;

// Premium tekshirish
if ($userId) {
    $chk = $pdo->prepare("SELECT is_premium FROM users WHERE id = ?");
    $chk->execute([$userId]);
    if (!$chk->fetchColumn()) {
        sse_send(['error' => 'AI chat faqat Premium foydalanuvchilar uchun.']);
        exit;
    }
} else {
    sse_send(['error' => 'AI chatdan foydalanish uchun tizimga kirishingiz kerak.']);
    exit;
}

$userLang = $inputLang ?: ($_SESSION['lang'] ?? 'uz');
if (!in_array($userLang, ['uz', 'ru', 'en'])) $userLang = 'uz';

// IDOR himoyasi
if ($userId && $sessionId) {
    $own = $pdo->prepare("SELECT id FROM ai_chat_sessions WHERE id = ? AND user_id = ?");
    $own->execute([$sessionId, $userId]);
    if (!$own->fetch()) { sse_send(['error' => 'Chat sessiyasi topilmadi']); exit; }
}

if (!validate_csrf($input['csrf_token'] ?? '')) {
    sse_send(['error' => "Xavfsizlik tokeni noto'g'ri"]);
    exit;
}

if (!ai_queue_try_acquire()) {
    sse_send(['busy' => true, 'msg' => "AI hozir band, biroz kuting..."]);
    exit;
}
register_shutdown_function('ai_queue_release');

// Kontent qidirish
$match = findBestMatches($pdo, $userMessage, AI_MAX_RECOMMENDATIONS, $userId);
$recommendations = $match['matched'] ? ai_build_recommendations($match['rows']) : [];

$context = ai_build_context_text($match['rows']);
$systemPrompt = ai_build_system_prompt($userLang);
$userContext = ai_build_user_context($pdo, $userId, $userLang);

// Suhbat tarixi
$history = [];
if ($userId) {
    $stmt = $pdo->prepare("SELECT role, message FROM ai_chat_messages WHERE user_id = :uid AND session_id = :sid ORDER BY id DESC LIMIT :lim");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':sid', $sessionId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', AI_HISTORY_MESSAGES, PDO::PARAM_INT);
    $stmt->execute();
    $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
} else {
    if (!isset($_SESSION['ai_guest_session_id']) || $_SESSION['ai_guest_session_id'] != $sessionId) {
        $_SESSION['ai_guest_session_id'] = $sessionId;
        $_SESSION['ai_guest_history'] = [];
    }
    $_SESSION['ai_guest_history'] = $_SESSION['ai_guest_history'] ?? [];
    $history = array_slice($_SESSION['ai_guest_history'], -AI_HISTORY_MESSAGES);
}

$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
];
foreach ($history as $h) {
    $messages[] = ['role' => $h['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $h['message']];
}
$messages[] = ['role' => 'user', 'content' => $userMessage . $context . $userContext];

// ===== Provider fallback tizimi =====
$fullText = '';
$gotAnyToken = false;
$workingProvider = null;

// 1) Cloud provider'larni sinab ko'rish (Groq → Cerebras → ...)
foreach (AI_PROVIDERS as $provider) {
    if (empty($provider['key'])) continue;

    $payload = [
        'model' => $provider['model'],
        'messages' => $messages,
        'stream' => true,
        'temperature' => 0.6,
        'max_tokens' => OLLAMA_NUM_PREDICT,
    ];

    $ch = curl_init($provider['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $provider['key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $provider['timeout'],
        CURLOPT_WRITEFUNCTION => function ($curlHandle, $chunk) use (&$fullText, &$gotAnyToken) {
            static $buffer = '';
            $buffer .= $chunk;
            while (($nl = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $nl));
                $buffer = substr($buffer, $nl + 1);
                if ($line === '' || $line === 'data: [DONE]') continue;
                if (strpos($line, 'data: ') !== 0) continue;

                $obj = json_decode(substr($line, 6), true);
                if (!is_array($obj)) continue;

                $piece = $obj['choices'][0]['delta']['content'] ?? '';
                if ($piece !== '') {
                    $gotAnyToken = true;
                    $fullText .= $piece;
                    $clean = preg_replace(['/\*{1,2}/', '/`+/'], '', $piece);
                    sse_send(['delta' => $clean]);
                }
            }
            return strlen($chunk);
        },
    ]);

    curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Muvaffaqiyatli ishladi
    if ($gotAnyToken) {
        $workingProvider = $provider['name'];
        break;
    }

    // Limit yoki xato — keyingi provider'ga o't
    error_log("UZDUB AI [{$provider['name']}] ishlamadi: HTTP {$httpCode}, curl: {$curlError}");
    $fullText = '';
    $gotAnyToken = false;
    continue;
}

// 2) Agar cloud provider'lar ishlamagan bo'lsa — Ollama (local)
if (!$gotAnyToken) {
    $payload = [
        'model' => OLLAMA_MODEL,
        'messages' => $messages,
        'stream' => true,
        'options' => [
            'temperature' => 0.6,
            'num_ctx' => OLLAMA_NUM_CTX,
            'num_predict' => OLLAMA_NUM_PREDICT,
            'num_thread' => OLLAMA_NUM_THREAD,
        ],
    ];

    $ch = curl_init(OLLAMA_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => OLLAMA_TIMEOUT,
        CURLOPT_WRITEFUNCTION => function ($curlHandle, $chunk) use (&$fullText, &$gotAnyToken) {
            static $buffer = '';
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
    curl_close($ch);

    if ($gotAnyToken) $workingProvider = 'ollama';
}

// Hech qanday provider ishlamagan
if (!$gotAnyToken) {
    error_log("UZDUB AI: barcha provider'lar ishlamadi (Groq, Cerebras, Ollama)");
    sse_send(['error' => "AI hozircha javob bera olmadi. Birozdan so'ng qaytadan urinib ko'ring."]);
    exit;
}

// Done signal
sse_send(['done' => true]);

$aiText = trim(preg_replace(['/\*{1,2}/', '/`+/'], '', $fullText));

// Chat sarlavhasini yangilash
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

if ($recommendations) {
    sse_send(['recommendations' => $recommendations]);
}
