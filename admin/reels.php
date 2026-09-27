<?php
/**
 * UZDUB — Reels boshqaruvi: VK video havolasi bilan qisqa video qo'shish,
 * tartibini o'zgartirish, yoqish/o'chirish va o'chirib tashlash.
 */
$page_title = 'Reels boshqaruvi';
include __DIR__ . '/includes/admin_header.php';
require_once __DIR__ . '/../includes/reels.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Xavfsizlik tokeni noto\'g\'ri.';
    } elseif (isset($_POST['add_reel'])) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $thumb = trim($_POST['thumb'] ?? '');
        $content_id = (int)($_POST['content_id'] ?? 0);
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($title === '' || $video_url === '') {
            $error = 'Sarlavha va VK video havolasi kiritilishi shart.';
        } elseif (!vk_parse_url($video_url)) {
            $error = 'Bu VK video havolasi emas. Namuna: https://vkvideo.ru/video-123456_789012';
        } else {
            if ($content_id > 0) {
                $chk = $pdo->prepare("SELECT id FROM content WHERE id = ?");
                $chk->execute([$content_id]);
                if (!$chk->fetch()) $content_id = 0;
            }
            $pdo->prepare("INSERT INTO reels (title, description, video_url, thumb, content_id, sort_order, created_by)
                           VALUES (?,?,?,?,?,?,?)")
                ->execute([
                    $title,
                    $description !== '' ? $description : null,
                    $video_url,
                    $thumb !== '' ? $thumb : null,
                    $content_id > 0 ? $content_id : null,
                    $sort_order,
                    (int)($_SESSION['admin_id'] ?? 0) ?: null,
                ]);

            // mp4 havolasini oldindan keshlaymiz — birinchi tomoshabin kutmaydi.
            $resolved = vk_resolve_video($video_url);
            $message = $resolved
                ? 'Reel qo\'shildi va video havolasi tayyorlandi.'
                : 'Reel qo\'shildi, lekin VK\'dan mp4 havolasi olinmadi — player VK embed\'ga o\'tadi.';
        }
    } elseif (isset($_POST['toggle_reel'])) {
        $pdo->prepare("UPDATE reels SET is_active = 1 - is_active WHERE id = ?")->execute([(int)$_POST['toggle_reel']]);
        $message = 'Holat o\'zgartirildi.';
    } elseif (isset($_POST['delete_reel'])) {
        $rid = (int)$_POST['delete_reel'];
        $pdo->prepare("DELETE FROM reel_likes WHERE reel_id = ?")->execute([$rid]);
        $pdo->prepare("DELETE FROM reels WHERE id = ?")->execute([$rid]);
        $message = 'Reel o\'chirildi.';
    } elseif (isset($_POST['save_order'])) {
        $st = $pdo->prepare("UPDATE reels SET sort_order = ? WHERE id = ?");
        foreach ((array)($_POST['order'] ?? []) as $rid => $ord) {
            $st->execute([(int)$ord, (int)$rid]);
        }
        $message = 'Tartib saqlandi.';
    }
}

$reels = $pdo->query("SELECT r.*, c.title AS content_title,
                             (SELECT COUNT(*) FROM reel_likes rl WHERE rl.reel_id = r.id) AS likes
                      FROM reels r
                      LEFT JOIN content c ON c.id = r.content_id
                      ORDER BY r.sort_order DESC, r.id DESC")->fetchAll();

$contents = $pdo->query("SELECT id, title FROM content ORDER BY title")->fetchAll();
?>
<style>
.card-box { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:18px; margin-bottom:14px; }
.reel-form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:12px; }
.reel-form-grid label { display:block; font-size:13px; opacity:.8; margin-bottom:4px; }
.reel-form-grid input, .reel-form-grid select, .reel-form-grid textarea { width:100%; box-sizing:border-box; }
.reel-hint { font-size:12px; opacity:.65; margin-top:4px; }
</style>

<h1>&#127902; Reels boshqaruvi</h1>
<?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

<p style="opacity:.75;">Qisqa vertikal videolar VK Video'da saqlanadi. Saytda ular <a href="../reels.php" target="_blank">Reels</a> sahifasida to'g'ridan-to'g'ri mp4 sifatida ijro etiladi.</p>

<div class="card-box">
    <h3>&#10133; Yangi reel</h3>
    <form method="post">
        <?php echo csrf_input(); ?>
        <div class="reel-form-grid">
            <div>
                <label>Sarlavha *</label>
                <input type="text" name="title" required maxlength="255" placeholder="Masalan: Eng qiziq lavha">
            </div>
            <div>
                <label>VK video havolasi *</label>
                <input type="text" name="video_url" required placeholder="https://vkvideo.ru/video-123456_789012">
                <div class="reel-hint">vkvideo.ru / vk.com / vk.ru — video yoki clip havolasi.</div>
            </div>
            <div>
                <label>Muqova rasmi (URL yoki fayl yo'li)</label>
                <input type="text" name="thumb" placeholder="https://... (bo'sh bo'lsa kontent posteri olinadi)">
            </div>
            <div>
                <label>Bog'langan kontent</label>
                <select name="content_id">
                    <option value="0">— yo'q —</option>
                    <?php foreach ($contents as $c): ?>
                    <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['title']); ?> (ID <?php echo (int)$c['id']; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="reel-hint">Reel ostida "Tomosha qilish" tugmasi shu kontentga olib boradi.</div>
            </div>
            <div>
                <label>Tartib (katta raqam yuqorida)</label>
                <input type="number" name="sort_order" value="0">
            </div>
            <div style="grid-column:1/-1;">
                <label>Tavsif</label>
                <textarea name="description" rows="2" placeholder="Qisqa izoh"></textarea>
            </div>
        </div>
        <button type="submit" class="btn" name="add_reel" value="1" style="margin-top:12px;">Qo'shish</button>
    </form>
</div>

<div class="card-box">
    <h3>&#128203; Reels ro'yxati (<?php echo count($reels); ?>)</h3>
    <?php if (!$reels): ?>
    <p style="opacity:.7;">Hozircha reel qo'shilmagan.</p>
    <?php else: ?>
    <form method="post">
        <?php echo csrf_input(); ?>
        <table>
            <tr>
                <th>#</th>
                <th>Sarlavha</th>
                <th>Kontent</th>
                <th>Ko'rish / Layk</th>
                <th>Tartib</th>
                <th>Holat</th>
                <th>Amallar</th>
            </tr>
            <?php foreach ($reels as $r): ?>
            <tr>
                <td><?php echo (int)$r['id']; ?></td>
                <td>
                    <?php echo e($r['title']); ?><br>
                    <a href="<?php echo e($r['video_url']); ?>" target="_blank" style="font-size:12px;opacity:.6;"><?php echo e($r['video_url']); ?></a>
                </td>
                <td><?php echo $r['content_title'] ? e($r['content_title']) : '-'; ?></td>
                <td><?php echo (int)$r['views']; ?> / <?php echo (int)$r['likes']; ?></td>
                <td><input type="number" name="order[<?php echo (int)$r['id']; ?>]" value="<?php echo (int)$r['sort_order']; ?>" style="width:80px;"></td>
                <td><?php echo $r['is_active'] ? '✅ faol' : '⛔ o\'chiq'; ?></td>
                <td style="white-space:nowrap;">
                    <button type="submit" class="btn" name="toggle_reel" value="<?php echo (int)$r['id']; ?>"><?php echo $r['is_active'] ? 'O\'chirish' : 'Yoqish'; ?></button>
                    <button type="submit" class="btn" name="delete_reel" value="<?php echo (int)$r['id']; ?>" onclick="return confirm('Reel butunlay o\'chirilsinmi?');">&#128465;</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <button type="submit" class="btn" name="save_order" value="1" style="margin-top:12px;">Tartibni saqlash</button>
    </form>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
