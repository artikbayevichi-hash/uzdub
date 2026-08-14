<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']),
        'samesite' => 'Lax'
    ]);
    session_start();
}
require_once __DIR__ . '/lang.php';

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// ===== Yengil fayl-kesh (og'ir so'rovlar natijasini qisqa muddatga keshlash) =====
function db_cache_get(string $key, int $ttl) {
    $file = __DIR__ . '/../cache/' . md5($key) . '.cache';
    if (is_file($file)) {
        $data = @unserialize(@file_get_contents($file));
        if (is_array($data) && isset($data['exp'], $data['val']) && time() < $data['exp']) {
            return $data['val'];
        }
    }
    return null;
}

function db_cache_set(string $key, $val, int $ttl): void {
    $dir = __DIR__ . '/../cache';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/' . md5($key) . '.cache', serialize(['exp' => time() + $ttl, 'val' => $val]));
}

// ===== ADMIN =====
function is_logged_in() { return isset($_SESSION['admin_id']); }
function require_login() { if (!is_logged_in()) { header('Location: login.php'); exit; } }

// ===== USER =====
function is_user() { return isset($_SESSION['user_id']); }
function current_user() { return $_SESSION['user_data'] ?? null; }

function require_user() {
    if (!is_user()) { header('Location: ' . ROOT_URL . '/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit; }
}

function check_premium_expiry($pdo, $user_db_id) {
    $stmt = $pdo->prepare("SELECT is_premium, premium_expires_at FROM users WHERE id = ?");
    $stmt->execute([$user_db_id]);
    $u = $stmt->fetch();
    if ($u && $u['is_premium'] && $u['premium_expires_at'] && strtotime($u['premium_expires_at']) < time()) {
        $pdo->prepare("UPDATE users SET is_premium=0, premium_expires_at=NULL WHERE id=?")->execute([$user_db_id]);
        if (isset($_SESSION['user_data'])) {
            $_SESSION['user_data']['is_premium'] = 0;
            $_SESSION['user_data']['premium_expires_at'] = null;
        }
    }
}

// ===== Joriy foydalanuvchida faol premium bor-yo'qligini tekshirish (paywall uchun) =====
function has_premium_access($pdo) {
    if (!is_user()) return false;
    check_premium_expiry($pdo, $_SESSION['user_id']);
    refresh_user_session($pdo, $_SESSION['user_id']);
    $u = current_user();
    return (bool)($u && $u['is_premium']);
}

// ===== AI chat uchun so'rovlar navbati (bir vaqtda juda ko'p Ollama so'rovi yubormaslik uchun) =====
function ai_queue_slot_path() {
    return sys_get_temp_dir() . '/uzdub_ai_active.count';
}

function ai_queue_try_acquire() {
    $path = ai_queue_slot_path();
    $fp = @fopen($path, 'c+');
    if (!$fp) return true; // fayl ochilmasa, cheklovsiz davom etamiz
    flock($fp, LOCK_EX);
    $count = (int)stream_get_contents($fp);
    if ($count >= OLLAMA_MAX_CONCURRENT) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }
    $count++;
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, (string)$count);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

function ai_queue_release() {
    $path = ai_queue_slot_path();
    $fp = @fopen($path, 'c+');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    $count = max(0, (int)stream_get_contents($fp) - 1);
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, (string)$count);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

// ===== AI chat: foydalanuvchining ko'rish tarixini olish (shaxsiy tavsiyalar uchun) =====
function ai_get_user_watch_history(PDO $pdo, int $userId, int $limit = 5): array {
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.category_id, cat.name AS cat_name, c.rating
        FROM watch_progress wp
        JOIN content c ON wp.content_id = c.id
        JOIN categories cat ON c.category_id = cat.id
        WHERE wp.user_id = ?
        GROUP BY c.id
        ORDER BY MAX(wp.updated_at) DESC
        LIMIT $limit
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== AI chat: xabardan janr kalit so'zlarini aniqlash (UZ, RU, EN tillarida) =====
function aiExtractGenreHints(string $message): array {
    static $map = [
        // O'zbekcha
        'kulgili' => 'komediya', 'komediya' => 'komediya', 'komik' => 'komediya', 'hazil' => 'komediya', 'hazilkash' => 'komediya',
        'romantik' => 'romantika', 'sevgi' => 'romantika', 'muhabbat' => 'romantika', 'romantika' => 'romantika', 'ishqiy' => 'romantika',
        "qo'rqinchli" => 'qorqinchli', 'qorqinchli' => 'qorqinchli', 'xorror' => 'qorqinchli', 'horror' => 'qorqinchli', "qo'rqmoq" => 'qorqinchli', 'dahshat' => 'qorqinchli',
        'jangari' => 'sarguzasht', 'aksiya' => 'sarguzasht', 'action' => 'sarguzasht', 'sarguzasht' => 'sarguzasht', 'jani' => 'sarguzasht',
        'fantastik' => 'fantastika', 'fantastika' => 'fantastika', 'fantaziya' => 'fantastika', 'fentezi' => 'fantastika', 'fantasy' => 'fantastika',
        'drama' => 'drama', 'dramatik' => 'drama',
        'triller' => 'triller', 'thriller' => 'triller',
        'harbiy' => 'harbiy', 'urush' => 'harbiy', 'vojenniy' => 'harbiy', 'war' => 'harbiy',
        'tarixiy' => 'tarixiy', 'tarix' => 'tarixiy', 'istorik' => 'tarixiy', 'historical' => 'tarixiy',
        'sport' => 'sport', 'sportiv' => 'sport',
        'sehrgar' => 'sehrgar', 'sehrli' => 'sehrgar', 'sehr' => 'sehrgar', 'magiya' => 'sehrgar', 'magic' => 'sehrgar',
        'isekai' => 'isekai',
        'hayotiy' => 'hayotiy', 'hayot' => 'hayotiy', 'slife' => 'hayotiy', 'slice' => 'hayotiy',
        'psixologik' => 'psixologik', 'psixologiya' => 'psixologik', 'psychological' => 'psixologik',
        'detektiv' => 'detektiv', 'detective' => 'detektiv', 'sirli' => 'detektiv', 'sir' => 'detektiv',
        'melodrama' => 'melodrama',
        'kriminal' => 'kriminal', 'crime' => 'kriminal',
        'mexa' => 'mecha', 'robot' => 'mecha',
        'muzik' => 'muzikal', 'musical' => 'muzikal', 'musiqa' => 'muzikal',
        'multfilm' => 'multfilm', 'animation' => 'multfilm', 'animated' => 'multfilm', 'anime' => 'anime',
        // Русский
        'смешной' => 'komediya', 'комедия' => 'komediya', 'юмор' => 'komediya',
        'романтика' => 'romantika', 'любовь' => 'romantika', 'романтический' => 'romantika',
        'страшный' => 'qorqinchli', 'ужасы' => 'qorqinchli', 'хоррор' => 'qorqinchli',
        'боевик' => 'sarguzasht', 'экшн' => 'sarguzasht', 'приключения' => 'sarguzasht',
        'фантастика' => 'fantastika', 'фэнтези' => 'fantastika',
        'триллер' => 'triller',
        'военный' => 'harbiy', 'война' => 'harbiy',
        'исторический' => 'tarixiy', 'история' => 'tarixiy',
        'спорт' => 'sport', 'спортивный' => 'sport',
        'детектив' => 'detektiv',
        'криминал' => 'kriminal',
        'драма' => 'drama',
        // English
        'funny' => 'komediya', 'comedy' => 'komediya', 'humor' => 'komediya', 'humour' => 'komediya',
        'romance' => 'romantika', 'romantic' => 'romantika', 'love' => 'romantika',
        'scary' => 'qorqinchli', 'horror' => 'qorqinchli', 'fright' => 'qorqinchli',
        'action' => 'sarguzasht', 'adventure' => 'sarguzasht',
        'fantasy' => 'fantastika', 'sci-fi' => 'fantastika', 'scifi' => 'fantastika', 'science' => 'fantastika',
        'thriller' => 'triller',
        'military' => 'harbiy', 'war' => 'harbiy', 'army' => 'harbiy',
        'historical' => 'tarixiy', 'history' => 'tarixiy',
        'sport' => 'sport', 'sports' => 'sport',
        'drama' => 'drama',
        'detective' => 'detektiv', 'mystery' => 'detektiv',
        'crime' => 'kriminal',
        'psychological' => 'psixologik',
    ];
    $lower = mb_strtolower($message);
    $hints = [];
    foreach ($map as $needle => $slug) {
        if (mb_strpos($lower, $needle) !== false) $hints[$slug] = true;
    }
    return array_keys($hints);
}

// ===== AI chat: ko'p tilli system prompt yaratish =====
function ai_build_system_prompt(string $lang = 'uz'): string {
    $prompts = [
        'uz' => "Sen UZDUB AI yordamchisan. Kino, anime va multfilm tavsiya qilasan. "
            . "Do'stona, qisqa (2-3 gap) va tabiiy javob ber. Emotikon ishlat. "
            . "Faqat o'zbek tilida javob ber. Savol noaniq bo'lsa, aniqlashtirish so'ra.\n"
            . "Agar bazadan kontentlar berilsa — eng mosini tavsiya qil, nomi, yili, janri va reytingini aytil. "
            . "Har bir tavsiyani " . (defined('SITE_URL') ? SITE_URL : 'http://localhost/uzdub') . "/watch.php?id=<ID> havolasi bilan tugat — foydalanuvchi shu havola orqali to'g'ridan-to'g'ri ko'ra oladi. "
            . "Havolani qisqa va tushunarli yoz, masalan: 'Ko'rish: " . (defined('SITE_URL') ? SITE_URL : 'http://localhost/uzdub') . "/watch.php?id=1'. "
            . "Ro'yxat bo'sh bo'lsa — saytda hali yo'q deb ayting va boshqa janr taklif qil.\n"
            . "Siyosat, din, huquq mavzularida javob bermaydi. Faqat UZDUB kontentini tavsiya qil.",

        'ru' => "Ты AI-помощник UZDUB. Рекомендуешь фильмы, аниме и мультфильмы. "
            . "Дружелюбно, кратко (2-3 предложения) и естественно отвечай. Используй эмодзи. "
            . "Только на русском языке. Если вопрос неясен — уточни.\n"
            . "Если из базы есть контент — порекомендуй лучший, укажи название, год, жанр, рейтинг. "
            . "Каждую рекомендацию завершай ссылкой " . (defined('SITE_URL') ? SITE_URL : 'http://localhost/uzdub') . "/watch.php?id=<ID> — пользователь сможет сразу посмотреть. "
            . "Пиши ссылку кратко, например: 'Смотреть: " . (defined('SITE_URL') ? SITE_URL : 'http://localhost/uzdub') . "/watch.php?id=1'. "
            . "Если списка нет — скажи что контента пока нет и предложи другой жанр.\n"
            . "Не отвечай на темы политики, религии, права. Только контент UZDUB.",

        'en' => "You are UZDUB AI assistant. You recommend movies, anime and cartoons. "
            . "Be friendly, brief (2-3 sentences) and natural. Use emojis. "
            . "Answer only in English. If the question is unclear — ask for clarification.\n"
            . "If there's content from the database — recommend the best, mention name, year, genre, rating. "
            . "End each recommendation with a link " . (defined('SITE_URL') ? SITE_URL : 'http://localhost/uzdub') . "/watch.php?id=<ID> — the user can watch directly. "
            . "Write the link briefly, e.g.: 'Watch: " . (defined('SITE_URL') ? SITE_URL : 'http://localhost/uzdub') . "/watch.php?id=1'. "
            . "If the list is empty — say content isn't available yet and suggest another genre.\n"
            . "Don't answer politics, religion, law topics. Only UZDUB content.",
    ];
    return $prompts[$lang] ?? $prompts['uz'];
}

// ===== AI chat: foydalanuvchi kontekstini (tarix + shaxsiy ma'lumot) yig'ish =====
function ai_build_user_context(PDO $pdo, ?int $userId, string $lang = 'uz'): string {
    $parts = [];
    if ($userId) {
        $history = ai_get_user_watch_history($pdo, $userId, 6);
        if (!empty($history)) {
            $labels = [
                'uz' => "Foydalanuvchining yaqinda ko'rgan kontentlari:",
                'ru' => "Недавно просмотренный контент пользователя:",
                'en' => "User's recently watched content:",
            ];
            $parts[] = ($labels[$lang] ?? $labels['uz']);
            foreach ($history as $h) {
                $parts[] = "- {$h['title']} ({$h['cat_name']})";
            }
            $parts[] = "";
            $prefLabels = [
                'uz' => "Foydalanuvchi ko'p ko'rgan kategoriyalarga asoslanib, shu janrdagi kontentlarni birinchi o'ringa qo'ying.",
                'ru' => "Основываясь на недавно просмотренных категориях пользователя, рекомендуйте контент из этих жанров в первую очередь.",
                'en' => "Based on the user's recently watched categories, prioritize content from those genres.",
            ];
            $parts[] = ($prefLabels[$lang] ?? $prefLabels['uz']);
        }
    }
    return !empty($parts) ? "\n\n" . implode("\n", $parts) : '';
}

// ===== AI chat uchun bazadan mos keladigan kontentni topish (bir nechta nomzod) =====
// Qaytaradi: ['matched' => bool, 'rows' => [...]]
// matched=false bo'lsa, 'rows' faqat suhbat uchun umumiy kontekst (eng ko'p ko'rilganlar),
// chatda tavsiya kartochkasi sifatida ko'rsatilmaydi — chunki so'rovga chindan mos kelmagan.
// $userId berilsa, foydalanuvchi yaqinda ko'rgan kategoriyalardagi kontentlar yuqoriroq ko'rinadi.
// ===== AI chat: foydalanuvchi xabaridan kategoriya niyatini aniqlash =====
function findBestMatches(PDO $pdo, string $message, int $limit = 3, ?int $userId = null): array {
    $cols = "c.id, c.title, c.description, c.poster, c.release_year, c.rating, c.is_premium, cat.name AS cat_name, "
          . "c.studio, c.director, c.duration, c.status";

    // Janrlarni birlashtirib olish (GROUP_CONCAT)
    $genreJoin = "LEFT JOIN (SELECT cg.content_id, GROUP_CONCAT(g.name SEPARATOR ', ') AS genre_names "
               . "FROM content_genres cg JOIN genres g ON g.id=cg.genre_id GROUP BY cg.content_id) gr ON gr.content_id=c.id";

    // Umumiy fallback (odatiy tartibda eng ko'p ko'rilganlar)
    $fallback = function () use ($pdo, $cols, $limit, $userId, $genreJoin) {
        $extraCols = ", gr.genre_names";
        if ($userId) {
            $stmt = $pdo->prepare("SELECT $cols $extraCols, IF(c.category_id IN (SELECT DISTINCT cat2.id FROM watch_progress wp JOIN content c2 ON wp.content_id=c2.id JOIN categories cat2 ON c2.category_id=cat2.id WHERE wp.user_id=?), 1, 0) AS pref
                FROM content c
                JOIN categories cat ON c.category_id = cat.id
                $genreJoin 
                ORDER BY pref DESC, c.views DESC
                LIMIT $limit");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->query("SELECT $cols $extraCols, 0 AS pref FROM content c JOIN categories cat ON c.category_id = cat.id $genreJoin  ORDER BY c.views DESC LIMIT $limit");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    };

    // 1) Janr bo'yicha so'rov (masalan: "kulgili anime tavsiya qiling")
    $genreHints = aiExtractGenreHints($message);
    if ($genreHints) {
        try {
            $ph = implode(',', array_fill(0, count($genreHints), '?'));
            $extraSelect = ', 0 AS pref'; $extraParams = [];
            if ($userId) {
                $extraSelect = ', IF(c.category_id IN (SELECT DISTINCT cat2.id FROM watch_progress wp2 JOIN content c3 ON wp2.content_id=c3.id JOIN categories cat2 ON c3.category_id=cat2.id WHERE wp2.user_id=?), 1, 0) AS pref';
                $extraParams = [$userId];
            }
            $extraCols = ", gr.genre_names";
            $stmt = $pdo->prepare("SELECT DISTINCT $cols $extraCols $extraSelect
                    FROM content c
                    JOIN categories cat ON c.category_id = cat.id
                    JOIN content_genres cg ON cg.content_id = c.id
                    JOIN genres g ON g.id = cg.genre_id
                    $genreJoin 
                    WHERE g.slug IN ($ph)
                    ORDER BY pref DESC, c.rating DESC, c.views DESC
                    LIMIT $limit");
            $allParams = array_merge($genreHints, $extraParams);
            $stmt->execute($allParams);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) return ['matched' => true, 'rows' => $rows];
        } catch (PDOException $e) {
            // genres/content_genres jadvallari hali o'rnatilmagan bo'lishi mumkin — pastga tushamiz
        }
    }

    // 2) Kalit so'zlar bo'yicha sarlavha/tavsif ustidan qidiruv (sarlavha mosligi og'irroq baholanadi)
    $words = preg_split('/\s+/u', mb_strtolower($message));
    $words = array_values(array_filter($words, fn($w) => mb_strlen($w) >= 2));

    if (empty($words)) {
        return ['matched' => false, 'rows' => $fallback()];
    }

    $titleConds = []; $descConds = []; $params = [];
    foreach ($words as $i => $w) {
        $titleConds[] = "(c.title LIKE :t{$i})";
        $descConds[]  = "(c.description LIKE :d{$i})";
        $params[":t{$i}"] = '%' . $w . '%';
        $params[":d{$i}"] = '%' . $w . '%';
    }
    $scoreSql = implode(' + ', array_map(fn($c) => "IF($c, 3, 0)", $titleConds))
              . ' + ' . implode(' + ', array_map(fn($c) => "IF($c, 1, 0)", $descConds));

    if ($userId) {
        $params[':uid'] = $userId;
        $prefSql = "IF(c.category_id IN (SELECT DISTINCT cat2.id FROM watch_progress wp JOIN content c2 ON wp.content_id=c2.id JOIN categories cat2 ON c2.category_id=cat2.id WHERE wp.user_id=:uid), 10, 0)";
    } else {
        $prefSql = '0';
    }

    $extraCols = ", gr.genre_names";
    $stmt = $pdo->prepare("SELECT $cols $extraCols, ($scoreSql) AS score, ($prefSql) AS pref
            FROM content c
            JOIN categories cat ON c.category_id = cat.id
            $genreJoin 
            HAVING score > 0
            ORDER BY pref DESC, score DESC, c.views DESC
            LIMIT $limit");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows) return ['matched' => true, 'rows' => $rows];

    // 3) So'zlar mos kelmagan bo'lsa — title, description, director, studio ustidan LIKE qidiruv
    $likeConds = [];
    $likeParams = [];
    foreach ($words as $i => $w) {
        $likeConds[] = "(c.title LIKE :l{$i} OR c.description LIKE :dl{$i} OR c.director LIKE :dr{$i} OR c.studio LIKE :s{$i})";
        $likeParams[":l{$i}"] = '%' . $w . '%';
        $likeParams[":dl{$i}"] = '%' . $w . '%';
        $likeParams[":dr{$i}"] = '%' . $w . '%';
        $likeParams[":s{$i}"] = '%' . $w . '%';
    }
    if (!empty($likeConds)) {
        $likeWhere = implode(' OR ', $likeConds);
        $stmt = $pdo->prepare("SELECT $cols $extraCols, 1 AS score, 0 AS pref FROM content c JOIN categories cat ON c.category_id = cat.id $genreJoin  WHERE $likeWhere ORDER BY c.views DESC LIMIT $limit");
        $stmt->execute($likeParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) return ['matched' => true, 'rows' => $rows];
    }

    // 4) "eng yaxshi", "top", "mashhur" kabi so'zlar bo'lsa — reyting bo'yicha eng yuqorilarni qaytarish
    $topWords = ['eng yaxshi', 'eng zo\'r', 'top', 'mashhur', 'mashhurlar', 'popular', 'best', 'top rated'];
    $lowerMsg = mb_strtolower($message);
    foreach ($topWords as $tw) {
        if (mb_strpos($lowerMsg, $tw) !== false) {
            $stmt = $pdo->prepare("SELECT $cols $extraCols, 0 AS pref FROM content c JOIN categories cat ON c.category_id = cat.id $genreJoin  ORDER BY c.rating DESC, c.views DESC LIMIT $limit");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) return ['matched' => true, 'rows' => $rows];
            break;
        }
    }

    return ['matched' => false, 'rows' => $fallback()];
}

// ===== Ollama uchun matn ko'rinishidagi kontekst (nomzod kontentlar) tayyorlash =====
function ai_build_context_text(array $rows): string {
    if (!$rows) return '';
    $lines = [];
    foreach ($rows as $r) {
        $desc = !empty($r['description']) ? mb_substr(trim(strip_tags($r['description'])), 0, 100) : '';
        $year = $r['release_year'] ?: '?';
        $rating = $r['rating'] !== null ? $r['rating'] : '?';
        $premium = !empty($r['is_premium']) ? ' [PREMIUM]' : '';
        $genres = !empty($r['genre_names']) ? " [{$r['genre_names']}]" : '';
        $studio = !empty($r['studio']) ? " ({$r['studio']})" : '';
        $statusMap = ['ongoing' => 'Davom etmoqda', 'completed' => 'Tugagan', 'upcoming' => 'Kelayotgan'];
        $status = !empty($r['status']) ? " " . ($statusMap[$r['status']] ?? $r['status']) : '';

        $lines[] = "- \"{$r['title']}\" (ID:{$r['id']}, {$r['cat_name']}, {$year}, ★{$rating}{$premium}{$genres}{$studio}{$status})";
        if ($desc) $lines[] = "  {$desc}";
    }
    return "\n[Bazadan:]\n" . implode("\n", $lines)
        . "\n\nLink: " . (defined('SITE_URL') ? SITE_URL : 'http://localhost/uzdub') . "/watch.php?id=<ID>";
}

// ===== Frontendda tavsiya kartochkasi sifatida ko'rsatish uchun tuzilgan ma'lumot =====
function ai_build_recommendations(array $rows): array {
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'         => (int)$r['id'],
            'title'      => $r['title'],
            'year'       => $r['release_year'],
            'rating'     => $r['rating'] !== null ? (float)$r['rating'] : null,
            'category'   => $r['cat_name'] ?? null,
            'is_premium' => !empty($r['is_premium']),
            'poster'     => $r['poster'] ? poster_url($r['poster']) : null,
            'url'        => ROOT_URL . '/watch.php?id=' . (int)$r['id'],
            'genres'     => $r['genre_names'] ?? null,
            'studio'     => $r['studio'] ?? null,
            'director'   => $r['director'] ?? null,
            'duration'   => $r['duration'] ?? null,
            'status'     => $r['status'] ?? null,
            'description'=> !empty($r['description']) ? mb_substr(trim(strip_tags($r['description'])), 0, 150) : null,
        ];
    }
    return $out;
}

function refresh_user_session($pdo, $user_db_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_db_id]);
    $u = $stmt->fetch();
    if ($u) {
        unset($u['password']);
        $_SESSION['user_id'] = $u['id'];
        $_SESSION['user_data'] = $u;
    }
}

function find_or_create_google_user($pdo, $google_id, $email, $name) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
    $stmt->execute([$google_id, $email]);
    $u = $stmt->fetch();
    if ($u) {
        if (empty($u['google_id'])) {
            $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?")->execute([$google_id, $u['id']]);
        }
        return $u['id'];
    }
    $uid = generate_user_id($pdo);
    $avatar_name = 'default.png';
    $pdo->prepare("INSERT INTO users (user_id, username, email, password, avatar, google_id) VALUES (?, ?, ?, '', ?, ?)")
        ->execute([$uid, $name, $email, $avatar_name, $google_id]);
    return $pdo->lastInsertId();
}

// ===== 8 xonali unikal ID yaratish =====
function generate_user_id($pdo) {
    do {
        $uid = str_pad((string)random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $exists = $pdo->prepare("SELECT id FROM users WHERE user_id = ?");
        $exists->execute([$uid]);
    } while ($exists->fetch());
    return $uid;
}

// ===== Kontent uchun avtomatik ID (masalan: KN0001, AN0002, MF0003, SR0004) =====
// ===== Slug generator (anime nomidan URL qism yasash) =====
// O'zbek/Rus lotin/kirill harflarini ASCII ga o'girib, toza slug hosil qiladi.
function generate_slug($title) {
    $s = trim((string)$title);
    if ($s === '') return null;
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'j','з'=>'z',
        'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya','ў'=>'o','қ'=>'q','ғ'=>'g',
        'ҳ'=>'h','ґ'=>'g','є'=>'e','і'=>'i','ї'=>'yi','ә'=>'a','ө'=>'o','ү'=>'u','ң'=>'n',
        'А'=>'a','Б'=>'b','В'=>'v','Г'=>'g','Д'=>'d','Е'=>'e','Ё'=>'yo','Ж'=>'j','З'=>'z',
        'И'=>'i','Й'=>'y','К'=>'k','Л'=>'l','М'=>'m','Н'=>'n','О'=>'o','П'=>'p','Р'=>'r',
        'С'=>'s','Т'=>'t','У'=>'u','Ф'=>'f','Х'=>'h','Ц'=>'ts','Ч'=>'ch','Ш'=>'sh','Щ'=>'sch',
        'Ъ'=>'','Ы'=>'y','Ь'=>'','Э'=>'e','Ю'=>'yu','Я'=>'ya','Ў'=>'o','Қ'=>'q','Ғ'=>'g',
        'Ҳ'=>'h','Ґ'=>'g','Є'=>'e','І'=>'i','Ї'=>'yi','Ә'=>'a','Ө'=>'o','Ү'=>'u','Ң'=>'n',
    ];
    $s = strtr($s, $map);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    $s = trim($s, '-');
    return $s !== '' ? substr($s, 0, 120) : null;
}

// Slugnning DB da yagonaligini ta'minlaydi: takrorlansa oxiriga -2, -3 ... qo'shadi.
function unique_slug($pdo, $title, $ignore_id = 0) {
    $base = generate_slug($title);
    if (!$base) return null;
    $slug = $base;
    $i = 2;
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM content WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $ignore_id]);
        if (!$stmt->fetch()) break;
        $slug = $base . '-' . $i;
        $i++;
    }
    return $slug;
}

function generate_content_code($pdo, $category_slug) {    $prefix_map = ['kino' => 'KN', 'anime' => 'AN', 'multfilm' => 'MF'];
    $prefix = $prefix_map[$category_slug] ?? 'CN';
    do {
        $num = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $code = $prefix . $num;
        $exists = $pdo->prepare("SELECT id FROM content WHERE content_code = ?");
        $exists->execute([$code]);
    } while ($exists->fetch());
    return $code;
}

// ===== VK Video — toza (o'z HTML5) player =====
// VK iframe'da "Смотрите также" (tavsiyalar) va sarlavhani URL parametri bilan yashirib
// bo'lmaydi — bular VK'ning o'z interfeysi (no_controls kabi parametr VK'da yo'q).
// Shu sababli VK video'ni to'g'ridan-to'g'ri mp4 fayl sifatida olib, O'Z playerimizda
// ko'rsatamiz: VK interfeysi umuman chiqmaydi, bandwidth ham ishlatilmaydi (tomoshabin
// VK CDN'dan to'g'ridan-to'g'ri yuklaydi — VK'ning o'z embed playeri ham shunday ishlaydi).

function vk_parse_url($url) {
    $q = [];
    $parsed = parse_url($url);
    if ($parsed && isset($parsed['query'])) parse_str($parsed['query'], $q);

    if (isset($q['oid']) && isset($q['id'])) {
        return [
            'oid' => $q['oid'],
            'id' => $q['id'],
            'hash' => $q['hash'] ?? '',
            't' => $q['t'] ?? '',
            'access' => $q['access_key'] ?? '',
        ];
    }
    if (preg_match('#(?:vk(?:video)?\.ru|vk\.com)/(?:clip)?video(-?\d+)_(\d+)#i', $url, $m)) {
        return [
            'oid' => $m[1],
            'id' => $m[2],
            'hash' => $q['hash'] ?? '',
            't' => $q['t'] ?? '',
            'access' => $q['access_key'] ?? '',
        ];
    }
    return null;
}

// Tozalangan VK iframe URL'i (ixtiyoriy: hd, t, loop, js_api, autoplay)
function vk_embed_src($url, $opts = []) {
    $info = vk_parse_url($url);
    if (!$info) return $url;

    $params = ['oid' => $info['oid'], 'id' => $info['id']];
    if ($info['hash'] !== '') $params['hash'] = $info['hash'];
    $params['hd'] = isset($opts['hd']) ? (int)$opts['hd'] : 3; // sukut: 720p
    if (!empty($opts['autoplay'])) $params['autoplay'] = 1; // sukut: avto ijro O'CHIK
    if (!empty($opts['loop'])) $params['loop'] = 1;
    if (!empty($opts['js_api'])) $params['js_api'] = 1;
    if (!empty($opts['t'])) $params['t'] = $opts['t'];
    elseif ($info['t'] !== '') $params['t'] = $info['t'];
    if ($info['access'] !== '') $params['access_key'] = $info['access'];

    return 'https://vkvideo.ru/video_ext.php?' . http_build_query($params);
}

function vk_cache_dir() {
    $dir = __DIR__ . '/../uploads/.vk_cache';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return (is_dir($dir) && is_writable($dir)) ? $dir : null;
}

// VK video'ning to'g'ridan-to'g'ri mp4 havolalarini server tomondan ajratadi.
// video_ext.php sahifasi (public video uchun token kerak emas) ichidagi files blokidan
// mp4_XXX URL'larini oladi va 24 soatga faylga keshlaydi. Qaytadi:
// ['best' => url, 'sources' => ['360p'=>url, ...], 'ts' => time()] yoki null.
function vk_resolve_video($url, $ttl_hours = 24) {
    $info = vk_parse_url($url);
    if (!$info) return null;

    $key = $info['oid'] . '_' . $info['id'];
    $dir = vk_cache_dir();
    $file = $dir ? $dir . '/' . $key . '.json' : null;

    if ($file && is_file($file)) {
        $cached = @json_decode(@file_get_contents($file), true);
        if (is_array($cached) && !empty($cached['best']) && isset($cached['ts'])
            && (time() - (int)$cached['ts']) < $ttl_hours * 3600) {
            return $cached;
        }
    }

    $embed = 'https://vkvideo.ru/video_ext.php?oid=' . rawurlencode($info['oid']) . '&id=' . rawurlencode($info['id']);
    if ($info['hash'] !== '') $embed .= '&hash=' . rawurlencode($info['hash']);

    // VK ba'zan login.vk.ru autologin redirect'i beradi — cookies jar kerak, aks holda
    // redirect zanjiri "invalid user"da uzilib qoladi.
    $jar = $dir ? $dir . '/cookies.txt' : null;
    if ($jar && is_file($jar)) @unlink($jar);

    $ch = curl_init($embed);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
    ];
    if ($jar) {
        $opts[CURLOPT_COOKIEJAR] = $jar;
        $opts[CURLOPT_COOKIEFILE] = $jar;
    }
    curl_setopt_array($ch, $opts);
    $html = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($html === false || $status !== 200) return null;

    if (!preg_match('#"files":\s*(\{[^}]*\})#', $html, $m)) return null;
    $files = json_decode(str_replace('\\/', '/', $m[1]), true);
    if (!is_array($files)) return null;

    $sources = [];
    foreach ($files as $k => $v) {
        if (preg_match('/^mp4_(\d+)$/', (string)$k, $km) && is_string($v) && strpos($v, 'http') === 0) {
            $sources[((int)$km[1]) . 'p'] = $v; // mp4_720 => "720p"
        }
    }
    if (!$sources) return null;
    uksort($sources, function ($a, $b) { return (int)$a <=> (int)$b; });

    $result = ['best' => end($sources), 'sources' => $sources, 'ts' => time(), 'key' => $key];
    if ($file) @file_put_contents($file, json_encode($result));
    return $result;
}

// VK video'ni toza HTML5 playerda ko'rsatadi; mp4 olinmasa VK iframe'ga qaytadi.
function render_vk_player($video_url, $player_id, $poster = null, $subs_html = '', $intro_start = 0, $intro_end = 0) {
    $res = vk_resolve_video($video_url);
    if ($res) {
        // Agar to'g'ridan-to'g'ri VK CDN biror tarmoqda ochilmasa — o'z serverimiz
        // orqali stream.php proxy'ga tushish (bandwidth ishlatadi, lekin ishonchli).
        $fallback = ROOT_URL . '/stream.php?url=' . urlencode($res['best']);
        return build_video_player($player_id, $res['best'], $poster, $subs_html, $res['sources'], $intro_start, $intro_end, false, false, $fallback);
    }
    return '<div class="player-wrap"><iframe src="' . e(vk_embed_src($video_url)) . '" allowfullscreen></iframe></div>';
}

// ===== RuTube (HLS) — toza HTML5 player =====
// RuTube API endi to'g'ridan-to'g'ri mp4 bermaydi — faqat HLS (m3u8). HLS'ni brauzer
// bevosita o'ynata olmaydi (CORS yo'q), shuning uchun playlist o'z serverimiz orqali
// stream.php (hls=1) da qayta yozilib, o'z playerimizda hls.js bilan ko'rsatiladi.
// Bu server bandwidth ishlatadi (VK mp4 dan farqli) — RuTube "zaxira" manba sifatida.

// rutube.ru/video/<id>/ yoki rutube.ru/embed/<id> dan video id'sini ajratadi.
// RuTube'da ikki turdagi ID bor: video_id (32-simvol hex, masalan embed/play uchun)
// va track_id (raqamli, ommaviy video sahifasida ishlatiladi). Ikkalasini ham taniymiz.
function rutube_parse_url($url) {
    if (!preg_match('#rutube\.ru/(?:(?:live/)?video(?:/private)?|(?:play/)?embed)/([a-z0-9]{32}|[0-9]+)#i', $url, $m)) return null;
    return $m[1];
}

// RuTube variant (m3u8) havolasini topadi va keshlaydi. Variant URL CDN'da ~7 kun amal
// qiladi (max-age=604800), lekin ishonchliligi uchun 24 soatda bir qayta so'raladi —
// keshlangan URL o'lsa ham keyingi ochilishda avtomatik yangi havola olinadi.
// Qaytadi: ['url' => ..., 'ts' => ..., 'key' => id].
function rutube_resolve($url, $ttl_hours = 24) {
    $id = rutube_parse_url($url);
    if (!$id) return null;

    $dir = vk_cache_dir();
    $file = $dir ? $dir . '/rutube_' . $id . '.json' : null;
    if ($file && is_file($file)) {
        $c = @json_decode(@file_get_contents($file), true);
        if (is_array($c) && !empty($c['url']) && isset($c['ts'])
            && (time() - (int)$c['ts']) < $ttl_hours * 3600) {
            return $c;
        }
    }

    // RuTube CDN (bl.rutube.ru) master playlist uchun spid cookie'ni talab qiladi —
    // options va master bir xil jar bilan so'raladi. Variant/segmentlar cookie kerak emas.
    $jar = $dir ? $dir . '/rutube_cookies.txt' : null;
    if ($jar && is_file($jar)) @unlink($jar);

    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    $hdrs = ['Referer: https://rutube.ru/', 'Origin: https://rutube.ru', 'Accept: application/json, text/plain, */*'];
    $base = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_USERAGENT => $ua,
        CURLOPT_HTTPHEADER => $hdrs,
        CURLOPT_ENCODING => '',
    ];
    if ($jar) {
        $base[CURLOPT_COOKIEJAR] = $jar;
        $base[CURLOPT_COOKIEFILE] = $jar;
    }

    // 1) play/options — master playlist havolasi
    $ch = curl_init("https://rutube.ru/api/play/options/$id/?format=json");
    curl_setopt_array($ch, $base);
    $body = curl_exec($ch);
    $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $st !== 200) return null;
    $opts = json_decode($body, true);
    if (!is_array($opts)) return null;
    $vb = $opts['video_balancer'] ?? [];
    $master = $vb['m3u8'] ?? ($vb['default'] ?? null);
    if (!is_string($master) || strpos($master, 'http') !== 0) return null;

    // 2) master playlist (shu jar bilan) — variant ro'yxatini olish
    $ch = curl_init($master);
    curl_setopt_array($ch, $base);
    $pl = curl_exec($ch);
    $plst = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($pl === false || $plst !== 200) return null;

    // 3) eng yuqori sifatli variantni tanlash (max BANDWIDTH)
    $best = null;
    $bestBw = -1;
    if (preg_match_all('/#EXT-X-STREAM-INF:[^\n]*BANDWIDTH\s*=\s*(\d+)[^\n]*\n([^\n]+)/i', $pl, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            $bw = (int)$mm[1];
            $u = trim($mm[2]);
            if ($bw > $bestBw) { $bestBw = $bw; $best = $u; }
        }
    } elseif (preg_match('#(https?://[^\s"\']+)#', $pl, $m2)) {
        $best = trim($m2[1]);
    }
    if (!$best) return null;

    // Nisbiy variant havolasi bo'lsa mutlaq qilamiz
    if (strpos($best, 'http') !== 0) {
        $parts = parse_url($master);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return null;
        $baseu = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) $baseu .= ':' . $parts['port'];
        $path = $parts['path'] ?? '/';
        $dirp = substr($path, 0, strrpos($path, '/') + 1);
        $best = ($best[0] === '/') ? $baseu . $best : $baseu . $dirp . $best;
    }

    $result = ['url' => $best, 'ts' => time(), 'key' => $id];
    if ($file) @file_put_contents($file, json_encode($result));
    return $result;
}

// RuTube video'ni o'z playerimizda (hls.js) ko'rsatadi. Playlist hali topilmasa
// (video moderatsiyada/yo'q) ham iframe emas — o'z playerimiz chiqadi va m3u8 paydo
// bo'lishi bilanoq avtomatik yuklanadi (data-hls-pending rejimi).
function render_rutube_player($video_url, $player_id, $poster = null, $subs_html = '', $intro_start = 0, $intro_end = 0) {
    $refresh = ROOT_URL . '/api/rutube-refresh.php?url=' . rawurlencode($video_url);
    $res = rutube_resolve($video_url);
    if ($res && !empty($res['url'])) {
        $proxy = ROOT_URL . '/stream.php?url=' . rawurlencode($res['url']) . '&hls=1';
        // data-hls-refresh: keshlangan URL muddati o'tsa player avtomatik yangi havola oladi
        return build_video_player($player_id, $proxy, $poster, $subs_html, [], $intro_start, $intro_end, false, false, null, true, $refresh);
    }
    return build_video_player($player_id, '', $poster, $subs_html, [], $intro_start, $intro_end, false, false, null, true, $refresh);
}

// ===== Rumble (iframe embed) =====
// Rumble'da watch sahifa ID'si va embed ID'si boshqa-boshqa bo'ladi
// (masalan /v7dvvd6-...html sahifa, lekin embed /v7bpc7y/). Shuning uchun
// to'g'ri embed havolasi Rumble oEmbed API orqali yechiladi va keshlanadi.
// Qabul qiladigan formatlar:
//   https://rumble.com/v7dvvd6-u-qiz-yolgiz1-qism.html  (watch sahifa)
//   https://rumble.com/embed/v7bpc7y/                   (embed)
function rumble_parse_url($url) {
    if (preg_match('#rumble\.com/(?:embed/)?(v[a-z0-9]+)(?:/|(?:-[^/]*)?\.html)#i', $url, $m)) return $m[1];
    return null;
}

// Rumble video'ning haqiqiy embed src manzilini oEmbed API orqali topadi va
// 24 soatga keshlaydi. Qaytadi: embed URL yoki null.
function rumble_embed_src($url, $ttl_hours = 24) {
    $key = rumble_parse_url($url);
    if (!$key) return null;

    $dir = vk_cache_dir();
    $file = $dir ? $dir . '/rumble_' . $key . '.json' : null;
    if ($file && is_file($file)) {
        $c = @json_decode(@file_get_contents($file), true);
        if (is_array($c) && !empty($c['embed']) && isset($c['ts'])
            && (time() - (int)$c['ts']) < $ttl_hours * 3600) {
            return $c['embed'];
        }
    }

    $ch = curl_init('https://wn0.rumble.com/api/Media/oembed.json?url=' . rawurlencode($url));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $embed = null;
    if ($body !== false && $st === 200) {
        $d = json_decode($body, true);
        if (is_array($d) && !empty($d['html']) && preg_match('#src\s*=\s*["\']([^"\']+)["\']#i', $d['html'], $m)) {
            $embed = trim($m[1]);
        } elseif (is_array($d) && !empty($d['html'])) {
            $embed = $d['html'];
        }
    }

    if ($embed) {
        if ($file) {
            $saved = ['embed' => $embed, 'ts' => time()];
            // rumble_resolve_video tomonidan yozilgan hls maydonini saqlab qolamiz
            if (is_file($file)) {
                $old = @json_decode(@file_get_contents($file), true);
                if (is_array($old) && !empty($old['hls'])) $saved['hls'] = $old['hls'];
            }
            @file_put_contents($file, json_encode($saved));
        }
        return $embed;
    }

    // oEmbed ishlamasa — URL'dagi ID bilan qisqa formatni sinash
    return 'https://rumble.com/embed/' . $key . '/';
}

// Rumble video'ning to'g'ridan-to'g'ri CDN chunklist (HLS) havolasini topadi va keshlaydi.
// Rumble CDN (1a-1791.com) ochiq — playlist va segmentlar server tomondan o'qiladi.
// embedJS endpoint'i (rumble.com/embedJS/...) PHP curl bilan ochiq — ua.ua.tar blokidan
// eng yuqori sifatli chunklist URL olinadi. Qaytadi: chunklist URL yoki null.
function rumble_resolve_video($url, $ttl_hours = 24) {
    $key = rumble_parse_url($url);
    if (!$key) return null;

    $dir = vk_cache_dir();
    $file = $dir ? $dir . '/rumble_' . $key . '.json' : null;
    if ($file && is_file($file)) {
        $c = @json_decode(@file_get_contents($file), true);
        if (is_array($c) && !empty($c['hls']) && isset($c['ts'])
            && (time() - (int)$c['ts']) < $ttl_hours * 3600) {
            return $c['hls'];
        }
    }

    // Embed ID — watch sahifa ID'sidan farq qiladi, oEmbed orqali topamiz
    $embed = rumble_embed_src($url);
    $embed_key = null;
    if ($embed && preg_match('#/embed/(v[a-z0-9]+)/?#i', $embed, $m)) {
        $embed_key = $m[1];
    } elseif ($embed && preg_match('#src\s*=\s*["\']([^"\']+)["\']#i', $embed, $m2)
              && preg_match('#/embed/(v[a-z0-9]+)/?#i', $m2[1], $m3)) {
        $embed_key = $m3[1];
    }
    if (!$embed_key) $embed_key = $key;
    if (!preg_match('#^v[a-z0-9]+$#i', $embed_key)) return null;

    $ch = curl_init('https://rumble.com/embedJS/u3/?request=video&ver=2&v=' . $embed_key);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $st !== 200) return null;
    $d = json_decode($body, true);
    if (!is_array($d)) return null;

    // Eng yuqori sifatni tanlash (720 > 480 > 360 > 240); u blok 480p beradi
    $tar = $d['ua']['tar'] ?? [];
    $best = null;
    foreach (['720', '480', '360', '240'] as $q) {
        if (!empty($tar[$q]['url'])) { $best = $tar[$q]['url']; break; }
    }
    if (!$best && !empty($d['u']['tar']['url'])) $best = $d['u']['tar']['url'];
    if (!$best || strpos($best, 'http') !== 0) return null;

    if ($file) {
        $saved = ['hls' => $best, 'ts' => time()];
        if (is_file($file)) {
            $old = @json_decode(@file_get_contents($file), true);
            if (is_array($old)) $saved = array_merge($old, $saved);
        }
        @file_put_contents($file, json_encode($saved));
    }
    return $best;
}

// Rumble video'ni o'z playerimizda (hls.js) ko'rsatadi. CDN chunklist (HLS) topilsa —
// stream.php (hls=1) orqali proksi qilinib o'z playerda ko'rsatiladi. Topilmasa
// (video hali tayyor emas va h.k.) — eski iframe embed zaxira sifatida ishlatiladi.
function render_rumble_player($video_url, $player_id, $poster = null, $subs_html = '', $intro_start = 0, $intro_end = 0) {
    $refresh = ROOT_URL . '/api/rumble-refresh.php?url=' . rawurlencode($video_url);
    $hls = rumble_resolve_video($video_url);
    if ($hls) {
        $proxy = ROOT_URL . '/stream.php?url=' . rawurlencode($hls) . '&hls=1';
        // data-hls-refresh: keshlangan CDN URL muddati o'tsa player avtomatik yangi havola oladi
        return build_video_player($player_id, $proxy, $poster, $subs_html, [], $intro_start, $intro_end, true, true, null, true, $refresh);
    }

    $src = rumble_embed_src($video_url);
    if (!$src) return null;
    // oEmbed html blok bo'lsa to'g'ridan-to'g'ri ishlatamiz, aks holda iframe quramiz
    if (strpos($src, '<iframe') !== false || strpos($src, '<script') !== false) {
        return '<div class="player-wrap">' . $src . '</div>';
    }
    return '<div class="player-wrap"><iframe src="' . e($src) . '" allowfullscreen allow="autoplay; encrypted-media"></iframe></div>';
}

// Havola to'g'ridan-to'g'ri video fayl (mp4/webm/...) bo'lsa true
function is_direct_video_url($url) {
    if (!preg_match('#^https?://#i', $url)) return false;
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['mp4', 'webm', 'ogv', 'ogg', 'mov', 'm4v'], true);
}

// To'g'ridan-to'g'ri video faylni toza playerda ko'rsatadi (proksi zaxira bilan)
function render_direct_video($video_url, $player_id, $poster = null, $subs_html = '', $intro_start = 0, $intro_end = 0) {
    $fallback = ROOT_URL . '/stream.php?url=' . urlencode($video_url);
    return build_video_player($player_id, $video_url, $poster, $subs_html, [], $intro_start, $intro_end, false, false, $fallback);
}

// ===== Odysee (toza HTML5 player) =====
// Odysee o'z saytini iframe'da ochishga yo'l qo'ymaydi (frame blokirovkasi). Shuning uchun
// video'ni to'g'ridan-to'g'ri mp4 streaming URL orqali O'Z playerimizda ko'rsatamiz.
// Streaming URL Odysee JSON-RPC "get" methodidan olinadi (player.odycdn.com) va 48 soatga
// keshlanadi. Odysee CDN Referer tekshiradi — shuning uchun stream.php proksi ishlatiladi.

// Odysee havolasidan kanal va claim nomini ajratadi.
// Formatlar: https://odysee.com/@Kanal/Claim  yoki  https://odysee.com/Claim
function odysee_parse_url($url) {
    if (preg_match('~https?://(?:www\.)?odysee\.com/(?:@([^/]+)/)?([^/?#]+)~i', $url, $m)) {
        $channel = ($m[1] !== '') ? '@' . trim($m[1]) : '';
        $name = trim($m[2]);
        if ($name === '' || preg_match('#\.(?:html?|php)$#i', $name)) return null;
        return ['channel' => $channel, 'name' => $name];
    }
    return null;
}

// Odysee video'ning to'g'ridan-to'g'ri mp4 streaming URL'ini oladi va keshlaydi.
// Qaytadi: streaming URL yoki null.
function odysee_resolve_video($url, $ttl_hours = 48) {
    $info = odysee_parse_url($url);
    if (!$info) return null;

    $dir = vk_cache_dir();
    $file = $dir ? $dir . '/odysee_' . md5($info['channel'] . '/' . $info['name']) . '.json' : null;
    if ($file && is_file($file)) {
        $cached = @json_decode(@file_get_contents($file), true);
        if (is_array($cached) && !empty($cached['stream_url']) && isset($cached['ts'])
            && (time() - (int)$cached['ts']) < $ttl_hours * 3600) {
            return $cached['stream_url'];
        }
    }

    $uri = 'lbry://' . (($info['channel'] !== '') ? $info['channel'] . '/' : '') . $info['name'];
    $payload = [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'get',
        'params' => ['uri' => $uri],
    ];
    $ch = curl_init('https://api.na-backend.odysee.com/api/v1/proxy');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $body = curl_exec($ch);
    $st = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $st !== 200) return null;

    $d = json_decode($body, true);
    if (!is_array($d) || empty($d['result']['streaming_url'])) return null;
    $streamUrl = $d['result']['streaming_url'];
    if (!preg_match('#^https://#i', $streamUrl)) return null;

    if ($file) @file_put_contents($file, json_encode(['stream_url' => $streamUrl, 'ts' => time()]));
    return $streamUrl;
}

// Odysee video'ni o'z playerimizda (stream.php proksi orqali) ko'rsatadi.
function render_odysee_player($video_url, $player_id, $poster = null, $subs_html = '', $intro_start = 0, $intro_end = 0) {
    $stream = odysee_resolve_video($video_url);
    if (!$stream) return null;
    $proxy = ROOT_URL . '/stream.php?url=' . rawurlencode($stream);
    return build_video_player($player_id, $proxy, $poster, $subs_html, [], $intro_start, $intro_end, false, false);
}

// VK, RuTube, Rumble, Odysee yoki to'g'ridan-to'g'ri video fayl bo'lsa — toza player qaytaradi, aks holda null
function render_clean_video_if_possible($video_url, $player_id, $poster = null, $subs_html = '', $intro_start = 0, $intro_end = 0, array $sources = []) {
    if (vk_parse_url($video_url)) return render_vk_player($video_url, $player_id, $poster, $subs_html, $intro_start, $intro_end);
    if (rutube_parse_url($video_url)) return render_rutube_player($video_url, $player_id, $poster, $subs_html, $intro_start, $intro_end);
    if (rumble_parse_url($video_url)) return render_rumble_player($video_url, $player_id, $poster, $subs_html, $intro_start, $intro_end);
    if (odysee_parse_url($video_url)) return render_odysee_player($video_url, $player_id, $poster, $subs_html, $intro_start, $intro_end);
    if (is_direct_video_url($video_url)) return render_direct_video($video_url, $player_id, $poster, $subs_html, $intro_start, $intro_end);
    return null;
}

// ===== Fayl yuklash =====
// $allowed_mimes berilsa, kengaytmadan tashqari haqiqiy fayl MIME turi ham tekshiriladi
// (masalan .jpg deb nomlangan zararli faylning oldini olish uchun)
function upload_file($file_input_name, $target_dir, $allowed_ext, $allowed_mimes = null) {
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES[$file_input_name];
    if ($file['size'] > MAX_UPLOAD_SIZE) return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) return false;

    if ($allowed_mimes !== null) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) finfo_close($finfo);
        if (!$real_mime || !in_array($real_mime, $allowed_mimes)) return false;
    }

    $new_name = uniqid('f_', true) . '.' . $ext;
    $target_path = $target_dir . $new_name;
    if (move_uploaded_file($file['tmp_name'], $target_path)) return $new_name;
    return false;
}

// ===== Foydalanuvchi IP manzilini olish =====
function client_ip() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// ===== AJAX so'rov ekanligini aniqlash =====
function is_ajax_request() {
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

// ===== Brute-force himoyasi (login urinishlari) =====
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

// $identifier masalan: 'user:1.2.3.4:ali123' yoki 'admin:1.2.3.4:admin'
function login_is_locked($pdo, $identifier) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > (NOW() - INTERVAL " . LOGIN_LOCKOUT_MINUTES . " MINUTE)");
    $stmt->execute([$identifier]);
    return (int)$stmt->fetchColumn() >= LOGIN_MAX_ATTEMPTS;
}

function login_register_failed($pdo, $identifier) {
    $pdo->prepare("INSERT INTO login_attempts (identifier) VALUES (?)")->execute([$identifier]);
    // Eski yozuvlarni vaqti-vaqti bilan tozalash (jadval shishib ketmasligi uchun)
    if (mt_rand(1, 50) === 1) {
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    }
}

function login_clear_attempts($pdo, $identifier) {
    $pdo->prepare("DELETE FROM login_attempts WHERE identifier = ?")->execute([$identifier]);
}

// ===== Telegram video havolasini stream proxy URL ga aylantirish =====
// ===== Video oqim URL — barcha turdagi manbalar uchun yagona yo'l =====
// Nisbiy fayl (uploads/videos/...), mutlaq http(s) havola yoki Telegram
// file_path (tg:...) → stream.php proksi havolasiga aylantiradi.
function resolve_video_stream_url($video_url, $base_path = 'uploads/videos/') {
    $v = trim((string)$video_url);
    if ($v === '') return null;
    if (strpos($v, 'tg:') === 0) {
        return ROOT_URL . '/stream.php?tg=' . urlencode(substr($v, 3));
    }
    if (preg_match('#^https?://#i', $v)) {
        return ROOT_URL . '/stream.php?url=' . urlencode($v);
    }
    return ROOT_URL . '/stream.php?url=' . urlencode($base_path . $v);
}

// ===== URL'ga qarab video_type tanlash (bot/admin URL bilan qo'shganda) =====
// tg: (Telegram fayli) -> telegram; mashhur platformalar va to'g'ridan-to'g'ri mp4 -> cloud
// (o'z playerida, to'g'ridan-to'g'ri CDN — minglab tomoshabin uchun yaxshi);
// qolgan https havolalar -> file (stream.php proksi orqali).
function video_type_for_url($url) {
    $u = (string)$url;
    if (strpos($u, 'tg:') === 0) return 'telegram';
    if (stripos($u, 'ok.ru/') !== false) return 'cloud';
    if (vk_parse_url($u) || rutube_parse_url($u) || rumble_parse_url($u) || odysee_parse_url($u) || is_direct_video_url($u)) {
        return 'cloud';
    }
    return 'file';
}

// ===== Telegram havolasini saqlashga tayyorlash =====
// api.telegram.org/file/bot<TOKEN>/<path> -> tg:<path> (token serverda qoladi)
// qolgan barcha https havolalar o'zicha saqlanadi
function telegram_normalize_url($url) {
    $u = trim((string)$url);
    if ($u === '') return null;
    if (preg_match('#^https?://api\.telegram\.org/file/bot[^/]+/(.+)$#i', $u, $m)) {
        return 'tg:' . $m[1];
    }
    return $u;
}

// ===== Poster ko'rsatish =====
// Poster to'liq URL (https://...) yoki eski fayl nomi (p_xxx.jpg) bo'lishi mumkin.
// URL bo'lsa to'g'ridan-to'g'ri qaytaradi, fayl nomi bo'lsa SITE_URL/uploads/posters/ prefiks qo'shib qaytaradi.
function poster_url($poster) {
    if (!$poster) return null;
    $p = trim((string)$poster);
    if ($p === '') return null;
    if (preg_match('#^https?://#i', $p) || strpos($p, '/') === 0 || strpos($p, 'data:') === 0) return $p;
    $site = defined('SITE_URL') ? SITE_URL : 'http://localhost/uzdub';
    return $site . '/uploads/posters/' . $p;
}

// ===== Internetdan poster rasmini yuklab olish =====
// URL dan rasm yuklab, uploads/posters/ ga saqlaydi; muvaffaqiyatda fayl nomini qaytaradi.
// SSRF himoyasi: barcha DNS yozuvlari tekshiriladi, redirect'lar har qadamda qayta tekshiriladi.
function url_is_private_ip(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $l = ip2long($ip);
        if ($l === false) return true;
        if (($l & 0xFF000000) === 0x7F000000) return true;               // 127/8
        if (($l & 0xFF000000) === 0x0A000000) return true;               // 10/8
        if (($l & 0xFFF00000) === 0xAC100000) return true;               // 172.16/12
        if (($l & 0xFFFF0000) === 0xC0A80000) return true;               // 192.168/16
        if (($l & 0xFFFF0000) === 0xA9FE0000) return true;               // 169.254/16
        if (($l & 0xC0000000) === 0x64400000) return true;               // 100.64/10 CGNAT
        if ($l === 0) return true;
        if (($l & 0xE0000000) === 0xE0000000) return true;               // multicast/reserved
        return false;
    }
    $ip = strtolower($ip);
    if ($ip === '::1' || $ip === '::') return true;
    if (strpos($ip, '::ffff:') === 0) return url_is_private_ip(substr($ip, 7));
    $bin = @inet_pton($ip);
    if ($bin === false) return true;
    if ((ord($bin[0]) & 0xFE) === 0xFC) return true;                          // fc00::/7 ULA
    if ((ord($bin[0]) & 0xFF) === 0xFE && (ord($bin[1]) & 0xC0) === 0x80) return true; // fe80::/10
    return false;
}

function url_host_is_private(string $url): bool {
    $parts = parse_url($url);
    if (!$parts || empty($parts['host'])) return true;
    if (isset($parts['user']) || isset($parts['pass'])) return true;
    $host = strtolower($parts['host']);
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return url_is_private_ip($host);
    }
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if ($records) {
        $checked = 0;
        foreach ($records as $r) {
            $ip = $r['type'] === 'AAAA' ? ($r['ipv6'] ?? '') : ($r['ip'] ?? '');
            if ($ip === '') continue;
            $checked++;
            if (url_is_private_ip($ip)) return true;
        }
        return $checked === 0;
    }
    $ip = @gethostbyname($host);
    if ($ip === false || $ip === $host || !filter_var($ip, FILTER_VALIDATE_IP)) return true;
    return url_is_private_ip($ip);
}

function download_poster($url, $target_dir) {
    $u = trim((string)$url);
    if ($u === '') return null;
    if (!preg_match('#^https?://#i', $u)) return false;
    if (url_host_is_private($u)) return false;

    $current = $u;
    for ($i = 0; $i <= 5; $i++) {
        if (url_host_is_private($current)) return false;

        $ch = curl_init($current);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS      => 0,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Uzdub/1.0)',
        ]);
        $data = curl_exec($ch);
        $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        if ($status >= 300 && $status < 400 && $redirect) {
            if (preg_match('#^https?://#i', $redirect)) {
                $current = $redirect;
            } else {
                $parts = parse_url($current);
                if (!$parts) return false;
                $base = $parts['scheme'] . '://' . $parts['host'];
                if (isset($parts['port'])) $base .= ':' . $parts['port'];
                $current = $redirect[0] === '/' ? $base . $redirect : $base . substr($parts['path'] ?? '/', 0, strrpos($parts['path'] ?? '/', '/') + 1) . $redirect;
            }
            continue;
        }

        if ($data === false || $data === '') return false;
        if (strlen($data) > MAX_UPLOAD_SIZE) return false;

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = strtolower(trim(explode(';', (string)$mime)[0]));
        if (!isset($allowed[$mime])) return false;

        $name = uniqid('p_', true) . '.' . $allowed[$mime];
        if (file_put_contents($target_dir . $name, $data)) return $name;
        return false;
    }
    return false;
}

// ===== Video player (subtitrlar bilan) =====
// $sources: ['1080p' => url, '720p' => url] — ixtiyoriy sifatli manbalar (file/telegram uchun)
// $intro_start / $intro_end: o'tkazib yuboriladigan intro oralig'i (soniyada) — "Skip Intro" tugmasi uchun
function render_player($video_type, $video_url, $base_path = 'uploads/videos/', array $subtitles = [], $player_id = 'mainVideo', $poster = null, array $sources = [], $intro_start = 0, $intro_end = 0, $resume_at = 0, $title = '', $next = null) {
    $subs_html = '';
    foreach ($subtitles as $sub) {
        $src = ROOT_URL . '/uploads/subtitles/' . e($sub['file_path']);
        $label = e($sub['label'] ?? $sub['language']);
        $lang = e($sub['language'] ?? 'uz');
        $subs_html .= '<track kind="subtitles" src="' . $src . '" srclang="' . $lang . '" label="' . $label . '">';
    }

    if ($video_type === 'cloud') {
        $clean = render_clean_video_if_possible($video_url, $player_id, $poster, $subs_html, $intro_start, $intro_end, $sources);
        if ($clean) return $clean;
        return '<div class="player-wrap"><iframe src="' . e($video_url) . '" allowfullscreen></iframe></div>';
    } elseif ($video_type === 'embed') {
        if (preg_match('#^https?://#i', $video_url)) {
            $clean = render_clean_video_if_possible($video_url, $player_id, $poster, $subs_html, $intro_start, $intro_end, $sources);
            if ($clean) return $clean;
            return '<div class="player-wrap"><iframe src="' . e($video_url) . '" allowfullscreen allow="autoplay; encrypted-media"></iframe></div>';
        }
        // Embed code (iframe tegi) ichidagi VK/direct video havolasini ham toza playerda ko'rsatamiz
        if (preg_match('#<iframe[^>]+src=["\']([^"\']+)["\']#i', $video_url, $m)) {
            $inner = html_entity_decode($m[1]);
            $clean = render_clean_video_if_possible($inner, $player_id, $poster, $subs_html, $intro_start, $intro_end, $sources);
            if ($clean) return $clean;
        }
        return '<div class="player-wrap">' . $video_url . '</div>';
    } elseif ($video_type === 'file' || $video_type === 'telegram') {
        $stream_url = resolve_video_stream_url($video_url, $base_path);
        if ($stream_url) {
            return build_video_player($player_id, $stream_url, $poster, $subs_html, $sources, $intro_start, $intro_end, true, true, null, false, null, $resume_at, $title, $next);
        }
        return '<p class="player-error">Video havolasi noto\'g\'ri.</p>';
    }
    return '';
}

// ===== HTML5 video player (yagona, xatolik fallback bilan) =====
// $sources: ['1080p' => url, '720p' => url] — ixtiyoriy sifatli manbalar
// $intro_start / $intro_end: o'tkazib yuboriladigan intro oralig'i (soniyada) — "Skip Intro" tugmasi uchun
function build_video_player($player_id, $stream_url, $poster = null, $subs_html = '', array $sources = [], $intro_start = 0, $intro_end = 0, $crossorigin = true, $autoplay = true, $fallback_url = null, $hls = false, $hls_refresh = null, $resume_at = 0, $title = '', $next = null) {
    $intro_start = max(0, (int)$intro_start);
    $intro_end = max(0, (int)$intro_end);
    // Custom (anibla-style) player. Barcha UI/JS: js/player.js da.
    // video elemanti: data-hls / data-hls-pending / data-hls-refresh / data-fallback
    // attribute'lari orqali konfiguratsiya oladi.
    $attrs = 'playsinline preload="metadata" controlsList="nodownload" oncontextmenu="return false"';
    if ($crossorigin) $attrs .= ' crossorigin="anonymous"';
    if ($poster) $attrs .= ' poster="' . e($poster) . '"';
    if ($fallback_url) $attrs .= ' data-fallback="' . e($fallback_url) . '"';
    if ($hls) {
        if ($stream_url !== '') {
            $attrs .= ' data-hls="' . e($stream_url) . '"';
        } else {
            $attrs .= ' data-hls-pending="1"';
        }
        if ($hls_refresh) $attrs .= ' data-hls-refresh="' . e($hls_refresh) . '"';
        $src_attr = '';
    } else {
        $src_attr = ' src="' . e($stream_url) . '"';
    }

    $qualities = [];
    if (!empty($sources)) {
        foreach ($sources as $label => $url) {
            $label = trim($label);
            $url = trim($url);
            if ($label === '' || $url === '') continue;
            $qualities[$label] = $url;
        }
        if ($qualities) $qualities['Auto'] = $stream_url;
    }

    $cfg = [
        'autoplay' => (bool)$autoplay,
        'resumeAt' => (int)$resume_at,
        'introStart' => (int)$intro_start,
        'introEnd' => (int)$intro_end,
        'qualities' => (object)$qualities,
        'title' => (string)$title,
        'next' => $next,
        'strings' => [
            'loading' => t('player_loading'),
            'pending' => t('player_pending'),
            'skip_intro' => t('player_skip_intro'),
            'resume_title' => t('player_resume_title'),
            'resume_sub' => t('player_resume_sub'),
            'resume_continue' => t('player_resume_continue'),
            'resume_restart' => t('player_resume_restart'),
            'error_title' => t('player_error_title'),
            'error_sub' => t('player_error_sub'),
            'retry' => t('player_retry'),
            'ended_title' => t('player_ended'),
            'replay' => t('player_replay'),
            'next_ep' => t('player_next_ep'),
            'speed' => t('player_speed'),
            'quality' => t('player_quality'),
            'subtitles' => t('player_subtitles'),
            'off' => t('player_off'),
            'pip' => t('player_pip'),
            'fullscreen' => t('player_fullscreen'),
            'cinema' => t('player_cinema'),
            'cinema_off' => t('player_cinema_off'),
            'settings' => t('player_settings'),
        ],
    ];
    $cfg_json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($cfg_json === false) $cfg_json = '{}';
    $cfg_attr = htmlspecialchars($cfg_json, ENT_QUOTES, 'UTF-8');

    $html = '<div class="player-wrap has-video-container">'
        . '<div class="udp-player" data-udp data-udp-config="' . $cfg_attr . '">'
        . '<video id="' . e($player_id) . '" ' . $attrs . $src_attr . '>' . $subs_html . '</video>'
        . '</div></div>';

    return $html;
}

function get_content_subtitles(PDO $pdo, int $content_id): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM content_subtitles WHERE content_id = ?");
        $stmt->execute([$content_id]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_user_rating(PDO $pdo, int $user_id, int $content_id): ?int {
    try {
        $stmt = $pdo->prepare("SELECT rating FROM ratings WHERE user_id = ? AND content_id = ?");
        $stmt->execute([$user_id, $content_id]);
        $r = $stmt->fetchColumn();
        return $r !== false ? (int)$r : null;
    } catch (PDOException $e) {
        return null;
    }
}

function get_avg_user_rating(PDO $pdo, int $content_id): ?float {
    try {
        $stmt = $pdo->prepare("SELECT ROUND(AVG(rating), 1) FROM ratings WHERE content_id = ?");
        $stmt->execute([$content_id]);
        $v = $stmt->fetchColumn();
        return $v !== null ? (float)$v : null;
    } catch (PDOException $e) {
        return null;
    }
}

function is_content_watched(PDO $pdo, int $user_id, int $content_id, int $episode_id = 0): bool {
    try {
        $stmt = $pdo->prepare("SELECT id FROM watch_history WHERE user_id = ? AND content_id = ? AND episode_id = ?");
        $stmt->execute([$user_id, $content_id, $episode_id]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function mark_content_watched(PDO $pdo, int $user_id, int $content_id, int $episode_id = 0): void {
    try {
        $pdo->prepare("INSERT INTO watch_history (user_id, content_id, episode_id) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE watched_at = CURRENT_TIMESTAMP")
            ->execute([$user_id, $content_id, $episode_id]);
        $pdo->prepare("UPDATE watch_progress SET position_seconds = duration_seconds WHERE user_id = ? AND content_id = ? AND episode_id = ?")
            ->execute([$user_id, $content_id, $episode_id]);

        // Ko'rishlar: "Ko'rilgan" bo'limiga birinchi o'tishda 1 ta ko'rish hisoblanadi.
        // Bir xil foydalanuvchi kontentni qayta ko'rsa ham qo'shilmaydi.
        $chk = $pdo->prepare("SELECT id FROM user_content_status WHERE user_id = ? AND content_id = ? AND status = 'completed'");
        $chk->execute([$user_id, $content_id]);
        $first_watch = !(bool)$chk->fetch();

        $pdo->prepare("INSERT INTO user_content_status (user_id, content_id, status) VALUES (?,?,'completed')
            ON DUPLICATE KEY UPDATE status = IF(status = 'favorite', 'favorite', 'completed'), updated_at = CURRENT_TIMESTAMP")
            ->execute([$user_id, $content_id]);

        if ($first_watch) {
            $pdo->prepare("UPDATE content SET views = views + 1 WHERE id = ?")->execute([$content_id]);
        }

        $pdo->prepare("INSERT INTO watched_content (user_id, content_id, episode_id) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE completed_at = CURRENT_TIMESTAMP")
            ->execute([$user_id, $content_id, $episode_id]);
    } catch (PDOException $e) {}
}

// ===== API rate limiting =====
function rate_limit_check(PDO $pdo, string $endpoint, int $max_hits = 30, int $window_seconds = 60): bool {
    $identifier = client_ip() . ':' . ($endpoint);
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE identifier = ? AND endpoint = ? AND hit_at > (NOW() - INTERVAL ? SECOND)");
        $stmt->execute([$identifier, $endpoint, $window_seconds]);
        if ((int)$stmt->fetchColumn() >= $max_hits) return false;
        $pdo->prepare("INSERT INTO rate_limits (identifier, endpoint) VALUES (?,?)")->execute([$identifier, $endpoint]);
        if (mt_rand(1, 100) === 1) {
            $pdo->exec("DELETE FROM rate_limits WHERE hit_at < (NOW() - INTERVAL 1 DAY)");
        }
        return true;
    } catch (PDOException $e) {
        return true;
    }
}

function rate_limit_deny_json(): void {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(429);
    echo json_encode(['error' => 'Juda ko\'p so\'rov. Biroz kuting.']);
    exit;
}

// ===== Open Graph meta =====
function og_meta_tags(string $title, string $description = '', ?string $image = null, ?string $url = null): string {
    $site = defined('SITE_URL') ? SITE_URL : 'http://localhost/uzdub';
    $desc = mb_strimwidth(strip_tags($description), 0, 200, '...');
    $img = $image ? (strpos($image, 'http') === 0 ? $image : $site . '/' . ltrim($image, '/')) : $site . '/assets/cat.png';
    $page_url = $url ?: ($site . $_SERVER['REQUEST_URI']);
    return '<meta name="description" content="' . e($desc) . '">' . "\n"
        . '<meta property="og:title" content="' . e($title) . '">' . "\n"
        . '<meta property="og:description" content="' . e($desc) . '">' . "\n"
        . '<meta property="og:image" content="' . e($img) . '">' . "\n"
        . '<meta property="og:url" content="' . e($page_url) . '">' . "\n"
        . '<meta property="og:type" content="website">' . "\n"
        . '<meta name="twitter:card" content="summary_large_image">';
}


// ===== Avatar URL =====
function avatar_url($avatar, $base = ROOT_URL . '/') {
    if ($avatar) return $base . 'uploads/avatars/' . e($avatar);
    return $base . 'assets/default-avatar.svg';
}

// ===== Vaqtni chiroyli ko'rsatish =====
function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Hozirgina';
    if ($diff < 3600) return floor($diff/60) . ' daqiqa oldin';
    if ($diff < 86400) return floor($diff/3600) . ' soat oldin';
    return date('d.m.Y H:i', strtotime($datetime));
}

// ===== Content tarjimasi (title/description) =====
function t_title($item) {
    $lang = $GLOBALS['current_lang'] ?? 'uz';
    if ($lang === 'ru' && !empty($item['title_ru'])) return $item['title_ru'];
    if ($lang === 'en' && !empty($item['title_en'])) return $item['title_en'];
    return $item['title'] ?? '';
}

function t_desc($item) {
    $lang = $GLOBALS['current_lang'] ?? 'uz';
    if ($lang === 'ru' && !empty($item['description_ru'])) return $item['description_ru'];
    if ($lang === 'en' && !empty($item['description_en'])) return $item['description_en'];
    return $item['description'] ?? '';
}

/**
 * Qidiruv natijasida nomni yozilgan tilga qarab ko'rsatadi:
 *  - ruscha (kirill) yozilsa -> title_ru
 *  - lotin yozuvda inglizcha nomga mos tushsa -> title_en
 *  - aks holda title (o'zbekcha)
 */
function search_display_title($item, $q = '') {
    $q = mb_strtolower(trim((string)$q));
    if ($q !== '' && preg_match('/[а-яё]/u', $q)) {
        return !empty($item['title_ru']) ? $item['title_ru'] : ($item['title'] ?? '');
    }
    if ($q !== '' && !empty($item['title_en'])) {
        $en = mb_strtolower($item['title_en']);
        if (mb_strpos($en, $q) !== false) return $item['title_en'];
    }
    return t_title($item);
}

// ===== Session tracking (user_sessions) =====
// Xuddi shu turdagi qurilmaning (telefon/planshet/kompyuter) eski seanslarini o'chiradi
// — shunda ro'yxatda har qurilma turidan faqat bittasi qoladi
function dedupe_device_sessions($pdo, $user_id, $exclude_token, $ua) {
    $device = parse_user_agent($ua)['device'] ?? 'desktop';
    if ($device !== 'mobile' && $device !== 'tablet' && $device !== 'desktop') return;

    $stmt = $pdo->prepare("SELECT id, user_agent FROM user_sessions WHERE user_id = ? AND session_token != ?");
    $stmt->execute([$user_id, $exclude_token]);
    $deleteIds = [];
    foreach ($stmt->fetchAll() as $old) {
        if ((parse_user_agent($old['user_agent'])['device'] ?? 'desktop') === $device) {
            $deleteIds[] = (int)$old['id'];
        }
    }
    if ($deleteIds) {
        $pdo->exec("DELETE FROM user_sessions WHERE id IN (" . implode(',', $deleteIds) . ")");
    }
}

function record_user_session($pdo, $user_id) {
    $token = session_id();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ip = client_ip();

    $pdo->prepare("DELETE FROM user_sessions WHERE session_token = ?")->execute([$token]);

    // Xuddi shu turdagi qurilmadagi (telefon/planshet/kompyuter) eski seansni o'chirib, dublikat oldini olamiz
    dedupe_device_sessions($pdo, $user_id, $token, $ua);

    $stmt = $pdo->prepare("INSERT INTO user_sessions (user_id, session_token, user_agent, ip_address, last_activity) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $token, $ua, $ip]);
    $_SESSION['session_db_id'] = $pdo->lastInsertId();
}

function touch_user_session($pdo) {
    if (!is_user()) return;
    $sid = $_SESSION['session_db_id'] ?? 0;
    if ($sid > 0) {
        try {
            $pdo->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE id = ? AND user_id = ?")->execute([$sid, $_SESSION['user_id']]);
        } catch (PDOException $e) {}
    }
}

// Telefon raqamni maskalash: +998 33 *** ** 09
function mask_phone($phone) {
    $d = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strlen($d) >= 10) {
        $country = substr($d, 0, 3);
        $operator = substr($d, 3, 2);
        $last2 = substr($d, -2);
        return '+' . $country . ' ' . $operator . ' *** ** ' . $last2;
    }
    return $phone;
}

// Foydalanuvchining barcha faol sessiyalarini bekor qiladi (chiqarib yuboradi)
function revoke_user_sessions($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT session_token FROM user_sessions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $pdo->prepare("DELETE FROM user_sessions WHERE user_id = ?")->execute([$user_id]);

    // PHP session fayllarini ham o'chiramiz (best-effort)
    $savePath = ini_get('session.save_path');
    if (!$savePath) $savePath = sys_get_temp_dir();
    $savePath = rtrim($savePath, '/\\');
    foreach ($tokens as $t) {
        if ($t !== '') @unlink($savePath . DIRECTORY_SEPARATOR . 'sess_' . $t);
    }
    return count($tokens);
}

function parse_user_agent($ua = '') {
    $ua = (string)$ua;

    $os = 'Unknown';
    $os_version = '';
    $browser = 'Unknown';
    $browser_version = '';
    $device = 'desktop'; // desktop | mobile | tablet

    // ---- Operatsion tizim ----
    if (preg_match('/Windows NT ([\d.]+)/i', $ua, $m)) {
        $os = 'Windows';
        $win = ['10.0' => '10', '6.3' => '8.1', '6.2' => '8', '6.1' => '7', '6.0' => 'Vista', '5.1' => 'XP', '5.0' => '2000'];
        $os_version = $win[$m[1]] ?? $m[1];
    } elseif (preg_match('/Mac OS X ([\d_]+)/i', $ua, $m)) {
        $os = 'macOS';
        $os_version = str_replace('_', '.', $m[1]);
    } elseif (preg_match('/iPad.*?OS ([\d_]+)/i', $ua, $m) || preg_match('/iOS ([\d_]+)/i', $ua, $m)) {
        $os = preg_match('/iPad/i', $ua) ? 'iPadOS' : 'iOS';
        $os_version = str_replace('_', '.', $m[1]);
    } elseif (preg_match('/iPhone OS ([\d_]+)/i', $ua, $m)) {
        $os = 'iOS';
        $os_version = str_replace('_', '.', $m[1]);
    } elseif (preg_match('/Android ([\d.]+)/i', $ua, $m)) {
        $os = 'Android';
        $os_version = $m[1];
    } elseif (preg_match('/CrOS/i', $ua)) {
        $os = 'ChromeOS';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    } elseif (preg_match('/Windows Phone/i', $ua)) {
        $os = 'Windows Phone';
    }

    // ---- Brauzer ----
    if (preg_match('/Edg[eA]?\/([\d.]+)/i', $ua, $m)) {
        $browser = 'Edge'; $browser_version = $m[1];
    } elseif (preg_match('/OPR\/([\d.]+)/i', $ua, $m)) {
        $browser = 'Opera'; $browser_version = $m[1];
    } elseif (preg_match('/YaBrowser\/([\d.]+)/i', $ua, $m)) {
        $browser = 'Yandex Browser'; $browser_version = $m[1];
    } elseif (preg_match('/SamsungBrowser\/([\d.]+)/i', $ua, $m)) {
        $browser = 'Samsung Internet'; $browser_version = $m[1];
    } elseif (preg_match('/UCBrowser\/([\d.]+)/i', $ua, $m)) {
        $browser = 'UC Browser'; $browser_version = $m[1];
    } elseif (preg_match('/FxiOS\/([\d.]+)/i', $ua, $m)) {
        $browser = 'Firefox'; $browser_version = $m[1];
    } elseif (preg_match('/CriOS\/([\d.]+)/i', $ua, $m)) {
        $browser = 'Chrome'; $browser_version = $m[1];
    } elseif (preg_match('/Firefox\/([\d.]+)/i', $ua, $m)) {
        $browser = 'Firefox'; $browser_version = $m[1];
    } elseif (preg_match('/Chrome\/([\d.]+)/i', $ua, $m)) {
        $browser = 'Chrome'; $browser_version = $m[1];
    } elseif (preg_match('/Safari\/([\d.]+)/i', $ua, $m)) {
        $browser = 'Safari'; $browser_version = $m[1];
    }

    // ---- Qurilma turi ----
    if (preg_match('/curl\/|wget\/|python-requests|Python-urllib|Googlebot|bingbot|YandexBot|DuckDuckBot|TelegramBot|okhttp|node-fetch|axios|http\.client|PostmanRuntime|bot\//i', $ua)) {
        $device = 'bot';
    } elseif (preg_match('/iPad/i', $ua) || preg_match('/Tablet|PlayBook|Silk/i', $ua)) {
        $device = 'tablet';
    } elseif (preg_match('/Mobile|iPhone|Android|Windows Phone|BlackBerry|Opera Mini|IEMobile/i', $ua)) {
        $device = 'mobile';
    } else {
        $device = 'desktop';
    }

    // iPad'da os iPadOS deb belgilansin (Android planshet bo'lsa ham Android)
    if ($device === 'tablet' && $os === 'iOS') $os = 'iPadOS';

    $device_label = ($device === 'mobile') ? 'phone' : ($device === 'tablet' ? 'tablet' : ($device === 'bot' ? 'bot' : 'computer'));
    $os_label = $os . ($os_version !== '' ? ' ' . $os_version : '');
    $browser_label = $browser . ($browser_version !== '' ? ' ' . $browser_version : '');

    return compact('browser', 'browser_version', 'browser_label', 'os', 'os_version', 'os_label', 'device', 'device_label');
}

function send_email($to, $subject, $textBody, $htmlBody = null) {
    require_once __DIR__ . '/../vendor/autoload.php';

    $host = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $port = (int)(getenv('SMTP_PORT') ?: 587);
    $username = getenv('SMTP_USERNAME') ?: '';
    $password = getenv('SMTP_PASSWORD') ?: '';
    $encryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: $username;
    $fromName = getenv('SMTP_FROM_NAME') ?: 'UZDUB';

    if (!$username || !$password) return false;

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $encryption;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body = $htmlBody ?: $textBody;
        if ($htmlBody) {
            $mail->isHTML(true);
            $mail->AltBody = $textBody;
        }

        $mail->send();
        return true;
    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('PHPMailer error: ' . $e->getMessage());
        return false;
    }
}

function notify_send($pdo, $user_id, $type, $title, $message = '', $target_url = null, $sender_id = null) {
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, sender_id, type, title, message, target_url) VALUES (?,?,?,?,?,?)")
            ->execute([$user_id, $sender_id, $type, $title, $message, $target_url]);
    } catch (PDOException $e) {
        error_log('notify_send error: ' . $e->getMessage());
    }
}

function notify_send_bulk($pdo, $user_ids, $type, $title, $message = '', $target_url = null, $sender_id = null) {
    if (empty($user_ids)) return;
    $placeholders = implode(',', array_fill(0, count($user_ids), '(?,?,?,?,?,?)'));
    $params = [];
    foreach ($user_ids as $uid) {
        $params[] = $uid;
        $params[] = $sender_id;
        $params[] = $type;
        $params[] = $title;
        $params[] = $message;
        $params[] = $target_url;
    }
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, sender_id, type, title, message, target_url) VALUES $placeholders")
            ->execute($params);
    } catch (PDOException $e) {
        error_log('notify_send_bulk error: ' . $e->getMessage());
    }
}

function mask_email($email) {
    if (!$email || strpos($email, '@') === false) return $email;
    $parts = explode('@', $email, 2);
    $name = $parts[0];
    $domain = $parts[1];
    $len = mb_strlen($name);
    if ($len <= 2) {
        $masked = mb_substr($name, 0, 1) . str_repeat('*', max(1, $len - 1));
    } else {
        $masked = mb_substr($name, 0, 1) . str_repeat('*', max(1, $len - 2)) . mb_substr($name, -1);
    }
    return $masked . '@' . $domain;
}

/**
 * Chiroyli email tashqi qobig'i (full HTML). Barcha xatlar shu orqali yuboriladi.
 * $contentHtml — ichki mazmun (HTML).
 */
function email_layout(string $title, string $contentHtml): string {
    $brand = e(getenv('SMTP_FROM_NAME') ?: 'UZDUB');
    $year = date('Y');
    return '<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . $title . '</title>
</head>
<body style="margin:0;padding:0;background:#060a13;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#060a13;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#0d1424;border:1px solid #1e2b45;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.5);">
<tr><td style="background:linear-gradient(135deg,#0a1a33,#0d4f9e);padding:32px 40px;text-align:center;">
<div style="font-size:40px;line-height:1;">🛡️</div>
<div style="margin-top:10px;font-size:28px;font-weight:800;letter-spacing:3px;color:#ffffff;">' . $brand . '</div>
<div style="margin-top:6px;font-size:11px;letter-spacing:4px;text-transform:uppercase;color:#8fc3ff;">' . $title . '</div>
</td></tr>
<tr><td style="padding:36px 40px;background:#0d1424;">
' . $contentHtml . '
</td></tr>
<tr><td style="padding:24px 40px;border-top:1px solid #1e2b45;text-align:center;background:#0a0f1c;">
<div style="font-size:12px;color:#5a6b85;line-height:1.8;">
© ' . $year . ' ' . $brand . ' Platform. Barcha huquqlar himoyalangan.<br>
Agar bu xatni siz so\'ramagan bo\'lsangiz, shunchaki e\'tiborsiz qoldiring.
</div>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>';
}

/**
 * Tasdiqlash kodi uchun chiroyli karta.
 * $label — kod nima uchun (masalan "Tasdiqlash kodi")
 * $code — 6 xonali kod
 * $note — izoh (masalan "Bu kod 5 daqiqa amal qiladi.")
 */
function email_code_card(string $label, string $code, string $note = ''): string {
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $safeLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $safeNote = htmlspecialchars($note, ENT_QUOTES, 'UTF-8');
    $codeSpaced = implode(' ', str_split($safeCode));
    return '<div style="text-align:center;">
<div style="font-size:15px;color:#a9bbd6;font-weight:600;">' . $safeLabel . '</div>
<div style="margin:22px auto 0;padding:20px 24px;background:#081021;border:1px solid #2a4a7f;border-radius:14px;display:inline-block;">
<div style="font-size:36px;font-weight:800;letter-spacing:8px;color:#ffffff;font-family:\'Courier New\',monospace;">' . $codeSpaced . '</div>
</div>
' . ($safeNote ? '<div style="margin-top:20px;font-size:12.5px;color:#7c8db0;">' . $safeNote . '</div>' : '') . '
</div>';
}

/**
 * Email ichiga oddiy chiroyli blok (tushuntirish matni uchun).
 */
function email_paragraph(string $html): string {
    return '<div style="font-size:14.5px;line-height:1.7;color:#c9d6ea;text-align:center;">' . $html . '</div>';
}

// ===== Video manba URL o'zgarishlarini kuzatish =====

// Havoladan manba turini aniqlaydi: rumble / rutube / vk / direct / unknown
function video_source_type($url) {
    if (rumble_parse_url($url)) return 'rumble';
    if (rutube_parse_url($url)) return 'rutube';
    if (vk_parse_url($url)) return 'vk';
    if (is_direct_video_url($url)) return 'direct';
    return 'unknown';
}

// Episode (yoki content-level) uchun kuzatilgan oxirgi holatni qaytaradi
function video_source_get_state($episode_id, $content_id = 0) {
    global $pdo;
    if (!$pdo) return null;
    $st = $pdo->prepare("SELECT * FROM video_source_state WHERE episode_id = ? AND content_id = ?");
    $st->execute([(int)$episode_id, (int)$content_id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Yangi resolve natijasini kuzatadi. O'zgarish aniqlansa
// (manba URL almashtirilgan, oqim URL yangilangan, video buzilgan yoki tiklanmagan)
// video_source_log'ga yozadi va Telegram orqali xabar yuboradi.
function video_source_track($episode_id, $content_id, $source_type, $video_url, $hls, $ok) {
    global $pdo;
    if (!$pdo) return;
    $episode_id = (int)$episode_id;
    $content_id = (int)$content_id;
    $status = $ok ? 'ok' : 'broken';
    $prev = video_source_get_state($episode_id, $content_id);

    if (!$prev) {
        try {
            $pdo->prepare("INSERT INTO video_source_state (episode_id, content_id, source_type, video_url, hls, status) VALUES (?,?,?,?,?,?)")
                ->execute([$episode_id, $content_id, $source_type, $video_url, $hls, $status]);
        } catch (PDOException $e) {}
        return;
    }

    $event = null;
    $detail = '';
    if ($prev['video_url'] && $prev['video_url'] !== $video_url) {
        $event = 'url_changed';
        $detail = 'Manba URL o\'zgardi: ' . $prev['video_url'] . ' -> ' . $video_url;
    } elseif ($prev['status'] === 'ok' && $status === 'broken') {
        $event = 'broken';
        $detail = 'Video manbasi ochilmayapti (o\'chirilgan yoki mavjud emas)';
    } elseif ($prev['status'] === 'broken' && $status === 'ok') {
        $event = 'recovered';
        $detail = 'Video manbasi qayta tiklandi';
    }

    try {
        $pdo->prepare("UPDATE video_source_state SET content_id=?, source_type=?, video_url=?, hls=?, status=? WHERE episode_id=? AND content_id=?")
            ->execute([$content_id, $source_type, $video_url, $hls, $status, $episode_id, $content_id]);
    } catch (PDOException $e) {}

    if ($event) {
        video_source_log_and_notify($episode_id, $content_id, $source_type, $video_url, $event, $detail);
    }
}

// O'zgarishni log'ga yozadi va (takroriy spamming oldini olish uchun oxirgi 6 soatda
// xabar yuborilmagan bo'lsa) Telegram orqali adminlarga xabar yuboradi.
function video_source_log_and_notify($episode_id, $content_id, $source_type, $video_url, $event, $detail) {
    global $pdo;
    if (!$pdo) return;
    try {
        $pdo->prepare("INSERT INTO video_source_log (episode_id, content_id, source_type, video_url, event, detail) VALUES (?,?,?,?,?,?)")
            ->execute([$episode_id, $content_id, $source_type, $video_url, $event, $detail]);
        $log_id = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        return;
    }

    if (!function_exists('tg_send_message')) return;

    // Oxirgi 6 soat ichida shu episode uchun xabar yuborilganmi — spamdan qochamiz
    try {
        $recent = $pdo->prepare("SELECT id FROM video_source_log WHERE episode_id = ? AND notified = 1 AND created_at > (NOW() - INTERVAL 6 HOUR) LIMIT 1");
        $recent->execute([$episode_id]);
        if ($recent->fetch()) return;
    } catch (PDOException $e) {}

    $title = '#' . $content_id;
    $where = '';
    try {
        if ($episode_id > 0) {
            $ct = $pdo->prepare("SELECT c.title, c.title_ru, c.title_en, c.title_jp FROM content c JOIN episodes e ON e.content_id = c.id WHERE e.id = ?");
            $ct->execute([$episode_id]);
        } else {
            $ct = $pdo->prepare("SELECT title, title_ru, title_en, title_jp FROM content WHERE id = ?");
            $ct->execute([$content_id]);
        }
        $row = $ct->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $title = $row['title'] ?: ($row['title_ru'] ?: ($row['title_en'] ?: ($row['title_jp'] ?: '#' . $content_id)));
        }
    } catch (PDOException $e) {}

    $labels = [
        'url_changed' => 'Manba URL almashtirildi',
        'broken'      => 'Video buzilgan',
        'recovered'   => 'Video tiklandi',
    ];
    $label = $labels[$event] ?? $event;
    $icons = ['url_changed' => "\xF0\x9F\x94\x81", 'broken' => "\xF0\x9F\x94\xB4", 'recovered' => "\xE2\x9C\x85"];
    $icon = $icons[$event] ?? "\xE2\x84\xB9";

    $text = $icon . ' <b>' . $label . '</b>' . "\n"
        . "\xF0\x9F\x8E\xAC Kontent: <b>" . e($title) . '</b>' . "\n"
        . ($episode_id > 0 ? "\xF0\x9F\x8E\x9E Qism #" . (int)$episode_id . "\n" : '')
        . "\xF0\x9F\x93\x8C Manba: <b>" . e($source_type) . "</b>\n"
        . "\xF0\x9F\x94\x97 " . e($video_url) . "\n"
        . ($detail ? "\xE2\x84\xB9\xEF\xB8\x8F " . e($detail) . "\n" : '')
        . "\xF0\x9F\x95\x90 " . date('d.m.Y H:i');

    if (tg_send_message($text)) {
        try {
            $pdo->prepare("UPDATE video_source_log SET notified = 1 WHERE id = ?")->execute([$log_id]);
        } catch (PDOException $e) {}
    }
}
