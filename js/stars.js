// ================================================================
// UZDUB — 3D ko'p qatlamli yulduzlar foni
// Uch xil chuqurlikdagi qatlam (uzoq/o'rta/yaqin) + parallaks (sichqoncha
// va gyroskop asosida) + vaqti-vaqti bilan uchib o'tuvchi "otilma yulduz"
// haqiqiy 3D chuqurlik hissiyotini beradi.
// ================================================================
(function () {
    const canvas = document.getElementById('stars-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let w, h, dpr;
    let layers = [];
    let shootingStars = [];
    let mouseX = 0, mouseY = 0;      // -1..1 oralig'ida normallashtirilgan
    let targetX = 0, targetY = 0;
    const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Chuqurlik qatlamlari: uzoqdagi yulduzlar kichik/sekin, yaqindagilari katta/tez —
    // shu farq parallaks bilan birga ko'zga 3D bo'lib ko'rinadi
    const LAYER_CONFIG = [
        { count: 90,  minR: 0.4, maxR: 1.0, speed: 0.015, parallax: 6,  alphaMax: 0.55, color: '150,180,215' },
        { count: 55,  minR: 0.8, maxR: 1.7, speed: 0.035, parallax: 16, alphaMax: 0.75, color: '170,200,235' },
        { count: 28,  minR: 1.3, maxR: 2.6, speed: 0.07,  parallax: 32, alphaMax: 0.95, color: '200,225,255' }
    ];

    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        w = window.innerWidth;
        h = window.innerHeight;
        canvas.width = w * dpr;
        canvas.height = h * dpr;
        canvas.style.width = w + 'px';
        canvas.style.height = h + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    window.addEventListener('resize', resize);
    resize();

    function createLayers() {
        layers = LAYER_CONFIG.map(cfg => {
            const stars = [];
            for (let i = 0; i < cfg.count; i++) {
                stars.push({
                    x: Math.random() * w,
                    y: Math.random() * h,
                    r: Math.random() * (cfg.maxR - cfg.minR) + cfg.minR,
                    alpha: Math.random() * cfg.alphaMax * 0.6 + 0.1,
                    delta: (Math.random() * 0.01) + 0.003,
                    twinklePhase: Math.random() * Math.PI * 2
                });
            }
            return { cfg, stars };
        });
    }
    createLayers();

    function maybeSpawnShootingStar() {
        if (reduceMotion) return;
        if (Math.random() < 0.0035 && shootingStars.length < 2) {
            const startX = Math.random() * w * 0.6 + w * 0.2;
            shootingStars.push({
                x: startX,
                y: -10,
                vx: -3.2 - Math.random() * 2,
                vy: 3.2 + Math.random() * 2,
                len: 90 + Math.random() * 60,
                life: 1
            });
        }
    }

    // Sichqoncha holatiga qarab qatlamlarni siljitib, 3D chuqurlik ta'siri yaratadi
    window.addEventListener('mousemove', function (e) {
        targetX = (e.clientX / w) * 2 - 1;
        targetY = (e.clientY / h) * 2 - 1;
    });
    // Mobil qurilmalarda giroskop orqali xuddi shunday effekt
    window.addEventListener('deviceorientation', function (e) {
        if (e.gamma == null || e.beta == null) return;
        targetX = Math.max(-1, Math.min(1, e.gamma / 30));
        targetY = Math.max(-1, Math.min(1, (e.beta - 40) / 30));
    });

    function draw() {
        ctx.clearRect(0, 0, w, h);

        // Sichqoncha siljishini yumshoq (lerp) qilib kuzatish — keskin sakrashsiz
        mouseX += (targetX - mouseX) * 0.04;
        mouseY += (targetY - mouseY) * 0.04;

        for (const layer of layers) {
            const { cfg, stars } = layer;
            const offX = mouseX * cfg.parallax;
            const offY = mouseY * cfg.parallax;

            for (const s of stars) {
                s.twinklePhase += s.delta;
                const twinkle = (Math.sin(s.twinklePhase) + 1) / 2;
                const alpha = Math.max(0.05, Math.min(cfg.alphaMax, s.alpha + twinkle * 0.3));

                const drawX = s.x + offX;
                const drawY = s.y + offY;

                ctx.beginPath();
                ctx.arc(drawX, drawY, s.r, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(${cfg.color}, ${alpha})`;
                ctx.shadowColor = `rgba(79,195,247,${0.4 * twinkle})`;
                ctx.shadowBlur = s.r * 2.2;
                ctx.fill();

                if (!reduceMotion) {
                    s.y += cfg.speed;
                    s.x -= cfg.speed * 0.15;
                    if (s.y > h + 5) { s.y = -5; s.x = Math.random() * w; }
                    if (s.x < -5) { s.x = w + 5; }
                }
            }
        }

        // Otilma yulduzlar
        maybeSpawnShootingStar();
        shootingStars = shootingStars.filter(function (st) {
            const grad = ctx.createLinearGradient(st.x, st.y, st.x - st.vx * (st.len / 4), st.y - st.vy * (st.len / 4));
            grad.addColorStop(0, `rgba(255,255,255,${st.life})`);
            grad.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.strokeStyle = grad;
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.moveTo(st.x, st.y);
            ctx.lineTo(st.x - st.vx * (st.len / 4), st.y - st.vy * (st.len / 4));
            ctx.stroke();

            st.x += st.vx * 4;
            st.y += st.vy * 4;
            st.life -= 0.02;
            return st.life > 0 && st.y < h + 50;
        });

        requestAnimationFrame(draw);
    }
    draw();
})();
