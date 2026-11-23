/**
 * assets/natural-tree.js — رسم «الشجرة الطبيعية» والتفاعل معها
 * (تكبير/تصغير، سحب، بحث، تفاصيل الشخص، وتحديث تلقائي عند الإضافة أو الحذف).
 */
(function () {
    'use strict';

    const CFG = window.NT_CONFIG || {};
    const T = CFG.text || {};
    const Layout = window.NaturalTreeLayout;
    const MAX_WIDTH = Math.min(2000, Number(CFG.maxWidth) || 2000);
    const $ = id => document.getElementById(id);
    const viewport = $('ntViewport'), world = $('ntWorld'), wood = $('ntWood'), leavesEl = $('ntLeaves'), canopy = $('ntCanopy');
    const panel = $('ntPanel'), panelBody = $('ntPanelBody'), loading = $('ntLoading'), toastEl = $('ntToast');
    const searchInput = $('ntSearch'), resultsEl = $('ntResults');
    const reduceMotion = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    const ASPECT = { tree: 1.3, balanced: 2.4, large: 6 };
    const POLL_MS = 25000;

    const store = {
        get(key, fallback) { try { const v = localStorage.getItem('natural-tree:' + key); return v === null ? fallback : v; } catch (e) { return fallback; } },
        set(key, value) { try { localStorage.setItem('natural-tree:' + key, value); } catch (e) { /* التخزين غير متاح */ } }
    };
    const params = new URLSearchParams(location.search);

    const state = {
        data: null, people: new Map(), spouses: new Map(), childrenOf: new Map(), forest: null,
        result: null, rootId: toId(params.get('root')), selected: toId(params.get('id')),
        density: ASPECT[store.get('density', 'tree')] ? store.get('density', 'tree') : 'tree',
        years: store.get('years', '1') === '1', foliage: store.get('foliage', '1') === '1',
        sig: null, cards: new Map(), userMoved: false, busy: false
    };
    const view = { k: 1, x: 0, y: 0 };

    function toId(v) { const n = Number(v); return Number.isFinite(n) && n > 0 ? n : null; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); }
    function fmt(n) { return Number(n).toLocaleString(CFG.lang === 'en' ? 'en-US' : 'ar-EG'); }
    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

    /* ---------------------------------------------------------------- */
    /* البيانات                                                          */
    /* ---------------------------------------------------------------- */
    async function fetchJSON(url) {
        const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
        if (res.status === 401) { location.href = 'access.php'; throw new Error('auth'); }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    function indexData(data) {
        state.data = data;
        state.sig = data.sig || null;
        state.people = new Map();
        for (const p of data.people || []) state.people.set(p.id, p);
        state.spouses = new Map();
        for (const pair of data.marriages || []) {
            const [h, w] = pair;
            if (!state.spouses.has(h)) state.spouses.set(h, []);
            if (!state.spouses.has(w)) state.spouses.set(w, []);
            state.spouses.get(h).push(w);
            state.spouses.get(w).push(h);
        }
        state.childrenOf = new Map();
        for (const p of state.people.values()) for (const parent of [p.father_id, p.mother_id]) {
            if (!parent || !state.people.has(parent)) continue;
            if (!state.childrenOf.has(parent)) state.childrenOf.set(parent, []);
            state.childrenOf.get(parent).push(p.id);
        }
        state.forest = Layout.buildForest(data.people || []);
    }

    /* قياس أسماء البطاقات بالخط الحقيقي حتى يكون عرض كل بطاقة على قدر الاسم */
    const measureCtx = document.createElement('canvas').getContext('2d');
    function measureWidths(people) {
        const family = getComputedStyle(document.body).fontFamily || 'Tahoma, sans-serif';
        measureCtx.font = '700 15px ' + family;
        const widths = {};
        for (const p of people) widths[p.id] = Math.ceil(measureCtx.measureText(p.name || '').width);
        return widths;
    }

    /* ---------------------------------------------------------------- */
    /* حساب التوزيع (Web Worker مع بديل في الصفحة نفسها)                    */
    /* ---------------------------------------------------------------- */
    let worker = null, workerToken = 0;
    const pending = new Map();
    function getWorker() {
        if (worker === false) return null;
        if (worker) return worker;
        try {
            worker = new Worker('assets/natural-tree-worker.js?v=1');
            worker.onmessage = e => { const p = pending.get(e.data.token); if (!p) return; pending.delete(e.data.token); e.data.error ? p.reject(new Error(e.data.error)) : p.resolve(e.data.result); };
            worker.onerror = () => { for (const p of pending.values()) p.reject(new Error('worker')); pending.clear(); worker.terminate(); worker = false; };
        } catch (e) { worker = false; }
        return worker || null;
    }
    function computeLayout() {
        const people = (state.data.people || []).map(p => ({ id: p.id, name: p.name, father_id: p.father_id, mother_id: p.mother_id, birth_date: p.birth_date, is_primary_ancestor: p.is_primary_ancestor }));
        const widths = measureWidths(state.data.people || []);
        const options = { rootId: state.rootId, aspect: ASPECT[state.density], maxWidth: MAX_WIDTH };
        const local = () => Layout.layout(people, Object.assign({}, options, { forest: state.forest, measure: p => widths[p.id] || 0 }));
        const w = getWorker();
        if (!w) return Promise.resolve(local());
        return new Promise((resolve, reject) => {
            const token = ++workerToken;
            pending.set(token, { resolve, reject });
            w.postMessage({ token, people, widths, options });
        }).catch(() => local());
    }

    /* ---------------------------------------------------------------- */
    /* الرسم                                                             */
    /* ---------------------------------------------------------------- */
    const f1 = n => (Math.round(n * 10) / 10).toString();
    function polyPath(points) {
        if (!points.length) return '';
        let d = 'M' + f1(points[0][0]) + ' ' + f1(points[0][1]);
        for (let i = 1; i < points.length; i++) d += 'L' + f1(points[i][0]) + ' ' + f1(points[i][1]);
        return d + 'Z';
    }
    function curvePath(segs) {
        let d = 'M' + f1(segs[0][0]) + ' ' + f1(segs[0][1]);
        for (const s of segs) d += 'C' + f1(s[2]) + ' ' + f1(s[3]) + ' ' + f1(s[4]) + ' ' + f1(s[5]) + ' ' + f1(s[6]) + ' ' + f1(s[7]);
        return d;
    }

    function rootsAndGround(res) {
        const W = res.width, H = res.height, gy = res.groundY, x = res.trunk ? res.trunk.x : W / 2;
        const base = res.trunk ? res.trunk.baseWidth : 30;
        let roots = '';
        const spread = [-1, -0.55, -0.22, 0.2, 0.58, 1];
        spread.forEach((s, i) => {
            const reach = clamp(base * (1.6 + Math.abs(s) * 1.8), 30, W / 2);
            const ex = clamp(x + s * reach, 6, W - 6);
            const depth = Math.min(H - gy - 6, base * (0.5 + (i % 3) * 0.25) + 18);
            const sx = x + s * base * 0.32;
            const seg = [sx, gy - 6, sx + (ex - sx) * 0.25, gy + depth * 0.15, ex - (ex - sx) * 0.3, gy + depth * 0.85, ex, gy + depth];
            roots += polyPath(Layout.outline({ kind: 'branch', segs: [seg], w0: base * (0.32 - Math.abs(s) * 0.1), w1: 1.2 }, 10));
        });
        const grass = `M0 ${f1(gy + 6)} Q ${f1(W * 0.25)} ${f1(gy - 10)} ${f1(W / 2)} ${f1(gy - 2)} T ${f1(W)} ${f1(gy + 4)} L ${W} ${H} L 0 ${H} Z`;
        return `<path class="nt-soil" d="${grass}"/><path class="nt-roots" d="${roots}"/>`;
    }

    function renderWood(res) {
        const W = res.width, H = res.height;
        const groups = { thick: '', mid: '', hl: '', fine1: '', fine2: '', fine3: '' };
        let trunk = '';
        for (const b of res.branches) {
            const maxW = Math.max(b.w0, b.w1);
            if (b.kind === 'trunk') {
                trunk = polyPath(Layout.outline(b, 28));
                const e = b.segs[0], r1 = b.w1 / 2;
                trunk += `M${f1(e[6] - r1)} ${f1(e[7])}a${f1(r1)} ${f1(r1 * 0.7)} 0 1 0 ${f1(2 * r1)} 0a${f1(r1)} ${f1(r1 * 0.7)} 0 1 0 ${f1(-2 * r1)} 0Z`;
                continue;
            }
            if (maxW < 2.6) {
                const key = maxW < 1.4 ? 'fine1' : maxW < 2 ? 'fine2' : 'fine3';
                groups[key] += curvePath(b.segs);
                continue;
            }
            let d = polyPath(Layout.outline(b, maxW > 14 ? 22 : maxW > 6 ? 14 : 8));
            const r0 = b.w0 / 2, sx = b.segs[0][0], sy = b.segs[0][1];
            d += `M${f1(sx - r0)} ${f1(sy)}a${f1(r0)} ${f1(r0)} 0 1 0 ${f1(2 * r0)} 0a${f1(r0)} ${f1(r0)} 0 1 0 ${f1(-2 * r0)} 0Z`;   // مفصل مستدير عند بداية الغصن
            if (maxW >= 9) groups.thick += d; else groups.mid += d;
            if (maxW >= 4.5) {
                const off = -maxW * 0.16;
                const segs = b.segs.map(s => s.map((v, i) => (i % 2 === 0 ? v + off : v)));
                groups.hl += polyPath(Layout.outline({ kind: b.kind, segs, w0: b.w0 * 0.3, w1: b.w1 * 0.3 }, 7));
            }
        }
        const sw = Math.max(0.6, res.scale);
        wood.setAttribute('viewBox', `0 0 ${W} ${H}`);
        wood.setAttribute('width', W);
        wood.setAttribute('height', H);
        wood.innerHTML = `
<defs>
  <linearGradient id="ntBark" x1="0" y1="0" x2="1" y2="0">
    <stop offset="0" stop-color="#2b1a0e"/><stop offset=".28" stop-color="#5d3b20"/><stop offset=".55" stop-color="#7a5130"/><stop offset=".8" stop-color="#4c2f19"/><stop offset="1" stop-color="#24160b"/>
  </linearGradient>
  <linearGradient id="ntSoil" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2f4a2a"/><stop offset=".12" stop-color="#2a2016"/><stop offset="1" stop-color="#120d08"/></linearGradient>
  <filter id="ntBarkTex" x="0" y="0" width="1" height="1">
    <feTurbulence type="fractalNoise" baseFrequency="0.015 0.22" numOctaves="2" seed="7" result="n"/>
    <feColorMatrix in="n" type="matrix" values="0 0 0 0 0.1  0 0 0 0 0.06  0 0 0 0 0.03  0 0 0 0.55 -0.12" result="streaks"/>
    <feComposite in="streaks" in2="SourceGraphic" operator="in" result="tex"/>
    <feMerge><feMergeNode in="SourceGraphic"/><feMergeNode in="tex"/></feMerge>
  </filter>
</defs>
<g class="nt-ground">${rootsAndGround(res)}</g>
<path class="nt-trunk" d="${trunk}" fill="url(#ntBark)" filter="url(#ntBarkTex)"/>
<path class="nt-w-thick" d="${groups.thick}"/>
<path class="nt-w-mid" d="${groups.mid}"/>
<path class="nt-w-hl" d="${groups.hl}"/>
<path class="nt-w-fine" d="${groups.fine3}" stroke-width="${f1(2.2 * sw)}"/>
<path class="nt-w-fine" d="${groups.fine2}" stroke-width="${f1(1.7 * sw)}"/>
<path class="nt-w-fine" d="${groups.fine1}" stroke-width="${f1(1.2 * sw)}"/>
<path id="ntLine" class="nt-line" d=""/>`;
    }

    function renderCanopy(res) {
        const f = 3, W = Math.ceil(res.width / f), H = Math.ceil(res.height / f);
        canopy.width = W; canopy.height = H;
        canopy.hidden = !state.foliage;
        if (!state.foliage) return;
        const ctx = canopy.getContext('2d');
        ctx.clearRect(0, 0, W, H);
        const tones = ['rgba(86,160,92,', 'rgba(64,140,96,', 'rgba(110,170,80,'];
        for (const c of res.cards) {
            if (c.isRoot) continue;
            const p = state.people.get(c.id);
            const r = (Math.max(c.w, c.h) * 0.85 + 16 * res.scale) / f;
            const x = c.cx / f, y = c.cy / f;
            const tone = p && p.status === 'Deceased' ? 'rgba(196,150,62,' : tones[c.id % 3];
            const g = ctx.createRadialGradient(x, y, 0, x, y, r);
            g.addColorStop(0, tone + '0.20)');
            g.addColorStop(1, tone + '0)');
            ctx.fillStyle = g;
            ctx.fillRect(x - r, y - r, r * 2, r * 2);
        }
    }

    function cardClass(c, p) {
        let cls = 'nt-leaf';
        cls += p && p.gender === 'Female' ? ' is-f' : ' is-m';
        cls += p && p.status === 'Deceased' ? ' is-dead' : ' is-alive';
        if (c.children) cls += ' has-kids';
        if (c.isRoot) cls += ' is-root';
        return cls;
    }
    function cardInner(c, p) {
        const name = p ? p.name : '';
        const sub = c.isRoot ? (T.founder || '') : (p && p.birth_year ? String(p.birth_year) : '');
        return `<span class="n" dir="auto">${esc(name)}</span>${sub ? `<span class="y">${esc(sub)}</span>` : ''}`;
    }
    function renderLeaves(res, animate) {
        const fs = 15 * res.scale;
        leavesEl.style.setProperty('--fs', f1(fs) + 'px');
        const seen = new Set();
        const frag = document.createDocumentFragment();
        for (const c of res.cards) {
            const p = state.people.get(c.id);
            let el = state.cards.get(c.id);
            const fresh = !el;
            if (fresh) {
                el = document.createElement('button');
                el.type = 'button';
                el.dataset.id = c.id;
                state.cards.set(c.id, el);
                frag.appendChild(el);
            }
            el.className = cardClass(c, p) + (fresh && animate ? ' is-new' : '');
            el.innerHTML = cardInner(c, p);
            el.title = p ? p.name : '';
            el.setAttribute('aria-label', (p ? p.name : '') + (p && p.birth_year ? ' ' + p.birth_year : '') + ', ' + (T.generation || '') + ' ' + (c.depth + 1));
            el.style.width = f1(c.w) + 'px';
            el.style.height = f1(c.h) + 'px';
            el.style.transform = `translate(${f1(c.x)}px, ${f1(c.y)}px)`;
            seen.add(c.id);
        }
        leavesEl.appendChild(frag);
        for (const [id, el] of state.cards) {
            if (seen.has(id)) continue;
            state.cards.delete(id);
            if (animate && !reduceMotion) { el.classList.add('is-gone'); setTimeout(() => el.remove(), 500); } else el.remove();
        }
        if (animate) requestAnimationFrame(() => requestAnimationFrame(() => { for (const el of leavesEl.querySelectorAll('.is-new')) el.classList.remove('is-new'); }));
    }

    function render(res, animate) {
        state.result = res;
        world.style.width = res.width + 'px';
        world.style.height = res.height + 'px';
        world.classList.toggle('is-animating', !!animate && !reduceMotion);
        world.classList.toggle('no-years', !state.years);
        world.classList.toggle('is-big', res.cards.length > 1200);
        if (animate && !reduceMotion) { wood.classList.add('is-fading'); setTimeout(() => wood.classList.remove('is-fading'), 30); }
        renderWood(res);
        renderCanopy(res);
        renderLeaves(res, animate);
        if (animate) setTimeout(() => world.classList.remove('is-animating'), 750);
        updateSummary();
        buildRootSelect();
        if (state.selected && !state.cards.has(state.selected) && !state.people.has(state.selected)) closePanel();
        else if (state.selected) highlight(state.selected);
    }

    function updateSummary() {
        const r = state.result;
        if (!r || !r.cards.length) { $('ntSummary').textContent = state.data && !(state.data.people || []).length ? T.empty : ''; return; }
        const px = n => Number(n).toLocaleString(CFG.lang === 'en' ? 'en-US' : 'ar-EG', { useGrouping: false });
        $('ntSummary').textContent = `${fmt(r.stats.count)} ${T.people} · ${fmt(r.stats.generations)} ${T.generations} · ${(T.widthOf || '%1 / %2').replace('%1', px(r.width)).replace('%2', px(MAX_WIDTH))}`;
    }

    function buildRootSelect() {
        const sel = $('ntRoot'), field = $('ntRootField');
        const roots = state.forest ? state.forest.roots.filter(r => r.size > 1) : [];
        const primary = state.forest && state.forest.primary;
        const options = primary && !roots.includes(primary) ? [primary].concat(roots) : roots;
        field.hidden = options.length < 2;
        if (field.hidden) return;
        const currentRoot = state.result ? state.result.rootId : null;
        sel.innerHTML = options.map(n => `<option value="${n.id}"${n.id === currentRoot ? ' selected' : ''}>${esc(n.person.name)} (${fmt(n.size)})</option>`).join('');
    }

    /* ---------------------------------------------------------------- */
    /* التكبير والسحب                                                    */
    /* ---------------------------------------------------------------- */
    let lodClass = '';
    function applyView() {
        world.style.transform = `translate(${f1(view.x)}px, ${f1(view.y)}px) scale(${view.k})`;   // تحويل ثنائي الأبعاد ليُعاد رسم النص بوضوح بعد التكبير
        const e = view.k * (state.result ? state.result.scale : 1);
        const cls = e < 0.2 ? 'lod-dot' : e < 0.42 ? 'lod-far' : '';
        if (cls !== lodClass) { if (lodClass) world.classList.remove(lodClass); if (cls) world.classList.add(cls); lodClass = cls; }
        world.classList.add('is-moving');
        clearTimeout(applyView.t);
        applyView.t = setTimeout(() => world.classList.remove('is-moving'), 140);
    }
    function vpRect() { return viewport.getBoundingClientRect(); }
    function limits() {
        const r = state.result, rect = vpRect();
        if (!r) return { min: 0.05, max: 4 };
        const fit = Math.min((rect.width - 24) / r.width, (rect.height - 24) / r.height);
        return { min: Math.min(fit * 0.7, 1), max: Math.max(3, 2.6 / r.scale) };
    }
    function clampView(v) {
        const r = state.result, rect = vpRect();
        if (!r) return v;
        const lim = limits();
        v.k = clamp(v.k, lim.min, lim.max);
        // لا تختفي الشجرة خارج الشاشة: يبقى جزء منها ظاهراً دائماً
        const w = r.width * v.k, h = r.height * v.k, mx = rect.width / 2, my = rect.height / 2;
        v.x = clamp(v.x, Math.min(mx - w, (rect.width - w) / 2), Math.max(mx, (rect.width - w) / 2));
        v.y = clamp(v.y, Math.min(my - h, (rect.height - h) / 2), Math.max(my, (rect.height - h) / 2));
        return v;
    }
    function fitView() {
        const r = state.result, rect = vpRect();
        const k = Math.min((rect.width - 24) / r.width, (rect.height - 24) / r.height);
        return { k, x: (rect.width - r.width * k) / 2, y: (rect.height - r.height * k) / 2 };
    }
    let anim = null;
    function animateTo(target, ms) {
        cancelAnimationFrame(anim);
        stopInertia();
        const t = clampView(Object.assign({}, target));
        if (reduceMotion || !ms) { Object.assign(view, t); applyView(); return; }
        const from = Object.assign({}, view), start = performance.now();
        const ease = p => (p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2);
        const step = now => {
            const p = Math.min(1, (now - start) / ms), e = ease(p);
            // تكبير لوغاريتمي حتى تبدو الحركة متساوية السرعة
            view.k = Math.exp(Math.log(from.k) + (Math.log(t.k) - Math.log(from.k)) * e);
            view.x = from.x + (t.x - from.x) * e;
            view.y = from.y + (t.y - from.y) * e;
            applyView();
            if (p < 1) anim = requestAnimationFrame(step);
        };
        anim = requestAnimationFrame(step);
    }
    function zoomAt(px, py, factor, ms) {
        const lim = limits();
        const k = clamp(view.k * factor, lim.min, lim.max);
        const target = { k, x: px - (px - view.x) * (k / view.k), y: py - (py - view.y) * (k / view.k) };
        if (ms) animateTo(target, ms); else { Object.assign(view, clampView(target)); applyView(); }
    }
    function centerOn(id, ms, zoom) {
        const c = state.result && state.result.cards.find(x => x.id === id);
        if (!c) return;
        const rect = vpRect();
        const readable = clamp(1.05 / state.result.scale, limits().min, limits().max);
        const k = zoom === false ? view.k : Math.max(view.k, readable);
        let cx = rect.width / 2, cy = rect.height / 2;
        if (!panel.hidden) {
            const pr = panel.getBoundingClientRect();
            if (pr.width < rect.width * 0.7) cx = pr.left > rect.width / 2 ? pr.left / 2 : (pr.right + rect.width) / 2;
            else cy = Math.max(90, (pr.top - rect.top) / 2);
        }
        animateTo({ k, x: cx - c.cx * k, y: cy - c.cy * k }, ms === undefined ? 520 : ms);
    }

    let inertia = null;
    function stopInertia() { cancelAnimationFrame(inertia); inertia = null; }
    const pointers = new Map();
    let gesture = null, lastTap = null;
    const hintEl = $('ntHint');
    function hideHint() { if (hintEl && !hintEl.hidden) { hintEl.hidden = true; store.set('hint', '0'); } }
    if (store.get('hint', '1') === '0' && hintEl) hintEl.hidden = true;
    viewport.addEventListener('pointerdown', e => {
        if (e.pointerType === 'mouse' && e.button !== 0) return;
        hideHint();
        if (e.target.closest('.nt-hint')) return;
        cancelAnimationFrame(anim);
        stopInertia();
        viewport.setPointerCapture(e.pointerId);
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        startGesture(e);
    });
    function startGesture(e) {
        const pts = [...pointers.values()];
        const rect = vpRect();
        if (pts.length === 1) gesture = { type: 'pan', sx: pts[0].x, sy: pts[0].y, vx: view.x, vy: view.y, moved: 0, t: performance.now(), hist: [], target: e.target };
        else if (pts.length === 2) {
            const [a, b] = pts;
            gesture = { type: 'pinch', d: Math.hypot(a.x - b.x, a.y - b.y) || 1, mx: (a.x + b.x) / 2 - rect.left, my: (a.y + b.y) / 2 - rect.top, k: view.k, vx: view.x, vy: view.y, moved: 99 };
        }
    }
    viewport.addEventListener('pointermove', e => {
        if (!pointers.has(e.pointerId) || !gesture) return;
        pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
        const pts = [...pointers.values()];
        if (gesture.type === 'pan' && pts.length === 1) {
            const dx = e.clientX - gesture.sx, dy = e.clientY - gesture.sy;
            gesture.moved = Math.max(gesture.moved, Math.hypot(dx, dy));
            if (gesture.moved < 4) return;
            state.userMoved = true;
            Object.assign(view, clampView({ k: view.k, x: gesture.vx + dx, y: gesture.vy + dy }));
            applyView();
            const now = performance.now();
            gesture.hist.push({ t: now, x: e.clientX, y: e.clientY });
            if (gesture.hist.length > 6) gesture.hist.shift();
        } else if (gesture.type === 'pinch' && pts.length >= 2) {
            const [a, b] = pts, rect = vpRect();
            const d = Math.hypot(a.x - b.x, a.y - b.y) || 1;
            const mx = (a.x + b.x) / 2 - rect.left, my = (a.y + b.y) / 2 - rect.top;
            const lim = limits();
            const k = clamp(gesture.k * d / gesture.d, lim.min, lim.max);
            const wx = (gesture.mx - gesture.vx) / gesture.k, wy = (gesture.my - gesture.vy) / gesture.k;
            state.userMoved = true;
            Object.assign(view, clampView({ k, x: mx - wx * k, y: my - wy * k }));
            applyView();
        }
    });
    function endPointer(e) {
        if (!pointers.has(e.pointerId)) return;
        pointers.delete(e.pointerId);
        const g = gesture;
        if (pointers.size === 1) { startGesture(e); if (gesture) gesture.moved = 99; return; }
        gesture = null;
        if (!g || e.type === 'pointercancel') return;
        if (g.type === 'pan' && g.moved < 6 && performance.now() - g.t < 600) { handleTap(e); return; }
        if (g.type === 'pan' && g.hist.length >= 2 && !reduceMotion) {
            const a = g.hist[0], b = g.hist[g.hist.length - 1], dt = Math.max(16, b.t - a.t);
            let vx = (b.x - a.x) / dt * 16, vy = (b.y - a.y) / dt * 16;
            if (performance.now() - b.t > 80 || Math.hypot(vx, vy) < 2) return;
            const step = () => {
                vx *= 0.92; vy *= 0.92;
                Object.assign(view, clampView({ k: view.k, x: view.x + vx, y: view.y + vy }));
                applyView();
                if (Math.hypot(vx, vy) > 0.4) inertia = requestAnimationFrame(step);
            };
            inertia = requestAnimationFrame(step);
        }
    }
    viewport.addEventListener('pointerup', endPointer);
    viewport.addEventListener('pointercancel', endPointer);
    function handleTap(e) {
        const el = document.elementFromPoint(e.clientX, e.clientY);
        const leaf = el && el.closest && el.closest('.nt-leaf');
        const now = performance.now(), rect = vpRect();
        if (lastTap && now - lastTap.t < 320 && Math.hypot(e.clientX - lastTap.x, e.clientY - lastTap.y) < 30) {
            lastTap = null;
            state.userMoved = true;
            zoomAt(e.clientX - rect.left, e.clientY - rect.top, 2, 320);
            return;
        }
        lastTap = { t: now, x: e.clientX, y: e.clientY };
        if (leaf) select(Number(leaf.dataset.id), { zoom: false });
    }
    leavesEl.addEventListener('click', e => {
        const leaf = e.target.closest('.nt-leaf');
        if (leaf && e.detail === 0) select(Number(leaf.dataset.id), { center: true });   // لوحة المفاتيح
    });
    viewport.addEventListener('wheel', e => {
        e.preventDefault();
        hideHint();
        cancelAnimationFrame(anim);
        stopInertia();
        state.userMoved = true;
        const rect = vpRect();
        const mouseWheel = e.deltaMode === 1 || (Math.abs(e.deltaY) >= 40 && e.deltaX === 0 && Number.isInteger(e.deltaY));
        if (e.ctrlKey || e.metaKey || mouseWheel) {
            const unit = e.deltaMode === 1 ? 33 : 1;
            zoomAt(e.clientX - rect.left, e.clientY - rect.top, Math.exp(-e.deltaY * unit * (e.ctrlKey ? 0.01 : 0.0018)));
        } else {
            Object.assign(view, clampView({ k: view.k, x: view.x - e.deltaX, y: view.y - e.deltaY }));
            applyView();
        }
    }, { passive: false });
    viewport.addEventListener('keydown', e => {
        if (e.target !== viewport && !e.target.classList.contains('nt-leaf')) return;
        const rect = vpRect(), step = 80;
        const map = { ArrowLeft: [step, 0], ArrowRight: [-step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] };
        if (map[e.key] && e.target === viewport) { e.preventDefault(); animateTo({ k: view.k, x: view.x + map[e.key][0], y: view.y + map[e.key][1] }, 160); }
        else if (e.key === '+' || e.key === '=') { e.preventDefault(); zoomAt(rect.width / 2, rect.height / 2, 1.4, 220); }
        else if (e.key === '-' || e.key === '_') { e.preventDefault(); zoomAt(rect.width / 2, rect.height / 2, 1 / 1.4, 220); }
        else if (e.key === '0') { e.preventDefault(); animateTo(fitView(), 380); }
    });

    document.querySelector('.nt-zoom').addEventListener('click', e => {
        const btn = e.target.closest('[data-act]');
        if (!btn || !state.result) return;
        const rect = vpRect();
        state.userMoved = true;
        switch (btn.dataset.act) {
            case 'in': zoomAt(rect.width / 2, rect.height / 2, 1.5, 260); break;
            case 'out': zoomAt(rect.width / 2, rect.height / 2, 1 / 1.5, 260); break;
            case 'fit': animateTo(fitView(), 420); break;
            case 'root': if (state.result.rootId) centerOn(state.result.rootId); break;
            case 'full':
                if (!document.fullscreenElement && document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(() => {});
                else if (document.exitFullscreen) document.exitFullscreen().catch(() => {});
                break;
        }
    });

    /* ---------------------------------------------------------------- */
    /* اختيار شخص: إبراز نسبه حتى المؤسس + لوحة التفاصيل                     */
    /* ---------------------------------------------------------------- */
    function chainIds(id) {
        const links = state.result ? state.result.links : {};
        const chain = [id];
        let cur = id, guard = 0;
        while (links[cur] !== undefined && guard++ < 10000) { cur = links[cur]; chain.push(cur); }
        return chain;
    }
    function highlight(id) {
        const r = state.result;
        for (const el of leavesEl.querySelectorAll('.is-line, .is-sel')) el.classList.remove('is-line', 'is-sel');
        const line = $('ntLine');
        if (!r || !line || !state.cards.has(id)) { if (line) line.setAttribute('d', ''); world.classList.remove('has-sel'); return; }
        const chain = chainIds(id);
        const inChain = new Set(chain);
        const byTo = new Map(), stems = new Map();
        for (const b of r.branches) {
            if (b.kind === 'stem') stems.set(b.from, b);
            else if (b.kind !== 'trunk' && b.kind !== 'knot') byTo.set(b.to, b);
        }
        let d = '';
        for (let i = 0; i < chain.length - 1; i++) {
            const child = chain[i], parent = chain[i + 1];
            const stem = stems.get(parent);
            if (stem && stem.to === child) { d += curvePath(stem.segs); continue; }
            const b = byTo.get(child);
            if (!b) continue;
            if (b.kind === 'petiole' && stem) {
                const s = stem.segs[0], y = b.segs[0][1];
                d += `M${f1(s[0])} ${f1(s[1])}L${f1(s[0])} ${f1(y)}`;
            }
            d += curvePath(b.segs);
            if (typeof child === 'string') { const knot = r.branches.find(x => x.kind === 'knot' && x.from === child); if (knot) d += curvePath(knot.segs); }
        }
        line.setAttribute('d', d);
        for (const cid of inChain) { const el = state.cards.get(cid); if (el) el.classList.add('is-line'); }
        const el = state.cards.get(id);
        if (el) el.classList.add('is-sel');
        world.classList.add('has-sel');
    }

    function personName(id) { const p = state.people.get(id); return p ? p.name : ''; }
    function lineageText(id) {
        const parts = [];
        let p = state.people.get(id), guard = 0;
        while (p && guard++ < 40) {
            parts.push(p.name);
            const next = state.people.get(p.father_id) || null;
            if (!next) break;
            parts.push(p.gender === 'Female' ? T.daughter : T.son);
            p = next;
        }
        return parts.join(' ');
    }
    function personLink(id) {
        const p = state.people.get(id);
        if (!p) return esc(T.none);
        return `<button type="button" class="nt-link" data-go="${p.id}">${esc(p.name)}</button>`;
    }
    function descendants(id) {
        const node = state.forest && state.forest.byId.get(id);
        return node ? node.size - 1 : 0;
    }
    function renderPanel(id) {
        const p = state.people.get(id);
        if (!p) { closePanel(); return; }
        const card = state.result && state.result.cards.find(c => c.id === id);
        const node = state.forest.byId.get(id);
        const kids = (state.childrenOf.get(id) || []).map(cid => state.people.get(cid)).filter(Boolean)
            .sort((a, b) => String(a.birth_date || '9999').localeCompare(String(b.birth_date || '9999')) || a.id - b.id);
        const spouses = (state.spouses.get(id) || []).filter(sid => state.people.has(sid));
        const chips = [];
        if (card) chips.push(`${esc(T.generation)} ${fmt(card.depth + 1)}`);
        if (card && card.isRoot) chips.push(esc(T.founder));
        chips.push(esc(p.status === 'Deceased' ? T.deceased : T.alive));
        chips.push(esc(p.gender === 'Female' ? T.female : T.male));
        const kidList = kids.slice(0, 24).map(k => personLink(k.id)).join('');
        const moreKids = kids.length > 24 ? `<span class="nt-more">${esc((T.more || '').replace('%d', fmt(kids.length - 24)))}</span>` : '';
        const photo = p.image_url ? `<img class="nt-photo" src="${esc(p.image_url)}" alt="" loading="lazy" onerror="this.remove()">` : '';
        const notDrawn = !card ? `<p class="nt-warn">${esc(node && state.result && Layout.topRootOf(node).id !== state.result.rootId ? T.otherTree : T.notInTree)}</p>` : '';
        panelBody.innerHTML = `
            <div class="nt-panel-head">${photo}<div><h2 id="ntPanelName" dir="auto">${esc(p.name)}</h2>${p.alt ? `<p class="nt-alt" dir="auto">${esc(p.alt)}</p>` : ''}</div></div>
            <p class="nt-chips">${chips.map(c => `<span>${c}</span>`).join('')}</p>
            ${notDrawn}
            <dl class="nt-facts">
                <dt>${esc(T.lineage)}</dt><dd dir="auto">${esc(lineageText(id))}</dd>
                <dt>${esc(T.father)}</dt><dd>${p.father_id ? personLink(p.father_id) : esc(T.none)}</dd>
                <dt>${esc(T.mother)}</dt><dd>${p.mother_id ? personLink(p.mother_id) : esc(T.none)}</dd>
                <dt>${esc(T.spouses)}</dt><dd>${spouses.length ? spouses.map(personLink).join('') : esc(T.none)}</dd>
                <dt>${esc(T.birth)}</dt><dd dir="ltr">${esc(p.birth_date || T.none)}</dd>
                <dt>${esc(T.children)}</dt><dd>${kids.length ? `<span class="nt-count">${fmt(kids.length)}</span>${kidList}${moreKids}` : esc(T.none)}</dd>
                <dt>${esc(T.descendants)}</dt><dd>${fmt(descendants(id))}</dd>
            </dl>
            <div class="nt-actions">
                ${card ? `<button type="button" class="nt-btn" data-focus="${id}">${esc(T.focus)}</button>` : ''}
                <a class="nt-btn" href="tree.php?id=${id}">${esc(T.classic)}</a>
                <a class="nt-btn" href="relationship.php?first=${id}">${esc(T.relation)}</a>
                ${CFG.admin ? `<a class="nt-btn nt-primary" href="add_person.php?edit=${id}">${esc(T.edit)}</a>` : ''}
            </div>`;
    }
    function select(id, opts) {
        if (!state.people.has(id)) return;
        const node = state.forest.byId.get(id);
        if (node && state.result && !state.cards.has(id)) {
            const top = Layout.topRootOf(node);
            if (top && top.size > 1 && top.id !== state.result.rootId) {      // الشخص في شجرة أخرى: ننتقل إليها
                state.rootId = top.id;
                state.selected = id;
                relayout(false).then(() => select(id, opts));
                return;
            }
        }
        state.selected = id;
        panel.hidden = false;
        document.body.classList.add('panel-open');
        renderPanel(id);
        highlight(id);
        syncUrl();
        if (state.cards.has(id) && (!opts || opts.center !== false)) centerOn(id, undefined, opts && opts.zoom === false ? false : undefined);
    }
    function closePanel() {
        panel.hidden = true;
        document.body.classList.remove('panel-open');
        state.selected = null;
        highlight(null);
        syncUrl();
    }
    function syncUrl() {
        try {
            const url = new URL(location.href);
            if (state.selected) url.searchParams.set('id', state.selected); else url.searchParams.delete('id');
            if (state.result && state.forest && state.forest.primary && state.result.rootId !== state.forest.primary.id) url.searchParams.set('root', state.result.rootId);
            else url.searchParams.delete('root');
            history.replaceState(null, '', url);
        } catch (e) { /* ignore */ }
    }
    $('ntPanelClose').addEventListener('click', closePanel);
    panelBody.addEventListener('click', e => {
        const go = e.target.closest('[data-go]');
        if (go) { select(Number(go.dataset.go)); return; }
        const focus = e.target.closest('[data-focus]');
        if (focus) centerOn(Number(focus.dataset.focus));
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            if (!resultsEl.hidden) { hideResults(); return; }
            if (!$('ntSettings').hidden) { toggleSettings(false); return; }
            if (!panel.hidden) closePanel();
        }
    });

    /* ---------------------------------------------------------------- */
    /* البحث                                                             */
    /* ---------------------------------------------------------------- */
    const normalize = s => String(s || '').normalize('NFKD').toLowerCase()
        .replace(/[ً-ٰٟـ]/g, '').replace(/[أإآٱ]/g, 'ا').replace(/[ىيیئ]/g, 'ي').replace(/[ةە]/g, 'ه')
        .replace(/ک/g, 'ك').replace(/[ۆ]/g, 'و').replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
    const STOP = new Set(['بن', 'ابن', 'بنت', 'كور', 'كچ']);
    let activeResult = -1, resultIds = [];
    function chainLabel(p) {
        const parts = [p.name];
        let cur = p, guard = 0;
        while (guard++ < 2) { cur = state.people.get(cur.father_id); if (!cur) break; parts.push(cur.name); }
        return parts;
    }
    function runSearch() {
        const q = normalize(searchInput.value).split(' ').filter(w => w && !STOP.has(w));
        if (!q.length || !state.people.size) { hideResults(); return; }
        const hits = [];
        for (const p of state.people.values()) {
            const words = normalize(chainLabel(p).join(' ') + ' ' + (p.alt || '')).split(' ');
            let ok = true, score = 0;
            for (let i = 0; i < q.length; i++) {
                const idx = words.findIndex(w => w.startsWith(q[i]));
                if (idx === -1) { if (!words.some(w => w.includes(q[i]))) { ok = false; break; } score += 3; }
                else score += idx === i ? 0 : 1;
            }
            if (ok) hits.push({ p, score });
            if (hits.length > 400) break;
        }
        hits.sort((a, b) => a.score - b.score || a.p.name.localeCompare(b.p.name));
        resultIds = hits.slice(0, 40).map(h => h.p.id);
        activeResult = resultIds.length ? 0 : -1;
        if (!resultIds.length) resultsEl.innerHTML = `<li class="nt-empty" role="option" aria-disabled="true">${esc(T.noResults)}</li>`;
        else resultsEl.innerHTML = resultIds.map((id, i) => {
            const p = state.people.get(id), chain = chainLabel(p);
            const drawn = state.cards.has(id);
            return `<li id="ntOpt${i}" role="option" data-id="${id}" aria-selected="${i === 0}"><span class="n" dir="auto">${esc(chain[0])}</span>${chain.length > 1 ? `<span class="c" dir="auto">${esc(chain.slice(1).join(' · '))}</span>` : ''}${drawn ? '' : `<span class="o">${esc(T.otherTree)}</span>`}</li>`;
        }).join('');
        resultsEl.hidden = false;
        searchInput.setAttribute('aria-expanded', 'true');
        if (activeResult >= 0) searchInput.setAttribute('aria-activedescendant', 'ntOpt0'); else searchInput.removeAttribute('aria-activedescendant');
    }
    function hideResults() { resultsEl.hidden = true; searchInput.setAttribute('aria-expanded', 'false'); searchInput.removeAttribute('aria-activedescendant'); }
    function moveActive(delta) {
        if (!resultIds.length) return;
        activeResult = (activeResult + delta + resultIds.length) % resultIds.length;
        resultsEl.querySelectorAll('[role=option]').forEach((li, i) => li.setAttribute('aria-selected', String(i === activeResult)));
        searchInput.setAttribute('aria-activedescendant', 'ntOpt' + activeResult);
        const li = $('ntOpt' + activeResult);
        if (li) li.scrollIntoView({ block: 'nearest' });
    }
    function pickResult(id) {
        hideResults();
        searchInput.blur();
        select(id);
    }
    let searchTimer = 0;
    searchInput.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(runSearch, 90); });
    searchInput.addEventListener('focus', () => { if (searchInput.value.trim()) runSearch(); });
    searchInput.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (resultsEl.hidden) runSearch(); else moveActive(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); moveActive(-1); }
        else if (e.key === 'Enter') { e.preventDefault(); if (activeResult >= 0 && resultIds[activeResult]) pickResult(resultIds[activeResult]); }
    });
    resultsEl.addEventListener('pointerdown', e => e.preventDefault());
    resultsEl.addEventListener('click', e => { const li = e.target.closest('[data-id]'); if (li) pickResult(Number(li.dataset.id)); });
    document.addEventListener('pointerdown', e => { if (!e.target.closest('.nt-search')) hideResults(); });

    /* ---------------------------------------------------------------- */
    /* الخيارات                                                          */
    /* ---------------------------------------------------------------- */
    function toggleSettings(open) {
        const box = $('ntSettings'), btn = $('ntSettingsBtn');
        const show = open === undefined ? box.hidden : open;
        box.hidden = !show;
        btn.setAttribute('aria-expanded', String(show));
    }
    $('ntSettingsBtn').addEventListener('click', () => toggleSettings());
    document.addEventListener('pointerdown', e => { if (!$('ntSettings').hidden && !e.target.closest('#ntSettings, #ntSettingsBtn')) toggleSettings(false); });
    $('ntDensity').value = state.density;
    $('ntDensity').addEventListener('change', e => { state.density = ASPECT[e.target.value] ? e.target.value : 'tree'; store.set('density', state.density); state.userMoved = false; relayout(false); });
    $('ntRoot').addEventListener('change', e => { state.rootId = toId(e.target.value); state.userMoved = false; closePanel(); relayout(false); });
    $('ntYears').checked = state.years;
    $('ntYears').addEventListener('change', e => { state.years = e.target.checked; store.set('years', state.years ? '1' : '0'); world.classList.toggle('no-years', !state.years); });
    $('ntFoliage').checked = state.foliage;
    $('ntFoliage').addEventListener('change', e => { state.foliage = e.target.checked; store.set('foliage', state.foliage ? '1' : '0'); if (state.result) renderCanopy(state.result); });

    /* ---------------------------------------------------------------- */
    /* التحميل والتحديث التلقائي                                           */
    /* ---------------------------------------------------------------- */
    let toastTimer = 0;
    function toast(text) {
        toastEl.textContent = text;
        toastEl.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toastEl.hidden = true; }, 4200);
    }
    function showLoading(on, text) {
        loading.hidden = !on;
        if (text) $('ntLoadingText').textContent = text;
    }
    function showError() {
        loading.hidden = false;
        loading.classList.add('is-error');
        $('ntLoadingText').innerHTML = `${esc(T.loadError)} <button type="button" class="nt-btn" id="ntRetry">${esc(T.retry)}</button>`;
        $('ntRetry').addEventListener('click', () => { loading.classList.remove('is-error'); $('ntLoadingText').textContent = T.loading; load(); });
    }

    async function relayout(animate) {
        if (!state.data) return;
        if (state.rootId && !state.forest.byId.has(state.rootId)) state.rootId = null;
        const before = state.result;
        let anchor = null;
        if (before && animate) {   // نحافظ على موضع الشخص المختار (أو المؤسس) على الشاشة
            const id = state.selected && state.cards.has(state.selected) ? state.selected : before.rootId;
            const c = before.cards.find(x => x.id === id);
            if (c) anchor = { id, sx: view.x + c.cx * view.k, sy: view.y + c.cy * view.k };
        }
        if (!animate) showLoading(true, T.loading);
        const res = await computeLayout();
        render(res, animate);
        showLoading(false);
        if (anchor) {
            const c = res.cards.find(x => x.id === anchor.id);
            if (c) { Object.assign(view, clampView({ k: view.k, x: anchor.sx - c.cx * view.k, y: anchor.sy - c.cy * view.k })); applyView(); return; }
        }
        if (state.selected && state.cards.has(state.selected)) { Object.assign(view, clampView(fitView())); applyView(); centerOn(state.selected, 0); }
        else if (!state.userMoved || !before) { Object.assign(view, clampView(fitView())); applyView(); }
    }

    async function load() {
        showLoading(true, T.loading);
        try {
            const data = await fetchJSON(CFG.dataUrl);
            if (!data || !Array.isArray(data.people)) throw new Error('bad data');
            if (document.fonts && document.fonts.ready) await Promise.race([document.fonts.ready, new Promise(r => setTimeout(r, 2500))]);
            indexData(data);
            await relayout(false);
            if (state.selected && state.people.has(state.selected)) select(state.selected);
            schedulePoll();
        } catch (error) {
            if (String(error && error.message) !== 'auth') showError();
        }
    }

    let pollTimer = 0;
    function schedulePoll() { clearTimeout(pollTimer); pollTimer = setTimeout(checkForChanges, POLL_MS); }
    async function checkForChanges() {
        if (document.hidden || state.busy || !state.data) { schedulePoll(); return; }
        state.busy = true;
        try {
            const sig = await fetchJSON(CFG.dataUrl + '?sig=1');
            if (sig && sig.sig && sig.sig !== state.sig) {
                const data = await fetchJSON(CFG.dataUrl);
                const oldIds = new Set(state.people.keys());
                const newIds = new Set((data.people || []).map(p => p.id));
                let added = 0, removed = 0;
                for (const id of newIds) if (!oldIds.has(id)) added++;
                for (const id of oldIds) if (!newIds.has(id)) removed++;
                indexData(data);
                await relayout(true);
                if (state.selected) { if (state.people.has(state.selected)) renderPanel(state.selected); else closePanel(); }
                const parts = [];
                if (added) parts.push(`${T.added} ${fmt(added)}`);
                if (removed) parts.push(`${T.removed} ${fmt(removed)}`);
                toast(`${T.updated}: ${parts.length ? parts.join('، ') : T.changed}`);
            }
        } catch (e) { /* نحاول مجدداً في الدورة القادمة */ }
        state.busy = false;
        schedulePoll();
    }
    document.addEventListener('visibilitychange', () => { if (!document.hidden && state.data) checkForChanges(); });
    window.addEventListener('pageshow', e => { if (e.persisted && state.data) checkForChanges(); });

    if (window.ResizeObserver) {
        let last = null;
        new ResizeObserver(() => {
            if (!state.result) return;
            const rect = vpRect();
            if (last && Math.abs(last.w - rect.width) < 2 && Math.abs(last.h - rect.height) < 2) return;
            if (!state.userMoved) { Object.assign(view, clampView(fitView())); applyView(); }
            else if (last) { view.x += (rect.width - last.w) / 2; view.y += (rect.height - last.h) / 2; Object.assign(view, clampView(view)); applyView(); }
            last = { w: rect.width, h: rect.height };
        }).observe(viewport);
    }

    load();
})();
