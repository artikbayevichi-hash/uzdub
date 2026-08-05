<?php
/**
 * Telegram 2FA bot — umumiy ishlov berish funksiyasi.
 * Webhook (HTTP POST) ham, polling (CLI) ham shu funksiyani chaqiradi.
 */

function tg_2fa_send_contact_request($chat_id) {
    $url = 'https://api.telegram.org/bot' . TG_2FA_BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id' => $chat_id,
        'text' => "📱 Tasdiqlash uchun telefon raqamingizni ulashing:\n\nBoshlang'ich bo'limda \"Raqamni ulashish\" tugmasini bosing.",
        'reply_markup' => json_encode([
            'keyboard' => [[['text' => '📱 Raqamni ulashish', 'request_contact' => true]]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]),
    ];
    return tg_api_call($url, $data);
}

function tg_2fa_send_remove_keyboard($chat_id, $text) {
    $url = 'https://api.telegram.org/bot' . TG_2FA_BOT_TOKEN . '/sendMessage';
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
        'reply_markup' => json_encode(['remove_keyboard' => true]),
    ];
    return tg_api_call($url, $data);
}

function tg_2fa_answer_callback($callback_id, $text = '') {
    $url = 'https://api.telegram.org/bot' . TG_2FA_BOT_TOKEN . '/answerCallbackQuery';
    $data = ['callback_query_id' => $callback_id];
    if ($text !== '') $data['text'] = $text;
    return tg_api_call($url, $data);
}

function tg_2fa_edit_message($chat_id, $message_id, $text) {
    $url = 'https://api.telegram.org/bot' . TG_2FA_BOT_TOKEN . '/editMessageText';
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML',
    ];
    return tg_api_call($url, $data);
}

function tg_2fa_handle_callback_query(PDO $pdo, array $cq): void {
    $chat_id = (int)($cq['message']['chat']['id'] ?? 0);
    $message_id = $cq['message']['message_id'] ?? null;
    $callback_id = $cq['id'] ?? '';
    $data = $cq['data'] ?? '';
    $from = $cq['from'] ?? [];
    $tg_user_id = (string)($from['id'] ?? '');

    if (!$chat_id || strpos($data, 'lg:') !== 0) {
        tg_2fa_answer_callback($callback_id, 'Amal bajarilmadi.');
        return;
    }

    $parts = explode(':', $data);
    $action = $parts[1] ?? '';
    $token = $parts[2] ?? '';

    if ($token === '' || !in_array($action, ['yes', 'no'], true)) {
        tg_2fa_answer_callback($callback_id, 'So\'rov noto\'g\'ri.');
        return;
    }

    $stmt = $pdo->prepare("SELECT la.*, u.username, u.telegram_chat_id FROM login_approvals la JOIN users u ON u.id = la.user_id WHERE la.token = ? LIMIT 1");
    $stmt->execute([$token]);
    $appr = $stmt->fetch();

    // Tugmani faqat o'sha foydalanuvchining chatidan bosish mumkin
    if (!$appr || (string)$appr['telegram_chat_id'] !== (string)$chat_id) {
        tg_2fa_answer_callback($callback_id, 'Foydalanuvchi mos kelmadi.');
        return;
    }

    if ($appr['status'] !== 'pending') {
        tg_2fa_answer_callback($callback_id, 'Bu so\'rov allaqachon hal qilingan.');
        return;
    }

    if (strtotime($appr['expires_at']) < time()) {
        $pdo->prepare("UPDATE login_approvals SET status = 'expired' WHERE id = ?")->execute([$appr['id']]);
        tg_2fa_answer_callback($callback_id, 'So\'rov muddati tugagan.');
        if ($message_id) tg_2fa_edit_message($chat_id, $message_id, "⏳ Kirish so'rovi muddati tugadi.");
        return;
    }

    if ($action === 'yes') {
        $pdo->prepare("UPDATE login_approvals SET status = 'approved', decided_at = NOW() WHERE id = ?")->execute([$appr['id']]);
        tg_2fa_answer_callback($callback_id, 'Kirish tasdiqlandi!');
        if ($message_id) tg_2fa_edit_message($chat_id, $message_id, "✅ Kirish <b>tasdiqlandi</b>. Endi saytga qaytishingiz mumkin.");
    } else {
        $pdo->prepare("UPDATE login_approvals SET status = 'denied', decided_at = NOW() WHERE id = ?")->execute([$appr['id']]);
        $sessions = revoke_user_sessions($pdo, (int)$appr['user_id']);
        tg_2fa_answer_callback($callback_id, 'Kirish rad etildi, sessiyalar yakunlandi.');
        if ($message_id) tg_2fa_edit_message($chat_id, $message_id, "🚫 Kirish <b>rad etildi</b>. Barcha faol sessiyalar yakunlandi (" . (int)$sessions . ").");
    }
}

function tg_2fa_handle_update(PDO $pdo, array $update): void {
    if (!empty($update['callback_query'])) {
        tg_2fa_handle_callback_query($pdo, $update['callback_query']);
        return;
    }

    if (empty($update['message'])) return;

    $msg = $update['message'];
    $chat_id = (int)($msg['chat']['id'] ?? 0);
    $text = trim($msg['text'] ?? '');
    $from = $msg['from'] ?? [];
    $tg_user_id = (string)($from['id'] ?? '');
    $tg_username = $from['username'] ?? '';

    if (!$chat_id) return;

    // Foydalanuvchi "Raqamni ulashish" tugmasini bosgan (phone number keladi)
    if (!empty($msg['contact']) && !empty($msg['contact']['phone_number'])) {
        $phone = preg_replace('/[^0-9+]/', '', $msg['contact']['phone_number']);

        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE telegram_chat_id = ? LIMIT 1");
        $stmt->execute([(string)$chat_id]);
        $user = $stmt->fetch();

        if ($user) {
            $pdo->prepare("UPDATE users SET telegram_phone = ?, telegram_user_id = ? WHERE id = ?")
                ->execute([$phone, $tg_user_id, $user['id']]);

            $verify_code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE users SET tg_verify_code = ?, tg_verify_expires = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE id = ?")
                ->execute([$verify_code, $user['id']]);

            tg_2fa_send_remove_keyboard(
                $chat_id,
                "✅ Telegram akkauntingiz <b>" . e($user['username']) . "</b> akkaunti bilan bog'landi!\n\n"
                . "📱 Telefon: <code>$phone</code>\n"
                . "🔢 Saytda kiritish uchun tasdiqlash kodi: <code>$verify_code</code>\n\n"
                . "Kod 3 daqiqa amal qiladi."
            );
        } else {
            tg_2fa_send_remove_keyboard($chat_id, "❌ Avval saytdan himoya kodini oling va /link KOD deb yozing.");
        }
        return;
    }

    if ($text === '') return;

    $cmd = strtok(strtolower($text), ' ');
    $arg = trim(strtok(' ') ?: '');

    if ($cmd === '/code') {
        $stmt = $pdo->prepare("SELECT id, username, telegram_phone, tg_verify_code, tg_verify_expires FROM users WHERE telegram_chat_id = ? LIMIT 1");
        $stmt->execute([(string)$chat_id]);
        $linked = $stmt->fetch();

        if (!$linked) {
            tg_2fa_send_message($chat_id, "❌ Telegram akkauntingiz hech qanday UZDUB akkauntiga bog'lanmagan. Saytdagi Xavfsizlik bo'limidan kod oling va <code>/link KOD</code> deb yozing.");
            return;
        }

        if (empty($linked['telegram_phone'])) {
            tg_2fa_send_contact_request($chat_id);
            return;
        }

        $verify_code = $linked['tg_verify_code'];
        if (empty($verify_code) || empty($linked['tg_verify_expires']) || strtotime($linked['tg_verify_expires']) < time()) {
            $verify_code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $pdo->prepare("UPDATE users SET tg_verify_code = ?, tg_verify_expires = DATE_ADD(NOW(), INTERVAL 3 MINUTE) WHERE id = ?")
                ->execute([$verify_code, $linked['id']]);
        }

        tg_2fa_send_code($chat_id, $verify_code);
        return;
    }

    if ($cmd === '/start' || $cmd === '/link') {
        $code = strtoupper($arg);

        if ($code === '') {
            $check = $pdo->prepare("SELECT id FROM users WHERE telegram_chat_id = ? LIMIT 1");
            $check->execute([(string)$chat_id]);
            $linked = $check->fetch();

            if ($linked) {
                $phoneStmt = $pdo->prepare("SELECT telegram_phone FROM users WHERE id = ?");
                $phoneStmt->execute([$linked['id']]);
                $hasPhone = !empty($phoneStmt->fetchColumn());
                if ($hasPhone) {
                    tg_2fa_send_message($chat_id, "✅ Akkauntingiz allaqachon bog'langan. Saytdagi tasdiqlash kodini kiriting.");
                } else {
                    tg_2fa_send_contact_request($chat_id);
                }
            } else {
                tg_2fa_send_message($chat_id, "👋 UZDUB 2FA botiga xush kelibsiz!\n\nHimoyani yoqish uchun saytdagi kodni yozing: <code>/link KOD</code>");
            }
            return;
        }

        $stmt = $pdo->prepare("SELECT id, username, tg_link_code, tg_link_expires FROM users WHERE tg_link_code = ? LIMIT 1");
        $stmt->execute([$code]);
        $user = $stmt->fetch();

        if (!$user || !$user['tg_link_code']) {
            tg_2fa_send_message($chat_id, "❌ Noto'g'ri kod. Saytdagi \"Xavfsizlik\" bo'limidan yangi kod oling va qayta urinib ko'ring.");
            return;
        }

        if ($user['tg_link_expires'] && strtotime($user['tg_link_expires']) < time()) {
            $pdo->prepare("UPDATE users SET tg_link_code = NULL, tg_link_expires = NULL, tg_verify_code = NULL, tg_verify_expires = NULL WHERE id = ?")
                ->execute([$user['id']]);
            tg_2fa_send_message($chat_id, "⏳ Kod muddati tugagan. Saytdan yangi kod oling.");
            return;
        }

        $pdo->prepare("UPDATE users SET telegram_chat_id = ?, telegram_user_id = ?, tg_link_code = NULL, tg_link_expires = NULL WHERE id = ?")
            ->execute([(string)$chat_id, $tg_user_id, $user['id']]);

        tg_2fa_send_message(
            $chat_id,
            "✅ <b>" . e($user['username']) . "</b> akkauntingiz bog'landi!\n\n"
            . "Endi tasdiqlash uchun telefon raqamingizni ulashing."
        );
        tg_2fa_send_contact_request($chat_id);
        return;
    }

    if ($cmd === '/help') {
        tg_2fa_send_message(
            $chat_id,
            "UZDUB 2FA boti\n\n"
            . "Saytdagi Xavfsizlik bo'limidan \"Yoqish\" tugmasini bosing va berilgan kodni bu yerda yozing:\n"
            . "<code>/link KOD</code>\n\n"
            . "Agar yordam kerak bo'lsa: @uzdub_platform"
        );
        return;
    }

    tg_2fa_send_message(
        $chat_id,
        "🤖 UZDUB 2FA boti\n\n"
        . "Saytdagi himoya kodini yozing:\n<code>/link KOD</code>"
    );
}
