<?php
// ===== TIL TIZIMI (UZ / RU / EN) =====

$GLOBALS['translations'] = [
    'uz' => [
        'home' => 'Bosh sahifa',
        'movies' => 'Kino',
        'anime' => 'Anime',
        'cartoons' => 'Multfilm',
        'chat' => 'Chat',
        'messages' => 'Xabarlar',
        'my_list' => "Ro'yxatim",
        'premium' => 'Premium',
        'search_placeholder' => 'Qidirish yoki ID...',
        'login' => 'Kirish',
        'register' => "Ro'yxatdan o'tish",
        'logout' => 'Chiqish',
        'watch' => 'Tomosha qilish',
        'details' => 'Batafsil',
        'add_to_list' => "Keyinroq ko'rish",
        'in_list' => "Ro'yxatda",
        'similar' => "O'xshash kontentlar",
        'episodes' => 'Qismlar',
        'send_message' => 'Xabar yuborish',
        'profile' => 'Profil',
        'buy_premium' => 'Premium olish',
        'search' => 'Qidirish',
    ],
    'ru' => [
        'home' => 'Главная',
        'movies' => 'Фильмы',
        'anime' => 'Аниме',
        'cartoons' => 'Мультфильмы',
        'chat' => 'Чат',
        'messages' => 'Сообщения',
        'my_list' => 'Мой список',
        'premium' => 'Премиум',
        'search_placeholder' => 'Поиск или ID...',
        'login' => 'Войти',
        'register' => 'Регистрация',
        'logout' => 'Выйти',
        'watch' => 'Смотреть',
        'details' => 'Подробнее',
        'add_to_list' => 'Смотреть позже',
        'in_list' => 'В списке',
        'similar' => 'Похожие',
        'episodes' => 'Серии',
        'send_message' => 'Написать сообщение',
        'profile' => 'Профиль',
        'buy_premium' => 'Купить Премиум',
        'search' => 'Поиск',
    ],
    'en' => [
        'home' => 'Home',
        'movies' => 'Movies',
        'anime' => 'Anime',
        'cartoons' => 'Cartoons',
        'chat' => 'Chat',
        'messages' => 'Messages',
        'my_list' => 'My List',
        'premium' => 'Premium',
        'search_placeholder' => 'Search or ID...',
        'login' => 'Login',
        'register' => 'Register',
        'logout' => 'Logout',
        'watch' => 'Watch',
        'details' => 'Details',
        'add_to_list' => 'Watch Later',
        'in_list' => 'In List',
        'similar' => 'Similar Content',
        'episodes' => 'Episodes',
        'send_message' => 'Send Message',
        'profile' => 'Profile',
        'buy_premium' => 'Get Premium',
        'search' => 'Search',
    ],
];

// Tilni aniqlash: ?lang= -> cookie -> standart 'uz'
if (isset($_GET['lang']) && in_array($_GET['lang'], ['uz', 'ru', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    setcookie('site_lang', $_GET['lang'], time() + 60 * 60 * 24 * 365, '/');
}

$GLOBALS['current_lang'] = $_SESSION['lang'] ?? ($_COOKIE['site_lang'] ?? 'uz');
if (!in_array($GLOBALS['current_lang'], ['uz', 'ru', 'en'])) {
    $GLOBALS['current_lang'] = 'uz';
}

function t($key) {
    $lang = $GLOBALS['current_lang'] ?? 'uz';
    return $GLOBALS['translations'][$lang][$key] ?? ($GLOBALS['translations']['uz'][$key] ?? $key);
}

function current_lang() {
    return $GLOBALS['current_lang'] ?? 'uz';
}
