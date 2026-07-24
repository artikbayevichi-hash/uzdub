<?php
$splash_plans = [
    ['label_key' => 'splash_plan_1m', 'price' => '10 000', 'days' => 30, 'features' => ['splash_hdfit', 'splash_unlimited', 'splash_premium_cont']],
    ['label_key' => 'splash_plan_3m', 'price' => '25 000', 'days' => 90, 'features' => ['splash_hdfit', 'splash_unlimited', 'splash_premium_cont', 'splash_popular'], 'popular' => true],
    ['label_key' => 'splash_plan_1y', 'price' => '80 000', 'days' => 365, 'features' => ['splash_hdfit', 'splash_unlimited', 'splash_premium_cont', 'splash_best']],
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
                <span class="splash-line line-1"><?php echo t('splash_line1'); ?></span>
                <span class="splash-line line-2"><?php echo t('splash_line2'); ?></span>
                <span class="splash-line line-3"><?php echo t('splash_line3'); ?></span>
            </h1>
            <p class="splash-subtitle"><?php echo t('splash_subtitle'); ?></p>

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
                    <?php echo t('splash_add_account'); ?>
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
                <?php echo t('splash_enter'); ?>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </div>

        <div class="splash-features">
            <div class="splash-feature-card">
                <div class="splash-feature-icon">🎬</div>
                <h3><?php echo t('splash_feature_kino'); ?></h3>
                <p><?php echo t('splash_feature_kino_desc'); ?></p>
            </div>
            <div class="splash-feature-card">
                <div class="splash-feature-icon">🎌</div>
                <h3><?php echo t('splash_feature_anime'); ?></h3>
                <p><?php echo t('splash_feature_anime_desc'); ?></p>
            </div>
            <div class="splash-feature-card">
                <div class="splash-feature-icon">🤖</div>
                <h3><?php echo t('splash_feature_ai'); ?></h3>
                <p><?php echo t('splash_feature_ai_desc'); ?></p>
            </div>
        </div>

        <div class="splash-premium-section">
            <h2 class="splash-section-title"><?php echo t('splash_premium_title'); ?></h2>
            <p class="splash-section-desc"><?php echo t('splash_premium_desc'); ?></p>
            <div class="splash-plans-grid">
                <?php foreach ($splash_plans as $plan): ?>
                <div class="splash-plan-card <?php echo !empty($plan['popular']) ? 'popular' : ''; ?>">
                    <?php if (!empty($plan['popular'])): ?>
                    <div class="splash-popular-badge"><?php echo t('splash_popular'); ?></div>
                    <?php endif; ?>
                    <div class="splash-plan-name"><?php echo t($plan['label_key']); ?></div>
                    <div class="splash-plan-price"><?php echo $plan['price']; ?> <span><?php echo t('splash_sum'); ?></span></div>
                    <ul class="splash-plan-features">
                        <?php foreach ($plan['features'] as $f): ?>
                        <li>✓ <?php echo t($f); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="splash-about">
            <h2 class="splash-section-title"><?php echo t('splash_about_title'); ?></h2>
            <p><?php echo t('splash_about_desc'); ?></p>
            <div class="splash-stats">
                <div class="splash-stat">
                    <span class="splash-stat-num" id="statContent">500+</span>
                    <span class="splash-stat-label"><?php echo t('splash_stat_content'); ?></span>
                </div>
                <div class="splash-stat">
                    <span class="splash-stat-num" id="statUsers">10K+</span>
                    <span class="splash-stat-label"><?php echo t('splash_stat_users'); ?></span>
                </div>
                <div class="splash-stat">
                    <span class="splash-stat-num" id="statRating">4.8</span>
                    <span class="splash-stat-label"><?php echo t('splash_stat_rating'); ?></span>
                </div>
            </div>
        </div>

        <div class="splash-footer">
            <button class="splash-enter-btn" onclick="dismissSplash()">
                <?php echo t('splash_enter'); ?>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
            <p class="splash-footer-text">&copy; <?php echo date('Y'); ?> UZDUB PLATFORM.UZ — <?php echo t('splash_footer_copy'); ?></p>
        </div>
    </div>
</div>
