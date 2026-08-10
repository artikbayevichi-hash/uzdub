# ============================================================================
#  Repair-FrozenWindow.ps1
#  Windows 11 uchun — qotib qolgan / xira (transparent) oynalarni avtomatik
#  tuzatish skripti.
#
#  Nima qiladi:
#    1) Javob bermayotgan (Not Responding) GUI jarayonlarini topib,
#       majburiy yopadi (taskkill /F).
#    2) Ma'lum "muammoli" ilovalarni (Widgets, Teams, SearchHost va h.k.)
#       majburiy tugatadi.
#    3) Agar muammo hal bo'lmasa, explorer.exe jarayonini qayta ishga
#       tushiradi (bu ish stoli / Vazifalar panelini qayta yuklaydi).
#    4) Har bir qadam va natijani konsolda rangli va tushunarli ko'rsatadi.
#
#  Foydalanish (Administrator kerak emas):
#    powershell -NoProfile -ExecutionPolicy Bypass -File .\Repair-FrozenWindow.ps1
#    yoki shu jilddagi Repair-FrozenWindow.bat faylini ikki marta bosish.
# ============================================================================

$ErrorActionPreference = 'SilentlyContinue'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

# ------------------ Konsol yordamchi funksiyalari --------------------------
function Write-Header { param([string]$Text) Write-Host "`n=== $Text ===" -ForegroundColor Cyan }
function Write-OK     { param([string]$Text) Write-Host "  [OK] $Text" -ForegroundColor Green }
function Write-Warn   { param([string]$Text) Write-Host "  [!] $Text" -ForegroundColor Yellow }
function Write-Fail   { param([string]$Text) Write-Host "  [X] $Text" -ForegroundColor Red }

Write-Host "============================================================" -ForegroundColor Cyan
Write-Host "  Windows 11 — Qotib qolgan oynalarni tuzatish skripti" -ForegroundColor Cyan
Write-Host "============================================================" -ForegroundColor Cyan

# Javob bermayotgan jarayonlar ro'yxatini qaytaruvchi funksiya
function Get-FrozenProcesses {
    Get-Process | Where-Object { $_.MainWindowHandle -ne 0 -and -not $_.HasExited } | ForEach-Object {
        try {
            if (-not $_.Responding) {
                [pscustomobject]@{ Name = $_.ProcessName; Id = $_.Id; Title = $_.MainWindowTitle }
            }
        } catch { }
    }
}

# ------------------ 1-QADAM: Not Responding jarayonlarni o'ldirish ---------
Write-Header "1-qadam: Javob bermayotgan (Not Responding) jarayonlarni aniqlash"

$frozen = @(Get-FrozenProcesses)
if ($frozen.Count -eq 0) {
    Write-OK "Javob bermayotgan GUI jarayoni topilmadi."
} else {
    Write-Warn "Topildi — jami $($frozen.Count) ta muammoli jarayon:"
    foreach ($p in $frozen) {
        Write-Host ("     - {0} (PID: {1})  Oyna: [{2}]" -f $p.Name, $p.Id, $p.Title) -ForegroundColor Yellow
    }
    Write-Host "  Majburiy yopilmoqda (taskkill /F)..."
    foreach ($p in $frozen) {
        $proc = Get-Process -Id $p.Id -ErrorAction SilentlyContinue
        if ($proc) { $proc | Stop-Process -Force }
    }
    Start-Sleep -Seconds 2
    $stillFrozen = @(Get-FrozenProcesses)
    if ($stillFrozen.Count -eq 0) {
        Write-OK "Barcha javob bermayotgan jarayonlar muvaffaqiyatli tugatildi."
    } else {
        Write-Warn "Ba'zi jarayonlar hali ham javob bermayapti ($($stillFrozen.Count) ta)."
    }
}

# ------------------ 2-QADAM: Ma'lum muammoli ilovalarni tugatish -----------
Write-Header "2-qadam: Ma'lum muammoli ilovalarni tugatish"

$problemApps = @(
    'Widgets', 'Widget', 'MicrosoftTeams', 'Teams', 'ms-teams',
    'SearchHost', 'MiniSearchHost', 'SearchUI', 'SearchApp',
    'ShellExperienceHost', 'StartMenuExperienceHost',
    'Cortana', 'Weather', 'YourPhone', 'PeopleExperienceHost'
)

$killed = @()
foreach ($app in $problemApps) {
    $procs = @(Get-Process -Name $app -ErrorAction SilentlyContinue)
    foreach ($pr in $procs) {
        $pr | Stop-Process -Force
        $killed += "$app (PID $($pr.Id))"
    }
}

if ($killed.Count -eq 0) {
    Write-OK "Muammoli ilovalardan hech biri ishlab turgani yo'q edi."
} else {
    Write-Warn ("Tugatildi: " + ($killed -join ', '))
}

# ------------------ 3-QADAM: Kerak bo'lsa explorer.exe qayta ishga tushirish
$stillFrozen = @(Get-FrozenProcesses)

if ($stillFrozen.Count -gt 0) {
    Write-Header "3-qadam: Hali ham muammo bor — explorer.exe qayta ishga tushirilmoqda"
    Write-Host "  explorer.exe jarayoni to'xtatilmoqda..."
    Stop-Process -Name explorer -Force
    Start-Sleep -Seconds 3

    if (-not (Get-Process -Name explorer -ErrorAction SilentlyContinue)) {
        Start-Process explorer.exe
        Start-Sleep -Seconds 3
    }

    if (Get-Process -Name explorer -ErrorAction SilentlyContinue) {
        Write-OK "explorer.exe muvaffaqiyatli qayta ishga tushirildi (ish stoli yangilandi)."
    } else {
        Write-Fail "explorer.exe ishga tushmadi. Qo'lda bajarish: Start-Process explorer.exe"
    }
} else {
    Write-Header "3-qadam: Explorer qayta yuklash shart emas"
    Write-OK "Javob bermayotgan jarayon qolmadi, explorer.exe alohida yuklanmadi."
    Write-Host "  Qat'iy istasangiz explorer'ni ham yangilash mumkin:" -ForegroundColor Gray
    Write-Host "      Stop-Process -Name explorer -Force; Start-Process explorer.exe" -ForegroundColor Gray
}

# ------------------ Yakuniy tekshiruv va natija ----------------------------
Write-Header "Yakuniy tekshiruv"

$finalFrozen = @(Get-FrozenProcesses)
if ($finalFrozen.Count -eq 0) {
    Write-OK "Tayyor! Hozirda javob bermayotgan GUI jarayoni topilmadi."
    Write-Host "  Agar xira oyna hali ham ko'rinayotgan bo'lsa, butun kompyuterni qayta ishga tushiring." -ForegroundColor Yellow
} else {
    Write-Fail "Hali ham $($finalFrozen.Count) ta jarayon javob bermayapti."
    foreach ($p in $finalFrozen) {
        Write-Host ("     - {0} (PID: {1})  Oyna: [{2}]" -f $p.Name, $p.Id, $p.Title) -ForegroundColor Red
    }
    Write-Host "  Ushbu jarayon nomini skriptdagi `$problemApps ro'yxatiga qo'shishingiz mumkin." -ForegroundColor Yellow
}

Write-Host "`nSkript tugadi. Qaytish uchun Enter'ni bosing..." -ForegroundColor Gray
Read-Host | Out-Null
