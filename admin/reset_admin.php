<?php
// BIR MARTALIK SKRIPT: admin login/parolni to'g'rilaydi
// Ishlatgandan so'ng avtomatik o'chiriladi

require_once __DIR__ . '/../config/db.php';

$new_username = 'Doniyorbek';
$new_password = bin2hex(random_bytes(8));

$hash = password_hash($new_password, PASSWORD_DEFAULT);

// admins jadvalida yozuv bor-yo'qligini tekshirish
$check = $pdo->query("SELECT COUNT(*) c FROM admins")->fetch();

if ($check['c'] > 0) {
    $stmt = $pdo->prepare("UPDATE admins SET username = ?, password = ? WHERE id = (SELECT id FROM (SELECT id FROM admins ORDER BY id LIMIT 1) t)");
    $stmt->execute([$new_username, $hash]);
    $action = 'yangilandi';
} else {
    $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
    $stmt->execute([$new_username, $hash]);
    $action = 'yaratildi';
}
?>
<!DOCTYPE html>
<html lang="uz">
<head><meta charset="UTF-8"><title>Admin reset</title>
<style>body{background:#0a0e17;color:#e8eef5;font-family:Arial;padding:60px;text-align:center;}
.box{background:#121a2b;border:1px solid #2196f3;border-radius:10px;padding:30px;max-width:500px;margin:0 auto;}
a{color:#4fc3f7;}</style></head>
<body>
<div class="box">
<h2 style="color:#4fc3f7;">✅ Admin ma'lumotlari <?php echo $action; ?>!</h2>
<p>Login: <b><?php echo htmlspecialchars($new_username); ?></b></p>
<p>Parol: <b><?php echo htmlspecialchars($new_password); ?></b></p>
<p style="margin-top:20px; color:#ef9a9a;">⚠️ Bu skript endi o'chiriladi.</p>
</div>
</body>
</html>
<?php
@unlink(__FILE__);
exit;
