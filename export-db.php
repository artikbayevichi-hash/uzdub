<?php
/**
 * export-db.php — ma'lumotlar bazasini SQL faylga eksport qilish (CLI).
 *
 * Ishlatish (terminaldan):
 *   php export-db.php              -> exports/database.sql (avtomatik, nusxa yaratadi)
 *   php export-db.php out.sql      -> belgilangan faylga
 *
 * Avval mysqldump qidiriladi (XAMPP: C:\xampp\mysql\bin\mysqldump.exe),
 * topilmasa ichki (PDO) eksportor ishlaydi.
 */

require_once __DIR__ . '/config/db.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Faqat terminaldan ishlatiladi: php export-db.php\n");
}

$outFile = isset($argv[1]) ? $argv[1] : __DIR__ . '/exports/database.sql';
if (!is_dir(dirname($outFile))) {
    mkdir(dirname($outFile), 0777, true);
}

$mysqldump = null;
$candidates = [
    getenv('MYSQLDUMP'),
    'C:/xampp/mysql/bin/mysqldump.exe',
    '/usr/bin/mysqldump',
    '/usr/local/bin/mysqldump',
    '/opt/lampp/bin/mysqldump',
];
foreach ($candidates as $c) {
    if ($c && is_file($c)) { $mysqldump = $c; break; }
}

function run_mysqldump(string $bin, string $out): bool
{
    global $argv;
    $cmd = '"' . $bin . '" --default-character-set=utf8mb4 '
        . '--no-tablespaces --single-transaction --routines --triggers '
        . '-u' . DB_USER . ' ' . (DB_PASS !== '' ? '-p"' . DB_PASS . '" ' : '')
        . '"' . DB_NAME . '" > "' . $out . '" 2>&1';
    $ret = 0;
    $lines = [];
    exec($cmd, $lines, $ret);
    return $ret === 0 && filesize($out) > 0;
}

function pdo_export(string $out): bool
{
    global $pdo;
    $db = DB_NAME;
    $sql  = "-- UZDUB PLATFORM database export\n";
    $sql .= "-- DB: {$db}, sana: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        $t = (string)$t;
        $create = $pdo->query("SHOW CREATE TABLE `" . $t . "`")->fetch(PDO::FETCH_NUM);
        $sql .= "DROP TABLE IF EXISTS `" . $t . "`;\n";
        $sql .= $create[1] . ";\n\n";

        $rows = $pdo->query("SELECT * FROM `" . $t . "`");
        $cols = array_keys($rows->fetch(PDO::FETCH_ASSOC) ?: []);
        $rows->execute();

        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            $vals = [];
            foreach ($cols as $c) {
                $v = $row[$c];
                if ($v === null) { $vals[] = 'NULL'; continue; }
                $vals[] = $pdo->quote((string)$v);
            }
            $sql .= "INSERT INTO `" . $t . "` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ");\n";
        }
        $sql .= "\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return file_put_contents($out, $sql) !== false;
}

if ($mysqldump) {
    echo "mysqldump topildi: $mysqldump\n";
    $ok = run_mysqldump($mysqldump, $outFile);
    if (!$ok) {
        echo "mysqldump xato berdi, ichki eksportor bilan davom etamiz...\n";
        $ok = pdo_export($outFile);
    }
} else {
    echo "mysqldump topilmadi, ichki eksportor ishlatilmoqda.\n";
    $ok = pdo_export($outFile);
}

if ($ok) {
    echo "[OK] Eksport tayyor: " . realpath($outFile) . " (" . filesize($outFile) . " bayt)\n";
    exit(0);
}

fwrite(STDERR, "[XATO] Eksport muvaffaqiyatsiz tugadi.\n");
exit(1);
