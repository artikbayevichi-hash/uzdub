<?php
/**
 * UZDUB PLATFORM — Brauzer orqali ZIP yuklab olish skripti
 *
 * Usul: PowerShell Compress-Archive (temp .ps1 fayl orqali)
 *
 * Ishga tushirish: http://localhost/uzdub/download-zip.php
 * Natija: uzdub_full.zip
 */

set_time_limit(120);
$sourceDir = __DIR__;
$excludeNames = ['.git', 'node_modules', 'vendor', '.gitignore', '_create_zip.php', 'download-zip.php'];

// ---------------------------------------------------------------
// HTML interfeys
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $hasZipArch  = class_exists('ZipArchive');
    $hasPowershell = false;
    if (function_exists('exec')) {
        $out = []; $r = 0;
        @exec('powershell -NoProfile -Command "Get-Command Compress-Archive 2>$null"', $out, $r);
        if ($r === 0) $hasPowershell = true;
    }
    ?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📦 UZDUB PLATFORM ZIP yuklab olish</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
    background:linear-gradient(135deg,#0a0e27 0%,#1a1040 100%);
    min-height:100vh; display:flex; align-items:center; justify-content:center;
    color:#e0e0e0; padding:20px;
}
.card {
    background:rgba(18,26,43,0.9); backdrop-filter:blur(14px);
    border:1px solid rgba(33,150,243,0.3); border-radius:16px;
    padding:40px; max-width:500px; width:100%; text-align:center;
    box-shadow:0 24px 60px rgba(0,0,0,0.5); animation:fadeUp .5s ease both;
}
@keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
.icon { font-size:48px; margin-bottom:16px; }
h1 { font-size:22px; margin-bottom:8px; color:#fff; }
p { font-size:14px; color:#9e9e9e; margin-bottom:8px; line-height:1.6; }
.badge {
    display:inline-block; padding:4px 14px; border-radius:20px;
    font-size:12px; font-weight:600; margin-bottom:20px;
}
.badge.ok { background:rgba(76,175,80,0.15); color:#a5d6a7; border:1px solid #4caf50; }
.badge.fail { background:rgba(229,57,53,0.15); color:#ef9a9a; border:1px solid #e53935; }
.btn {
    display:inline-block; padding:14px 36px;
    background:linear-gradient(135deg,#2196f3,#7c4dff);
    color:#fff; border:none; border-radius:10px; font-size:16px; font-weight:600;
    cursor:pointer; transition:transform .2s ease,box-shadow .2s ease;
    text-decoration:none; margin-top:8px;
}
.btn:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(33,150,243,0.35); }
.btn:active { transform:translateY(0); }
.btn:disabled { opacity:.6; cursor:not-allowed; transform:none; }
.status { margin-top:16px; padding:12px; border-radius:8px; font-size:13px; display:none; }
.status.loading { display:block; background:rgba(33,150,243,0.1); border:1px solid rgba(33,150,243,0.3); color:#90caf9; }
.status.success { display:block; background:rgba(76,175,80,0.1); border:1px solid #4caf50; color:#a5d6a7; }
.status.error { display:block; background:rgba(229,57,53,0.1); border:1px solid #e53935; color:#ef9a9a; }
.note { font-size:12px; color:#616161; margin-top:16px; }
.manual-box {
    margin-top:20px; padding:16px; background:rgba(0,0,0,0.2); border-radius:8px;
    text-align:left; font-size:13px;
}
.manual-box code {
    display:block; padding:10px; background:#0d1424; border-radius:6px;
    font-family:Consolas,monospace; font-size:12px; color:#e0e0e0;
    margin:8px 0; word-break:break-all;
    border:1px solid rgba(33,150,243,0.15);
}
</style>
</head>
<body>
<div class="card">
    <div class="icon">📦</div>
    <h1>UZDUB PLATFORM — Loyiha ZIP</h1>
    <p>Barcha fayllarni ZIP formatida yuklab oling.</p>
    <?php if ($hasZipArch): ?>
        <div class="badge ok">✅ PHP ZipArchive</div>
    <?php elseif ($hasPowershell): ?>
        <div class="badge ok">✅ PowerShell Compress-Archive</div>
    <?php else: ?>
        <div class="badge fail">❌ Hech qanday usul topilmadi</div>
    <?php endif; ?>

    <?php if ($hasZipArch || $hasPowershell): ?>
        <form method="post" id="zipForm">
            <button type="submit" class="btn" id="downloadBtn">
                📥 ZIP yaratish va yuklab olish
            </button>
        </form>
        <div class="status" id="status"></div>
    <?php endif; ?>

    <?php if (!$hasZipArch && !$hasPowershell): ?>
    <div class="manual-box">
        <strong>Qo'lda ZIP yaratish:</strong>
        <p style="margin:6px 0;font-size:12px;">Win+R → <code>powershell</code> → Enter, so'ng shuni yozing:</p>
        <code>Compress-Archive -Path C:\xampp\htdocs\uzdub -DestinationPath C:\uzdub_full.zip -Force</code>
        <p style="margin:6px 0;font-size:12px;color:#757575;">ZIP fayl <strong>C:\uzdub_full.zip</strong> da tayyor bo'ladi.</p>
    </div>
    <?php endif; ?>

    <div class="note">⏱ Jarayon bir necha daqiqa davom etishi mumkin</div>
</div>
<script>
document.getElementById('zipForm')?.addEventListener('submit', function() {
    var btn = document.getElementById('downloadBtn');
    var status = document.getElementById('status');
    btn.disabled = true; btn.textContent = '⏳ ZIP yaratilmoqda...';
    status.className = 'status loading';
    status.textContent = "Fayllar yig'ilmoqda, iltimos kuting...";
});
</script>
</body>
</html>
    <?php
    exit;
}

// ---------------------------------------------------------------
// POST — ZIP yaratish
// ---------------------------------------------------------------

$tmpZip = tempnam(sys_get_temp_dir(), 'uzdub_') . '.zip';

// --- 1-USUL: PHP ZipArchive ---
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        $count = 0;
        foreach ($iter as $file) {
            $fp = $file->getRealPath();
            $rel = 'uzdub/' . substr($fp, strlen($sourceDir) + 1);
            $rel = str_replace('\\', '/', $rel);
            $skip = false;
            foreach ($excludeNames as $ex) {
                if (strpos('/' . str_replace('\\', '/', $fp) . '/', '/' . $ex . '/') !== false) { $skip = true; break; }
            }
            if ($skip) continue;
            $zip->addFile($fp, $rel);
            $count++;
        }
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="uzdub_full.zip"');
        header('Content-Length: ' . filesize($tmpZip));
        readfile($tmpZip);
        unlink($tmpZip);
        exit;
    }
}

// --- 2-USUL: PowerShell Compress-Archive (.ps1 fayl orqali) ---
if (function_exists('exec')) {
    // PowerShell single-quote escaping: ' -> ''
    $srcEscaped = str_replace("'", "''", $sourceDir);
    $dstEscaped = str_replace("'", "''", $tmpZip);

    // Exclude array: single-quoted PowerShell string array
    $excludesPs = '@(';
    $parts = [];
    foreach ($excludeNames as $ex) {
        $parts[] = "'" . str_replace("'", "''", $ex) . "'";
    }
    $excludesPs .= implode(', ', $parts) . ')';

    // PowerShell skripti — temp faylga yoziladi
    $psScript = <<<'PSSCRIPT'
$ErrorActionPreference = 'Stop'
$src = 'SRC_PLACEHOLDER'
$dst = 'DST_PLACEHOLDER'
$exclude = EXCLUDE_PLACEHOLDER

try {
    $items = Get-ChildItem -Path $src -Recurse -File | Where-Object {
        $path = $_.FullName
        $skip = $false
        foreach ($ex in $exclude) {
            if ($path -match [regex]::Escape($ex)) { $skip = $true; break }
        }
        return -not $skip
    }

    if (Test-Path $dst) { Remove-Item $dst -Force }

    $allPaths = @($items | ForEach-Object { $_.FullName })
    Compress-Archive -Path $allPaths -DestinationPath $dst -CompressionLevel Optimal -Force
    Write-Output "OK:$($items.Count)"
} catch {
    Write-Output "ERR:$($_.Exception.Message)"
}
PSSCRIPT;

    // Placeholderlarni almashtiramiz (xavfsiz, chunki path da $ yoki " muammo emas)
    $psScript = str_replace(
        ['SRC_PLACEHOLDER', 'DST_PLACEHOLDER', 'EXCLUDE_PLACEHOLDER'],
        [$srcEscaped, $dstEscaped, $excludesPs],
        $psScript
    );

    // Temp .ps1 fayl yaratamiz
    $psFile = tempnam(sys_get_temp_dir(), 'uzdub_') . '.ps1';
    file_put_contents($psFile, $psScript);

    // PowerShell ni -File orqali ishga tushiramiz (hech qanday quoting muammosi yo'q!)
    $output = [];
    $returnVar = 0;
    $psCmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File "' . $psFile . '"';
    @exec($psCmd, $output, $returnVar);

    @unlink($psFile); // ps1 faylni tozalaymiz

    $firstLine = $output[0] ?? '';

    if (strpos($firstLine, 'OK:') === 0 && file_exists($tmpZip) && filesize($tmpZip) > 0) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="uzdub_full.zip"');
        header('Content-Length: ' . filesize($tmpZip));
        readfile($tmpZip);
        @unlink($tmpZip);
        exit;
    }

    // Xatolik bo'lsa
    $errMsg = htmlspecialchars(implode("\n", array_slice($output, 0, 5)));
    header('Content-Type: text/html; charset=utf-8');
    echo '<div style="padding:20px;font-family:sans-serif;">';
    echo '<h2 style="color:#e53935;">❌ ZIP yaratilmadi</h2>';
    echo '<p style="color:#e0e0e0;">PowerShell orqali ZIP yaratishda xatolik yuz berdi:</p>';
    echo '<pre style="background:#1a1a2e;padding:12px;border-radius:8px;color:#ff8a80;overflow-x:auto;">' . $errMsg . '</pre>';
    echo '<hr style="border-color:#333;margin:16px 0;">';
    echo '<p><strong>Qo\'lda bajarish:</strong></p>';
    echo '<pre style="background:#0d1424;padding:10px;border-radius:6px;color:#e0e0e0;font-size:12px;">';
    echo 'Win+R → powershell → Enter, so\'ng:';
    echo "\n" . 'Compress-Archive -Path C:\xampp\htdocs\uzdub -DestinationPath C:\uzdub_full.zip -Force';
    echo '</pre>';
    echo '</div>';
    exit;
}

// --- Hech narsa ishlamadi ---
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="uz">
<head><meta charset="UTF-8"><title>❌ ZIP xatolik</title></head>
<body style="background:#0a0e27;color:#e0e0e0;font-family:sans-serif;padding:40px;">
<h2 style="color:#e53935;">❌ ZIP yaratib bo'lmadi</h2>
<p>PHP da <strong>ZipArchive</strong> ham yo'q, <strong>exec()</strong> funksiyasi ham o'chirilgan.</p>
<p>Ikkala yo'l bilan tuzatishingiz mumkin:</p>
<h3>1-usul: ZipArchive yoqish</h3>
<pre style="background:#0d1424;padding:10px;border-radius:6px;">
XAMPP Panel → Apache Config → php.ini
→ "extension=zip" qatorini topib, boshidagi ";" ni o'chiring
→ Apache ni restart qiling
</pre>
<h3>2-usul: Qo'lda PowerShell</h3>
<pre style="background:#0d1424;padding:10px;border-radius:6px;">
Win+R → powershell → Enter
Compress-Archive -Path C:\xampp\htdocs\uzdub -DestinationPath C:\uzdub_full.zip -Force
</pre>
</body>
</html>
<?php
