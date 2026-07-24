<?php
$splash_plans = [
    ['label' => '1 Oy', 'price' => '10 000', 'days' => 30, 'features' => ['HD sifat', 'Cheklovsiz tomosha', 'Premium kontent']],
    ['label' => '3 Oy', 'price' => '25 000', 'days' => 90, 'features' => ['HD sifat', 'Cheklovsiz tomosha', 'Premium kontent', '⭐ Mashhur'], 'popular' => true],
    ['label' => '1 Yil', 'price' => '80 000', 'days' => 365, 'features' => ['HD sifat', 'Cheklovsiz tomosha', 'Premium kontent', 'Engfoydali']],
];
$splash_user = is_user() ? current_user() : null;
$splash_user_json = $splash_user ? json_encode([
    'user_id'     => $splash_user['user_id'],
    'username'    => $splash_user['username'],
    'avatar'      => avatar_url($splash_user['avatar']),
    'is_premium'  => !empty($splash_user['is_premium']),
    'switch_token'=> $splash_user['switch_token'] ?? '',
]) : '';
?>
<div id="splash-screen" class="splash-screen" <?php if ($splash_user_json): ?>data-user="<?php echo htmlspecialchars($splash_user_json, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
    <div class="splash-bg-particles" id="splashParticles"></div>
    <div class="splash-content">

        <div class="splash-hero">
            <div class="splash-logo-wrap">
                <div class="splash-logo-ring"></div>
                <div class="splash-logo-ring ring-2"></div>
                <div class="splash-logo-ring ring-3"></div>
                <span class="splash-logo">🎬 UZDUB PLATFORM</span>
            </div>
            <h1 class="splash-title">
                <span class="splash-line line-1">Kino, Anime</span>
                <span class="splash-line line-2">Multfilmlar</span>
                <span class="splash-line line-3">O'zbek tilida</span>
            </h1>
            <p class="splash-subtitle">Barcha sevimli kontentlaringiz bir joyda. Sifatli dublyaj bilan.</p>

            <?php if ($splash_user): ?>
            <div class="splash-user-card">
                <a href="/uzdub/profile.php?uid=<?php echo e($splash_user['user_id']); ?>" class="splash-user-link">
                    <img src="<?php echo avatar_url($splash_user['avatar']); ?>" alt="" class="splash-user-avatar">
                    <div class="splash-user-info">
                        <span class="splash-user-name"><?php echo e($splash_user['username']); ?></span>
                        <?php if ($splash_user['is_premium']): ?>
                        <span class="splash-user-premium">⭐ Premium</span>
                        <?php endif; ?>
                    </div>
                </a>
            </div>

            <div class="splash-accounts-section">
                <div class="splash-accounts-list" style="display:none;"></div>
                <a href="/uzdub/auth/login.php" class="splash-add-account-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    Akkaunt qo'shish
                </a>
            </div>

            <?php else: ?>
            <div class="splash-buttons">
                <a href="/uzdub/auth/login.php" class="splash-btn splash-btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    <?php echo t('login'); ?>
                </a>
                <a href="/uzdub/auth/register.php" class="splash-btn splash-btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                    <?php echo t('register'); ?>
                </a>
            </div>
            <?php endif; ?>

            <button id="splashSkip" class="splash-skip" onclick="dismissSplash()">
                Saytga kirish
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </div>

        <div class="splash-features">
            <div class="splash-feature-card">
                <div class="splash-feature-icon">🎬</div>
                <h3>Kino</h3>
                <p>Eng so'nggi kino va filmlar o'zbek tilida</p>
            </div>
            <div class="splash-feature-card">
                <div class="splash-feature-icon">🎌</div>
                <h3>Anime</h3>
                <p>Sevimli animelaringiz sifatli dublyaj bilan</p>
            </div>
            <div class="splash-feature-card">
                <div class="splash-feature-icon">🤖</div>
                <h3>AI Yordamchi</h3>
                <p>AI bilan kino tavsiyalar oling</p>
            </div>
        </div>

        <div class="splash-premium-section">
            <h2 class="splash-section-title">⭐ Premium obuna</h2>
            <p class="splash-section-desc">Premium bilan barcha kontentlarga cheklovsiz kirish</p>
            <div class="splash-plans-grid">
                <?php foreach ($splash_plans as $plan): ?>
                <div class="splash-plan-card <?php echo !empty($plan['popular']) ? 'popular' : ''; ?>">
                    <?php if (!empty($plan['popular'])): ?>
                    <div class="splash-popular-badge">Mashhur</div>
                    <?php endif; ?>
                    <div class="splash-plan-name"><?php echo $plan['label']; ?></div>
                    <div class="splash-plan-price"><?php echo $plan['price']; ?> <span>so'm</span></div>
                    <ul class="splash-plan-features">
                        <?php foreach ($plan['features'] as $f): ?>
                        <li>✓ <?php echo $f; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="splash-about">
            <h2 class="splash-section-title">UZDUB PLATFORM haqida</h2>
            <p>UZDUB PLATFORM — bu kino, anime va multfilmlarni o'zbek tilida tomosha qilish uchun platforma. Biz sifatli dublyaj va qulay interfeys bilan sizga xizmat ko'rsatamiz.</p>
            <div class="splash-stats">
                <div class="splash-stat">
                    <span class="splash-stat-num" id="statContent">500+</span>
                    <span class="splash-stat-label">Kontent</span>
                </div>
                <div class="splash-stat">
                    <span class="splash-stat-num" id="statUsers">10K+</span>
                    <span class="splash-stat-label">Foydalanuvchi</span>
                </div>
                <div class="splash-stat">
                    <span class="splash-stat-num" id="statRating">4.8</span>
                    <span class="splash-stat-label">Reyting</span>
                </div>
            </div>
        </div>

        <div class="splash-footer">
            <button class="splash-enter-btn" onclick="dismissSplash()">
                Saytga kirish
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
            <p class="splash-footer-text">&copy; <?php echo date('Y'); ?> UZDUB PLATFORM.UZ — Barcha huquqlar himoyalangan</p>
        </div>
    </div>
</div>
