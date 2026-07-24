<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['switch_token']) || empty($_SESSION['switch_user_id'])) {
    header('Location: /uzdub/index.php');
    exit;
}

$token  = $_SESSION['switch_token'];
$user_id = $_SESSION['switch_user_id'];
$username = current_user()['username'] ?? '';
$avatar   = avatar_url(current_user()['avatar'] ?? null);
$premium  = !empty(current_user()['is_premium']);

unset($_SESSION['switch_token'], $_SESSION['switch_user_id']);
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body>
<script>
(function() {
    var accounts = [];
    try { accounts = JSON.parse(localStorage.getItem('uzdub_accounts')) || []; } catch(e) {}
    var entry = {
        user_id: <?php echo json_encode($user_id); ?>,
        username: <?php echo json_encode($username); ?>,
        avatar: <?php echo json_encode($avatar); ?>,
        is_premium: <?php echo json_encode($premium); ?>,
        switch_token: <?php echo json_encode($token); ?>
    };
    var exists = accounts.find(function(a) { return a.user_id === entry.user_id; });
    if (!exists) { accounts.push(entry); } else { exists.switch_token = entry.switch_token; }
    localStorage.setItem('uzdub_accounts', JSON.stringify(accounts));
    localStorage.setItem('uzdub_current_account', JSON.stringify(entry));
    window.location.href = <?php echo json_encode($_SESSION['login_redirect'] ?? '/uzdub/index.php'); ?>;
    <?php unset($_SESSION['login_redirect']); ?>
})();
</script>
</body>
</html>
