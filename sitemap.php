<?php
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/xml; charset=utf-8');

$base = 'https://seltzer-wisdom-fanciness.ngrok-free.dev/uzdub';

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?php echo $base; ?>/index.php</loc><priority>1.0</priority></url>
  <url><loc><?php echo $base; ?>/category.php?slug=kino</loc><priority>0.9</priority></url>
  <url><loc><?php echo $base; ?>/category.php?slug=anime</loc><priority>0.9</priority></url>
  <url><loc><?php echo $base; ?>/category.php?slug=multfilm</loc><priority>0.9</priority></url>
<?php
$stmt = $pdo->query("SELECT id, title_uz FROM content WHERE status='published' ORDER BY id DESC LIMIT 500");
while ($row = $stmt->fetch()) {
    $slug = str_replace(' ', '-', trim($row['title_uz']));
    echo "  <url><loc>{$base}/watch.php?id={$row['id']}</loc><priority>0.7</priority></url>\n";
}
?>
</urlset>
<?php
