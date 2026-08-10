# UZDUB admin bot — polling daemonini avtomatik ishga tushuradi.
# Agar daemon allaqachon ishlayotgan bo'lsa, hech narsa qilmaydi.
# Har 5 daqiqada vazifa tomonidan chaqiriladi (qulab qolsa qayta tiklanishi uchun).

$ErrorActionPreference = 'SilentlyContinue'

$cacheDir = 'C:\xampp\htdocs\uzdub\cache'
$lock     = Join-Path $cacheDir 'adminbot.pid'
$offset   = Join-Path $cacheDir 'adminbot_offset.json'
$log      = Join-Path $cacheDir 'adminbot.log'
$php      = 'C:\xampp\php\php.exe'
$script   = 'C:\xampp\htdocs\uzdub\api\adminbot-poll.php'

if (!(Test-Path $cacheDir)) { New-Item -ItemType Directory -Path $cacheDir -Force | Out-Null }

function Is-ProcessAlive([int]$pid) {
    if ($pid -le 0) { return $false }
    return [bool](Get-Process -Id $pid -ErrorAction Stop)
}

# Daemon osonlikcha "osilib qolishini" aniqlash:
# offset fayli heartbeat sifatida ishlatiladi (har polling sikli yangilanadi).
# Agar 6 daqiqadan ko'proq eskirgan bo'lsa — daemon osilgan deb hisoblanadi.
function Is-OffsetStale([string]$path) {
    if (!(Test-Path $path)) { return $false } # hali ishga tushmagan, oddiy yo'l
    try {
        $json = Get-Content $path -Raw | ConvertFrom-Json
        if (-not $json.updated_at) { return $false }
        $updated = [datetime]$json.updated_at
        return ((Get-Date) - $updated).TotalMinutes -gt 6
    } catch {
        return $false
    }
}

# Allaqachon ishlayotganini tekshirish (lekin osilgan bo'lsa -> o'ldiramiz)
if (Test-Path $lock) {
    $existing = (Get-Content $lock -Raw).Trim()
    $alive = $false
    if ($existing) {
        $alive = Is-ProcessAlive ([int]$existing)
        if ($alive -and (Is-OffsetStale $offset)) {
            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Daemon osilgan, o'ldirilmoqda (PID $existing)"
            Stop-Process -Id ([int]$existing) -Force -ErrorAction SilentlyContinue
            $alive = $false
        }
    }
    if ($alive) { exit 0 }
}

# Eski (o'lik) pid faylini tozalab, daemonni ishga tushiramiz
Remove-Item $lock -Force -ErrorAction SilentlyContinue

$p = Start-Process -FilePath $php `
    -ArgumentList @($script, '--daemon') `
    -WindowStyle Hidden `
    -PassThru `
    -RedirectStandardOutput $log `
    -RedirectStandardError  ($log + '.err')

if ($p -and !$p.HasExited) {
    Set-Content -Path $lock -Value $p.Id
}

exit 0
