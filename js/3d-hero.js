(function() {
    if (window._uzdub3DHeroInitialized) return;
    window._uzdub3DHeroInitialized = true;

    var CONFIG = {
        parallaxStrength: 20,
        contentTilt: 8,
        speed: 0.06,
        perspective: 1500,
        mobileParallaxStrength: 8,
        isMobile: false
    };

    var heroSection, heroContent, heroSlides;
    var currentX = 0, currentY = 0;
    var targetX = 0, targetY = 0;
    var rafId;

    function init() {
        heroSection = document.querySelector('.hero-carousel');
        if (!heroSection) return;

        CONFIG.isMobile = window.innerWidth < 768 || ('ontouchstart' in window && window.innerWidth < 1024);

        heroContent = document.querySelector('.hero-content');
        heroSlides = document.querySelectorAll('.hero-slide');

        heroSection.style.transformStyle = 'preserve-3d';
        heroSection.style.perspective = CONFIG.perspective + 'px';

        if (heroContent) {
            heroContent.style.transformStyle = 'preserve-3d';
            heroContent.style.willChange = 'transform';
            heroContent.style.transition = 'transform 0.1s ease-out';
        }

        heroSlides.forEach(function(slide) {
            slide.style.transformStyle = 'preserve-3d';
            slide.style.transition = 'opacity 1.1s ease-in-out, transform 6s ease-out';
        });

        if (!CONFIG.isMobile) {
            window.addEventListener('mousemove', onMouseMove);
        }
        animate();
    }

    function onMouseMove(e) {
        if (CONFIG.isMobile) return;
        targetX = ((e.clientX / window.innerWidth) * 2 - 1) * CONFIG.parallaxStrength;
        targetY = ((e.clientY / window.innerHeight) * 2 - 1) * CONFIG.parallaxStrength;
    }

    function animate() {
        currentX += (targetX - currentX) * CONFIG.speed;
        currentY += (targetY - currentY) * CONFIG.speed;

        if (heroContent) {
            var strength = CONFIG.isMobile ? CONFIG.mobileParallaxStrength : CONFIG.parallaxStrength;
            var rotateY = currentX * 0.03;
            var rotateX = -currentY * 0.03;

            heroContent.style.transform =
                'translateZ(30px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        }

        heroSlides.forEach(function(slide, index) {
            if (!slide.classList.contains('active')) return;
            var strength = CONFIG.isMobile ? CONFIG.mobileParallaxStrength * 0.5 : CONFIG.parallaxStrength;
            slide.style.backgroundPositionX = 'calc(50% + ' + currentX * 0.3 + 'px)';
            slide.style.backgroundPositionY = 'calc(50% + ' + currentY * 0.3 + 'px)';
        });

        rafId = requestAnimationFrame(animate);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
