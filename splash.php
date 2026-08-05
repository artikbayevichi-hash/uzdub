<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = 'UZDUB PLATFORM';

$splash_plans = [
    ['label_key' => 'splash_plan_1m', 'price' => '10 000', 'days' => 30, 'features' => ['splash_hdfit', 'splash_unlimited', 'splash_premium_cont']],
    ['label_key' => 'splash_plan_3m', 'price' => '25 000', 'days' => 90, 'features' => ['splash_hdfit', 'splash_unlimited', 'splash_premium_cont', 'splash_popular'], 'popular' => true],
    ['label_key' => 'splash_plan_1y', 'price' => '80 000', 'days' => 365, 'features' => ['splash_hdfit', 'splash_unlimited', 'splash_premium_cont', 'splash_best']],
];

$splash_user = is_user() ? current_user() : null;
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b0f19">
    <title>UZDUB PLATFORM — <?php echo t('splash_line1'); ?></title>
    <meta name="description" content="Kino, Anime, Multfilmlar — O'zbek tilida. Barcha sevimli kontentlaringiz bir joyda.">
    <link rel="stylesheet" href="/uzdub/css/landing-splash.css">
    <link rel="stylesheet" href="/uzdub/css/emoji-blue.css">
    <script src="/uzdub/js/emoji-blue.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"></noscript>
</head>
<body class="ls-page">
    <div class="ls-particles" id="lsParticles"></div>

    <!-- ===== HEADER ===== -->
    <header class="ls-header">
        <div class="ls-header-inner">
            <a href="/uzdub/splash.php" class="ls-header-logo">🎬 UZDUB</a>
            <div class="ls-header-actions">
                <?php if ($splash_user): ?>
                <button id="lsEnterBtn" class="ls-btn ls-btn-primary ls-btn-sm">
                    <?php echo t('splash_enter'); ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
                <?php else: ?>
                <a href="/uzdub/auth/login.php" class="ls-btn ls-btn-primary ls-btn-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    <?php echo t('login'); ?>
                </a>
                <a href="/uzdub/auth/register.php" class="ls-btn ls-btn-outline ls-btn-sm">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    <?php echo t('register'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- ===== HERO ===== -->
    <section class="ls-hero">
        <div class="ls-wrap">
            <div class="ls-logo-wrap">
                <div class="ls-logo-ring"></div>
                <div class="ls-logo-ring ls-ring-2"></div>
                <div class="ls-logo-ring ls-ring-3"></div>
                <div class="ls-logo">🎬 UZDUB</div>
                <div class="ls-logo-sub">PLATFORM</div>
            </div>

            <h1 class="ls-hero-title">
                <span class="ls-hero-line ls-line-1">UZDUB-da nima qila olasiz?</span>
            </h1>

            <p class="ls-hero-sub">Kino, Anime va Multfilmlar</p>
            <p class="ls-hero-sub-sm">O'zbek tilida</p>

            <?php if ($splash_user): ?>
            <div class="ls-user-card">
                <img src="<?php echo avatar_url($splash_user['avatar']); ?>" alt="" class="ls-user-avatar">
                <div>
                    <div class="ls-user-name"><?php echo e($splash_user['username']); ?></div>
                    <?php if (!empty($splash_user['is_premium'])): ?>
                    <div class="ls-user-premium">👑 Premium</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ===== NIMA UCHUN UZDUB? ===== -->
    <section class="ls-why">
        <div class="ls-wrap">
            <div class="ls-why-badge">✨ Nima uchun UZDUB?</div>
            <h2 class="ls-section-title ls-fade-up">Sizning ehtiyojingiz — <br>bizning ustuvorligimiz</h2>
            <p class="ls-section-desc ls-fade-up">Biz faqat video yuklamaymiz — biz tajriba yaratamiz. Har bir qator, har bir sahna, har bir dublyaj — e'tibor bilan tayyorlangan.</p>

            <div class="ls-why-grid">
                <div class="ls-why-card ls-fade-up">
                    <div class="ls-why-num">01</div>
                    <h3>Sifatli dublyaj</h3>
                    <p>Har bir kino va anime professional o'zbek tilida dublyajlangan. Hech qanday avtomatik tarjima — faqat insoniya sifat.</p>
                </div>
                <div class="ls-why-card ls-fade-up">
                    <div class="ls-why-num">02</div>
                    <h3>Tezkor yangilanish</h3>
                    <p>Eng so'nggi kinolar va animelar birinchi bizda paydo bo'ladi. Kundalik yangilanish bilan doimo yangilikdasiz.</p>
                </div>
                <div class="ls-why-card ls-fade-up">
                    <div class="ls-why-num">03</div>
                    <h3>Qulay interfeys</h3>
                    <p>Oddiy va tushunarli dizayn. Kim bo'lishingizdan qat'i nazar, saytda bemalol yo'nalashtirasiz.</p>
                </div>
                <div class="ls-why-card ls-fade-up">
                    <div class="ls-why-num">04</div>
                    <h3>AI yordamchi</h3>
                    <p>Sun'iy intellekt sizga yoqadigan kino va animelarni topishda yordam beradi. Shaxsiy tavsiyalar — har doim siz uchun.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== QANDAY ISHLAYDI? ===== -->
    <section class="ls-how">
        <div class="ls-wrap">
            <h2 class="ls-section-title ls-fade-up">Qanday ishlaydi?</h2>
            <p class="ls-section-desc ls-fade-up">Faqat 3 qadam — va siz sevimli kontentingizni tomosha qilishingiz mumkin</p>

            <div class="ls-how-steps">
                <div class="ls-how-step ls-fade-up">
                    <div class="ls-step-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    </div>
                    <div class="ls-step-num">1</div>
                    <h3>Ro'yxatdan o'ting</h3>
                    <p>Bepul akkaunt yarating yoki Google orqali kiring. Bir necha soniya ichida tayyor.</p>
                </div>
                <div class="ls-how-connector"></div>
                <div class="ls-how-step ls-fade-up">
                    <div class="ls-step-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </div>
                    <div class="ls-step-num">2</div>
                    <h3>Toping</h3>
                    <p>Qidiruv orqali yoki AI yordamchisi bilan o'zingizga yoqadigan kontentni toping.</p>
                </div>
                <div class="ls-how-connector"></div>
                <div class="ls-how-step ls-fade-up">
                    <div class="ls-step-icon">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                    </div>
                    <div class="ls-step-num">3</div>
                    <h3>Tomosha qiling</h3>
                    <p>HD sifatda, cheklovsiz, istalgan vaqtda. O'zingizga qulay joyda — uyda, ishda, yo'lda.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== AFZALLIKLAR ===== -->
    <section class="ls-features">
        <div class="ls-wrap">
            <h2 class="ls-section-title ls-fade-up"><?php echo t('splash_about_title'); ?></h2>
            <p class="ls-section-desc ls-fade-up"><?php echo t('splash_about_desc'); ?></p>

            <div class="ls-features-grid">
                <div class="ls-feature-card">
                    <div class="ls-feature-icon">🎬</div>
                    <h3><?php echo t('splash_feature_kino'); ?></h3>
                    <p><?php echo t('splash_feature_kino_desc'); ?></p>
                </div>
                <div class="ls-feature-card">
                    <div class="ls-feature-icon">🎌</div>
                    <h3><?php echo t('splash_feature_anime'); ?></h3>
                    <p><?php echo t('splash_feature_anime_desc'); ?></p>
                </div>
                <div class="ls-feature-card">
                    <div class="ls-feature-icon">🎞️</div>
                    <h3><?php echo t('splash_feature_multfilm') ?: 'Multfilm'; ?></h3>
                    <p><?php echo t('splash_feature_multfilm_desc') ?: "Bolalar uchun multfilmlar o'zbek tilida"; ?></p>
                </div>
                <div class="ls-feature-card">
                    <div class="ls-feature-icon">🤖</div>
                    <h3><?php echo t('splash_feature_ai'); ?></h3>
                    <p><?php echo t('splash_feature_ai_desc'); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== IJTIMOIY ISBOTLAR ===== -->
    <section class="ls-social-proof">
        <div class="ls-wrap">
            <h2 class="ls-section-title ls-fade-up">Ijtimoiy isbotlar</h2>
            <p class="ls-section-desc ls-fade-up">Foydalanuvchilarimiz nima deydi</p>

            <div class="ls-testimonials">
                <div class="ls-testimonial ls-fade-up">
                    <div class="ls-testimonial-stars">⭐⭐⭐⭐⭐</div>
                    <p class="ls-testimonial-text">"UZDUB — bu men kutgan platforma. Sifatli dublyaj, qulay interfeys va doimiy yangilanish. Boshqa saytlarga qaraganda ancha yaxshi!"</p>
                    <div class="ls-testimonial-author">
                        <div class="ls-testimonial-avatar">A</div>
                        <div>
                            <div class="ls-testimonial-name">Aziz K.</div>
                            <div class="ls-testimonial-role">Premium foydalanuvchi</div>
                        </div>
                    </div>
                </div>
                <div class="ls-testimonial ls-fade-up">
                    <div class="ls-testimonial-stars">⭐⭐⭐⭐⭐</div>
                    <p class="ls-testimonial-text">"AI yordamchisi juda foydali. Menga yoqadigan turdagi animelarni topib berdi. Endi har kuni yangi narsa kashf etaman."</p>
                    <div class="ls-testimonial-author">
                        <div class="ls-testimonial-avatar">S</div>
                        <div>
                            <div class="ls-testimonial-name">Sardor M.</div>
                            <div class="ls-testimonial-role">Anime ixlosmandi</div>
                        </div>
                    </div>
                </div>
                <div class="ls-testimonial ls-fade-up">
                    <div class="ls-testimonial-stars">⭐⭐⭐⭐⭐</div>
                    <p class="ls-testimonial-text">"Bolalarim uchun multfilmlarni topish juda oson. O'zbek tilida, sifatli va bemalol tomosha qilishlari mumkin. Rahmat UZDUB!"</p>
                    <div class="ls-testimonial-author">
                        <div class="ls-testimonial-avatar">N</div>
                        <div>
                            <div class="ls-testimonial-name">Nodira B.</div>
                            <div class="ls-testimonial-role">Ona va foydalanuvchi</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== PREMIUM ===== -->
    <section class="ls-premium">
        <div class="ls-wrap">
            <div class="ls-premium-glow"></div>
            <h2 class="ls-section-title ls-fade-up"><?php echo t('splash_premium_title'); ?></h2>
            <p class="ls-section-desc ls-fade-up"><?php echo t('splash_premium_desc'); ?></p>

            <div class="ls-plans">
                <?php foreach ($splash_plans as $plan): ?>
                <div class="ls-plan <?php echo !empty($plan['popular']) ? 'popular' : ''; ?>">
                    <?php if (!empty($plan['popular'])): ?>
                    <div class="ls-plan-badge"><?php echo t('splash_popular'); ?></div>
                    <?php endif; ?>
                    <div class="ls-plan-name"><?php echo t($plan['label_key']); ?></div>
                    <div class="ls-plan-price"><?php echo $plan['price']; ?> <span><?php echo t('splash_sum'); ?></span></div>
                    <ul class="ls-plan-features">
                        <?php foreach ($plan['features'] as $f): ?>
                        <li><?php echo t($f); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ===== STATISTIKA ===== -->
    <section class="ls-stats">
        <div class="ls-wrap">
            <div class="ls-stats-grid">
                <div class="ls-stat-item ls-fade-up">
                    <span class="ls-stat-num" id="lsStatContent">0</span>
                    <span class="ls-stat-label"><?php echo t('splash_stat_content'); ?></span>
                </div>
                <div class="ls-stat-item ls-fade-up">
                    <span class="ls-stat-num" id="lsStatUsers">0</span>
                    <span class="ls-stat-label"><?php echo t('splash_stat_users'); ?></span>
                </div>
                <div class="ls-stat-item ls-fade-up">
                    <span class="ls-stat-num" id="lsStatRating">0</span>
                    <span class="ls-stat-label"><?php echo t('splash_stat_rating'); ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== OXIRGI CTA ===== -->
    <section class="ls-final-cta">
        <div class="ls-wrap">
            <div class="ls-final-cta-card ls-fade-up">
                <div class="ls-final-cta-glow"></div>
                <h2>Boshlashga tayyormisiz?</h2>
                <p>Hozir ro'yxatdan o'ting va birinchi qadamni bosing. Barchasi bepul!</p>
                <div class="ls-btn-group" style="margin-top:24px;">
                    <?php if (!$splash_user): ?>
                    <a href="/uzdub/auth/register.php" class="ls-btn ls-btn-primary">
                        🚀 Boshlash
                    </a>
                    <?php else: ?>
                    <button id="lsEnterBtnFinal" class="ls-btn ls-btn-primary">
                        🚀 Saytga kirish
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ===== FOOTER ===== -->
    <footer class="ls-footer">
        <div class="ls-wrap">
            <div class="ls-footer-brand">🎬 UZDUB PLATFORM</div>
            <p class="ls-footer-copy">&copy; <?php echo date('Y'); ?> UZDUB PLATFORM.UZ — <?php echo t('splash_footer_copy'); ?></p>
            <div class="ls-footer-links">
                <a href="/uzdub/auth/login.php"><?php echo t('login'); ?></a>
                <a href="/uzdub/auth/register.php"><?php echo t('register'); ?></a>
            </div>
        </div>
    </footer>

    <script>
    document.getElementById('lsEnterBtnBottom')?.addEventListener('click',function(){
        localStorage.setItem('uzdub_splash_seen','1');
        window.location.href='/uzdub/index.php';
    });
    var finalBtn=document.getElementById('lsEnterBtnFinal');
    if(finalBtn) finalBtn.addEventListener('click',function(){
        localStorage.setItem('uzdub_splash_seen','1');
        window.location.href='/uzdub/index.php';
    });
    </script>
    <script src="/uzdub/js/landing-splash.js"></script>
</body>
</html>
