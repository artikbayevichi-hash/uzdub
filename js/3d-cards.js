(function() {
    if (window._uzdub3DCardInitialized) return;
    window._uzdub3DCardInitialized = true;

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        window._uzdub3DCardInitialized = true;
        return;
    }

    var CONFIG = {
        tiltMax: 10,
        scaleHover: 1.045,
        scaleRest: 1.0,
        perspective: 1200,
        speed: 0.12,
        glareOpacity: 0.2,
        borderGlow: 'rgba(33, 150, 243, 0.7)',
        shadowBlur: 24,
        shadowColor: 'rgba(33, 150, 243, 0.4)'
    };

    function initCards() {
        var cards = document.querySelectorAll('.card');
        cards.forEach(function(card) {
            card.style.transformStyle = 'preserve-3d';
            card.style.willChange = 'transform';
            card.style.transition = 'transform 0.1s ease-out, box-shadow 0.35s ease, border-color 0.35s ease';

            card.addEventListener('mouseenter', onEnter);
            card.addEventListener('mousemove', onMove);
            card.addEventListener('mouseleave', onLeave);
            card.addEventListener('touchstart', onTouchStart, { passive: true });
            card.addEventListener('touchmove', onTouchMove, { passive: false });
            card.addEventListener('touchend', onTouchEnd);
        });
    }

    function onTouchStart(e) {
        var card = e.currentTarget;
        card.style.transition = 'transform 0.1s ease-out, box-shadow 0.35s ease, border-color 0.35s ease';
        card.style.zIndex = '50';
        card.style.boxShadow =
            '0 20px 50px rgba(0, 0, 0, 0.6), ' +
            '0 0 40px ' + CONFIG.shadowColor + ', ' +
            'inset 0 1px 0 rgba(255, 255, 255, 0.15)';
        card.style.borderColor = CONFIG.borderGlow;
        card.style.background = 'rgba(18, 26, 43, 0.7)';
    }

    function onTouchMove(e) {
        var card = e.currentTarget;
        var touch = e.touches[0];
        var rect = card.getBoundingClientRect();
        var x = touch.clientX - rect.left;
        var y = touch.clientY - rect.top;
        var centerX = rect.width / 2;
        var centerY = rect.height / 2;

        var rotateX = ((y - centerY) / centerY) * -CONFIG.tiltMax;
        var rotateY = ((x - centerX) / centerX) * CONFIG.tiltMax;

        var glareX = (x / rect.width) * 100;
        var glareY = (y / rect.height) * 100;

        card.style.transform =
            'perspective(' + CONFIG.perspective + 'px) ' +
            'rotateX(' + rotateX + 'deg) ' +
            'rotateY(' + rotateY + 'deg) ' +
            'scale(' + CONFIG.scaleHover + ') ' +
            'translateY(-8px)';

        card.style.setProperty('--glare-x', glareX + '%');
        card.style.setProperty('--glare-y', glareY + '%');
    }

    function onTouchEnd(e) {
        var card = e.currentTarget;
        card.style.transition = 'transform 0.5s ease-out, box-shadow 0.5s ease, border-color 0.5s ease';
        card.style.transform =
            'perspective(' + CONFIG.perspective + 'px) ' +
            'rotateX(0deg) rotateY(0deg) ' +
            'scale(' + CONFIG.scaleRest + ') translateY(0px)';
        card.style.zIndex = '';
        card.style.boxShadow = '';
        card.style.borderColor = '';
        card.style.background = '';
        card.style.setProperty('--glare-x', '50%');
        card.style.setProperty('--glare-y', '30%');
    }

    function onEnter(e) {
        var card = e.currentTarget;
        card.style.transition = 'transform 0.1s ease-out, box-shadow 0.35s ease, border-color 0.35s ease';
        card.style.zIndex = '50';
        card.style.boxShadow =
            '0 20px 50px rgba(0, 0, 0, 0.6), ' +
            '0 0 40px ' + CONFIG.shadowColor + ', ' +
            'inset 0 1px 0 rgba(255, 255, 255, 0.15)';
        card.style.borderColor = CONFIG.borderGlow;
        card.style.background = 'rgba(18, 26, 43, 0.7)';
    }

    function onMove(e) {
        var card = e.currentTarget;
        var rect = card.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;
        var centerX = rect.width / 2;
        var centerY = rect.height / 2;

        var rotateX = ((y - centerY) / centerY) * -CONFIG.tiltMax;
        var rotateY = ((x - centerX) / centerX) * CONFIG.tiltMax;

        var glareX = (x / rect.width) * 100;
        var glareY = (y / rect.height) * 100;

        card.style.transform =
            'perspective(' + CONFIG.perspective + 'px) ' +
            'rotateX(' + rotateX + 'deg) ' +
            'rotateY(' + rotateY + 'deg) ' +
            'scale(' + CONFIG.scaleHover + ') ' +
            'translateY(-8px)';

        card.style.setProperty('--glare-x', glareX + '%');
        card.style.setProperty('--glare-y', glareY + '%');
    }

    function onLeave(e) {
        var card = e.currentTarget;
        card.style.transition = 'transform 0.5s ease-out, box-shadow 0.5s ease, border-color 0.5s ease';
        card.style.transform =
            'perspective(' + CONFIG.perspective + 'px) ' +
            'rotateX(0deg) rotateY(0deg) ' +
            'scale(' + CONFIG.scaleRest + ') translateY(0px)';
        card.style.zIndex = '';
        card.style.boxShadow = '';
        card.style.borderColor = '';
        card.style.background = '';
        card.style.setProperty('--glare-x', '50%');
        card.style.setProperty('--glare-y', '30%');
    }

    function enhanceCardStyles() {
        var style = document.createElement('style');
        style.id = 'uzdub-3d-card-styles';
        style.textContent = `
            .card {
                position: relative;
                transform-style: preserve-3d;
                will-change: transform;
            }
            .card::before {
                content: '';
                position: absolute;
                inset: 0;
                background: radial-gradient(
                    circle at var(--glare-x, 50%) var(--glare-y, 30%),
                    rgba(255, 255, 255, 0.22) 0%,
                    rgba(255, 255, 255, 0.05) 35%,
                    rgba(79, 195, 247, 0.15) 100%
                );
                z-index: 2;
                pointer-events: none;
                border-radius: 14px;
                transition: background 0.15s ease-out;
                opacity: 0;
            }
            .card:hover::before {
                opacity: 1;
            }
            .card img {
                transform: translateZ(20px);
                transition: transform 0.35s ease;
            }
            .card:hover img {
                transform: translateZ(35px) scale(1.03);
            }
            .card-info {
                transform: translateZ(25px);
                transition: transform 0.35s ease;
            }
            .card:hover .card-info {
                transform: translateZ(40px);
            }
            .card h3 {
                transform: translateZ(30px);
                transition: transform 0.35s ease;
            }
            .card:hover h3 {
                transform: translateZ(45px);
            }
            .card .meta {
                transform: translateZ(22px);
                transition: transform 0.35s ease;
            }
            .card:hover .meta {
                transform: translateZ(38px);
            }
            @media (hover: none) {
                .card:hover { transform: none !important; }
                .card::before { display: none; }
            }
        `;
        
        var existing = document.getElementById('uzdub-3d-card-styles');
        if (existing) existing.remove();
        document.head.appendChild(style);
    }

    function init() {
        enhanceCardStyles();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initCards);
        } else {
            initCards();
        }

        var observer = new MutationObserver(function() {
            initCards();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    init();
})();
