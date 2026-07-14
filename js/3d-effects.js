(function() {
    if (window._uzdub3DInitialized) return;
    window._uzdub3DInitialized = true;

    var CONFIG = {
        particleCount: 1200,
        connectionDistance: 100,
        mouseInfluence: 60,
        waveSpeed: 0.0004,
        waveAmplitude: 25,
        cameraZ: 400,
        fov: 60,
        colors: {
            primary: 0x2196f3,
            secondary: 0x4fc3f7,
            deep: 0x0d47a1,
            white: 0xffffff,
            bg: 0x0a0e17
        }
    };

    var mouse = { x: 0, y: 0, targetX: 0, targetY: 0 };
    var scene, camera, renderer, particles, particleSystem, connectionsMesh;
    var animationId;
    var clock = new THREE.Clock();
    var canvas3d;

    function init() {
        var isMobileDevice = window.innerWidth < 768 || ('ontouchstart' in window && window.innerWidth < 1024);
        var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Mobil va sensorli qurilmalarda og'ir WebGL zarrachalar foniga
        // ehtiyoj yo'q — batareya va tezlikni tejash uchun yengil fon (yulduzlar/orb)
        // bilan cheklanamiz, bu foydalanuvchi tajribasini yaxshilaydi.
        if (isMobileDevice || reducedMotion) return;

        CONFIG.particleCount = 500;
        CONFIG.connectionDistance = 70;
        canvas3d = document.createElement('canvas');
        canvas3d.id = 'bg-3d-canvas';
        canvas3d.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;';
        
        var existing = document.getElementById('bg-3d-canvas');
        if (existing) existing.remove();
        
        document.body.prepend(canvas3d);

        scene = new THREE.Scene();
        scene.fog = new THREE.FogExp2(CONFIG.colors.bg, 0.00025);

        camera = new THREE.PerspectiveCamera(CONFIG.fov, window.innerWidth / window.innerHeight, 1, 2000);
        camera.position.z = CONFIG.cameraZ;

        renderer = new THREE.WebGLRenderer({ canvas: canvas3d, alpha: true, antialias: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setClearColor(0x000000, 0);

        createParticles();
        createConnections();
        createAmbientLights();

        window.addEventListener('resize', onResize);
        window.addEventListener('mousemove', onMouseMove);
        // Sahifa fonda bo'lganda animatsiyani to'xtatib, protsessor/batareyani tejash
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (animationId) cancelAnimationFrame(animationId);
            } else {
                animate();
            }
        });

        animate();
    }

    function createParticles() {
        var geometry = new THREE.BufferGeometry();
        var positions = new Float32Array(CONFIG.particleCount * 3);
        var velocities = new Float32Array(CONFIG.particleCount * 3);
        var colors = new Float32Array(CONFIG.particleCount * 3);
        var sizes = new Float32Array(CONFIG.particleCount);

        var colorPrimary = new THREE.Color(CONFIG.colors.primary);
        var colorSecondary = new THREE.Color(CONFIG.colors.secondary);
        var colorWhite = new THREE.Color(CONFIG.colors.white);

        for (var i = 0; i < CONFIG.particleCount; i++) {
            var i3 = i * 3;

            positions[i3] = (Math.random() - 0.5) * 1200;
            positions[i3 + 1] = (Math.random() - 0.5) * 800;
            positions[i3 + 2] = (Math.random() - 0.5) * 600;

            velocities[i3] = (Math.random() - 0.5) * 0.3;
            velocities[i3 + 1] = (Math.random() - 0.5) * 0.3;
            velocities[i3 + 2] = (Math.random() - 0.5) * 0.2;

            var colorChoice = Math.random();
            var chosenColor;
            if (colorChoice < 0.4) chosenColor = colorPrimary;
            else if (colorChoice < 0.7) chosenColor = colorSecondary;
            else chosenColor = colorWhite;

            colors[i3] = chosenColor.r;
            colors[i3 + 1] = chosenColor.g;
            colors[i3 + 2] = chosenColor.b;

            sizes[i] = Math.random() * 2.5 + 0.5;
        }

        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
        geometry.setAttribute('size', new THREE.BufferAttribute(sizes, 1));

        var material = new THREE.PointsMaterial({
            size: 2,
            vertexColors: true,
            transparent: true,
            opacity: 0.85,
            blending: THREE.AdditiveBlending,
            depthWrite: false,
            sizeAttenuation: true
        });

        particleSystem = new THREE.Points(geometry, material);
        particleSystem._velocities = velocities;
        scene.add(particleSystem);
    }

    function createConnections() {
        var geometry = new THREE.BufferGeometry();
        var maxConnections = 400;
        var positions = new Float32Array(maxConnections * 6);
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setDrawRange(0, 0);

        var material = new THREE.LineBasicMaterial({
            color: CONFIG.colors.primary,
            transparent: true,
            opacity: 0.15,
            blending: THREE.AdditiveBlending,
            depthWrite: false
        });

        connectionsMesh = new THREE.LineSegments(geometry, material);
        connectionsMesh._positions = positions;
        connectionsMesh._maxConnections = maxConnections;
        scene.add(connectionsMesh);
    }

    function createAmbientLights() {
        var light1 = new THREE.PointLight(CONFIG.colors.primary, 2, 800);
        light1.position.set(200, 150, 200);
        scene.add(light1);

        var light2 = new THREE.PointLight(CONFIG.colors.secondary, 1.5, 600);
        light2.position.set(-200, -100, 150);
        scene.add(light2);
    }

    function updateConnections() {
        if (!particleSystem || !connectionsMesh) return;

        var positions = particleSystem.geometry.attributes.position.array;
        var connectionPositions = connectionsMesh._positions;
        var maxConn = connectionsMesh._maxConnections;
        var connCount = 0;
        var checked = 0;
        var maxChecked = Math.min(positions.length / 3, 200);

        for (var i = 0; i < maxChecked && connCount < maxConn; i++) {
            var i3 = i * 3;
            var x1 = positions[i3];
            var y1 = positions[i3 + 1];
            var z1 = positions[i3 + 2];

            for (var j = i + 1; j < maxChecked && connCount < maxConn; j++) {
                var j3 = j * 3;
                var dx = x1 - positions[j3];
                var dy = y1 - positions[j3 + 1];
                var dz = z1 - positions[j3 + 2];
                var dist = Math.sqrt(dx * dx + dy * dy + dz * dz);

                if (dist < CONFIG.connectionDistance) {
                    var c = connCount * 6;
                    connectionPositions[c] = x1;
                    connectionPositions[c + 1] = y1;
                    connectionPositions[c + 2] = z1;
                    connectionPositions[c + 3] = positions[j3];
                    connectionPositions[c + 4] = positions[j3 + 1];
                    connectionPositions[c + 5] = positions[j3 + 2];
                    connCount++;
                }
            }
        }

        connectionsMesh.geometry.attributes.position.needsUpdate = true;
        connectionsMesh.geometry.setDrawRange(0, connCount * 2);
    }

    function animate() {
        animationId = requestAnimationFrame(animate);

        var delta = clock.getDelta();
        var time = performance.now() * 0.001;

        mouse.x += (mouse.targetX - mouse.x) * 0.05;
        mouse.y += (mouse.targetY - mouse.y) * 0.05;

        if (particleSystem) {
            var positions = particleSystem.geometry.attributes.position.array;
            var velocities = particleSystem._velocities;

            for (var i = 0; i < positions.length; i += 3) {
                positions[i] += velocities[i] * 0.4 + Math.sin(time * 0.5 + i) * 0.02;
                positions[i + 1] += velocities[i + 1] * 0.4 + Math.cos(time * 0.3 + i) * 0.02;
                positions[i + 2] += velocities[i + 2] * 0.2;

                if (Math.abs(positions[i]) > 600) velocities[i] *= -1;
                if (Math.abs(positions[i + 1]) > 400) velocities[i + 1] *= -1;
                if (Math.abs(positions[i + 2]) > 300) velocities[i + 2] *= -1;
            }

            particleSystem.geometry.attributes.position.needsUpdate = true;
            particleSystem.rotation.y += 0.0002;
            particleSystem.rotation.x += Math.sin(time * 0.15) * 0.0001;
        }

        if (camera && !isMobile()) {
            camera.position.x += (mouse.x * CONFIG.mouseInfluence - camera.position.x) * 0.03;
            camera.position.y += (-mouse.y * CONFIG.mouseInfluence - camera.position.y) * 0.03;
            camera.lookAt(scene.position);
        }

        updateConnections();
        renderer.render(scene, camera);
    }

    function onResize() {
        if (!camera || !renderer) return;
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    }

    function onMouseMove(e) {
        mouse.targetX = (e.clientX / window.innerWidth) * 2 - 1;
        mouse.targetY = (e.clientY / window.innerHeight) * 2 - 1;
    }

    function isMobile() {
        return window.innerWidth < 768 || ('ontouchstart' in window);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.UZDUB3D = {
        destroy: function() {
            if (animationId) cancelAnimationFrame(animationId);
            if (particleSystem) scene.remove(particleSystem);
            if (connectionsMesh) scene.remove(connectionsMesh);
            if (renderer) renderer.dispose();
            if (canvas3d) canvas3d.remove();
            window._uzdub3DInitialized = false;
        }
    };
})();
