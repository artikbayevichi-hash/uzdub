(function() {
    if (window._uzdub3DAnimInitialized) return;
    window._uzdub3DAnimInitialized = true;

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window._uzdub3DAnimInitialized = true;
        return;
    }

    var CONFIG = {
        staggerDelay: 80,
        duration: 700,
        initialY: 40,
        initialOpacity: 0
    };

    function initEntranceAnimations() {
        var sections = document.querySelectorAll('.content-section');
        var cards = document.querySelectorAll('.card');

        sections.forEach(function(section, index) {
            section.style.opacity = CONFIG.initialOpacity;
            section.style.transform = 'translateY(' + CONFIG.initialY + 'px)';
            section.style.transition = 'opacity ' + CONFIG.duration + 'ms ease-out, transform ' + CONFIG.duration + 'ms ease-out';
            section.style.transitionDelay = (index * CONFIG.staggerDelay) + 'ms';
        });

        cards.forEach(function(card, index) {
            if (card.closest('.hero-carousel')) return;
            card.style.opacity = CONFIG.initialOpacity;
            card.style.transform = 'translateY(30px) scale(0.95)';
            card.style.transition = 'opacity 500ms ease-out, transform 500ms ease-out';
            card.style.transitionDelay = Math.min(index * 30, 500) + 'ms';
        });

        triggerEntrance();
    }

    function triggerEntrance() {
        setTimeout(function() {
            var sections = document.querySelectorAll('.content-section');
            sections.forEach(function(section) {
                section.style.opacity = '1';
                section.style.transform = 'translateY(0)';
            });

            var cards = document.querySelectorAll('.card');
            cards.forEach(function(card, index) {
                if (card.closest('.hero-carousel')) return;
                setTimeout(function() {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0) scale(1)';
                }, Math.min(index * 30, 500));
            });
        }, 100);
    }

    function initScrollAnimations() {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0) scale(1)';
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.15,
            rootMargin: '0px 0px -50px 0px'
        });

        document.querySelectorAll('.card').forEach(function(card) {
            observer.observe(card);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initEntranceAnimations();
            initScrollAnimations();
        });
    } else {
        initEntranceAnimations();
        initScrollAnimations();
    }
})();
