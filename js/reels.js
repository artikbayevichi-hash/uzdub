/* UZDUB Reels — vertikal lenta: avto ijro, lazy VK resolve, layk va cheksiz yuklash */
(function () {
    var feed = document.getElementById('reelsFeed');
    if (!feed) return;

    var ROOT = window.ROOT_URL || '';
    var CSRF = window.__REELS_CSRF__ || '';
    var muted = true;
    var offset = 0;
    var hasMore = true;
    var loading = false;
    var current = null;

    function toast(msg) {
        var el = document.getElementById('reelsToast');
        if (!el) return;
        el.textContent = msg;
        el.classList.add('show');
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.classList.remove('show'); }, 2200);
    }

    function fmt(n) {
        n = Number(n) || 0;
        if (n >= 1000000) return (n / 1000000).toFixed(1).replace('.0', '') + 'M';
        if (n >= 1000) return (n / 1000).toFixed(1).replace('.0', '') + 'K';
        return String(n);
    }

    function buildSlide(reel) {
        var slide = document.createElement('div');
        slide.className = 'reel-slide is-paused';
        slide.dataset.id = reel.id;
        if (reel.src) slide.dataset.src = reel.src;
        if (reel.embed) slide.dataset.embed = reel.embed;

        if (reel.thumb) {
            var poster = document.createElement('div');
            poster.className = 'reel-poster';
            poster.style.backgroundImage = 'url("' + reel.thumb + '")';
            slide.appendChild(poster);
        }

        var video = document.createElement('video');
        video.playsInline = true;
        video.loop = true;
        video.muted = muted;
        video.preload = 'none';
        video.setAttribute('playsinline', '');
        video.setAttribute('webkit-playsinline', '');
        if (reel.thumb) video.poster = reel.thumb;
        slide.appendChild(video);

        var spinner = document.createElement('div');
        spinner.className = 'reel-spinner';
        slide.appendChild(spinner);

        var badge = document.createElement('div');
        badge.className = 'reel-play-badge';
        badge.textContent = '▶';
        slide.appendChild(badge);

        var info = document.createElement('div');
        info.className = 'reel-info';
        var h = document.createElement('h3');
        h.className = 'reel-title';
        h.textContent = reel.title;
        info.appendChild(h);
        if (reel.description) {
            var p = document.createElement('p');
            p.className = 'reel-desc';
            p.textContent = reel.description;
            info.appendChild(p);
        }
        if (reel.content_url) {
            var a = document.createElement('a');
            a.className = 'reel-watch-btn';
            a.href = reel.content_url;
            a.textContent = '▶ ' + (reel.content_title || 'Tomosha qilish');
            info.appendChild(a);
        }
        slide.appendChild(info);

        var actions = document.createElement('div');
        actions.className = 'reel-actions';
        actions.appendChild(actionBtn('like', reel.liked ? '❤️' : '🤍', fmt(reel.likes), reel.liked));
        actions.appendChild(actionBtn('sound', muted ? '🔇' : '🔊', 'Ovoz', false));
        actions.appendChild(actionBtn('share', '🔗', 'Ulashish', false));
        slide.appendChild(actions);

        return slide;
    }

    function actionBtn(act, icon, label, on) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'reel-act' + (on ? ' liked' : '');
        b.dataset.act = act;
        var i = document.createElement('span');
        i.className = 'reel-act-icon';
        i.textContent = icon;
        var s = document.createElement('span');
        s.className = 'reel-act-label';
        s.textContent = label;
        b.appendChild(i);
        b.appendChild(s);
        return b;
    }

    function showError(slide, msg) {
        slide.classList.add('has-error');
        if (slide.querySelector('.reel-error')) return;
        var d = document.createElement('div');
        d.className = 'reel-error';
        d.textContent = msg;
        slide.appendChild(d);
    }

    function useEmbed(slide) {
        var embed = slide.dataset.embed;
        if (!embed) { showError(slide, 'Videoni ochib bo\'lmadi'); return; }
        var video = slide.querySelector('video');
        if (video) video.remove();
        if (slide.querySelector('iframe')) return;
        var f = document.createElement('iframe');
        f.src = embed;
        f.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
        f.allowFullscreen = true;
        slide.appendChild(f);
        slide.classList.remove('is-paused');
        slide.classList.add('is-playing');
    }

    // VK havolasi keshda bo'lmasa server tomondan ajratib olamiz.
    function ensureSource(slide) {
        if (slide.dataset.resolving === '1' || slide.dataset.resolved === '1') return Promise.resolve();
        var video = slide.querySelector('video');
        if (!video) return Promise.resolve();

        if (slide.dataset.src) {
            slide.dataset.resolved = '1';
            video.src = slide.dataset.src;
            return Promise.resolve();
        }

        slide.dataset.resolving = '1';
        return fetch(ROOT + '/api/reel-resolve.php?id=' + encodeURIComponent(slide.dataset.id))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                slide.dataset.resolving = '0';
                if (d && d.ok && d.src) {
                    slide.dataset.src = d.src;
                    slide.dataset.resolved = '1';
                    video.src = d.src;
                } else {
                    if (d && d.embed) slide.dataset.embed = d.embed;
                    useEmbed(slide);
                }
            })
            .catch(function () {
                slide.dataset.resolving = '0';
                showError(slide, 'Tarmoq xatosi — qayta urinib ko\'ring');
            });
    }

    // Muddati o'tgan VK havolasi (403/404) — bir marta yangilab ko'ramiz, keyin embed.
    function onVideoError(slide) {
        var video = slide.querySelector('video');
        if (!video) return;
        if (slide.dataset.retried === '1') { useEmbed(slide); return; }
        slide.dataset.retried = '1';
        fetch(ROOT + '/api/reel-resolve.php?refresh=1&id=' + encodeURIComponent(slide.dataset.id))
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.ok && d.src) {
                    slide.dataset.src = d.src;
                    video.src = d.src;
                    video.play().catch(function () {});
                } else {
                    if (d && d.embed) slide.dataset.embed = d.embed;
                    useEmbed(slide);
                }
            })
            .catch(function () { useEmbed(slide); });
    }

    function play(slide) {
        if (!slide || current === slide) return;
        if (current) pause(current);
        current = slide;
        ensureSource(slide).then(function () {
            var video = slide.querySelector('video');
            if (!video || !video.src) return;
            video.muted = muted;
            var pr = video.play();
            if (pr && pr.catch) pr.catch(function () { slide.classList.add('is-paused'); });
        });
        countView(slide.dataset.id);
        prefetchNext(slide);
    }

    function pause(slide) {
        var video = slide.querySelector('video');
        if (video) video.pause();
        slide.classList.remove('is-playing');
        slide.classList.add('is-paused');
    }

    function prefetchNext(slide) {
        var next = slide.nextElementSibling;
        if (next && next.classList.contains('reel-slide')) ensureSource(next);
    }

    var viewed = {};
    function countView(id) {
        if (!id || viewed[id]) return;
        viewed[id] = true;
        fetch(ROOT + '/api/reel-view.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reel_id: Number(id) })
        }).catch(function () {});
    }

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
            if (en.isIntersecting && en.intersectionRatio >= 0.6) {
                play(en.target);
            } else if (current === en.target) {
                pause(en.target);
                current = null;
            }
        });
        if (hasMore && feed.scrollHeight - feed.scrollTop - feed.clientHeight < feed.clientHeight * 2) {
            loadMore();
        }
    }, { root: feed, threshold: [0, 0.6, 1] });

    var added = {};
    function addReels(items) {
        items.forEach(function (reel) {
            if (added[reel.id]) return;
            added[reel.id] = true;
            var slide = buildSlide(reel);
            feed.appendChild(slide);
            var video = slide.querySelector('video');
            if (video) {
                video.addEventListener('playing', function () {
                    slide.classList.remove('is-paused');
                    slide.classList.add('is-playing');
                });
                video.addEventListener('error', function () { onVideoError(slide); });
            }
            observer.observe(slide);
        });
    }

    function loadMore() {
        if (loading || !hasMore) return;
        loading = true;
        fetch(ROOT + '/api/reels.php?offset=' + offset)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                loading = false;
                if (!d || !d.ok) { hasMore = false; return; }
                offset = d.offset;
                hasMore = !!d.has_more;
                addReels(d.items || []);
            })
            .catch(function () { loading = false; });
    }

    function toggleLike(slide, btn) {
        if (!window.UZDUB_IS_LOGGED_IN) { toast('Layk qo\'yish uchun tizimga kiring'); return; }
        fetch(ROOT + '/api/reel-like.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reel_id: Number(slide.dataset.id), csrf_token: CSRF })
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) { toast(d && d.msg ? d.msg : 'Xatolik'); return; }
                btn.classList.toggle('liked', d.liked);
                btn.querySelector('.reel-act-icon').textContent = d.liked ? '❤️' : '🤍';
                btn.querySelector('.reel-act-label').textContent = fmt(d.likes);
            })
            .catch(function () { toast('Tarmoq xatosi'); });
    }

    function setMuted(next) {
        muted = next;
        Array.prototype.forEach.call(feed.querySelectorAll('video'), function (v) { v.muted = muted; });
        Array.prototype.forEach.call(feed.querySelectorAll('.reel-act[data-act="sound"] .reel-act-icon'), function (i) {
            i.textContent = muted ? '🔇' : '🔊';
        });
    }

    feed.addEventListener('click', function (ev) {
        var btn = ev.target.closest('.reel-act');
        var slide = ev.target.closest('.reel-slide');
        if (!slide) return;

        if (btn) {
            ev.preventDefault();
            if (btn.dataset.act === 'like') toggleLike(slide, btn);
            else if (btn.dataset.act === 'sound') setMuted(!muted);
            else if (btn.dataset.act === 'share') share(slide);
            return;
        }
        if (ev.target.closest('.reel-info')) return;

        var video = slide.querySelector('video');
        if (!video || !video.src) return;
        if (video.paused) { video.play().catch(function () {}); }
        else { pause(slide); }
    });

    function share(slide) {
        var url = location.origin + location.pathname + '?id=' + slide.dataset.id;
        if (navigator.share) {
            navigator.share({ url: url }).catch(function () {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function () { toast('Havola nusxalandi'); });
        }
    }

    document.addEventListener('keydown', function (ev) {
        if (!current) return;
        if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
            var target = ev.key === 'ArrowDown' ? current.nextElementSibling : current.previousElementSibling;
            if (target) { ev.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
        } else if (ev.key === 'm' || ev.key === 'M') {
            setMuted(!muted);
        }
    });

    var initial = window.__REELS__ || { items: [], offset: 0, has_more: false };
    offset = initial.offset || (initial.items || []).length;
    hasMore = !!initial.has_more;
    addReels(initial.items || []);

})();
