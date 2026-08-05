/* ===== UZDUB — Barcha emojilarni yaxlit ko'k siluetga aylantirish =====
   Emoji glifi <span class="emoji-blue"> ga o'ralib, JS inyeksiya qiladigan
   SVG feColorMatrix (id=emoji-blue-fm) orqali har bir piksel sof ko'k
   (0,0,255) rangga o'tkaziladi — shakl alfa orqali saqlanadi.
   Izoh/chat kontenti (.emoji-keep) o'ralmaydi. Dynamik kontent uchun
   MutationObserver ishlatiladi. */
(function () {
    if (window.__uzdubEmojiBlue) return;
    window.__uzdubEmojiBlue = true;

    var RE = /(\p{Regional_Indicator}{2}|\p{Extended_Pictographic}\uFE0F?|[\uD83C-\uDBFF][\uDC00-\uDFFF])(?:\u200D(?:\p{Extended_Pictographic}\uFE0F?|[\uD83C-\uDBFF][\uDC00-\uDFFF]))*/gu;

    function hasKeep(el) {
        while (el && el.nodeType === 1 && el !== document.body && el !== document.documentElement) {
            if (el.classList && el.classList.contains('emoji-keep')) return true;
            el = el.parentNode;
        }
        return false;
    }

    function skip(el) {
        if (!el) return true;
        if (el.nodeType === 1) {
            var tag = el.tagName;
            if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT' || tag === 'TEXTAREA') return true;
            if (el.classList && (el.classList.contains('emoji-blue') || el.classList.contains('emoji-keep'))) return true;
        }
        return hasKeep(el);
    }

    function wrap(node) {
        var parent = node.parentNode;
        if (skip(parent)) return;
        var text = node.nodeValue;
        if (!text || !text.length) return;
        RE.lastIndex = 0;
        if (!RE.test(text)) return;
        RE.lastIndex = 0;

        var frag = document.createDocumentFragment();
        var last = 0, m, wrapped = false;
        while ((m = RE.exec(text)) !== null) {
            if (m.index > last) frag.appendChild(document.createTextNode(text.slice(last, m.index)));
            if (m[0].indexOf('\u{1F451}') !== -1) {
                frag.appendChild(document.createTextNode(m[0]));
            } else {
                var span = document.createElement('span');
                span.className = 'emoji-blue';
                span.textContent = m[0];
                frag.appendChild(span);
            }
            last = m.index + m[0].length;
            wrapped = true;
        }
        if (!wrapped) return;
        if (last < text.length) frag.appendChild(document.createTextNode(text.slice(last)));
        parent.replaceChild(frag, node);
    }

    function scan(root) {
        if (!root || skip(root)) return;
        if (root.nodeType === 3) { wrap(root); return; }
        if (root.nodeType !== 1) return;

        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode: function (n) {
                return skip(n.parentNode) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
            }
        });
        var nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        for (var i = 0; i < nodes.length; i++) wrap(nodes[i]);
    }

    function injectFilter() {
        if (document.getElementById('emoji-blue-fm')) return;
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', '0');
        svg.setAttribute('height', '0');
        svg.setAttribute('style', 'position:absolute;width:0;height:0;overflow:hidden');
        var defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
        var filter = document.createElementNS('http://www.w3.org/2000/svg', 'filter');
        filter.setAttribute('id', 'emoji-blue-fm');
        var fm = document.createElementNS('http://www.w3.org/2000/svg', 'feColorMatrix');
        fm.setAttribute('type', 'matrix');
        fm.setAttribute('color-interpolation-filters', 'sRGB');
        fm.setAttribute('values', '0 0 0 0 0.1294  0 0 0 0 0.5882  0 0 0 0 0.9529  0 0 0 1 0');
        filter.appendChild(fm);
        defs.appendChild(filter);
        svg.appendChild(defs);
        document.body.appendChild(svg);
    }

    function run() {
        if (!document.body) { setTimeout(run, 50); return; }
        injectFilter();
        scan(document.body);

        if (!window.MutationObserver) return;
        var timer = null;
        var mo = new MutationObserver(function (muts) {
            if (timer) clearTimeout(timer);
            timer = setTimeout(function () {
                var roots = [];
                for (var i = 0; i < muts.length; i++) {
                    var m = muts[i];
                    if (m.type === 'characterData') {
                        if (m.target && m.target.nodeType === 3 && m.target.parentNode && !skip(m.target.parentNode)) roots.push(m.target);
                        continue;
                    }
                    var added = m.addedNodes;
                    for (var j = 0; j < added.length; j++) roots.push(added[j]);
                }
                for (var k = 0; k < roots.length; k++) {
                    var t = roots[k];
                    if (!t || skip(t) || skip(t.parentNode)) continue;
                    var dup = false;
                    for (var s = 0; s < k; s++) {
                        if (roots[s] === t || (roots[s].contains && roots[s].contains(t))) { dup = true; break; }
                    }
                    if (dup) continue;
                    scan(t);
                }
            }, 120);
        });
        mo.observe(document.body, { childList: true, subtree: true, characterData: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
