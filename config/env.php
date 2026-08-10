<?php
/**
 * .env faylini yuklash (KEY=VALUE format)
 */
function load_env(string $path): void {
    if (!is_file($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val, " \t\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

function env(string $key, $default = '') {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    // Windows Apache mod_php'da putenv/getenv thread-race tufayli getenv ba'zan
    // false qaytaradi (qiymat mavjud bo'lsa ham). Ishonchliligi uchun .env
    // fayldan to'g'ridan-to'g'ri o'qiymiz (har so'rovda bir marta).
    static $_lines = null;
    if ($_lines === null) {
        $_p = __DIR__ . '/../.env';
        $_lines = is_file($_p) ? file($_p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    }
    foreach ($_lines as $_l) {
        $_l = trim($_l);
        if ($_l === '' || $_l[0] === '#') continue;
        if (strpos($_l, '=') === false) continue;
        [$k, $val] = explode('=', $_l, 2);
        if (trim($k) === $key) return trim($val, " \t\"'");
    }
    return $default;
}

load_env(__DIR__ . '/../.env');
