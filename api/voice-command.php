<?php
/* ============================================================
   api/voice-command.php
   "UZDUB PLATFORM AI" ovozli yordamchisi uchun buyruq (intent) tahlilchisi.
   1) Avval oddiy saytga tegishli buyruqlarni (sahifaga o'tish,
      qidirish, kontentni ochish, video boshqaruvi) aniqlashga harakat qiladi
   2) Hech biriga mos kelmasa — umumiy suhbat uchun Ollama'ga murojaat qiladi
   ============================================================ */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ai_secrets.php';
require_once __DIR__ . '/../includes/functions.php';

$input = json_decode(file_get_contents('php://input'), true);
$text = isset($input['text']) ? trim($input['text']) : '';

if ($text === '') {
    echo json_encode(['action' => 'speak', 'speak' => "Buyruqni eshitmadim, qaytadan urinib ko'ring."]);
    exit;
}

if (!validate_csrf($input['csrf_token'] ?? '')) {
    echo json_encode(['action' => 'speak', 'speak' => "Xavfsizlik tokeni noto'g'ri. Sahifani yangilang."]);
    exit;
}

$norm = mb_strtolower(trim($text));
$norm = preg_replace('/[.,!?;:]/u', '', $norm);

$BASE = '/uzdub/';

function contains($haystack, array $needles) {
    foreach ($needles as $n) {
        if (mb_strpos($haystack, $n) !== false) return true;
    }
    return false;
}

/* Berilgan trigger so'zlardan keyingi qismni "ob'ekt nomi" sifatida ajratib olish
   Masalan: "re zero ni och" + trigger "ni och" -> "re zero" */
function extractEntity($norm, array $triggers) {
    foreach ($triggers as $t) {
        $pos = mb_strpos($norm, $t);
        if ($pos !== false) {
            $entity = trim(mb_substr($norm, 0, $pos));
            if ($entity !== '') return $entity;
        }
    }
    return null;
}

$response = null;

/* ---------- 1) Navigatsiya buyruqlari ---------- */
if (contains($norm, ['bosh sahifa', 'asosiy sahifa', 'bosh sahifaga'])) {
    $response = ['action' => 'navigate', 'url' => $BASE . 'index.php', 'speak' => "Bosh sahifaga o'tyapman."];
} elseif (contains($norm, ['anime bo\'lim', 'anime bolim', 'animega']) || $norm === 'anime') {
    $response = ['action' => 'navigate', 'url' => $BASE . 'category.php?slug=anime', 'speak' => "Anime bo'limini ochyapman."];
} elseif (contains($norm, ['kino bo\'lim', 'kino bolim', 'kinolarga']) || $norm === 'kino') {
    $response = ['action' => 'navigate', 'url' => $BASE . 'category.php?slug=kino', 'speak' => "Kino bo'limini ochyapman."];
} elseif (contains($norm, ['multfilm bo\'lim', 'multfilm bolim', 'multfilmlarga']) || contains($norm, ['multfilm'])) {
    $response = ['action' => 'navigate', 'url' => $BASE . 'category.php?slug=multfilm', 'speak' => "Multfilm bo'limini ochyapman."];
} elseif (contains($norm, ['ro\'yxatim', 'royxatim', 'saqlanganlar', 'mening ro\'yxatim'])) {
    $response = ['action' => 'navigate', 'url' => $BASE . 'mylist.php', 'speak' => "Ro'yxatingizni ochyapman."];
} elseif (contains($norm, ['xabarlarim', 'xabarlar bo\'limi', 'inbox'])) {
    $response = ['action' => 'navigate', 'url' => $BASE . 'inbox.php', 'speak' => "Xabarlaringizni ochyapman."];
} elseif (contains($norm, ['premium', 'obuna'])) {
    $response = ['action' => 'navigate', 'url' => $BASE . 'premium.php', 'speak' => "Premium sahifasini ochyapman."];
} elseif (contains($norm, ['umumiy chat', 'global chat', 'suhbat xonasi'])) {
    $response = ['action' => 'navigate', 'url' => $BASE . 'global_chat.php', 'speak' => "Umumiy chatni ochyapman."];
} elseif (contains($norm, ['profilim', 'mening profilim'])) {
    $uid = is_user() ? current_user()['user_id'] : null;
    if ($uid) {
        $response = ['action' => 'navigate', 'url' => $BASE . 'profile.php?uid=' . urlencode($uid), 'speak' => "Profilingizni ochyapman."];
    } else {
        $response = ['action' => 'speak', 'speak' => "Profilni ko'rish uchun avval tizimga kiring."];
    }
} elseif (contains($norm, ['ortga', 'orqaga qayt', 'orqaga qaytar'])) {
    $response = ['action' => 'back', 'speak' => "Orqaga qaytyapman."];
}

/* ---------- 2) Video boshqaruvi (watch sahifasida ishlaydi) ---------- */
if (!$response) {
    if (contains($norm, ['pauza qil', 'to\'xtat', 'toxtat', 'video to\'xtat'])) {
        $response = ['action' => 'video', 'control' => 'pause', 'speak' => "To'xtatdim."];
    } elseif (contains($norm, ['davom ettir', 'ishga tushir', 'play qil', 'davom et'])) {
        $response = ['action' => 'video', 'control' => 'play', 'speak' => "Davom ettiryapman."];
    } elseif (contains($norm, ['ovozni o\'chir', 'ovozni ochir', 'tovushni o\'chir', 'mute qil'])) {
        $response = ['action' => 'video', 'control' => 'mute', 'speak' => "Ovozni o'chirdim."];
    } elseif (contains($norm, ['ovozni yoq', 'ovozni och', 'tovushni yoq'])) {
        $response = ['action' => 'video', 'control' => 'unmute', 'speak' => "Ovozni yoqdim."];
    } elseif (contains($norm, ['ekranni to\'ldir', 'to\'liq ekran', 'fullscreen'])) {
        $response = ['action' => 'video', 'control' => 'fullscreen', 'speak' => "To'liq ekran rejimi."];
    }
}

/* ---------- 3) Kontent ochish / qidirish ---------- */
if (!$response) {
    $entity = extractEntity($norm, ['ni och', 'ni tomosha qil', 'ni ishga tushir', 'ni boshla', 'ni izla', 'ni qidir']);

    if (!$entity && preg_match('/^(qidir|izla)\s+(.+)/u', $norm, $m)) {
        $entity = trim($m[2]);
    }
    if (!$entity && preg_match('/^(.+?)\s+(qidir|izla)$/u', $norm, $m)) {
        $entity = trim($m[1]);
    }

    if ($entity) {
        $stmt = $pdo->prepare("SELECT id, title FROM content WHERE title LIKE ? ORDER BY views DESC LIMIT 1");
        $stmt->execute(['%' . $entity . '%']);
        $found = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($found) {
            $response = ['action' => 'navigate', 'url' => $BASE . 'watch.php?id=' . (int)$found['id'], 'speak' => "\"" . $found['title'] . "\" ochilyapti."];
        } else {
            $response = ['action' => 'navigate', 'url' => $BASE . 'search.php?q=' . urlencode($entity), 'speak' => "\"" . $entity . "\" bo'yicha qidiryapman."];
        }
    }
}

/* ---------- 4) Salomlashish ---------- */
if (!$response && contains($norm, ['salom', 'assalomu alaykum', 'vazifang nima', 'nima qila olasan'])) {
    $response = ['action' => 'speak', 'speak' => "Salom! Men UZDUB PLATFORM AI. Sizga kino, anime yoki multfilm topishda, bo'limlarga o'tishda yordam bera olaman. Faqat buyruq bering."];
}

/* ---------- 5) Hech biriga mos kelmasa — umumiy suhbat AI'ga (Ollama) ---------- */
if (!$response) {
    $systemPrompt = "Siz UZDUB PLATFORM AI ovozli yordamchisiz. Faqat o'zbek tilida, juda qisqa (bitta-ikkita gap) va tabiiy tarzda javob bering, chunki javobingiz ovozda o'qib beriladi. Kino, anime, multfilm mavzusida yordam berasiz.";

    $payload = [
        'model' => OLLAMA_MODEL,
        'messages' => [
            ['role' => 'user', 'content' => $systemPrompt . "\n\nFoydalanuvchi: " . $text]
        ],
        'stream' => false,
        'options' => ['temperature' => 0.7, 'num_ctx' => 512, 'num_predict' => 100],
    ];

    $ch = curl_init(OLLAMA_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
    ]);
    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw !== false && $httpCode === 200) {
        $data = json_decode($raw, true);
        $aiText = trim($data['message']['content'] ?? '');
        $aiText = preg_replace('/\*{1,2}/', '', $aiText);
        $aiText = preg_replace('/`+/', '', $aiText);
        $response = ['action' => 'speak', 'speak' => $aiText !== '' ? $aiText : "Kechirasiz, javob topa olmadim."];
    } else {
        $response = ['action' => 'speak', 'speak' => "Kechirasiz, hozir javob bera olmayapman. AI xizmati ishlamayapti."];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
