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
function findBestMatches(PDO $pdo, string $message, int $limit = 3, ?int $userId = null): array {
    $cols = "c.id, c.title, c.description, c.poster, c.release_year, c.rating, c.is_premium, cat.name AS cat_name, "
          . "c.studio, c.director, c.duration, c.status";

    // Janrlarni birlashtirib olish (GROUP_CONCAT)
    $genreJoin = "LEFT JOIN (SELECT cg.content_id, GROUP_CONCAT(g.name SEPARATOR ', ') AS genre_names "
               . "FROM content_genres cg JOIN genres g ON g.id=cg.genre_id GROUP BY cg.content_id) gr ON gr.content_id=c.id";

    // Epizodlar sonini olish
    $epJoin = "LEFT JOIN (SELECT content_id, COUNT(*) AS episode_count FROM episodes GROUP BY content_id) ep ON ep.content_id=c.id";

    // Umumiy fallback (odatiy tartibda eng ko'p ko'rilganlar)
    $fallback = function () use ($pdo, $cols, $limit, $userId, $genreJoin, $epJoin) {
        $extraCols = ", gr.genre_names, ep.episode_count";
        if ($userId) {
            $stmt = $pdo->prepare("SELECT $cols $extraCols, IF(c.category_id IN (SELECT DISTINCT cat2.id FROM watch_progress wp JOIN content c2 ON wp.content_id=c2.id JOIN categories cat2 ON c2.category_id=cat2.id WHERE wp.user_id=?), 1, 0) AS pref
                FROM content c
                JOIN categories cat ON c.category_id = cat.id
                $genreJoin $epJoin
                ORDER BY pref DESC, c.views DESC
                LIMIT $limit");
            $stmt->execute([$userId]);
        } else {
            $stmt = $pdo->query("SELECT $cols $extraCols, 0 AS pref FROM content c JOIN categories cat ON c.category_id = cat.id $genreJoin $epJoin ORDER BY c.views DESC LIMIT $limit");
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
            $extraCols = ", gr.genre_names, ep.episode_count";
            $stmt = $pdo->prepare("SELECT DISTINCT $cols $extraCols $extraSelect
                    FROM content c
                    JOIN categories cat ON c.category_id = cat.id
                    JOIN content_genres cg ON cg.content_id = c.id
                    JOIN genres g ON g.id = cg.genre_id
                    $genreJoin $epJoin
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

    $extraCols = ", gr.genre_names, ep.episode_count";
    $stmt = $pdo->prepare("SELECT $cols $extraCols, ($scoreSql) AS score, ($prefSql) AS pref
            FROM content c
            JOIN categories cat ON c.category_id = cat.id
            $genreJoin $epJoin
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
        $stmt = $pdo->prepare("SELECT $cols $extraCols, 1 AS score, 0 AS pref FROM content c JOIN categories cat ON c.category_id = cat.id $genreJoin $epJoin WHERE $likeWhere ORDER BY c.views DESC LIMIT $limit");
        $stmt->execute($likeParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) return ['matched' => true, 'rows' => $rows];
    }

    // 4) "eng yaxshi", "top", "mashhur" kabi so'zlar bo'lsa — reyting bo'yicha eng yuqorilarni qaytarish
    $topWords = ['eng yaxshi', 'eng zo\'r', 'top', 'mashhur', 'mashhurlar', 'popular', 'best', 'top rated'];
    $lowerMsg = mb_strtolower($message);
    foreach ($topWords as $tw) {
        if (mb_strpos($lowerMsg, $tw) !== false) {
            $stmt = $pdo->prepare("SELECT $cols $extraCols, 0 AS pref FROM content c JOIN categories cat ON c.category_id = cat.id $genreJoin $epJoin ORDER BY c.rating DESC, c.views DESC LIMIT $limit");
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
            'episodes'   => isset($r['episode_count']) ? (int)$r['episode_count'] : null,
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

function generate_switch_token($pdo, $user_db_id) {
    $token = bin2hex(random_bytes(32));
    $pdo->prepare("UPDATE users SET switch_token = ? WHERE id = ?")->execute([$token, $user_db_id]);
    return $token;
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

// ===== Video player (subtitrlar bilan) =====
function render_player($video_type, $video_url, $base_path = 'uploads/videos/', array $subtitles = [], $player_id = 'mainVideo') {
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
        return '<div class="player-wrap"><video id="' . e($player_id) . '" controls autoplay crossorigin="anonymous" src="' . e($base_path) . e($video_url) . '">' . $subs_html . '</video></div>';
    }
    return '';
}

function get_content_subtitles(PDO $pdo, int $content_id, ?int $episode_id = null): array {
    try {
        if ($episode_id) {
            $stmt = $pdo->prepare("SELECT * FROM content_subtitles WHERE content_id = ? AND (episode_id = ? OR episode_id IS NULL) ORDER BY episode_id DESC");
            $stmt->execute([$content_id, $episode_id]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM content_subtitles WHERE content_id = ? AND episode_id IS NULL");
            $stmt->execute([$content_id]);
        }
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_user_rating(PDO $pdo, int $user_id, int $content_id): ?int {
    try {
        $stmt = $pdo->prepare("SELECT rating FROM content_ratings WHERE user_id = ? AND content_id = ?");
        $stmt->execute([$user_id, $content_id]);
        $r = $stmt->fetchColumn();
        return $r !== false ? (int)$r : null;
    } catch (PDOException $e) {
        return null;
    }
}

function get_avg_user_rating(PDO $pdo, int $content_id): ?float {
    try {
        $stmt = $pdo->prepare("SELECT ROUND(AVG(rating), 1) FROM content_ratings WHERE content_id = ?");
        $stmt->execute([$content_id]);
        $v = $stmt->fetchColumn();
        return $v !== null ? (float)$v : null;
    } catch (PDOException $e) {
        return null;
    }
}

function is_content_watched(PDO $pdo, int $user_id, int $content_id): bool {
    try {
        $stmt = $pdo->prepare("SELECT id FROM watched_content WHERE user_id = ? AND content_id = ?");
        $stmt->execute([$user_id, $content_id]);
        return (bool)$stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

function mark_content_watched(PDO $pdo, int $user_id, int $content_id, ?int $episode_id = null): void {
    try {
        $pdo->prepare("INSERT INTO watched_content (user_id, content_id, episode_id) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE completed_at = CURRENT_TIMESTAMP, episode_id = VALUES(episode_id)")
            ->execute([$user_id, $content_id, $episode_id]);
        $pdo->prepare("UPDATE watch_progress SET is_completed = 1 WHERE user_id = ? AND content_id = ?")
            ->execute([$user_id, $content_id]);
    } catch (PDOException $e) {}
}

function get_content_episodes(PDO $pdo, int $content_id): array {
    try {
        $stmt = $pdo->prepare("SELECT * FROM episodes WHERE content_id = ? ORDER BY season ASC, episode_number ASC");
        $stmt->execute([$content_id]);
        return $stmt->fetchAll() ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_next_episode(PDO $pdo, int $content_id, int $current_episode_id): ?array {
    $episodes = get_content_episodes($pdo, $content_id);
    $found = false;
    foreach ($episodes as $ep) {
        if ($found) return $ep;
        if ((int)$ep['id'] === $current_episode_id) $found = true;
    }
    return null;
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
