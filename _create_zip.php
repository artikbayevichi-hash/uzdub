<?php
/**
 * UZDUB — barcha o'zgartirilgan fayllarni ZIP-ga solish skripti
 * 
 * Ishga tushirish:
 *   php _create_zip.php
 * 
 * Natija: C:\xampp\htdocs\uzdub_fulldump.zip
 */

$sourceDir = __DIR__;
$zipPath   = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uzdub_fulldump.zip';

if (!class_exists('ZipArchive')) {
    die("Xatolik: PHP ZipArchive kengaytmasi o'rnatilmagan.\n");
}

// Chiqarib tashlanadigan papka va fayllar
$excludeDirs = [
    $sourceDir . DIRECTORY_SEPARATOR . '.git',
    $sourceDir . DIRECTORY_SEPARATOR . 'node_modules',
    $sourceDir . DIRECTORY_SEPARATOR . 'vendor',
];

$excludeFiles = [
    $sourceDir . DIRECTORY_SEPARATOR . '.gitignore',
    $sourceDir . DIRECTORY_SEPARATOR . '_create_zip.php',
];

echo "📦 ZIP yaratilmoqda: $zipPath\n\n";

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Xatolik: ZIP fayl yaratilmadi.\n");
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$count = 0;
foreach ($files as $file) {
    $filePath = $file->getRealPath();
    
    // Skip excluded directories
    $skip = false;
    foreach ($excludeDirs as $exDir) {
        if (strpos($filePath, $exDir) === 0) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;
    
    // Skip excluded files
    if (in_array($filePath, $excludeFiles)) continue;

    $relativePath = substr($filePath, strlen($sourceDir) + 1);
    $zip->addFile($filePath, 'uzdub/' . $relativePath);
    $count++;
}

$zip->close();

echo "✅ ZIP muvaffaqiyatli yaratildi!\n";
echo "   Fayl: $zipPath\n";
echo "   Jami fayllar: $count\n";
echo "   Hajmi: " . round(filesize($zipPath) / 1024 / 1024, 2) . " MB\n";
