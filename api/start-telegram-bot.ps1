# UZDUB Telegram 2FA bot — polling daemonini avtomatik ishga tushuradi.
# Agar daemon allaqachon ishlayotgan bo'lsa, hech narsa qilmaydi.
# Har 5 daqiqada vazifa tomonidan chaqiriladi (qulab qolsa qayta tiklanishi uchun).

$ErrorActionPreference = 'SilentlyContinue'

$cacheDir = 'C:\xampp\htdocs\uzdub\cache'
$lock     = Join-Path $cacheDir 'telegram_bot.pid'
$log      = Join-Path $cacheDir 'telegram_bot.log'
$php      = 'C:\xampp\php\php.exe'
$script   = 'C:\xampp\htdocs\uzdub\api\telegram-poll.php'

if (!(Test-Path $cacheDir)) { New-Item -ItemType Directory -Path $cacheDir -Force | Out-Null }

# Allaqachon ishlayotganini tekshirish
if (Test-Path $lock) {
    $existing = (Get-Content $lock -Raw).Trim()
    if ($existing) {
        try { $proc = Get-Process -Id ([int]$existing) -ErrorAction Stop } catch { $proc = $null }
        if ($proc) { exit 0 }
    }
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
