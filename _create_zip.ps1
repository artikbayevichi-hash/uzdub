# UZDUB — ZIP yaratish skripti
# Ishga tushirish: o'ng tugma → "Run with PowerShell"
# Yoki: powershell -NoProfile -ExecutionPolicy Bypass -File _create_zip.ps1

$src = $PSScriptRoot
$dst = Join-Path (Split-Path $PSScriptRoot -Parent) "uzdub_full.zip"
$exclude = @('.git', 'node_modules', 'vendor', '.gitignore', '_create_zip.php', 'download-zip.php', '_create_zip.bat', '_create_zip.ps1', '.freebuff')

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  UZDUB — ZIP yaratilmoqda..." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

try {
    Write-Host "[1/3] Fayllar yig'ilmoqda..." -ForegroundColor Yellow
    
    $items = Get-ChildItem -Path $src -Recurse -File | Where-Object {
        $path = $_.FullName
        $components = $path -split '[\\/]'
        $skip = ($components | Where-Object { $_ -in $exclude }).Count -gt 0
        return -not $skip
    }
    
    Write-Host "[2/3] ZIP arxivlanmoqda... ($($items.Count) ta fayl)" -ForegroundColor Yellow
    
    if (Test-Path $dst) {
        Remove-Item $dst -Force
    }
    
    $allPaths = @($items | ForEach-Object { $_.FullName })
    Compress-Archive -Path $allPaths -DestinationPath $dst -CompressionLevel Optimal -Force
    
    $size = [math]::Round((Get-Item $dst).Length / 1MB, 2)
    
    Write-Host ""
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "  ZIP MUVOFFAQIYATLI YARATILDI!" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    Write-Host "  Fayl:     $dst" -ForegroundColor Green
    Write-Host "  Hajmi:    ${size} MB" -ForegroundColor Green
    Write-Host "  Fayllar:  $($items.Count) ta" -ForegroundColor Green
    Write-Host "========================================" -ForegroundColor Green
    
} catch {
    Write-Host ""
    Write-Host "XATOLIK:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    Write-Host ""
    Write-Host "Sabablari:" -ForegroundColor Yellow
    Write-Host "  - Yo'l notog'ri? $src" -ForegroundColor Yellow
    Write-Host "  - Fayl band? $dst" -ForegroundColor Yellow
    Write-Host "  - Ruxsat yo'qmi?" -ForegroundColor Yellow
}

Write-Host ""
