<?php
/**
 * UZDUB admin bоt — kino/anime/multfilm qo'shish va qismlar boshqaruvi.
 *
 * Asosiy bot (TG_ADMIN_BOT_TOKEN) ishlatiladi. Foydalanish:
 *   - /start  /menu  — asosiy menyu
 *   - /add           — yangi kontent qo'shish (nom -> kategoriya -> janrlar -> poster -> qolgan ma'lumotlar)
 *   - /episodes      — qismlar boshqaruvi (aniq ID yoki aniq nom bilan qidiruv)
 *   - /cancel        — joriy amalni bekor qilish
 *   - /help          — yordam
 *
 * Ishga tushirish (XAMPP / localhost uchun polling daemon):
 *   php api/adminbot-poll.php --daemon
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/payment.php';

// ===== Sozlash =====
function ab_is_admin(int $chat_id): bool {
    if (!TG_ADMIN_BOT_TOKEN || TG_CHAT_ID === 'YOUR_CHAT_ID') return false;
    return (int)TG_CHAT_ID === $chat_id;
}

// ===== State jadvali =====
function ab_ensure_tables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS bot_admin_states (
        chat_id BIGINT NOT NULL PRIMARY KEY,
        step VARCHAR(50) NOT NULL DEFAULT '',
        data TEXT,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ab_state_get(PDO $pdo, int $chat_id): array {
    $stmt = $pdo->prepare("SELECT step, data FROM bot_admin_states WHERE chat_id = ?");
    $stmt->execute([$chat_id]);
    $row = $stmt->fetch();
    if (!$row) return ['step' => '', 'data' => []];
    return ['step' => $row['step'], 'data' => json_decode($row['data'] ?? '[]', true) ?: []];
}

function ab_state_set(PDO $pdo, int $chat_id, string $step, array $data): void {
    $pdo->prepare("INSERT INTO bot_admin_states (chat_id, step, data) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE step = VALUES(step), data = VALUES(data)")
        ->execute([$chat_id, $step, json_encode($data, JSON_UNESCAPED_UNICODE)]);
}

function ab_state_clear(PDO $pdo, int $chat_id): void {
    $pdo->prepare("DELETE FROM bot_admin_states WHERE chat_id = ?")->execute([$chat_id]);
}

// ===== Telegram yordamchi funksiyalar =====
function ab_e($s) { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

function ab_kb(array $rows): array { return ['inline_keyboard' => $rows]; }

function ab_send(int $chat_id, string $text, ?array $buttons = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($buttons !== null) $data['reply_markup'] = json_encode(ab_kb($buttons));
    return tg_api_call('https://api.telegram.org/bot' . TG_ADMIN_BOT_TOKEN . '/sendMessage', $data);
}

function ab_edit(int $chat_id, int $message_id, string $text, ?array $buttons = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];
    if ($buttons !== null) $data['reply_markup'] = json_encode(ab_kb($buttons));
    return tg_api_call('https://api.telegram.org/bot' . TG_ADMIN_BOT_TOKEN . '/editMessageText', $data);
}

function ab_answer(string $callback_id, string $text = '') {
    $data = ['callback_query_id' => $callback_id];
    if ($text !== '') $data['text'] = $text;
    return tg_api_call('https://api.telegram.org/bot' . TG_ADMIN_BOT_TOKEN . '/answerCallbackQuery', $data);
}

function ab_emit(int $chat_id, ?int $message_id, string $text, ?array $buttons = null): void {
    if ($message_id) ab_edit($chat_id, $message_id, $text, $buttons);
    else ab_send($chat_id, $text, $buttons);
}

function ab_tg_get_file_path(string $file_id): ?string {
    $res = tg_api_call('https://api.telegram.org/bot' . TG_ADMIN_BOT_TOKEN . '/getFile', ['file_id' => $file_id]);
    $d = json_decode((string)$res, true);
    return ($d && $d['ok'] && !empty($d['result']['file_path'])) ? $d['result']['file_path'] : null;
}

// ===== Menyu va yordam =====
function ab_main_menu(int $chat_id, ?int $message_id = null): void {
    $text = "🎬 <b>UZDUB ADMIN BOT</b>\n\n"
        . "Kontent va qismlarni Telegram orqali boshqaring:";
    $kb = [
        [['text' => '➕ Kontent qo\'shish', 'callback_data' => 'add']],
        [['text' => '🎬 Qismlar boshqaruvi', 'callback_data' => 'episodes']],
        [['text' => 'ℹ️ Yordam', 'callback_data' => 'help']],
    ];
    if ($message_id) ab_edit($chat_id, $message_id, $text, $kb);
    else ab_send($chat_id, $text, $kb);
}

function ab_send_help(int $chat_id, ?int $message_id = null): void {
    $text = "🤖 <b>UZDUB ADMIN BOT — yordam</b>\n\n"
        . "<b>/add</b> — yangi kino/anime/multfilm qo'shish\n"
        . "  Nomni yozasiz, kategoriya va janrlarni tugmalardan tanlaysiz.\n"
        . "  Poster: kanaldan forward/reply qilingan rasm yoki to'g'ridan-to'g'ri fayl yuboriladi.\n\n"
        . "<b>/episodes</b> — qismlar boshqaruvi\n"
        . "  Kontent <b>ID raqami</b> yoki <b>aniq nomi</b> bilan qidiriladi.\n"
        . "  Qism qo'shish uchun videoni yuborasiz (forward ham bo'ladi).\n\n"
        . "<b>/cancel</b> — joriy amalni bekor qilish\n"
        . "<b>/menu</b> — asosiy menyu";
    $kb = [[['text' => '🔙 Orqaga', 'callback_data' => 'menu']]];
    if ($message_id) ab_edit($chat_id, $message_id, $text, $kb);
    else ab_send($chat_id, $text, $kb);
}

// ===== Kontent qo'shish =====
function ab_start_add(PDO $pdo, int $chat_id, ?int $message_id = null): void {
    ab_state_set($pdo, $chat_id, 'add_title', []);
    $text = "🎬 <b>Yangi kontent qo'shish</b>\n\nKontent nomini yozing:";
    if ($message_id) ab_edit($chat_id, $message_id, $text);
    else ab_send($chat_id, $text);
}

function ab_ask_category(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    $data['category'] = null;
    $kb = [
        [['text' => '🎬 Kino', 'callback_data' => 'cat:kino']],
        [['text' => '🎌 Anime', 'callback_data' => 'cat:anime']],
        [['text' => '🎞️ Multfilm', 'callback_data' => 'cat:multfilm']],
    ];
    ab_state_set($pdo, $chat_id, 'add_category', $data);
    ab_emit($chat_id, $message_id, "🏷️ <b>Kategoriyani tanlang:</b>", $kb);
}

function ab_genre_keyboard(PDO $pdo, array $selected): array {
    $rows = [];
    $st = $pdo->query("SELECT id, name FROM genres ORDER BY name");
    foreach ($st as $g) {
        $on = in_array((int)$g['id'], $selected, true);
        $rows[] = ['text' => ($on ? '✅ ' : '☑️ ') . $g['name'], 'callback_data' => 'genre_toggle:' . (int)$g['id']];
    }
    $chunked = [];
    foreach (array_chunk($rows, 3) as $chunk) $chunked[] = $chunk;
    $chunked[] = [['text' => '✅ Tayyor', 'callback_data' => 'genres_done']];
    return $chunked;
}

function ab_ask_genres(PDO $pdo, int $chat_id, int $message_id, array $data): void {
    $data['genres'] = array_values(array_filter($data['genres'] ?? [], fn($v) => is_numeric($v)));
    ab_state_set($pdo, $chat_id, 'add_genres', $data);
    ab_edit($chat_id, $message_id, "🏷️ <b>Janrlarni tanlang</b> (bir nechta bo'lishi mumkin):\n\nTayyor bo'lgach «✅ Tayyor» tugmasini bosing.", ab_genre_keyboard($pdo, $data['genres']));
}

function ab_ask_poster(PDO $pdo, int $chat_id, int $message_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_poster', $data);
    $kb = [[['text' => '⏭️ O\'tkazib yuborish', 'callback_data' => 'skip']]];
    ab_edit($chat_id, $message_id, "🖼 <b>Poster URL</b>\n\n"
        . "Posterning to'liq URL manzilini yuboring (masalan Gemini orqali yaratilgan rasm).\n"
        . "URL <b>https://</b> bilan boshlanishi shart.\n"
        . "Agar poster bo'lmasa — «O'tkazib yuborish» tugmasini bosing.", $kb);
}

function ab_ask_year(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_year', $data);
    $kb = [[['text' => '⏭️ O\'tkazib yuborish', 'callback_data' => 'skip']]];
    $text = "📅 <b>Chiqqan yili</b> (masalan: 2024)\n\nRaqam yozing yoki «O'tkazib yuborish».";
    if ($message_id) ab_edit($chat_id, $message_id, $text, $kb);
    else ab_send($chat_id, $text, $kb);
}

function ab_ask_rating(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_rating', $data);
    $kb = [[['text' => '⏭️ O\'tkazib yuborish', 'callback_data' => 'skip']]];
    $text = "⭐ <b>Reyting</b> (0 — 10, masalan: 8.5)\n\nYozing yoki «O'tkazib yuborish».";
    if ($message_id) ab_edit($chat_id, $message_id, $text, $kb);
    else ab_send($chat_id, $text, $kb);
}

function ab_ask_studio(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_studio', $data);
    $kb = [[['text' => '⏭️ O\'tkazib yuborish', 'callback_data' => 'skip']]];
    $text = "🏢 <b>Studiya</b> (masalan: MAPPA)\n\nYozing yoki «O'tkazib yuborish».";
    if ($message_id) ab_edit($chat_id, $message_id, $text, $kb);
    else ab_send($chat_id, $text, $kb);
}

function ab_ask_director(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_director', $data);
    $kb = [[['text' => '⏭️ O\'tkazib yuborish', 'callback_data' => 'skip']]];
    $text = "🎬 <b>Rejissyor</b>\n\nYozing yoki «O'tkazib yuborish».";
    if ($message_id) ab_edit($chat_id, $message_id, $text, $kb);
    else ab_send($chat_id, $text, $kb);
}

function ab_ask_duration(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_duration', $data);
    $kb = [[['text' => '⏭️ O\'tkazib yuborish', 'callback_data' => 'skip']]];
    $text = "⏱ <b>Davomiylik</b> (masalan: 24 daqiqa, 1 soat 45 daqiqa)\n\nYozing yoki «O'tkazib yuborish».";
    if ($message_id) ab_edit($chat_id, $message_id, $text, $kb);
    else ab_send($chat_id, $text, $kb);
}

function ab_ask_intro(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_intro', $data);
    $kb = [[['text' => '⏭️ O\'tkazib yuborish', 'callback_data' => 'skip']]];
    $text = "🎬 <b>Intro oralig'i</b> — boshlanishi-tugashi, soniyalarda (masalan: 15-40)\n\n"
        . "Pleyer shu oralikda «Intro'ni o'tkazib yuborish» tugmasini ko'rsatadi va bosilganda 40-soniyadan davom ettiradi.\n"
        . "Intro yo'q bo'lsa — «O'tkazib yuborish» tugmasini bosing (yoki 0 yozing).";
    if ($message_id) ab_edit($chat_id, $message_id, $text, $kb);
    else ab_send($chat_id, $text, $kb);
}

function ab_parse_intro(string $text): ?array {
    $text = trim($text);
    if ($text === '' || $text === '0') return [0, 0];
    if (!preg_match('/^\s*(\d+)\s*[-–]\s*(\d+)\s*$/', $text, $m)) return null;
    $start = (int)$m[1];
    $end = (int)$m[2];
    if ($start < 0 || $end < 0 || $end > 3600 || $end <= $start) return null;
    return [$start, $end];
}

function ab_ask_status(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_status', $data);
    $kb = [
        [['text' => '✅ Tugallangan', 'callback_data' => 'status:completed']],
        [['text' => '🔄 Davom etmoqda', 'callback_data' => 'status:ongoing']],
        [['text' => '🆕 Yangi', 'callback_data' => 'status:upcoming']],
    ];
    ab_emit($chat_id, $message_id, "📌 <b>Holati:</b>", $kb);
}

function ab_ask_premium(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_premium', $data);
    $kb = [
        [['text' => '✅ Ha', 'callback_data' => 'prem:1']],
        [['text' => '❌ Yo\'q', 'callback_data' => 'prem:0']],
    ];
    ab_emit($chat_id, $message_id, "👑 <b>Premium tavsiya?</b>", $kb);
}

function ab_ask_video(PDO $pdo, int $chat_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_video', $data);
    ab_send($chat_id, "🎥 <b>Videoni yuboring</b>\n\n"
        . "Video fayl yuboring yoki boshqa kanaldan <b>forward</b> qiling.\n"
        . "Yoki <b>https://</b> bilan boshlanadigan to'g'ridan-to'g'ri video havolani yozing (masalan URL bot qaytargan havola).\n"
        . "Bekor qilish uchun: /cancel");
}

function ab_build_summary(array $data): string {
    $cat_names = ['kino' => '🎬 Kino', 'anime' => '🎌 Anime', 'multfilm' => '🎞️ Multfilm'];
    $status_names = ['completed' => 'Tugallangan', 'ongoing' => 'Davom etmoqda', 'upcoming' => 'Yangi'];
    $g = $data['genres'] ?? [];

    $lines = [];
    $lines[] = "📋 <b>Yangi kontent — tasdiqlash</b>";
    $lines[] = "";
    $lines[] = "🎬 <b>Nomi:</b> " . ab_e($data['title'] ?? '-');
    $lines[] = "🏷️ <b>Kategoriya:</b> " . ($cat_names[$data['category'] ?? ''] ?? '-');
    $lines[] = "🏷️ <b>Janrlar:</b> " . (empty($g) ? '-' : implode(', ', $g));
    $lines[] = "🖼 <b>Poster:</b> " . (!empty($data['poster']) ? "Ha ({$data['poster']})" : "Yo'q");
    $lines[] = "📅 <b>Yil:</b> " . ($data['release_year'] ?: '-');
    $lines[] = "⭐ <b>Reyting:</b> " . ($data['rating'] !== '' ? $data['rating'] : '-');
    $lines[] = "🏢 <b>Studiya:</b> " . ab_e($data['studio'] ?? '-');
    $lines[] = "🎬 <b>Rejissyor:</b> " . ab_e($data['director'] ?? '-');
    $lines[] = "⏱ <b>Davomiylik:</b> " . ab_e($data['duration'] ?? '-');
    $lines[] = "🎬 <b>Intro:</b> " . ((int)($data['intro_end'] ?? 0) > 0 ? (int)$data['intro_start'] . '-' . (int)$data['intro_end'] . ' soniya' : "Yo'q");
    $lines[] = "📌 <b>Holat:</b> " . ($status_names[$data['status'] ?? ''] ?? '-');
    $lines[] = "👑 <b>Premium:</b> " . (!empty($data['is_premium']) ? 'Ha' : 'Yo\'q');
    if (isset($data['tg_path']) && $data['tg_path']) {
        $lines[] = "🎥 <b>Video:</b> Yuborilgan (Telegram)";
    } elseif (!empty($data['video_src'])) {
        $lines[] = "🎥 <b>Video:</b> " . ab_e($data['video_src']);
    }
    return implode("\n", $lines);
}

function ab_ask_confirm(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'add_confirm', $data);
    $kb = [
        [['text' => '✅ Saqlash', 'callback_data' => 'add_save']],
        [['text' => '✏️ Qayta boshlash', 'callback_data' => 'add_restart']],
        [['text' => '❌ Bekor qilish', 'callback_data' => 'add_cancel']],
    ];
    $text = ab_build_summary($data);
    if ($message_id) ab_edit($chat_id, $message_id, $text, $kb);
    else ab_send($chat_id, $text, $kb);
}

function ab_do_save(PDO $pdo, int $chat_id, array $data): string {
    $cat_slug = $data['category'] ?? 'kino';
    if (!in_array($cat_slug, ['kino', 'anime', 'multfilm'], true)) $cat_slug = 'kino';

    $cat_stmt = $pdo->prepare("SELECT id FROM categories WHERE slug = ?");
    $cat_stmt->execute([$cat_slug]);
    $category_id = (int)$cat_stmt->fetchColumn();
    if (!$category_id) return "❌ Kategoriya topilmadi.";

    $title = trim($data['title'] ?? '');
    if ($title === '') return "❌ Kontent nomi bo'sh.";

    $is_anime = ($cat_slug === 'anime');
    $content_code = generate_content_code($pdo, $cat_slug);

    $video_url = null;
    if (!empty($data['tg_path'])) $video_url = 'tg:' . $data['tg_path'];
    elseif (!empty($data['video_src'])) $video_url = $data['video_src'];
    $status = $data['status'] ?? 'completed';
    if (!in_array($status, ['completed', 'ongoing', 'upcoming'], true)) $status = 'completed';

    $stmt = $pdo->prepare("INSERT INTO content
        (content_code, title, category_id, release_year, rating, is_premium, is_series, video_type, video_url, studio, director, duration, intro_start, intro_end, status, poster)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        $content_code,
        $title,
        $category_id,
        $data['release_year'] ? (int)$data['release_year'] : null,
        $data['rating'] !== '' ? (float)$data['rating'] : null,
        !empty($data['is_premium']) ? 1 : 0,
        $is_anime ? 1 : 0,
        'telegram',
        $video_url,
        $data['studio'] !== '' ? $data['studio'] : null,
        $data['director'] !== '' ? $data['director'] : null,
        $data['duration'] !== '' ? $data['duration'] : null,
        (int)($data['intro_start'] ?? 0),
        (int)($data['intro_end'] ?? 0),
        $status,
        $data['poster'] !== '' ? $data['poster'] : null,
    ]);
    $content_id = (int)$pdo->lastInsertId();

    if (!empty($data['genres'])) {
        $ins = $pdo->prepare("INSERT IGNORE INTO content_genres (content_id, genre_id) VALUES (?,?)");
        foreach ($data['genres'] as $gid) $ins->execute([$content_id, (int)$gid]);
    }

    $extra = $is_anime
        ? "\n\n🎌 Qismlarni qo'shish uchun: /episodes"
        : '';
    return "✅ <b>" . ab_e($title) . "</b> muvaffaqiyatli qo'shildi!\n\n"
        . "🆔 ID: <code>$content_code</code> (raqam: $content_id)$extra";
}

// ===== Qismlar boshqaruvi =====
function ab_start_episodes(PDO $pdo, int $chat_id, ?int $message_id = null): void {
    ab_state_set($pdo, $chat_id, 'ep_search', []);
    $text = "🎬 <b>Qismlar boshqaruvi</b>\n\n"
        . "Kontentning <b>ID raqamini</b> yoki <b>aniq nomini</b> yozing (variantlar ko'rsatilmaydi):";
    if ($message_id) ab_edit($chat_id, $message_id, $text);
    else ab_send($chat_id, $text);
}

function ab_episode_count(PDO $pdo, int $content_id): int {
    $st = $pdo->prepare("SELECT COUNT(*) FROM episodes WHERE content_id = ?");
    $st->execute([$content_id]);
    return (int)$st->fetchColumn();
}

function ab_next_episode_number(PDO $pdo, int $content_id): int {
    $st = $pdo->prepare("SELECT COALESCE(MAX(episode_number), 0) FROM episodes WHERE content_id = ? AND season = 1");
    $st->execute([$content_id]);
    return (int)$st->fetchColumn() + 1;
}

function ab_fetch_content(PDO $pdo, int $content_id): ?array {
    $st = $pdo->prepare("SELECT c.*, cat.slug AS cat_slug, cat.name AS cat_name FROM content c JOIN categories cat ON cat.id = c.category_id WHERE c.id = ?");
    $st->execute([$content_id]);
    $row = $st->fetch();
    return $row ?: null;
}

function ab_ep_menu(PDO $pdo, int $chat_id, ?int $message_id, array $data): void {
    $c = ab_fetch_content($pdo, (int)($data['content_id'] ?? 0));
    if (!$c) {
        ab_emit($chat_id, $message_id, "❌ Kontent topilmadi.");
        ab_state_clear($pdo, $chat_id);
        return;
    }
    $cnt = ab_episode_count($pdo, (int)$c['id']);
    $text = "🎬 <b>" . ab_e(t_title($c)) . "</b> (ID: {$c['id']})\n"
        . "🏷️ Kategoriya: " . ab_e($c['cat_name'] ?? '-') . "\n"
        . "🎞️ Qismlar: " . ($cnt > 0 ? $cnt : "yo'q") . "\n\n"
        . "Amalni tanlang:";
    $kb = [
        [['text' => '➕ Qism qo\'shish', 'callback_data' => 'ep_menu_add']],
        [['text' => '📋 Qismlar ro\'yxati', 'callback_data' => 'ep_menu_list']],
        [['text' => '🗑 Qism o\'chirish', 'callback_data' => 'ep_menu_del']],
        [['text' => '🏠 Bosh menyu', 'callback_data' => 'menu']],
    ];
    ab_state_set($pdo, $chat_id, 'ep_menu', $data);
    ab_emit($chat_id, $message_id, $text, $kb);
}

function ab_ep_ask_number(PDO $pdo, int $chat_id, int $message_id, array $data): void {
    $next = ab_next_episode_number($pdo, (int)$data['content_id']);
    $kb = [[['text' => "⚡ Avtomatik ($next)", 'callback_data' => 'ep_auto']]];
    ab_state_set($pdo, $chat_id, 'ep_num', $data);
    ab_edit($chat_id, $message_id, "Qism raqamini yozing yoki «⚡ Avtomatik ($next)» tugmasini bosing:", $kb);
}

function ab_ep_ask_video(PDO $pdo, int $chat_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'ep_video', $data);
    ab_send($chat_id, "🎥 " . (int)$data['ep_number'] . "-qism <b>videosini yuboring</b>\n\n"
        . "Video fayl yuboring yoki boshqa kanaldan <b>forward</b> qiling.\n"
        . "Yoki <b>https://</b> bilan boshlanadigan to'g'ridan-to'g'ri video havolani yozing.\n"
        . "Bekor qilish uchun: /cancel");
}

function ab_ep_ask_title(PDO $pdo, int $chat_id, array $data): void {
    ab_state_set($pdo, $chat_id, 'ep_title', $data);
    $kb = [[['text' => '⏭️ O\'tkazib yuborish', 'callback_data' => 'ep_skip_title']]];
    ab_send($chat_id, "Qism <b>nomi</b> (ixtiyoriy)\n\nYozing yoki «O'tkazib yuborish» tugmasini bosing.", $kb);
}

function ab_ep_save(PDO $pdo, int $chat_id, array $data, ?int $message_id = null): void {
    $cid = (int)($data['content_id'] ?? 0);
    $num = (int)($data['ep_number'] ?? 0);
    $path = trim($data['ep_tg'] ?? '');
    $src = trim($data['ep_src'] ?? '');
    $title = trim($data['ep_title'] ?? '');

    if (!$cid || $num < 1 || ($path === '' && $src === '')) {
        $msg = "❌ Qism qo'shishda xatolik. /episodes orqali qayta urinib ko'ring.";
        if ($message_id) ab_edit($chat_id, $message_id, $msg);
        else ab_send($chat_id, $msg);
        ab_state_clear($pdo, $chat_id);
        return;
    }

    $video_url = $path !== '' ? ('tg:' . $path) : $src;
    $pdo->prepare("INSERT INTO episodes (content_id, season, episode_number, title, video_type, video_url) VALUES (?,?,?,?, 'telegram', ?)")
        ->execute([$cid, 1, $num, $title !== '' ? $title : null, $video_url]);
    $pdo->prepare("UPDATE content SET is_series = 1 WHERE id = ?")->execute([$cid]);

    $c = ab_fetch_content($pdo, $cid);
    $name = $title !== '' ? $title : $num . '-qism';
    $msg = "✅ <b>" . ab_e($name) . "</b> qo'shildi!\n"
        . "🎬 " . ab_e(t_title($c)) . " (ID: $cid)\n"
        . "🎞️ Jami qismlar: " . ab_episode_count($pdo, $cid);

    if ($message_id) ab_edit($chat_id, $message_id, $msg);
    else ab_send($chat_id, $msg);

    // Qismlar menyusiga qaytish
    $data['content_id'] = $cid;
    ab_ep_menu($pdo, $chat_id, $message_id, $data);
}

function ab_ep_list(PDO $pdo, int $chat_id, int $message_id, array $data): void {
    $cid = (int)($data['content_id'] ?? 0);
    $c = ab_fetch_content($pdo, $cid);
    $st = $pdo->prepare("SELECT * FROM episodes WHERE content_id = ? ORDER BY season, episode_number");
    $st->execute([$cid]);
    $eps = $st->fetchAll();

    $lines = ["🎬 <b>" . ab_e(t_title($c)) . "</b> — qismlar:\n"];
    if (!$eps) {
        $lines[] = "Hozircha qism yo'q.";
    } else {
        foreach ($eps as $ep) {
            $lines[] = "• <b>" . (int)$ep['episode_number'] . "</b>. " . ab_e($ep['title'] ?? ('Qism ' . (int)$ep['episode_number']))
                . " <small>(ID: {$ep['id']}, " . ($ep['duration'] ?: '—') . ")</small>";
        }
    }
    $kb = [
        [['text' => '➕ Qism qo\'shish', 'callback_data' => 'ep_menu_add']],
        [['text' => '🔙 Orqaga', 'callback_data' => 'ep_back']],
    ];
    ab_edit($chat_id, $message_id, implode("\n", $lines), $kb);
}

function ab_ep_delete_menu(PDO $pdo, int $chat_id, int $message_id, array $data): void {
    $cid = (int)($data['content_id'] ?? 0);
    $st = $pdo->prepare("SELECT id, episode_number, title FROM episodes WHERE content_id = ? ORDER BY season, episode_number");
    $st->execute([$cid]);
    $eps = $st->fetchAll();

    if (!$eps) {
        ab_edit($chat_id, $message_id, "🗑 O'chiriladigan qism yo'q.");
        ab_ep_menu($pdo, $chat_id, $message_id, $data);
        return;
    }

    $rows = [];
    foreach ($eps as $ep) {
        $rows[] = [['text' => '🗑 ' . (int)$ep['episode_number'] . '. ' . ab_e($ep['title'] ?? ('Qism ' . (int)$ep['episode_number'])), 'callback_data' => 'ep_del:' . (int)$ep['id']]];
    }
    $rows[] = [['text' => '🔙 Orqaga', 'callback_data' => 'ep_back']];
    ab_edit($chat_id, $message_id, "🗑 <b>O'chiriladigan qismni tanlang:</b>", $rows);
}

// ===== Update ishlov berish =====
function ab_handle_message(PDO $pdo, int $chat_id, array $msg): void {
    $state = ab_state_get($pdo, $chat_id);
    $step = $state['step'] ?? '';
    $data = $state['data'] ?? [];
    $text = trim($msg['text'] ?? '');
    $cmd = strtolower(strtok($text, ' ') ?: $text);

    if ($cmd === '/start' || $cmd === '/menu') {
        ab_state_clear($pdo, $chat_id);
        ab_main_menu($chat_id);
        return;
    }
    if ($cmd === '/add') { ab_start_add($pdo, $chat_id); return; }
    if ($cmd === '/episodes') { ab_start_episodes($pdo, $chat_id); return; }
    if ($cmd === '/cancel') { ab_state_clear($pdo, $chat_id); ab_send($chat_id, "❌ Amal bekor qilindi."); ab_main_menu($chat_id); return; }
    if ($cmd === '/help') { ab_send_help($chat_id); return; }

    switch ($step) {
        case 'add_title':
            if ($text === '') { ab_send($chat_id, "Nom kiritilmadi. Kontent nomini yozing:"); return; }
            $data['title'] = $text;
            ab_ask_category($pdo, $chat_id, null, $data);
            return;

        case 'add_year':
            if ($text !== '') {
                if (!is_numeric($text) || (int)$text < 1900 || (int)$text > 2100) {
                    ab_send($chat_id, "❌ Noto'g'ri yil. Masalan: 2024 — yoki «O'tkazib yuborish» tugmasini bosing.");
                    return;
                }
                $data['release_year'] = (int)$text;
            }
            ab_ask_rating($pdo, $chat_id, null, $data);
            return;

        case 'add_rating':
            if ($text !== '') {
                if (!is_numeric($text) || (float)$text < 0 || (float)$text > 10) {
                    ab_send($chat_id, "❌ Noto'g'ri reyting (0 — 10). Masalan: 8.5");
                    return;
                }
                $data['rating'] = (float)$text;
            } else {
                $data['rating'] = '';
            }
            ab_ask_studio($pdo, $chat_id, null, $data);
            return;

        case 'add_studio':
            $data['studio'] = $text;
            ab_ask_director($pdo, $chat_id, null, $data);
            return;

        case 'add_director':
            $data['director'] = $text;
            ab_ask_duration($pdo, $chat_id, null, $data);
            return;

        case 'add_duration':
            $data['duration'] = $text;
            ab_ask_intro($pdo, $chat_id, null, $data);
            return;

        case 'add_intro':
            $parsed = ab_parse_intro($text);
            if ($parsed === null) {
                ab_send($chat_id, "❌ Noto'g'ri format. «15-40» kabi boshlanishi-tugashi yozing yoki «O'tkazib yuborish» tugmasini bosing. Masalan: 15-40");
                return;
            }
            [$data['intro_start'], $data['intro_end']] = $parsed;
            ab_ask_status($pdo, $chat_id, null, $data);
            return;

        case 'add_poster':
            // Poster: to'liq URL (https://...) — saytga rasm yuklanmaydi
            $t = trim($text);
            if ($t !== '' && preg_match('#^https?://#i', $t)) {
                $data['poster'] = $t;
                ab_ask_year($pdo, $chat_id, null, $data);
            } elseif ($t === '') {
                $data['poster'] = '';
                ab_ask_year($pdo, $chat_id, null, $data);
            } else {
                ab_send($chat_id, "❌ Poster to'liq URL manzili bo'lishi kerak (https://... bilan boshlansin) yoki «O'tkazib yuborish» tugmasini bosing.");
            }
            return;

        case 'add_video':
            // Kino / multfilm uchun yagona video
            $video = $msg['video'] ?? [];
            $file_id = $video['file_id'] ?? '';
            if ($file_id === '' && !empty($msg['document']) && strpos($msg['document']['mime_type'] ?? '', 'video') !== false) {
                $file_id = $msg['document']['file_id'] ?? '';
            }
            if ($file_id === '') {
                if (preg_match('#^https?://#i', $text)) {
                    $normalized = telegram_normalize_url($text);
                    if (!$normalized) {
                        ab_send($chat_id, "❌ Havola noto'g'ri. To'g'ridan-to'g'ri https:// video havola yozing yoki video fayl/forward qiling. /cancel — bekor qilish.");
                        return;
                    }
                    $data['video_src'] = $normalized;
                    $data['tg_path'] = null;
                    ab_ask_confirm($pdo, $chat_id, null, $data);
                    return;
                }
                ab_send($chat_id, "❌ Video topilmadi. Video fayl yuboring, forward qiling yoki https:// video havola yozing. /cancel — bekor qilish.");
                return;
            }
            $path = ab_tg_get_file_path((string)$file_id);
            if (!$path) {
                ab_send($chat_id, "❌ Videoni yuklab bo'lmadi. Qayta urinib ko'ring.");
                return;
            }
            $data['tg_path'] = $path;
            $data['video_src'] = null;
            ab_ask_confirm($pdo, $chat_id, null, $data);
            return;

        case 'add_confirm':
            ab_ask_confirm($pdo, $chat_id, null, $data);
            return;

        case 'ep_search':
            if ($text === '') { ab_send($chat_id, "ID yoki nom yozing:"); return; }
            $st = $pdo->prepare("SELECT c.id FROM content c WHERE c.id = ? OR c.title = ? OR c.title_ru = ? OR c.title_en = ?");
            $st->execute([is_numeric($text) ? (int)$text : 0, $text, $text, $text]);
            $rows = $st->fetchAll();
            if (count($rows) === 1) {
                $data['content_id'] = (int)$rows[0]['id'];
                $data['ep_number'] = null;
                ab_state_set($pdo, $chat_id, 'ep_menu', $data);
                $c = ab_fetch_content($pdo, $data['content_id']);
                $cnt = ab_episode_count($pdo, $data['content_id']);
                $kb = [
                    [['text' => '➕ Qism qo\'shish', 'callback_data' => 'ep_menu_add']],
                    [['text' => '📋 Qismlar ro\'yxati', 'callback_data' => 'ep_menu_list']],
                    [['text' => '🗑 Qism o\'chirish', 'callback_data' => 'ep_menu_del']],
                    [['text' => '🏠 Bosh menyu', 'callback_data' => 'menu']],
                ];
                ab_send($chat_id, "🎬 <b>" . ab_e(t_title($c)) . "</b> (ID: {$data['content_id']})\n"
                    . "🏷️ Kategoriya: " . ab_e($c['cat_name'] ?? '-') . "\n"
                    . "🎞️ Qismlar: " . ($cnt > 0 ? $cnt : "yo'q") . "\n\n"
                    . "Amalni tanlang:", $kb);
                return;
            }
            if (count($rows) === 0) {
                ab_send($chat_id, "❌ «" . ab_e($text) . "» bo'yicha kontent topilmadi.\n\nAniq <b>ID raqamini</b> yoki <b>aniq nomni</b> yozing:");
                return;
            }
            ab_send($chat_id, "⚠️ Bir nechta kontent topildi. Aniq <b>ID raqamini</b> yozing:");
            return;

        case 'ep_num':
            if (!is_numeric($text) || (int)$text < 1) {
                ab_send($chat_id, "❌ Noto'g'ri qism raqami. 1 dan katta raqam yozing yoki «⚡ Avtomatik» tugmasini bosing.");
                return;
            }
            $data['ep_number'] = (int)$text;
            ab_ep_ask_video($pdo, $chat_id, $data);
            return;

        case 'ep_video':
            $video = $msg['video'] ?? [];
            $file_id = $video['file_id'] ?? '';
            if ($file_id === '' && !empty($msg['document']) && strpos($msg['document']['mime_type'] ?? '', 'video') !== false) {
                $file_id = $msg['document']['file_id'] ?? '';
            }
            if ($file_id === '') {
                if (preg_match('#^https?://#i', $text)) {
                    $normalized = telegram_normalize_url($text);
                    if (!$normalized) {
                        ab_send($chat_id, "❌ Havola noto'g'ri. To'g'ridan-to'g'ri https:// video havola yozing yoki video fayl/forward qiling. /cancel — bekor qilish.");
                        return;
                    }
                    $data['ep_src'] = $normalized;
                    $data['ep_tg'] = null;
                    ab_ep_ask_title($pdo, $chat_id, $data);
                    return;
                }
                ab_send($chat_id, "❌ Video topilmadi. Video fayl yuboring, forward qiling yoki https:// video havola yozing. /cancel — bekor qilish.");
                return;
            }
            $path = ab_tg_get_file_path((string)$file_id);
            if (!$path) {
                ab_send($chat_id, "❌ Videoni yuklab bo'lmadi. Qayta urinib ko'ring.");
                return;
            }
            $data['ep_tg'] = $path;
            $data['ep_src'] = null;
            ab_ep_ask_title($pdo, $chat_id, $data);
            return;

        case 'ep_title':
            $data['ep_title'] = $text;
            ab_ep_save($pdo, $chat_id, $data);
            return;

        default:
            ab_state_clear($pdo, $chat_id);
            ab_main_menu($chat_id);
            return;
    }
}

function ab_handle_callback(PDO $pdo, array $cq): void {
    $chat_id = (int)($cq['message']['chat']['id'] ?? 0);
    $message_id = (int)($cq['message']['message_id'] ?? 0);
    $callback_id = (string)($cq['id'] ?? '');
    $cbdata = (string)($cq['data'] ?? '');

    if (!$chat_id) return;
    if (!ab_is_admin($chat_id)) {
        ab_answer($callback_id, 'Ruxsat yo\'q.');
        return;
    }
    ab_answer($callback_id);

    $state = ab_state_get($pdo, $chat_id);
    $data = $state['data'] ?? [];
    $step = $state['step'] ?? '';

    $parts = explode(':', $cbdata, 2);
    $action = $parts[0];
    $arg = $parts[1] ?? '';

    switch ($action) {
        case 'menu':
            ab_state_clear($pdo, $chat_id);
            ab_main_menu($chat_id, $message_id);
            return;

        case 'help':
            ab_send_help($chat_id, $message_id);
            return;

        case 'add':
            ab_start_add($pdo, $chat_id, $message_id);
            return;

        case 'episodes':
            ab_start_episodes($pdo, $chat_id, $message_id);
            return;

        case 'cat':
            if (!in_array($arg, ['kino', 'anime', 'multfilm'], true)) return;
            $data['category'] = $arg;
            $data['genres'] = [];
            ab_ask_genres($pdo, $chat_id, $message_id, $data);
            return;

        case 'genre_toggle':
            $gid = (int)$arg;
            $sel = $data['genres'] ?? [];
            if (in_array($gid, $sel, true)) {
                $sel = array_values(array_diff($sel, [$gid]));
            } else {
                $sel[] = $gid;
            }
            $data['genres'] = $sel;
            ab_state_set($pdo, $chat_id, 'add_genres', $data);
            ab_edit($chat_id, $message_id, "🏷️ <b>Janrlarni tanlang</b> (bir nechta bo'lishi mumkin):\n\nTayyor bo'lgach «✅ Tayyor» tugmasini bosing.", ab_genre_keyboard($pdo, $sel));
            return;

        case 'genres_done':
            ab_ask_poster($pdo, $chat_id, $message_id, $data);
            return;

        case 'skip':
            // Qaysi qadamda turganiga qarab keyingisiga o'tish
            if ($step === 'add_poster') { $data['poster'] = ''; ab_ask_year($pdo, $chat_id, $message_id, $data); }
            elseif ($step === 'add_year') { ab_ask_rating($pdo, $chat_id, $message_id, $data); }
            elseif ($step === 'add_rating') { $data['rating'] = ''; ab_ask_studio($pdo, $chat_id, $message_id, $data); }
            elseif ($step === 'add_studio') { $data['studio'] = ''; ab_ask_director($pdo, $chat_id, $message_id, $data); }
            elseif ($step === 'add_director') { $data['director'] = ''; ab_ask_duration($pdo, $chat_id, $message_id, $data); }
            elseif ($step === 'add_duration') { $data['duration'] = ''; ab_ask_intro($pdo, $chat_id, $message_id, $data); }
            elseif ($step === 'add_intro') { $data['intro_start'] = 0; $data['intro_end'] = 0; ab_ask_status($pdo, $chat_id, $message_id, $data); }
            else { ab_main_menu($chat_id, $message_id); }
            return;

        case 'status':
            if (!in_array($arg, ['completed', 'ongoing', 'upcoming'], true)) return;
            $data['status'] = $arg;
            ab_ask_premium($pdo, $chat_id, $message_id, $data);
            return;

        case 'prem':
            $data['is_premium'] = ($arg === '1') ? 1 : 0;
            if (($data['category'] ?? '') === 'anime') {
                ab_ask_confirm($pdo, $chat_id, $message_id, $data);
            } else {
                ab_ask_video($pdo, $chat_id, $data);
            }
            return;

        case 'add_save':
            $res = ab_do_save($pdo, $chat_id, $data);
            ab_state_clear($pdo, $chat_id);
            $kb = [[['text' => '🏠 Bosh menyu', 'callback_data' => 'menu']]];
            ab_edit($chat_id, $message_id, $res, $kb);
            return;

        case 'add_restart':
            ab_start_add($pdo, $chat_id, $message_id);
            return;

        case 'add_cancel':
            ab_state_clear($pdo, $chat_id);
            ab_main_menu($chat_id, $message_id);
            return;

        case 'ep_menu_add':
            ab_ep_ask_number($pdo, $chat_id, $message_id, $data);
            return;

        case 'ep_menu_list':
            ab_ep_list($pdo, $chat_id, $message_id, $data);
            return;

        case 'ep_menu_del':
            ab_ep_delete_menu($pdo, $chat_id, $message_id, $data);
            return;

        case 'ep_back':
            ab_ep_menu($pdo, $chat_id, $message_id, $data);
            return;

        case 'ep_auto':
            $data['ep_number'] = ab_next_episode_number($pdo, (int)($data['content_id'] ?? 0));
            ab_ep_ask_video($pdo, $chat_id, $data);
            return;

        case 'ep_skip_title':
            $data['ep_title'] = '';
            ab_ep_save($pdo, $chat_id, $data);
            return;

        case 'ep_del':
            $ep_id = (int)$arg;
            $cid = (int)($data['content_id'] ?? 0);
            $pdo->prepare("DELETE FROM episodes WHERE id = ? AND content_id = ?")->execute([$ep_id, $cid]);
            if (ab_episode_count($pdo, $cid) === 0) {
                $pdo->prepare("UPDATE content SET is_series = 0 WHERE id = ?")->execute([$cid]);
            }
            ab_ep_delete_menu($pdo, $chat_id, $message_id, $data);
            return;

        default:
            ab_main_menu($chat_id, $message_id);
            return;
    }
}

function ab_handle_update(PDO $pdo, array $update): void {
    if (!empty($update['callback_query'])) {
        ab_handle_callback($pdo, $update['callback_query']);
        return;
    }
    if (empty($update['message'])) return;

    $msg = $update['message'];
    $chat_id = (int)($msg['chat']['id'] ?? 0);
    if (!$chat_id) return;
    if (!ab_is_admin($chat_id)) return;

    ab_handle_message($pdo, $chat_id, $msg);
}
