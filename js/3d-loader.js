(function() {
    if (window._uzdub3DLoaderInitialized) return;
    window._uzdub3DLoaderInitialized = true;

    // Foydalanuvchi bir marta ko'rgandan keyin har bir sahifada qayta-qayta
    // to'liq ekranli yuklanish animatsiyasini ko'rsatib bezovta qilmaslik uchun
    var alreadyShown = sessionStorage.getItem('uzdub_loader_shown') === '1';
    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (alreadyShown || reducedMotion) return;
    sessionStorage.setItem('uzdub_loader_shown', '1');

    var isMobile = window.innerWidth < 768;
    var loaderSize = isMobile ? '50px' : '80px';
    var loaderFontSize = isMobile ? '18px' : '24px';

    var loader = document.createElement('div');
    loader.id = 'uzdub-3d-loader';
    loader.innerHTML = `
        <div style="
            position: fixed;
            inset: 0;
            background: radial-gradient(ellipse at center, #0f1830 0%, #05070d 100%);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        ">
            <div style="
                width: ${loaderSize};
                height: ${loaderSize};
                border: 4px solid rgba(33, 150, 243, 0.2);
                border-top-color: #2196f3;
                border-radius: 50%;
                animation: spin3d 1s linear infinite;
                box-shadow: 0 0 30px rgba(33, 150, 243, 0.5), inset 0 0 20px rgba(33, 150, 243, 0.3);
                transform-style: preserve-3d;
                perspective: 800px;
            "></div>
            <div style="
                margin-top: 20px;
                color: #4fc3f7;
                font-size: ${loaderFontSize};
                font-weight: 900;
                letter-spacing: 2px;
                text-shadow: 0 0 20px rgba(79, 195, 247, 0.8);
                animation: pulseText 1.5s ease-in-out infinite;
            ">UZDUB PLATFORM</div>
        </div>
        <style>
            @keyframes spin3d {
                0% { transform: rotateX(45deg) rotateZ(0deg); }
                100% { transform: rotateX(45deg) rotateZ(360deg); }
            }
            @keyframes pulseText {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.7; transform: scale(1.05); }
            }
        </style>
    `;
    document.body.prepend(loader);

    window.addEventListener('load', function() {
        setTimeout(function() {
            loader.style.opacity = '0';
            loader.style.visibility = 'hidden';
            setTimeout(function() {
                loader.remove();
            }, 400);
        }, 350);
    });
})();
