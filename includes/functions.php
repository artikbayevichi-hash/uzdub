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

// ===== ADMIN =====
function is_logged_in() { return isset($_SESSION['admin_id']); }
function require_login() { if (!is_logged_in()) { header('Location: login.php'); exit; } }

// ===== USER =====
function is_user() { return isset($_SESSION['user_id']); }
function current_user() { return $_SESSION['user_data'] ?? null; }

function require_user() {
    if (!is_user()) { header('Location: /uzdub/auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])); exit; }
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
            . "Har bir tavsiyani /uzdub/watch.php?id=<ID> havolasi bilan tugat — foydalanuvchi shu havola orqali to'g'ridan-to'g'ri ko'ra oladi. "
            . "Havolani qisqa va tushunarli yoz, masalan: 'Ko'rish: /uzdub/watch.php?id=1'. "
            . "Ro'yxat bo'sh bo'lsa — saytda hali yo'q deb ayting va boshqa janr taklif qil.\n"
            . "Siyosat, din, huquq mavzularida javob bermaydi. Faqat UZDUB kontentini tavsiya qil.",

        'ru' => "Ты AI-помощник UZDUB. Рекомендуешь фильмы, аниме и мультфильмы. "
            . "Дружелюбно, кратко (2-3 предложения) и естественно отвечай. Используй эмодзи. "
            . "Только на русском языке. Если вопрос неясен — уточни.\n"
            . "Если из базы есть контент — порекомендуй лучший, укажи название, год, жанр, рейтинг. "
            . "Каждую рекомендацию завершай ссылкой /uzdub/watch.php?id=<ID> — пользователь сможет сразу посмотреть. "
            . "Пиши ссылку кратко, например: 'Смотреть: /uzdub/watch.php?id=1'. "
            . "Если списка нет — скажи что контента пока нет и предложи другой жанр.\n"
            . "Не отвечай на темы политики, религии, права. Только контент UZDUB.",

        'en' => "You are UZDUB AI assistant. You recommend movies, anime and cartoons. "
            . "Be friendly, brief (2-3 sentences) and natural. Use emojis. "
            . "Answer only in English. If the question is unclear — ask for clarification.\n"
            . "If there's content from the database — recommend the best, mention name, year, genre, rating. "
            . "End each recommendation with a link /uzdub/watch.php?id=<ID> — the user can watch directly. "
            . "Write the link briefly, e.g.: 'Watch: /uzdub/watch.php?id=1'. "
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
        . "\n\nLink: /uzdub/watch.php?id=<ID>";
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
            'poster'     => $r['poster'] ? '/uzdub/uploads/posters/' . $r['poster'] : null,
            'url'        => '/uzdub/watch.php?id=' . (int)$r['id'],
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
function generate_content_code($pdo, $category_slug) {
    $prefix_map = ['kino' => 'KN', 'anime' => 'AN', 'multfilm' => 'MF'];
    $prefix = $prefix_map[$category_slug] ?? 'CN';
    do {
        $num = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $code = $prefix . $num;
        $exists = $pdo->prepare("SELECT id FROM content WHERE content_code = ?");
        $exists->execute([$code]);
    } while ($exists->fetch());
    return $code;
}

// ===== YouTube ID =====
function get_youtube_id($url) {
    $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/';
    if (preg_match($pattern, $url, $matches)) return $matches[1];
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
function telegram_stream_src($video_url) {
    $v = trim((string)$video_url);
    if ($v === '') return null;
    if (strpos($v, 'tg:') === 0) {
        return '/uzdub/stream.php?tg=' . urlencode(substr($v, 3));
    }
    if (preg_match('#^https?://#i', $v)) {
        return '/uzdub/stream.php?url=' . urlencode($v);
    }
    return null;
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
    if (($bin[0] & 0xFE) === 0xFC) return true;                          // fc00::/7 ULA
    if (($bin[0] & 0xFF) === 0xFE && ($bin[1] & 0xC0) === 0x80) return true; // fe80::/10
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
function render_player($video_type, $video_url, $base_path = 'uploads/videos/', array $subtitles = [], $player_id = 'mainVideo', $poster = null) {
    $subs_html = '';
    foreach ($subtitles as $sub) {
        $src = '/uzdub/uploads/subtitles/' . e($sub['file_path']);
        $label = e($sub['label'] ?? $sub['language']);
        $lang = e($sub['language'] ?? 'uz');
        $subs_html .= '<track kind="subtitles" src="' . $src . '" srclang="' . $lang . '" label="' . $label . '">';
    }

    if ($video_type === 'youtube') {
        $yt_id = get_youtube_id($video_url);
        if ($yt_id) return '<div class="player-wrap"><iframe src="https://www.youtube.com/embed/' . e($yt_id) . '" allowfullscreen allow="autoplay; encrypted-media"></iframe></div>';
        return '<p class="player-error">YouTube havolasi noto\'g\'ri.</p>';
    } elseif ($video_type === 'cloud') {
        return '<div class="player-wrap"><iframe src="' . e($video_url) . '" allowfullscreen></iframe></div>';
    } elseif ($video_type === 'file') {
        $stream_url = '/uzdub/stream.php?url=' . urlencode($base_path . $video_url);
        return build_video_player($player_id, $stream_url, $poster, $subs_html);
    } elseif ($video_type === 'telegram') {
        $stream_url = telegram_stream_src($video_url);
        if ($stream_url) return build_video_player($player_id, $stream_url, $poster, $subs_html);
        return '<p class="player-error">Telegram havolasi noto\'g\'ri.</p>';
    }
    return '';
}

// ===== HTML5 video player (yagona, xatolik fallback bilan) =====
function build_video_player($player_id, $stream_url, $poster = null, $subs_html = '') {
    // controlsList="nodownload" — brauzer playeridagi "Yuklab olish" tugmasini o'chiradi
    // oncontextmenu="return false" — o'ng tugma "Video saqlash" ni bloklaydi
    $attrs = 'controls playsinline preload="metadata" crossorigin="anonymous" controlsList="nodownload" oncontextmenu="return false"';
    if ($poster) $attrs .= ' poster="' . e($poster) . '"';
    $html = '<div class="player-wrap"><video id="' . e($player_id) . '" ' . $attrs . ' src="' . e($stream_url) . '">' . $subs_html . '</video></div>';

    $pid = json_encode($player_id);
    $html .= '<script>
(function(){
    var v = document.getElementById(' . $pid . ');
    if (!v) return;
    v.addEventListener("error", function(){
        if (v.dataset.errHandled) return;
        v.dataset.errHandled = "1";
        var wrap = v.parentNode;
        var box = document.createElement("div");
        box.style.cssText = "position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;background:rgba(0,0,0,0.85);color:#fff;z-index:5;text-align:center;padding:20px;";
        var txt = document.createElement("div");
        txt.style.fontWeight = "700";
        txt.textContent = "\u26a0 Video yuklanmadi";
        var sub = document.createElement("div");
        sub.style.cssText = "font-size:12px;opacity:.75";
        sub.textContent = "Internet yoki server holatini tekshiring";
        var retry = document.createElement("button");
        retry.textContent = "\u{1f504} Qayta urinish";
        retry.style.cssText = "background:#2196f3;color:#fff;border:none;border-radius:8px;padding:10px 22px;font-weight:700;cursor:pointer;";
        retry.onclick = function(){
            delete v.dataset.errHandled;
            v.load();
            if (box.parentNode) box.remove();
        };
        box.appendChild(txt);
        box.appendChild(sub);
        box.appendChild(retry);
        wrap.appendChild(box);
    });
})();
</script>';
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

function is_content_watched(PDO $pdo, int $user_id, int $content_id): bool {
    try {
        $stmt = $pdo->prepare("SELECT id FROM watch_history WHERE user_id = ? AND content_id = ?");
        $stmt->execute([$user_id, $content_id]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function mark_content_watched(PDO $pdo, int $user_id, int $content_id): void {
    try {
        $pdo->prepare("INSERT INTO watch_history (user_id, content_id) VALUES (?,?)
            ON DUPLICATE KEY UPDATE watched_at = CURRENT_TIMESTAMP")
            ->execute([$user_id, $content_id]);
        $pdo->prepare("UPDATE watch_progress SET position_seconds = duration_seconds WHERE user_id = ? AND content_id = ?")
            ->execute([$user_id, $content_id]);
        $pdo->prepare("INSERT INTO user_content_status (user_id, content_id, status) VALUES (?,?,'completed')
            ON DUPLICATE KEY UPDATE status = IF(status = 'favorite', 'favorite', 'completed'), updated_at = CURRENT_TIMESTAMP")
            ->execute([$user_id, $content_id]);
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
function avatar_url($avatar, $base = '/uzdub/') {
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
