/* ============================================================
   js/player.js - UZDUB custom video player (Telegram Desktop style)
   Vanilla JS. Elementi: <div class="udp-player" data-udp data-udp-config='{...}'>
   ============================================================ */

window.ROOT_URL = window.ROOT_URL || '/uzdub';

(function () {
    'use strict';

    var ICONS = {
        play: '<svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>',
        pause: '<svg viewBox="0 0 24 24"><path d="M6 4h4v16H6zM14 4h4v16h-4z"/></svg>',
        volOn: '<svg viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3z"/><path d="M16.5 12a4.5 4.5 0 0 0-2.5-4v8a4.5 4.5 0 0 0 2.5-4z"/></svg>',
        volMute: '<svg viewBox="0 0 24 24"><path d="M3 9v6h4l5 5V4L7 9H3z"/><path d="M16.5 12 20 8.5M20 15.5 16.5 12"/></svg>',
        pip: '<svg viewBox="0 0 24 24"><path d="M2 5a2 2 0 0 1 2-2h7v2H4v14h16v-7h2v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5z"/><path d="M8 11v6l4-3z"/></svg>',
        fs: '<svg viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zM5 10h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>',
        fsExit: '<svg viewBox="0 0 24 24"><path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/></svg>',
        cinema: '<svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M2 9v6M22 9v6"/></svg>',
        cinemaOff: '<svg viewBox="0 0 24 24"><path d="M4 6h16v12H4z" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
        gear: '<svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.61-.22l-2.39.96a7.3 7.3 0 0 0-1.62-.94l-.36-2.54a.5.5 0 0 0-.5-.42h-3.84a.5.5 0 0 0-.5.42l-.36 2.54c-.58.24-1.12.56-1.62.94l-2.39-.96a.5.5 0 0 0-.61.22L2.74 8.87a.5.5 0 0 0 .12.64l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.5.5 0 0 0-.12.64l1.92 3.32c.13.23.39.31.61.22l2.39-.96c.5.38 1.04.7 1.62.94l.36 2.54c.04.24.25.42.5.42h3.84c.25 0 .46-.18.5-.42l.36-2.54c.58-.24 1.12-.56 1.62-.94l2.39.96c.22.08.48 0 .61-.22l1.92-3.32a.5.5 0 0 0-.12-.64l-2.03-1.58zM12 15.6A3.6 3.6 0 1 1 12 8.4a3.6 3.6 0 0 1 0 7.2z"/></svg>',
        cc: '<svg viewBox="0 0 24 24"><path d="M20 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zM8.5 14.8c-2.9 0-2.9-5.6 0-5.6 1.3 0 2.1.6 2.7 1.4l-1.5 1c-.3-.5-.6-.8-1.2-.8-.9 0-.9 2.4 0 2.4.6 0 .9-.3 1.2-.8l1.5 1c-.6.8-1.4 1.4-2.7 1.4zm7 0c-2.9 0-2.9-5.6 0-5.6 1.3 0 2.1.6 2.7 1.4l-1.5 1c-.3-.5-.6-.8-1.2-.8-.9 0-.9 2.4 0 2.4.6 0 .9-.3 1.2-.8l1.5 1c-.6.8-1.4 1.4-2.7 1.4z"/></svg>',
        replay: '<svg viewBox="0 0 24 24"><path d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/></svg>',
        replay10: '<svg viewBox="0 0 24 24"><path d="M11.99 5V1l-5 5 5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6h-2c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z" fill="none"/><text x="9" y="16" font-size="9" font-weight="700" fill="currentColor" font-family="sans-serif">10</text></svg>',
        forward10: '<svg viewBox="0 0 24 24"><path d="M12.01 5V1l5 5-5 5V7c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6h2c0 4.42-3.58 8-8 8s-8-3.58-8-8 3.58-8 8-8z" fill="none"/><text x="7.5" y="16" font-size="9" font-weight="700" fill="currentColor" font-family="sans-serif">10</text></svg>',
        aspect: '<svg viewBox="0 0 24 24"><path d="M3 5v4h2V5h4V3H5a2 2 0 0 0-2 2zm2 10H3v4a2 2 0 0 0 2 2h4v-2H5v-4zm14 4h-4v2h4a2 2 0 0 0 2-2v-4h-2v4zm0-16h-4v2h4v4h2V5a2 2 0 0 0-2-2z"/></svg>'
    };

    function parseConfig(root) {
        try {
            return JSON.parse(root.dataset.udpConfig || '{}');
        } catch (e) {
            return {};
        }
    }

    function fmtTime(sec) {
        if (!isFinite(sec) || sec < 0) sec = 0;
        sec = Math.floor(sec);
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        var mm = m < 10 ? '0' + m : '' + m;
        var ss = s < 10 ? '0' + s : '' + s;
        return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
    }

    function el(tag, cls, html) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (html !== undefined) e.innerHTML = html;
        return e;
    }

    function initPlayer(root) {
        var video = root.querySelector('video');
        if (!video) return;

        var cfg = parseConfig(root);
        var S = cfg.strings || {};
        var isHls = !!(video.dataset.hls || video.dataset.hlsPending);
        var hls = null;

        root.classList.add('udp-init');

        /* ================= UI qurish ================= */
        // Buffering
        var buffering = el('div', 'udp-buffering');
        buffering.hidden = true;
        buffering.appendChild(el('div', 'udp-spinner'));
        var bufferingLabel = el('div', 'udp-buffering-label', S.loading || 'Video yuklanmoqda...');
        buffering.appendChild(bufferingLabel);
        root.appendChild(buffering);

        // Title bar
        var titlebar = el('div', 'udp-titlebar');
        if (cfg.title) {
            titlebar.appendChild(el('div', 'udp-titlebar-title', cfg.title));
        }
        if (cfg.next) {
            var nextBtn = el('a', 'udp-titlebar-next', '&#9654; ' + (cfg.next.label || S.next_ep || 'Keyingi qism'));
            nextBtn.href = cfg.next.href;
            titlebar.appendChild(nextBtn);
        }
        root.appendChild(titlebar);

        // Skip intro
        var skipWrap = el('div', 'udp-skip');
        skipWrap.hidden = true;
        var skipBtn = el('button', 'udp-skip-btn', '&#9197; ' + (S.skip_intro || 'Intro\u2019ni o\u2019tkazib yuborish'));
        skipBtn.type = 'button';
        skipWrap.appendChild(skipBtn);
        root.appendChild(skipWrap);

        // Resume overlay
        var resumeOv = el('div', 'udp-resume');
        resumeOv.hidden = true;
        var resumeBox = el('div', 'udp-resume-box');
        resumeBox.appendChild(el('div', 'udp-resume-title', S.resume_title || 'Davom etasizmi?'));
        var resumeSub = el('div', 'udp-resume-sub', '');
        resumeBox.appendChild(resumeSub);
        var resumeBtns = el('div', 'udp-resume-btns');
        var resumeCont = el('button', 'udp-resume-continue', '&#9654; ' + (S.resume_continue || 'Davom etish'));
        resumeCont.type = 'button';
        var resumeRestart = el('button', 'udp-resume-restart', '&#8635; ' + (S.resume_restart || 'Boshidan'));
        resumeRestart.type = 'button';
        resumeBtns.appendChild(resumeCont);
        resumeBtns.appendChild(resumeRestart);
        resumeBox.appendChild(resumeBtns);
        resumeOv.appendChild(resumeBox);
        root.appendChild(resumeOv);

        // Error overlay
        var errOv = el('div', 'udp-error');
        errOv.hidden = true;
        errOv.appendChild(el('div', 'udp-error-icon', '&#9888;'));
        errOv.appendChild(el('div', 'udp-error-title', S.error_title || 'Video yuklanmadi'));
        errOv.appendChild(el('div', 'udp-error-sub', S.error_sub || 'Internet yoki server holatini tekshiring'));
        var retryBtn = el('button', 'udp-btn-retry', '&#10227; ' + (S.retry || 'Qayta urinish'));
        retryBtn.type = 'button';
        errOv.appendChild(retryBtn);
        root.appendChild(errOv);

        // Ended overlay
        var endedOv = el('div', 'udp-ended');
        endedOv.hidden = true;
        endedOv.appendChild(el('div', 'udp-ended-icon', '&#127916;'));
        endedOv.appendChild(el('div', 'udp-ended-title', S.ended_title || 'Video tugadi'));
        var endedBtns = el('div', 'udp-ended-btns');
        var replayBtn = el('button', 'udp-btn-replay', ICONS.replay + ' ' + (S.replay || 'Qayta ko\u2018rish'));
        replayBtn.type = 'button';
        endedBtns.appendChild(replayBtn);
        if (cfg.next) {
            var nextBtn2 = el('a', 'udp-btn-next-ep', '&#9654; ' + (cfg.next.label || S.next_ep || 'Keyingi qism'));
            nextBtn2.href = cfg.next.href;
            endedBtns.appendChild(nextBtn2);
        }
        endedOv.appendChild(endedBtns);
        root.appendChild(endedOv);

        /* ===== CONTROLS (Telegram Desktop layout) ===== */
        var controls = el('div', 'udp-controls');
        var row = el('div', 'udp-controls-row');

        // --- LEFT: Volume ---
        var volWrap = el('div', 'udp-volume');
        var muteBtn = el('button', 'udp-btn udp-mute', ICONS.volOn);
        muteBtn.type = 'button';
        var volBar = el('div', 'udp-volume-bar');
        var volFill = el('div', 'udp-volume-fill');
        volBar.appendChild(volFill);
        volWrap.appendChild(muteBtn);
        volWrap.appendChild(volBar);
        row.appendChild(volWrap);

        // --- Skip 10s buttons ---
        var skipBackBtn = el('button', 'udp-btn udp-skip-back', ICONS.replay10);
        skipBackBtn.type = 'button';
        skipBackBtn.title = '10 soniya orqaga';
        row.appendChild(skipBackBtn);

        var skipFwdBtn = el('button', 'udp-btn udp-skip-fwd', ICONS.forward10);
        skipFwdBtn.type = 'button';
        skipFwdBtn.title = '10 soniya oldinga';
        row.appendChild(skipFwdBtn);

        // --- CENTER: Play + Current time + Seek + Remaining time ---
        var playBtn = el('button', 'udp-btn udp-play', ICONS.play);
        playBtn.type = 'button';
        row.appendChild(playBtn);

        var timeCurrent = el('span', 'udp-time udp-time-current', '00:00');
        row.appendChild(timeCurrent);

        // Seek bar
        var seekWrap = el('div', 'udp-seek');
        var seekTrack = el('div', 'udp-seek-track');
        var seekBuffer = el('div', 'udp-seek-buffer');
        var seekProgress = el('div', 'udp-seek-progress');
        var seekThumb = el('div', 'udp-seek-thumb');
        seekTrack.appendChild(seekBuffer);
        seekTrack.appendChild(seekProgress);
        seekTrack.appendChild(seekThumb);
        seekWrap.appendChild(seekTrack);
        var seekTooltip = el('div', 'udp-seek-tooltip', '0:00');
        seekWrap.appendChild(seekTooltip);
        row.appendChild(seekWrap);

        var timeRemaining = el('span', 'udp-time udp-time-remaining', '-00:00');
        row.appendChild(timeRemaining);

        // --- RIGHT: Aspect ratio + PiP + Settings ---
        var rightGroup = el('div', 'udp-controls-right');

        var aspectBtn = el('button', 'udp-btn udp-aspect', ICONS.aspect);
        aspectBtn.type = 'button';
        aspectBtn.title = 'Aspekt nisbati';
        aspectBtn.hidden = true; // shown only for vertical video
        rightGroup.appendChild(aspectBtn);

        var pipBtn = el('button', 'udp-btn udp-pip', ICONS.pip);
        pipBtn.type = 'button';
        pipBtn.title = S.pip || 'Kichik oyna';
        pipBtn.hidden = !document.pictureInPictureEnabled;
        rightGroup.appendChild(pipBtn);

        var gearBtn = el('button', 'udp-btn udp-settings', ICONS.gear);
        gearBtn.type = 'button';
        gearBtn.title = S.settings || 'Sozlamalar';
        var qualityBadge = el('span', 'udp-quality-badge', 'HD');
        gearBtn.appendChild(qualityBadge);
        rightGroup.appendChild(gearBtn);

        // Hidden buttons (handled by settings menu)
        var cinemaBtn = el('button', 'udp-btn udp-cinema-toggle', ICONS.cinema);
        cinemaBtn.type = 'button';
        cinemaBtn.title = S.cinema || 'Kino rejimi';
        cinemaBtn.hidden = true;

        var qualityBtn = el('button', 'udp-btn udp-quality', '<span class="udp-btn-label">Auto</span>');
        qualityBtn.type = 'button';
        qualityBtn.hidden = true;

        var speedBtn = el('button', 'udp-btn udp-speed', '<span class="udp-btn-label">1x</span>');
        speedBtn.type = 'button';
        speedBtn.hidden = true;

        var ccBtn = el('button', 'udp-btn udp-cc', ICONS.cc);
        ccBtn.type = 'button';
        ccBtn.title = S.subtitles || 'Subtitrlar';
        ccBtn.hidden = true;

        var fsBtn = el('button', 'udp-btn udp-fs-btn', ICONS.fs);
        fsBtn.type = 'button';
        fsBtn.title = S.fullscreen || 'To\u2018liq ekran';
        rightGroup.appendChild(fsBtn);

        row.appendChild(rightGroup);
        controls.appendChild(row);
        root.appendChild(controls);

        // Settings menu
        var menu = el('div', 'udp-menu');
        root.appendChild(menu);
        var menuBackdrop = el('div', 'udp-menu-backdrop');
        menuBackdrop.style.display = 'none';
        document.body.appendChild(menuBackdrop);

        var activeMenu = null;

        function openMenu(name) {
            menu.classList.add('udp-open');
            menuBackdrop.style.display = 'block';
            renderMenu(name || 'main');
            root.classList.add('udp-ui-visible');
        }
        function closeMenu() {
            menu.classList.remove('udp-open');
            menuBackdrop.style.display = 'none';
        }

        function menuItem(label, active, onClick, arrow) {
            var b = el('button', 'udp-menu-item' + (active ? ' udp-active' : ''));
            b.type = 'button';
            b.innerHTML = '<span>' + label + '</span><span class="udp-menu-check">&#10003;</span>' + (arrow ? '<span class="udp-menu-arrow">&#10095;</span>' : '');
            b.onclick = onClick;
            return b;
        }

        function renderMenu(name) {
            activeMenu = name;
            menu.innerHTML = '';
            if (name === 'speed') {
                var h = el('div', 'udp-menu-header', S.speed || 'Tezlik');
                var back = el('button', 'udp-menu-back', '&#10094;');
                back.type = 'button';
                back.onclick = function () { renderMenu('main'); };
                h.appendChild(back);
                menu.appendChild(h);
                var group = el('div', 'udp-menu-group');
                [0.5, 0.75, 1, 1.25, 1.5, 2].forEach(function (r) {
                    group.appendChild(menuItem(r + 'x', Math.abs(video.playbackRate - r) < 0.001, function () {
                        setRate(r);
                        renderMenu('speed');
                    }));
                });
                menu.appendChild(group);
                return;
            }
            if (name === 'quality') {
                var hq = el('div', 'udp-menu-header', S.quality || 'Sifat');
                var backq = el('button', 'udp-menu-back', '&#10094;');
                backq.type = 'button';
                backq.onclick = function () { renderMenu('main'); };
                hq.appendChild(backq);
                menu.appendChild(hq);
                var groupq = el('div', 'udp-menu-group');
                qualityOptions().forEach(function (q) {
                    groupq.appendChild(menuItem(q.label, q.active, function () {
                        setQuality(q);
                        renderMenu('quality');
                    }));
                });
                menu.appendChild(groupq);
                return;
            }
            if (name === 'cc') {
                var hc = el('div', 'udp-menu-header', S.subtitles || 'Subtitrlar');
                var backc = el('button', 'udp-menu-back', '&#10094;');
                backc.type = 'button';
                backc.onclick = function () { renderMenu('main'); };
                hc.appendChild(backc);
                menu.appendChild(hc);
                var groupc = el('div', 'udp-menu-group');
                groupc.appendChild(menuItem(S.off || 'O\u2018chirilgan', !currentTrackIndex(), function () { setTrack(-1); renderMenu('cc'); }));
                trackList().forEach(function (tr, i) {
                    groupc.appendChild(menuItem(tr.label, currentTrackIndex() === i, function () { setTrack(i); renderMenu('cc'); }));
                });
                menu.appendChild(groupc);
                return;
            }
            // main
            var g1 = el('div', 'udp-menu-group');
            g1.appendChild(menuItem(S.speed || 'Tezlik', false, function () { renderMenu('speed'); }, true));
            if (hasQualityOptions()) {
                g1.appendChild(menuItem(S.quality || 'Sifat', false, function () { renderMenu('quality'); }, true));
            }
            if (trackList().length) {
                g1.appendChild(menuItem(S.subtitles || 'Subtitrlar', false, function () { renderMenu('cc'); }, true));
            }
            menu.appendChild(g1);
            menu.appendChild(el('div', 'udp-menu-sep'));
            var g2 = el('div', 'udp-menu-group');
            if (document.pictureInPictureEnabled) {
                g2.appendChild(menuItem(S.pip || 'Kichik oyna', false, togglePip));
            }
            g2.appendChild(menuItem(S.cinema || 'Kino rejimi', document.body.classList.contains('udp-cinema'), toggleCinema));
            g2.appendChild(menuItem(S.fullscreen || 'To\u2018liq ekran', false, toggleFullscreen));
            menu.appendChild(g2);
        }

        menuBackdrop.onclick = closeMenu;

        /* ================= Video controls ================= */
        var lastSavedVolume = 1;
        var isMuted = false;
        var isDraggingSeek = false;
        var isDraggingVol = false;

        try {
            var savedVol = parseFloat(localStorage.getItem('udp_volume') || '1');
            if (!isNaN(savedVol)) { lastSavedVolume = Math.max(0, Math.min(1, savedVol)); }
            isMuted = localStorage.getItem('udp_muted') === '1';
        } catch (e) {}

        video.volume = lastSavedVolume;
        video.muted = isMuted;
        updateVolumeUI();

        function updatePlayIcon() {
            playBtn.innerHTML = (video.paused || video.ended) ? ICONS.play : ICONS.pause;
        }
        function updateVolumeUI() {
            muteBtn.innerHTML = video.muted || video.volume === 0 ? ICONS.volMute : ICONS.volOn;
            var v = video.muted ? 0 : video.volume;
            volFill.style.width = (v * 100) + '%';
        }
        function setVolume(v) {
            v = Math.max(0, Math.min(1, v));
            video.volume = v;
            video.muted = v === 0 ? false : isMuted ? true : false;
            if (v > 0) { video.muted = false; }
            if (v > 0) lastSavedVolume = v;
            updateVolumeUI();
            try {
                localStorage.setItem('udp_volume', String(lastSavedVolume));
                localStorage.setItem('udp_muted', video.muted ? '1' : '0');
            } catch (e) {}
        }
        function toggleMute() {
            if (video.volume === 0) {
                video.volume = lastSavedVolume || 1;
                video.muted = false;
            } else {
                video.muted = !video.muted;
            }
            updateVolumeUI();
            try { localStorage.setItem('udp_muted', video.muted ? '1' : '0'); } catch (e) {}
        }

        function togglePlay() {
            if (video.paused || video.ended) {
                var p = video.play();
                if (p && p.catch) p.catch(function () {});
            } else {
                video.pause();
            }
        }

        function setRate(r) {
            video.playbackRate = r;
            speedBtn.querySelector('.udp-btn-label').textContent = r + 'x';
        }

        function updateTime() {
            var d = video.duration;
            if (!isFinite(d)) d = 0;
            var remaining = d - video.currentTime;
            if (remaining < 0) remaining = 0;
            timeCurrent.textContent = fmtTime(video.currentTime);
            timeRemaining.textContent = '-' + fmtTime(remaining);
        }

        function updateSeek() {
            var d = video.duration;
            if (!isFinite(d) || d === 0) { d = 1; }
            var pct = (video.currentTime / d) * 100;
            seekProgress.style.width = pct + '%';
            seekThumb.style.left = pct + '%';
        }
        function updateBuffer() {
            var d = video.duration;
            if (!isFinite(d) || d === 0) return;
            var end = 0;
            try {
                for (var i = 0; i < video.buffered.length; i++) {
                    if (video.buffered.end(i) > end) end = video.buffered.end(i);
                }
            } catch (e) {}
            seekBuffer.style.width = (Math.min(100, (end / d) * 100)) + '%';
        }

        function seekFromEvent(ev) {
            var rect = seekTrack.getBoundingClientRect();
            var ratio = (ev.clientX - rect.left) / rect.width;
            if (ratio < 0) ratio = 0;
            if (ratio > 1) ratio = 1;
            var d = video.duration;
            if (!isFinite(d) || d === 0) return;
            var t = ratio * d;
            video.currentTime = t;
            updateSeek();
            return t;
        }

        function updateTooltip(ev) {
            var rect = seekTrack.getBoundingClientRect();
            var ratio = (ev.clientX - rect.left) / rect.width;
            ratio = Math.max(0, Math.min(1, ratio));
            seekTooltip.textContent = fmtTime(ratio * (video.duration || 0));
            seekTooltip.style.left = (ratio * rect.width) + 'px';
        }

        seekWrap.addEventListener('pointerdown', function (ev) {
            ev.preventDefault();
            isDraggingSeek = true;
            seekWrap.classList.add('udp-dragging');
            seekWrap.setPointerCapture(ev.pointerId);
            updateTooltip(ev);
        });
        seekWrap.addEventListener('pointermove', function (ev) {
            if (isDraggingSeek) { seekFromEvent(ev); }
            updateTooltip(ev);
        });
        seekWrap.addEventListener('pointerup', function (ev) {
            if (!isDraggingSeek) return;
            isDraggingSeek = false;
            seekWrap.classList.remove('udp-dragging');
            seekWrap.releasePointerCapture(ev.pointerId);
            var wasEnded = video.ended;
            if (wasEnded) { video.currentTime = video.duration; }
            seekFromEvent(ev);
            var p = video.play();
            if (p && p.catch) p.catch(function () {});
        });

        volBar.addEventListener('pointerdown', function (ev) {
            ev.preventDefault();
            isDraggingVol = true;
            volBar.setPointerCapture(ev.pointerId);
            setVolFromEvent(ev);
        });
        volBar.addEventListener('pointermove', function (ev) {
            if (isDraggingVol) setVolFromEvent(ev);
        });
        volBar.addEventListener('pointerup', function (ev) {
            if (!isDraggingVol) return;
            isDraggingVol = false;
            volBar.releasePointerCapture(ev.pointerId);
        });
        function setVolFromEvent(ev) {
            var rect = volBar.getBoundingClientRect();
            var v = (ev.clientX - rect.left) / rect.width;
            v = Math.max(0, Math.min(1, v));
            setVolume(v);
        }

        muteBtn.addEventListener('click', toggleMute);
        playBtn.addEventListener('click', togglePlay);
        video.addEventListener('click', function (ev) {
            if (ev.target === video && !video.dragging) togglePlay();
        });

        video.addEventListener('play', function () { root.classList.add('udp-playing'); root.classList.remove('udp-paused'); updatePlayIcon(); });
        video.addEventListener('pause', function () { root.classList.remove('udp-playing'); root.classList.add('udp-paused'); updatePlayIcon(); root.classList.add('udp-ui-visible'); });
        video.addEventListener('ended', function () {
            root.classList.remove('udp-playing');
            root.classList.add('udp-paused');
            updatePlayIcon();
            showEnded();
        });
        video.addEventListener('timeupdate', function () { updateTime(); updateSeek(); updateSkip(); });
        video.addEventListener('progress', updateBuffer);
        video.addEventListener('durationchange', function () { updateTime(); updateSeek(); });
        video.addEventListener('volumechange', updateVolumeUI);
        video.addEventListener('ratechange', function () { speedBtn.querySelector('.udp-btn-label').textContent = video.playbackRate + 'x'; });

        /* ================= Buffering ================= */
        function setLoading(show, label) {
            buffering.hidden = !show;
            if (label) bufferingLabel.textContent = label;
        }
        setLoading(video.readyState < 3);
        video.addEventListener('loadedmetadata', function () { setLoading(false); });
        video.addEventListener('canplay', function () { setLoading(false); });
        video.addEventListener('playing', function () { setLoading(false); });
        video.addEventListener('waiting', function () { if (!video.ended) setLoading(true); });
        video.addEventListener('seeking', function () { if (video.readyState < 3) setLoading(true); });
        video.addEventListener('seeked', function () { if (video.readyState >= 3) setLoading(false); });

        /* ================= Aspect ratio (vertikal video) ================= */
        var currentAspect = 'contain'; // 'contain' | 'cover' | 'fill'
        var aspectModes = ['contain', 'cover', 'fill'];
        var aspectIndex = 0;

        function fitVideo() {
            var w = video.videoWidth, h = video.videoHeight;
            if (!w || !h) return;
            var ratio = w / h;
            if (ratio < 1.25) {
                root.classList.add('udp-vertical');
                aspectBtn.hidden = false;
                var maxW = Math.round(0.78 * window.innerHeight * ratio);
                var wrapW = root.parentElement ? root.parentElement.clientWidth : window.innerWidth;
                root.style.maxWidth = Math.min(wrapW, maxW) + 'px';
                root.style.marginLeft = 'auto';
                root.style.marginRight = 'auto';
            } else {
                root.classList.remove('udp-vertical');
                aspectBtn.hidden = true;
                root.style.maxWidth = '';
                root.style.marginLeft = '';
                root.style.marginRight = '';
            }
        }
        video.addEventListener('loadedmetadata', fitVideo);
        video.addEventListener('resize', fitVideo);
        window.addEventListener('resize', fitVideo);

        // Aspect ratio toggle (only for vertical videos)
        aspectBtn.onclick = function (ev) {
            ev.stopPropagation();
            aspectIndex = (aspectIndex + 1) % aspectModes.length;
            currentAspect = aspectModes[aspectIndex];
            video.style.objectFit = currentAspect;
        };

        /* ================= Skip intro ================= */
        var introStart = cfg.introStart || 0;
        var introEnd = cfg.introEnd || 0;
        function updateSkip() {
            var show = !video.ended && introEnd > introStart && video.currentTime >= introStart && video.currentTime < introEnd && video.duration > introEnd;
            skipWrap.hidden = !show;
        }
        skipBtn.onclick = function () {
            if (video.duration > introEnd) video.currentTime = introEnd + 0.05;
            skipWrap.hidden = true;
            var p = video.play();
            if (p && p.catch) p.catch(function () {});
        };
        video.addEventListener('timeupdate', updateSkip);
        video.addEventListener('durationchange', updateSkip);

        /* ================= HLS (hls.js) ================= */
        function startHls(src) {
            if (window.Hls && Hls.isSupported()) {
                initHls(src);
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                video.src = src;
            } else if (!window.Hls) {
                var s = document.createElement('script');
                s.src = ROOT_URL + '/js/hls.min.js';
                s.onload = function () {
                    if (window.Hls && Hls.isSupported()) initHls(src);
                    else showError();
                };
                s.onerror = function () { showError(); };
                document.head.appendChild(s);
            } else {
                showError();
            }
        }
        function initHls(src) {
            hls = new Hls({ maxBufferLength: 30, maxMaxBufferLength: 120 });
            hls.loadSource(src);
            hls.attachMedia(video);
            hls.on(Hls.Events.MANIFEST_PARSED, function () {
                buildHlsQualityMenu();
            });
            hls.on(Hls.Events.ERROR, function (evt, data) {
                if (data && data.fatal) {
                    if (data.type === Hls.ErrorTypes.NETWORK_ERROR && !video.dataset.hlsRetried && video.dataset.hlsRefresh) {
                        video.dataset.hlsRetried = '1';
                        setLoading(true);
                        fetch(video.dataset.hlsRefresh)
                            .then(function (r) { return r.json(); })
                            .then(function (d) {
                                if (d && d.ok && d.url) {
                                    try { hls && hls.destroy(); } catch (e) {}
                                    startHls(d.url);
                                } else {
                                    setLoading(false);
                                    showError();
                                }
                            })
                            .catch(function () { setLoading(false); showError(); });
                        return;
                    }
                    setLoading(false);
                    try { hls && hls.destroy(); } catch (e) {}
                    showError();
                }
            });
        }
        function buildHlsQualityMenu() {
            if (!hls || !hls.levels || !hls.levels.length) return;
            var hasMulty = hls.levels.length > 1;
            if (hasMulty) {
                hlsAutoLevels = hls.levels.map(function (l) { return l.height || 0; });
                // Update quality badge
                var currentLevel = hls.currentLevel;
                var label = 'Auto';
                if (currentLevel >= 0 && hls.levels[currentLevel]) {
                    var h = hls.levels[currentLevel].height || 0;
                    label = h >= 1000 ? Math.round(h / 1000) + 'k' : h + 'p';
                }
                qualityBadge.textContent = label;
            }
        }
        var hlsAutoLevels = [];

        if (isHls) {
            if (video.dataset.hls) {
                startHls(video.dataset.hls);
            } else if (video.dataset.hlsPending) {
                bufferingLabel.textContent = S.pending || 'Video tayyorlanmoqda...';
                (function pollPending() {
                    if (video.dataset.hls) {
                        startHls(video.dataset.hls);
                        return;
                    }
                    fetch(video.dataset.hlsRefresh)
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d && d.ok && d.url) {
                                video.dataset.hls = d.url;
                                startHls(d.url);
                            } else if (d && d.msg === 'login_required') {
                                bufferingLabel.textContent = 'Video ko\u2018rish uchun saytga kiring';
                            } else {
                                setTimeout(pollPending, 15000);
                            }
                        })
                        .catch(function () { setTimeout(pollPending, 15000); });
                })();
            }
        }

        /* ================= Quality ================= */
        var qualityOptions = function () {
            if (isHls && hlsAutoLevels.length) {
                var opts = [{ label: 'Auto', level: -1, active: hls.currentLevel === -1 }];
                hlsAutoLevels.forEach(function (height) {
                    opts.push({ label: height >= 1000 ? Math.round(height / 1000) + 'k' : height + 'p', level: height, active: hls.currentLevel === height });
                });
                return opts;
            }
            var qs = [];
            if (cfg.qualities && Object.keys(cfg.qualities).length) {
                Object.keys(cfg.qualities).forEach(function (label) {
                    qs.push({ label: label, url: cfg.qualities[label], active: false });
                });
            }
            if (qs.length > 1) {
                var cur = video.currentSrc || '';
                qs.forEach(function (q) { q.active = q.url === cur; });
            }
            return qs;
        };
        var hasQualityOptions = function () {
            if (isHls && hlsAutoLevels.length) return true;
            return cfg.qualities && Object.keys(cfg.qualities).length > 1;
        };
        var setQuality = function (q) {
            if (isHls && hls) {
                hls.currentLevel = q.level;
                qualityBadge.textContent = q.label;
                return;
            }
            if (!q.url) return;
            var cur = video.currentTime;
            var wasPlaying = !video.paused && !video.ended;
            video.src = q.url;
            video.load();
            video.addEventListener('loadedmetadata', function handler() {
                if (cur < video.duration) video.currentTime = cur;
                video.removeEventListener('loadedmetadata', handler);
            });
            if (wasPlaying) { var p = video.play(); if (p && p.catch) p.catch(function () {}); }
            qualityBadge.textContent = q.label;
        };

        /* ================= Subtitles ================= */
        var trackList = function () {
            return Array.prototype.slice.call(video.querySelectorAll('track[src]'));
        };
        var currentTrackIndex = function () {
            var list = trackList();
            for (var i = 0; i < list.length; i++) {
                if (list[i].mode === 'showing') return i;
            }
            return -1;
        };
        var setTrack = function (i) {
            var list = trackList();
            list.forEach(function (tr, idx) {
                tr.mode = idx === i ? 'showing' : 'disabled';
            });
            var has = list.length > 0 && i >= 0;
            ccBtn.classList.toggle('udp-btn-cc-on', has);
        };
        if (trackList().length) {
            ccBtn.hidden = false;
            ccBtn.onclick = function () { openMenu('cc'); };
        }
        ccBtn.classList.remove('udp-btn-cc-on');

        /* ================= PiP ================= */
        function togglePip() {
            if (document.pictureInPictureElement === video) {
                document.exitPictureInPicture().catch(function () {});
            } else if (video !== document.pictureInPictureElement) {
                video.requestPictureInPicture().catch(function () {});
            }
        }
        pipBtn.onclick = function (ev) { ev.stopPropagation(); togglePip(); };

        /* ================= Fullscreen ================= */
        function isFs() {
            return document.fullscreenElement === root ||
                document.webkitFullscreenElement === root ||
                document.mozFullScreenElement === root;
        }
        function toggleFullscreen() {
            if (isFs()) {
                if (document.exitFullscreen) document.exitFullscreen();
                else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
                else if (document.mozCancelFullScreen) document.mozCancelFullScreen();
            } else {
                var fn = root.requestFullscreen || root.webkitRequestFullscreen || root.mozRequestFullScreen;
                if (fn) fn.call(root);
            }
        }
        fsBtn.onclick = toggleFullscreen;
        document.addEventListener('fullscreenchange', onFsChange);
        document.addEventListener('webkitfullscreenchange', onFsChange);
        document.addEventListener('mozfullscreenchange', onFsChange);
        function onFsChange() {
            root.classList.toggle('udp-fs', isFs());
            fsBtn.innerHTML = isFs() ? ICONS.fsExit : ICONS.fs;
            fitVideo();
        }

        /* ================= Cinema mode ================= */
        var scrim = null;
        var cinemaCloseBtn = null;
        function toggleCinema() {
            var on = document.body.classList.toggle('udp-cinema');
            cinemaBtn.innerHTML = on ? ICONS.cinemaOff : ICONS.cinema;
            if (on) {
                if (!scrim) {
                    scrim = el('div', 'udp-cinema-scrim');
                    document.body.appendChild(scrim);
                    cinemaCloseBtn = el('button', 'udp-cinema-close', '&#10005;');
                    cinemaCloseBtn.title = S.cinema_off || 'Kino rejimidan chiqish';
                    document.body.appendChild(cinemaCloseBtn);
                    cinemaCloseBtn.onclick = toggleCinema;
                    scrim.onclick = toggleCinema;
                }
            } else {
                if (scrim) { scrim.remove(); scrim = null; }
                if (cinemaCloseBtn) { cinemaCloseBtn.remove(); cinemaCloseBtn = null; }
            }
            fitVideo();
        }
        cinemaBtn.onclick = function (ev) { ev.stopPropagation(); toggleCinema(); };

        /* ================= Skip 10s buttons ================= */
        skipBackBtn.onclick = function (ev) { ev.stopPropagation(); video.currentTime = Math.max(0, video.currentTime - 10); showUi(); };
        skipFwdBtn.onclick = function (ev) { ev.stopPropagation(); video.currentTime = Math.min((video.duration || 0), video.currentTime + 10); showUi(); };

        /* ================= Keyboard ================= */
        document.addEventListener('keydown', function (ev) {
            if (document.activeElement && /input|textarea|select/.test(document.activeElement.tagName)) return;
            if (!root.getBoundingClientRect().width) return;
            switch (ev.key) {
                case ' ':
                    ev.preventDefault();
                    togglePlay();
                    break;
                case 'ArrowRight':
                    ev.preventDefault();
                    video.currentTime = Math.min((video.duration || 0), video.currentTime + 10);
                    showUi();
                    break;
                case 'ArrowLeft':
                    ev.preventDefault();
                    video.currentTime = Math.max(0, video.currentTime - 10);
                    showUi();
                    break;
                case 'ArrowUp':
                    ev.preventDefault();
                    setVolume(video.volume + 0.1);
                    break;
                case 'ArrowDown':
                    ev.preventDefault();
                    setVolume(video.volume - 0.1);
                    break;
                case 'f':
                case 'F':
                    toggleFullscreen();
                    break;
                case 'm':
                case 'M':
                    toggleMute();
                    break;
                case 'Escape':
                    if (document.body.classList.contains('udp-cinema')) toggleCinema();
                    closeMenu();
                    break;
            }
        });

        /* ================= UI auto-hide ================= */
        var hideTimer = null;
        function showUi() {
            root.classList.add('udp-ui-visible');
            clearTimeout(hideTimer);
            if (!video.paused && !video.ended) {
                hideTimer = setTimeout(function () {
                    root.classList.remove('udp-ui-visible');
                }, 3000);
            }
        }
        root.addEventListener('mousemove', showUi);
        root.addEventListener('mouseenter', showUi);
        root.addEventListener('pointerdown', showUi);

        /* ================= Menu buttons ================= */
        gearBtn.onclick = function (ev) { ev.stopPropagation(); openMenu('main'); };
        speedBtn.onclick = function (ev) { ev.stopPropagation(); openMenu('speed'); };
        qualityBtn.onclick = function (ev) { ev.stopPropagation(); openMenu('quality'); };

        /* ================= Resume ================= */
        var resumeAt = parseInt(cfg.resumeAt || '0', 10);
        var resumeHandled = false;
        function showResume() {
            if (resumeHandled || resumeAt <= 5) return;
            resumeHandled = true;
            if (video.duration > resumeAt + 5) {
                resumeSub.textContent = fmtTime(resumeAt) + ' \u2014 ' + (S.resume_sub || 'qayerda qolgan edingiz');
                resumeOv.hidden = false;
                video.pause();
                root.classList.add('udp-ui-visible');
            }
        }
        resumeCont.onclick = function () {
            resumeOv.hidden = true;
            video.currentTime = resumeAt;
            var p = video.play();
            if (p && p.catch) p.catch(function () {});
        };
        resumeRestart.onclick = function () {
            resumeOv.hidden = true;
            var p = video.play();
            if (p && p.catch) p.catch(function () {});
        };
        video.addEventListener('loadedmetadata', showResume);
        video.addEventListener('canplay', showResume);
        if (resumeAt > 5) {
            setLoading(false);
        }

        /* ================= Autoplay ================= */
        video.addEventListener('loadedmetadata', function onMeta() {
            video.removeEventListener('loadedmetadata', onMeta);
            if (resumeAt > 5) return;
            setTimeout(function () {
                if (!video.paused) return;
                var p = video.play();
                if (p && p.catch) p.catch(function () {});
            }, 300);
        });

        /* ================= Error ================= */
        function showError() {
            setLoading(false);
            errOv.hidden = false;
        }
        function hideError() {
            errOv.hidden = true;
        }
        video.addEventListener('error', function () {
            if (video.dataset.fallback && !video.dataset.fallbackUsed) {
                video.dataset.fallbackUsed = '1';
                var cur = video.currentTime;
                var wasPlaying = !video.paused && !video.ended;
                video.removeAttribute('crossorigin');
                video.src = video.dataset.fallback;
                video.load();
                video.addEventListener('loadedmetadata', function onFbMeta() {
                    video.removeEventListener('loadedmetadata', onFbMeta);
                    delete video.dataset.errHandled;
                    if (cur > 0 && cur < video.duration) video.currentTime = cur;
                    if (wasPlaying) { var p = video.play(); if (p && p.catch) p.catch(function () {}); }
                });
                return;
            }
            showError();
        });
        retryBtn.onclick = function () {
            hideError();
            endedOv.hidden = true;
            if (isHls && video.dataset.hls) {
                try { hls && hls.destroy(); } catch (e) {}
                hls = null;
                startHls(video.dataset.hls);
            } else {
                video.load();
                var p = video.play();
                if (p && p.catch) p.catch(function () {});
            }
        };

        /* ================= Ended ================= */
        function showEnded() {
            endedOv.hidden = false;
        }
        replayBtn.onclick = function () {
            endedOv.hidden = true;
            video.currentTime = 0;
            var p = video.play();
            if (p && p.catch) p.catch(function () {});
        };

        /* ================= Initial state ================= */
        updatePlayIcon();
        updateTime();
        updateSeek();
        setRate(1);
        if (hasQualityOptions()) {
            // Determine initial quality badge label
            if (isHls && hls && hls.currentLevel >= 0 && hls.levels[hls.currentLevel]) {
                var h = hls.levels[hls.currentLevel].height || 0;
                qualityBadge.textContent = h >= 1000 ? Math.round(h / 1000) + 'k' : h + 'p';
            } else if (cfg.qualities && Object.keys(cfg.qualities).length > 1) {
                qualityBadge.textContent = 'HD';
            }
        }
    }

    function initAll() {
        document.querySelectorAll('.udp-player[data-udp]').forEach(function (root) {
            if (root.classList.contains('udp-init')) return;
            initPlayer(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
