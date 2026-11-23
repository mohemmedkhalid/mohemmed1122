/**
 * assets/natural-tree-layout.js
 * محرك توزيع «الشجرة الطبيعية» للعائلة.
 *
 * - المؤسس على الجذع في الأسفل، والأجيال تصعد لتكوّن التاج.
 * - العرض الكلي لا يتجاوز maxWidth (2000 بكسل افتراضياً) مهما زاد عدد الأفراد.
 * - كل فرع عائلي يأخذ مساحة خاصة به (contour packing) فلا تتداخل البطاقات ولا تتقاطع الأغصان.
 * - عند ضيق العرض تتحول العائلة الكبيرة من مروحة أفقية إلى غصن صاعد تتوزع عليه
 *   البطاقات يميناً ويساراً بشكل متدرج عمودياً، ثم تصغر البطاقات تلقائياً إذا لزم.
 *
 * لا يعتمد على DOM، ويعمل في المتصفح وفي Node (node --test tests/natural-tree-layout.test.js).
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else root.NaturalTreeLayout = factory();
})(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    const DEFAULTS = {
        maxWidth: 2000,      // الحد الأقصى لعرض الشجرة بالبكسل
        margin: 24,          // هامش جانبي ثابت داخل الـ 2000
        topPad: 36,
        bottomPad: 18,
        unit: 29,            // ارتفاع الصف الأساسي (البطاقة تشغل صفين)
        cardRows: 2,
        cardH: 44,
        hGap: 14,            // أقل مسافة أفقية بين بطاقتين أو فرعين
        minCardW: 78,
        maxCardW: 200,
        cardPad: 30,
        corridorMin: 18,     // ممر الغصن الصاعد بين جهتي العائلة
        leafStackMin: 5,     // عدد الأبناء بلا أحفاد الذي يجعلهم عنقوداً صاعداً بدل صف أفقي
        fanMax: 7,           // أكثر من هذا العدد من الأبناء يتحول إلى غصن صاعد
        twigMax: 12,         // أكثر من هذا العدد من الأبناء يتوزع على غُصينات
        twigSize: 7,
        aspect: 1.3,         // أقصى نسبة ارتفاع/عرض مفضلة قبل تصغير البطاقات
        scales: [1, 0.9, 0.8, 0.71, 0.63, 0.56, 0.5, 0.44, 0.39, 0.34, 0.3, 0.26, 0.23, 0.2, 0.17],
        groundGap: 34,       // المسافة بين بطاقة المؤسس والأرض
        rootDepth: 70,       // عمق الجذور تحت الأرض
        measure: null        // دالة (person) => عرض الاسم بوحدات التخطيط
    };

    const BUCKET_BASE = 40;
    const BUCKET_STEP = Math.log(1.18);
    const idNum = n => (typeof n.id === 'number' ? n.id : 1e12 + Number(String(n.id).slice(1)));

    function toId(value) {
        if (value === null || value === undefined || value === '') return null;
        const n = Number(value);
        return Number.isFinite(n) && n > 0 ? n : null;
    }

    function birthKey(person) {
        const raw = person.birth_date || person.birth_year || '';
        const year = parseInt(String(raw).slice(0, 4), 10);
        return Number.isFinite(year) && year > 0 ? String(raw) : '9999';
    }

    function defaultMeasure(person) {
        const name = String(person.name || person.full_name || '');
        return Math.max(name.length, 2) * 8.6;
    }

    /* ------------------------------------------------------------------ */
    /*  بناء الغابة: كل شخص يظهر مرة واحدة تحت أبيه، أو تحت أمه إذا لم يكن الأب مسجلاً */
    /* ------------------------------------------------------------------ */
    function buildForest(people) {
        const byId = new Map();
        for (const person of people || []) {
            const id = toId(person && person.id);
            if (!id || byId.has(id)) continue;
            byId.set(id, { id, person, children: [], parent: null, depth: 0, size: 1, height: 0, key: birthKey(person) });
        }
        for (const node of byId.values()) {
            const f = toId(node.person.father_id), m = toId(node.person.mother_id);
            node.parent = (f && f !== node.id && byId.get(f)) || (m && m !== node.id && byId.get(m)) || null;
        }
        // قطع أي حلقة ناتجة عن بيانات خاطئة (شخص جدّ لنفسه)
        const state = new Map();
        for (const start of byId.values()) {
            const chain = [];
            let node = start;
            while (node && !state.has(node.id)) { state.set(node.id, 1); chain.push(node); node = node.parent; }
            if (node && state.get(node.id) === 1) node.parent = null;
            for (const item of chain) state.set(item.id, 2);
        }
        for (const node of byId.values()) if (node.parent) node.parent.children.push(node);
        const roots = [];
        for (const node of byId.values()) {
            node.children.sort((a, b) => (a.key < b.key ? -1 : a.key > b.key ? 1 : a.id - b.id));
            if (!node.parent) roots.push(node);
        }
        // العمق والحجم والارتفاع بدون تكرار ذاتي عميق
        for (const r of roots) {
            const order = [r];
            r.depth = 0;
            for (let i = 0; i < order.length; i++) {
                for (const c of order[i].children) { c.depth = order[i].depth + 1; order.push(c); }
            }
            for (let i = order.length - 1; i >= 0; i--) {
                const n = order[i];
                n.size = 1; n.height = 0;
                for (const c of n.children) { n.size += c.size; n.height = Math.max(n.height, c.height + 1); }
            }
        }
        roots.sort((a, b) => b.size - a.size || a.id - b.id);
        const flagged = [...byId.values()].filter(n => Number(n.person.is_primary_ancestor || n.person.primary || 0) === 1)
            .sort((a, b) => b.size - a.size || a.id - b.id);
        const primary = flagged[0] || roots[0] || null;
        return { byId, roots, primary };
    }

    function topRootOf(node) {
        let n = node;
        while (n && n.parent) n = n.parent;
        return n;
    }

    /* ------------------------------------------------------------------ */
    /*  الأشكال (Shapes): لكل فرع حدود يسار/يمين لكل صف، نسبةً إلى بطاقة رأسه     */
    /* ------------------------------------------------------------------ */
    function Contour() { this.L = []; this.R = []; }
    Contour.prototype.add = function (row, l, r) {
        const L = this.L, R = this.R;
        while (L.length <= row) { L.push(Infinity); R.push(-Infinity); }
        if (l < L[row]) L[row] = l;
        if (r > R[row]) R[row] = r;
    };
    Contour.prototype.merge = function (shape, dx, dr) {
        for (let k = 0; k < shape.H; k++) if (shape.L[k] !== Infinity) this.add(dr + k, shape.L[k] + dx, shape.R[k] + dx);
    };
    Contour.prototype.finish = function () {
        let minL = Infinity, maxR = -Infinity;
        for (let i = 0; i < this.L.length; i++) {
            if (this.L[i] < minL) minL = this.L[i];
            if (this.R[i] > maxR) maxR = this.R[i];
        }
        return { L: this.L, R: this.R, H: this.L.length, minL, maxR, width: maxR - minL };
    };

    function createEngine(opts, forest, root) {
        const P = Object.assign({}, DEFAULTS, opts || {});
        const U = P.unit, CR = P.cardRows, CH = Math.min(P.cardH, CR * U - 8);
        const measure = typeof P.measure === 'function' ? P.measure : defaultMeasure;
        const total = Math.max(root.size, 1);
        const trunkTop = Math.min(110, Math.max(18, 10 + 2.2 * Math.sqrt(total)));
        const widthCache = new Map();
        const natMemo = new Map();
        const memo = new Map();

        /*
         * العائلات الكبيرة جداً (أكثر من twigMax من الأبناء) تُقسم على غُصينات
         * افتراضية بلا بطاقة، كما تتفرع الأغصان الحقيقية قبل أن تحمل أوراقها.
         */
        const kidCache = new Map();
        let virtualSeq = 0;
        function makeTwigs(owner, list) {
            if (list.length <= P.twigMax) return list;
            const groups = Math.ceil(list.length / P.twigSize);
            const per = Math.ceil(list.length / groups);
            const twigs = [];
            for (let i = 0; i < list.length; i += per) {
                const part = list.slice(i, i + per);
                const twig = { id: 'v' + (++virtualSeq), virtual: true, person: null, parent: owner, depth: owner.depth,
                    size: part.reduce((a, n) => a + n.size, 0), children: part };
                kidCache.set(twig.id, part);
                twigs.push(twig);
            }
            return makeTwigs(owner, twigs);
        }
        function kidsOf(node) {
            let k = kidCache.get(node.id);
            if (!k) { k = makeTwigs(node, node.children); kidCache.set(node.id, k); }
            return k;
        }
        const rowsOf = node => (node.virtual ? 1 : CR);
        const chOf = node => (node.virtual ? 12 : CH);

        function cardW(node) {
            let w = widthCache.get(node.id);
            if (w === undefined) {
                if (node.virtual) { w = Math.max(14, thick(node) + 8); widthCache.set(node.id, w); return w; }
                const raw = Number(measure(node.person)) || 0;
                w = Math.max(P.minCardW, Math.min(P.maxCardW, raw + P.cardPad));
                if (node === root) w = Math.min(P.maxCardW + 40, Math.max(w + 24, 150));
                widthCache.set(node.id, w);
            }
            return w;
        }
        function thick(node) {
            if (node === root) return trunkTop;
            return Math.max(1.6, Math.min(trunkTop * 0.92, 1.6 + trunkTop * 0.95 * Math.sqrt(node.size / total)));
        }
        function corridor(node) { return Math.max(P.corridorMin, thick(node) + 12); }
        function stalk(node) { return Math.max(6, thick(node) / 2 + 3); }

        function leafShape(node) {
            const w = cardW(node), c = new Contour();
            for (let k = 0; k < CR; k++) c.add(k, -w / 2, w / 2);
            return Object.assign(c.finish(), { node, mode: 'leaf', kids: [] });
        }

        /* --- مروحة: الأبناء جنباً إلى جنب بشكل مقوّس (الأوسط أعلى) --- */
        function pendulum(items) {
            const sorted = items.slice().sort((a, b) => b.node.size - a.node.size || idNum(a.node) - idNum(b.node));
            const out = [];
            sorted.forEach((item, i) => { if (i % 2 === 0) out.push(item); else out.unshift(item); });
            return out;
        }
        function buildFan(node, shapes) {
            const n = shapes.length, w = cardW(node);
            const order = pendulum(shapes.map(s => ({ s, node: s.node })));
            const amp = n <= 2 ? 0 : Math.min(4, n - 1);
            const acc = new Contour();
            const placed = [];
            order.forEach((item, i) => {
                const S = item.s;
                const t = n > 1 ? (i - (n - 1) / 2) / ((n - 1) / 2) : 0;
                const raise = Math.round(amp * (1 - t * t));
                const st = stalk(S.node);
                let dx = 0;
                if (i > 0) {
                    dx = -Infinity;
                    const rows = raise + S.H;
                    for (let k = 0; k < rows; k++) {
                        const ar = acc.R[k];
                        if (ar === undefined || ar === -Infinity) continue;
                        const l = k < raise ? -st : S.L[k - raise];
                        if (l === Infinity) continue;
                        dx = Math.max(dx, ar + P.hGap - l);
                    }
                    if (dx === -Infinity) dx = placed[placed.length - 1].dx + P.hGap;
                }
                for (let k = 0; k < raise; k++) acc.add(k, dx - st, dx + st);
                acc.merge(S, dx, raise);
                placed.push({ s: S, dx, dr: raise, raise, side: 0 });
            });
            const first = placed[0].dx, last = placed[placed.length - 1].dx;
            let wsum = 0, wx = 0;
            for (const p of placed) { wsum += p.s.node.size; wx += p.s.node.size * p.dx; }
            // رأس العائلة بين منتصف الأبناء ومركز ثقلهم، ليتوازن التاج يميناً ويساراً.
            // للجذع الرئيسي نستخدم الوسيط الفعلي لمواقع البطاقات: نصفها يمين الجذع ونصفها يساره.
            let center = 0.5 * ((first + last) / 2) + 0.5 * (wx / wsum);
            if (node === root) {
                const xs = [];
                const walk = (sh, x) => { if (!sh.node.virtual) xs.push(x); for (const k of sh.kids) walk(k.s, x + k.dx); };
                for (const p of placed) walk(p.s, p.dx);
                xs.sort((a, b) => a - b);
                if (xs.length) center = xs.length % 2 ? xs[xs.length >> 1] : (xs[xs.length / 2 - 1] + xs[xs.length / 2]) / 2;
            }
            const xp = Math.min(last, Math.max(first, center));
            const span = Math.max(Math.abs(first - xp), Math.abs(last - xp));
            const lane = Math.max(2, Math.min(6, Math.ceil(span / 220)));
            const own = rowsOf(node);
            const base = own + lane;
            const out = new Contour();
            for (let k = 0; k < own; k++) out.add(k, -w / 2, w / 2);
            const margin = P.hGap / 2 + thick(node) / 2;
            const laneL = Math.min(first - xp, -w / 3) - margin, laneR = Math.max(last - xp, w / 3) + margin;
            for (let k = own; k < base; k++) out.add(k, laneL, laneR);
            for (let k = 0; k < acc.L.length; k++) if (acc.L[k] !== Infinity) out.add(base + k, acc.L[k] - xp, acc.R[k] - xp);
            for (const p of placed) { p.dx -= xp; p.dr += base; }
            return Object.assign(out.finish(), { node, mode: 'fan', kids: placed, lane, base });
        }

        /* --- غصن صاعد: الأبناء يتفرعون يميناً ويساراً على ارتفاعات متدرجة --- */
        function stackOrder(node, shapes) {
            const leaves = shapes.filter(s => kidsOf(s.node).length === 0);
            const inner = shapes.filter(s => kidsOf(s.node).length > 0).sort((a, b) => a.node.size - b.node.size || idNum(a.node) - idNum(b.node));
            return leaves.concat(inner);
        }
        function sideFits(occ, S, dx, r, ext0) {
            for (let k = 0; k < S.H; k++) {
                let a, b;
                if (k === 0) { a = ext0[0]; b = ext0[1]; }
                else { if (S.L[k] === Infinity) continue; a = dx + S.L[k]; b = dx + S.R[k]; }
                const row = r + k;
                if (row >= occ.L.length) return true;
                const ol = occ.L[row], orr = occ.R[row];
                if (ol === Infinity) continue;
                if (!(b + P.hGap <= ol || a - P.hGap >= orr)) return false;
            }
            return true;
        }
        function leaderFits(left, right, S, r) {
            for (let k = 0; k < S.H; k++) {
                if (S.L[k] === Infinity) continue;
                const row = r + k;
                const lr = row < left.R.length ? left.R[row] : -Infinity;
                const rl = row < right.L.length ? right.L[row] : Infinity;
                if (lr !== -Infinity && S.L[k] - P.hGap < lr) return false;
                if (rl !== Infinity && S.R[k] + P.hGap > rl) return false;
            }
            return true;
        }
        function buildStack(node, sideShapes, leader, oneSided) {
            const w = cardW(node), c = corridor(node), own = rowsOf(node);
            const left = new Contour(), right = new Contour();
            for (let k = 0; k < own; k++) { left.add(k, -w / 2, -c / 2); right.add(k, c / 2, w / 2); }
            const placed = [];
            const allLeaves = sideShapes.every(s => kidsOf(s.node).length === 0);
            // تبديل جهة البداية حسب الجيل حتى لا تميل الشجرة كلها إلى جهة واحدة
            const first = (node.depth + (typeof node.id === 'number' ? node.id : 0)) % 2 === 0 ? 1 : -1;
            let lastSide = first, maxDr = own - 1;
            const bySize = node === root || node.size > total * 0.1;
            const mass = { l: 0, r: 0 };
            sideShapes.forEach((S, i) => {
                let side;
                if (oneSided) side = first;
                else if (allLeaves || kidsOf(S.node).length === 0) side = i % 2 === 0 ? first : -first;
                else if (bySize) {
                    // الفروع الكبيرة توزع بحيث يتساوى عدد الأفراد يميناً ويساراً
                    side = mass.r < mass.l ? 1 : mass.l < mass.r ? -1 : -lastSide;
                } else {
                    const lt = left.L.length, rt = right.L.length;
                    side = rt < lt ? 1 : lt < rt ? -1 : -lastSide;
                }
                if (side < 0) mass.l += S.node.size; else mass.r += S.node.size;
                lastSide = side;
                const occ = side < 0 ? left : right;
                let dx, ext0;
                if (side < 0) { dx = -c / 2 - S.maxR; ext0 = [dx + S.L[0], -c / 2]; }
                else { dx = c / 2 - S.minL; ext0 = [c / 2, dx + S.R[0]]; }
                let r = own + (side !== first && !oneSided ? 1 : 0);
                while (!sideFits(occ, S, dx, r, ext0)) r++;
                occ.add(r, ext0[0], ext0[1]);
                for (let k = 1; k < S.H; k++) if (S.L[k] !== Infinity) occ.add(r + k, dx + S.L[k], dx + S.R[k]);
                placed.push({ s: S, dx, dr: r, raise: 0, side });
                if (r > maxDr) maxDr = r;
            });
            let stemTopRow;
            if (leader) {
                let r = Math.max(own + 1, maxDr + 1);
                while (!leaderFits(left, right, leader, r)) r++;
                placed.push({ s: leader, dx: 0, dr: r, raise: 0, side: 0 });
                stemTopRow = r;
            } else {
                stemTopRow = maxDr + 1;
            }
            const out = new Contour();
            for (let k = 0; k < own; k++) out.add(k, -w / 2, w / 2);
            for (let k = own; k < stemTopRow; k++) out.add(k, -c / 2, c / 2);
            for (let k = 0; k < left.L.length; k++) if (left.L[k] !== Infinity) out.add(k, left.L[k], left.R[k]);
            for (let k = 0; k < right.L.length; k++) if (right.L[k] !== Infinity) out.add(k, right.L[k], right.R[k]);
            if (leader) out.merge(leader, 0, stemTopRow);
            return Object.assign(out.finish(), { node, mode: 'stack', kids: placed, corridor: c, stemTopRow });
        }
        function pickLeader(shapes) {
            let best = null;
            for (const s of shapes) if (!best || s.node.size > best.node.size || (s.node.size === best.node.size && idNum(s.node) > idNum(best.node))) best = s;
            return best;
        }
        function split(node) {
            const kids = kidsOf(node), leaves = [], inner = [];
            for (const k of kids) (kidsOf(k).length ? inner : leaves).push(k);
            return { kids, leaves, inner };
        }
        // تاج العائلة: الأبناء الذين لهم ذرية يتفرعون معاً من رأس الغصن الصاعد
        const crownCache = new Map();
        function crownOf(node, inner) {
            if (inner.length === 1) return inner[0];
            let v = crownCache.get(node.id);
            if (!v) {
                v = { id: 'v' + (++virtualSeq), virtual: true, person: null, parent: node, depth: node.depth,
                    size: inner.reduce((a, n) => a + n.size, 0), children: inner };
                kidCache.set(v.id, inner);
                crownCache.set(node.id, v);
            }
            return v;
        }
        // mode: 'leader' قمة في الأعلى، 'masonry' عمودان متوازنان بلا قمة، 'one' جهة واحدة لأضيق عرض
        function stackFrom(node, getSide, getLeader, mode) {
            const kids = kidsOf(node);
            const leaderNode = mode === 'leader' ? pickLeader(kids.map(k => ({ node: k }))).node : null;
            const sides = [];
            for (const k of kids) if (k !== leaderNode) sides.push(getSide(k));
            const leader = leaderNode ? getLeader(leaderNode) : null;
            let ordered = stackOrder(node, sides);
            if (mode === 'masonry') ordered = ordered.filter(x => !kidsOf(x.node).length).concat(sides.filter(x => kidsOf(x.node).length).sort((a, b) => b.H - a.H || b.node.size - a.node.size));
            return buildStack(node, ordered, leader, mode === 'one');
        }
        // غصن صاعد تحمل جوانبه الأبناء بلا ذرية (كالأوراق)، ثم يتفرع في أعلاه إلى بقية الأبناء
        function hybridFrom(node, getLeaf, getTop) {
            const { leaves, inner } = split(node);
            if (!inner.length) return stackFrom(node, getLeaf, getLeaf, 'leader');
            return buildStack(node, leaves.map(getLeaf), getTop(crownOf(node, inner)), false);
        }

        /* --- التخطيط الطبيعي بدون قيود العرض --- */
        function natural(node) {
            let s = natMemo.get(node.id);
            if (s) return s;
            const { kids, leaves } = split(node);
            if (!kids.length) s = leafShape(node);
            else if (kids.length > 1 && leaves.length >= P.leafStackMin) s = hybridFrom(node, natural, natural);
            else s = buildFan(node, kids.map(natural));
            natMemo.set(node.id, s);
            return s;
        }

        /* --- التخطيط ضمن ميزانية عرض B (يضمن العرض ≤ B متى أمكن) --- */
        function bucket(B) {
            const k = Math.max(0, Math.floor(Math.log(Math.max(B, BUCKET_BASE) / BUCKET_BASE) / BUCKET_STEP));
            return { k, value: BUCKET_BASE * Math.exp(k * BUCKET_STEP) };
        }
        function withLeader(build, Bq, consider) {
            let budget = Bq;
            for (let attempt = 0; attempt < 2; attempt++) {
                const st = build(budget);
                consider(st);
                if (st.width <= Bq + 1e-6) return;
                budget = Math.max(P.minCardW, budget * (Bq / st.width) * 0.94);
            }
        }
        function solve(node, B) {
            const { kids, leaves, inner } = split(node);
            const nat = natural(node);
            if (!kids.length) return nat;
            if (nat.width <= B + 1e-6) return nat;
            const bk = bucket(B);
            const key = node.id + ':' + bk.k;
            const hit = memo.get(key);
            if (hit) return hit;
            const Bq = Math.min(B, bk.value);
            const fits = [], misses = [];
            const consider = s => { (s.width <= Bq + 1e-6 ? fits : misses).push(s); };
            if (kids.length === 1) {
                consider(buildFan(node, [solve(kids[0], Bq)]));
            } else {
                if (leaves.length < P.leafStackMin) {
                    const nats = kids.map(natural);
                    const sum = nats.reduce((a, s) => a + s.width, 0);
                    const avail = Bq - P.hGap * (kids.length - 1);
                    if (avail > 0) consider(buildFan(node, kids.map((k, i) => solve(k, avail * nats[i].width / sum))));
                }
                if (inner.length && leaves.length) withLeader(b => hybridFrom(node, natural, v => solve(v, b)), Bq, consider);
                const c = corridor(node);
                const S = (Bq - c) / 2;
                if (S >= P.minCardW && (inner.length > 1 || !leaves.length || !fits.length)) {
                    withLeader(b => stackFrom(node, k => solve(k, S), k => solve(k, b), 'leader'), Bq, consider);
                    if (inner.length > 1) consider(stackFrom(node, k => solve(k, S), null, 'masonry'));
                }
                if (!fits.length) {
                    const S1 = Bq - Math.max(cardW(node) / 2, c / 2);
                    consider(stackFrom(node, k => solve(k, Math.max(P.minCardW, S1)), null, 'one'));
                }
            }
            let best = null;
            if (fits.length) {
                for (const s of fits) {
                    const score = s.H * (s.mode === 'fan' ? (node === root ? 0.65 : 0.9) : 1);   // عند الجذع نفضّل المروحة لأنها توازن الجهتين
                    if (!best || score < best.score) best = { s, score };
                }
            } else {
                for (const s of misses) if (!best || s.width < best.s.width) best = { s, score: s.width };
            }
            memo.set(key, best.s);
            return best.s;
        }

        return { P, U, CR, CH, cardW, thick, corridor, natural, solve, trunkTop, stalk, kidsOf, chOf, rowsOf };
    }

    /* ------------------------------------------------------------------ */
    /*  التخطيط النهائي بالإحداثيات الحقيقية (y للأسفل كما في SVG)            */
    /* ------------------------------------------------------------------ */
    function layout(people, options) {
        const opts = Object.assign({}, DEFAULTS, options || {});
        const forest = options && options.forest ? options.forest : buildForest(people);
        let root = null;
        if (options && options.rootId != null) root = forest.byId.get(Number(options.rootId)) || null;
        if (!root) root = forest.primary;
        if (!root) return emptyResult(opts, forest);

        const E = createEngine(opts, forest, root);
        const { U, CR, CH } = E;
        const avail = opts.maxWidth - 2 * opts.margin;
        const nat = E.natural(root);
        let scale = 1, shape = nat;
        const trunkRowsFor = s => Math.max(3, Math.min(12, Math.round(s.H * 0.11)));
        const worldH = (s, sc) => ((s.H + trunkRowsFor(s)) * U + opts.groundGap + opts.rootDepth) * sc + opts.topPad + opts.bottomPad;
        if (nat.width > avail) {
            // أكبر حجم بطاقات يحافظ على شكل تاج متناسق (ارتفاع ≤ aspect × العرض)
            const scales = opts.scales;
            const attempt = i => {
                const sc = scales[i], B = avail / sc;
                let cand = E.solve(root, B);
                // استغلال العرض المتبقي: إذا جاء التاج أضيق من المسموح نعيد التوزيع بميزانية أوسع ليقل الارتفاع
                for (let k = 0, budget = B; k < 4 && cand.width < B * 0.9; k++) {
                    budget *= Math.min(1.6, (B / cand.width) * 0.98);
                    const wider = E.solve(root, budget);
                    if (wider.width <= B + 1e-6 && wider.H < cand.H) cand = wider; else if (wider.width > B) break;
                }
                const fit = Math.min(sc, avail / cand.width);
                return { cand, fit, ok: cand === nat || worldH(cand, fit) <= opts.aspect * opts.maxWidth };
            };
            let best = attempt(0);
            if (!best.ok && scales.length > 1) {
                let lo = 0, hi = scales.length - 1;
                const last = attempt(hi);
                best = last;
                if (last.ok) {
                    while (hi - lo > 1) {
                        const mid = (lo + hi) >> 1, r = attempt(mid);
                        if (r.ok) { hi = mid; best = r; } else lo = mid;
                    }
                }
            }
            shape = best.cand; scale = best.fit;
        }
        if (shape.width * scale > avail) scale = avail / shape.width;   // ضمان صارم للعرض

        // مواقع مطلقة (وحدات التخطيط، y للأعلى)
        const T = root.children.length ? trunkRowsFor(shape) : 0;
        const placed = [];
        const stack = [{ s: shape, ax: 0, row: 0, raise: 0, side: 0, parent: null }];
        while (stack.length) {
            const item = stack.pop();
            const rec = { node: item.s.node, s: item.s, ax: item.ax, row: item.row + (item.parent ? T : 0), raise: item.raise, side: item.side, parent: item.parent };
            placed.push(rec);
            for (const k of item.s.kids) stack.push({ s: k.s, ax: item.ax + k.dx, row: item.row + k.dr, raise: k.raise, side: k.side, parent: rec });
        }
        const minL = shape.minL;
        let topY = -Infinity;
        for (const p of placed) topY = Math.max(topY, p.row * U + E.chOf(p.node));
        const bottomY = -(opts.groundGap + opts.rootDepth);
        const worldWidth = Math.min(opts.maxWidth, Math.ceil(shape.width * scale + 2 * opts.margin));
        const extra = Math.max(0, (worldWidth - (shape.width * scale + 2 * opts.margin)) / 2);
        const X = ax => opts.margin + extra + (ax - minL) * scale;
        const Y = yu => opts.topPad + (topY - yu) * scale;
        const worldHeight = Math.ceil(opts.topPad + (topY - bottomY) * scale + opts.bottomPad);

        const cards = [];
        const byNode = new Map();
        const links = {};          // رابط كل عقدة (حقيقية أو غُصين) بأبيها داخل الرسم
        for (const p of placed) {
            byNode.set(p.node.id, p);
            if (p.parent) links[p.node.id] = p.parent.node.id;
            if (p.node.virtual) continue;
            const w = E.cardW(p.node) * scale, h = CH * scale;
            cards.push({
                id: p.node.id, depth: p.node.depth - root.depth, size: p.node.size, children: p.node.children.length,
                parentId: p.node.parent && p.node !== root ? p.node.parent.id : null,
                isRoot: p.node === root, mode: p.s.mode,
                x: X(p.ax) - w / 2, y: Y(p.row * U + CH), w, h, cx: X(p.ax), cy: Y(p.row * U + CH / 2)
            });
        }

        // الأغصان كمنحنيات Bezier مكعبة (إحداثيات العالم)
        const branches = [];
        const pt = (ax, yu) => [X(ax), Y(yu)];
        const tw = n => E.thick(n) * scale;
        const vline = (x, y0, y1) => [x, y0, x, y0 + (y1 - y0) / 3, x, y0 + (y1 - y0) * 2 / 3, x, y1];
        const rootRec = byNode.get(root.id);
        const forkY = rootRec.row * U + CH + T * U;
        const trunkBase = E.trunkTop * 1.55;
        {
            const [x0, y0] = pt(rootRec.ax, -opts.groundGap);
            const [, y1] = pt(rootRec.ax, root.children.length ? forkY : CH);
            branches.push({ kind: 'trunk', from: null, to: root.id, depth: 0,
                segs: [[x0, y0, x0, y0 + (y1 - y0) * 0.35, x0, y0 + (y1 - y0) * 0.7, x0, y1]],
                w0: trunkBase * scale, w1: E.trunkTop * scale });
        }
        // الغصن لا يكون أسمك من الغصن الذي يحمله (تناقص طبيعي للسماكة)
        const carried = new Map([[root.id, E.trunkTop * scale]]);
        const push = (b, parentId) => {
            const cap = carried.has(parentId) ? carried.get(parentId) : Infinity;
            b.w0 = Math.min(b.w0, cap);
            b.w1 = Math.min(b.w1, b.w0);
            if (b.to !== null && b.kind !== 'knot' && !carried.has(b.to)) carried.set(b.to, b.kind === 'stem' ? b.w1 : b.w0);
            branches.push(b);
        };
        for (const p of placed) {
            if (!p.s.kids.length) continue;
            const pw = E.cardW(p.node);
            const yStart = p.node === root ? forkY : p.row * U + E.chOf(p.node);
            if (p.node.virtual) {   // عقدة الغُصين: قطعة خشب قصيرة تصل الداخل بالخارج
                const [kx, ky0] = pt(p.ax, p.row * U), [, ky1] = pt(p.ax, yStart);
                push({ kind: 'knot', from: p.node.id, to: p.node.id, depth: p.node.depth + 1, segs: [vline(kx, ky0, ky1)], w0: tw(p.node), w1: tw(p.node) * 0.96 }, p.node.id);
            }
            const kidRecs = p.s.kids.map(k => byNode.get(k.s.node.id));
            if (p.s.mode === 'fan') {
                const startHalf = p.node === root ? E.trunkTop * 0.42 : p.node.virtual ? tw(p.node) / scale / 3 : pw / 3;
                for (const c of kidRecs) {
                    const yLane = (c.row - c.raise) * U;
                    const sx = p.ax + Math.max(-startHalf, Math.min(startHalf, 0.18 * (c.ax - p.ax)));
                    const [x0, y0] = pt(sx, yStart), [x3, y3] = pt(c.ax, yLane);
                    const segs = [[x0, y0, x0, (y0 + y3) / 2, x3, (y0 + y3) / 2, x3, y3]];
                    if (c.raise > 0) { const [, y4] = pt(c.ax, c.row * U); segs.push(vline(x3, y3, y4)); }
                    push({ kind: 'branch', from: p.node.id, to: c.node.id, depth: c.node.depth + 1, segs,
                        w0: tw(c.node), w1: c.node.virtual ? tw(c.node) : Math.max(1.2 * scale, tw(c.node) * 0.72) }, p.node.id);
                }
            } else {
                const leaderRec = kidRecs.find(c => c.side === 0) || null;
                const stemTop = leaderRec ? leaderRec.row * U : Math.max(...kidRecs.map(c => c.row * U + E.chOf(c.node) / 2)) + 4;
                const [x0, y0] = pt(p.ax, yStart), [, y1] = pt(p.ax, stemTop);
                push({ kind: 'stem', from: p.node.id, to: leaderRec ? leaderRec.node.id : null, depth: p.node.depth + 1,
                    segs: [vline(x0, y0, y1)],
                    w0: Math.max(tw(p.node) * 0.94, 1.6 * scale), w1: Math.max(1.4 * scale, leaderRec ? tw(leaderRec.node) * 0.9 : 1.6 * scale) }, p.node.id);
                for (const c of kidRecs) {
                    if (c.side === 0) continue;
                    const yj = c.row * U + E.chOf(c.node) / 2;
                    const edge = c.node.virtual ? c.ax : c.ax - c.side * E.cardW(c.node) / 2;
                    const rise = c.node.virtual ? 4 : 12;
                    const [sx, sy] = pt(p.ax, yj - rise), [ex, ey] = pt(edge, yj);
                    const w0 = c.node.virtual ? Math.min(tw(c.node), 18 * scale) : Math.min(tw(c.node), 18 * scale);
                    push({ kind: 'petiole', from: p.node.id, to: c.node.id, depth: c.node.depth + 1,
                        segs: [[sx, sy, sx + (ex - sx) * 0.45, sy, ex - (ex - sx) * 0.25, ey, ex, ey]],
                        w0, w1: c.node.virtual ? w0 : Math.max(1.1 * scale, w0 * 0.6) }, p.node.id);
                }
            }
        }

        const axisX = X(rootRec.ax);
        let left = 0, right = 0;
        for (const c of cards) { if (c.isRoot) continue; if (c.cx < axisX - 1) left++; else if (c.cx > axisX + 1) right++; }
        let maxDepth = 0;
        for (const c of cards) maxDepth = Math.max(maxDepth, c.depth);
        return {
            rootId: root.id, forest, cards, branches, links, scale,
            width: worldWidth, height: worldHeight, maxWidth: opts.maxWidth,
            axisX, groundY: Y(-opts.groundGap), trunk: { x: axisX, baseWidth: trunkBase * scale, topWidth: E.trunkTop * scale, groundY: Y(-opts.groundGap) },
            stats: { count: cards.length, generations: maxDepth + 1, left, right, balance: left + right ? Math.abs(left - right) / (left + right) : 0 }
        };
    }

    function emptyResult(opts, forest) {
        return { rootId: null, forest, cards: [], branches: [], links: {}, scale: 1, width: Math.min(opts.maxWidth, 600), height: 400, maxWidth: opts.maxWidth,
            axisX: 300, groundY: 360, trunk: null, stats: { count: 0, generations: 0, left: 0, right: 0, balance: 0 } };
    }

    /* ------------------------------------------------------------------ */
    /*  أدوات هندسية: نقاط المنحنى، والإطار المتدرج السماكة                    */
    /* ------------------------------------------------------------------ */
    function bez(s, t) {
        const u = 1 - t, a = u * u * u, b = 3 * u * u * t, c = 3 * u * t * t, d = t * t * t;
        return [a * s[0] + b * s[2] + c * s[4] + d * s[6], a * s[1] + b * s[3] + c * s[5] + d * s[7]];
    }
    function centerline(branch, perSeg) {
        const n = perSeg || 12, pts = [];
        branch.segs.forEach((s, i) => { for (let j = i === 0 ? 0 : 1; j <= n; j++) pts.push(bez(s, j / n)); });
        return pts;
    }
    /** مضلع غصن يبدأ سميكاً وينتهي رفيعاً، مع تموّج خفيف طبيعي */
    function outline(branch, perSeg) {
        const pts = centerline(branch, perSeg || 10);
        const n = pts.length;
        if (n < 2) return [];
        const leftSide = [], rightSide = [];
        for (let i = 0; i < n; i++) {
            const a = pts[Math.max(0, i - 1)], b = pts[Math.min(n - 1, i + 1)];
            let dx = b[0] - a[0], dy = b[1] - a[1];
            const len = Math.hypot(dx, dy) || 1;
            dx /= len; dy /= len;
            const t = i / (n - 1);
            const ease = branch.kind === 'trunk' ? Math.pow(t, 0.7) : t;
            const half = (branch.w0 + (branch.w1 - branch.w0) * ease) / 2;
            leftSide.push([pts[i][0] - dy * half, pts[i][1] + dx * half]);
            rightSide.push([pts[i][0] + dy * half, pts[i][1] - dx * half]);
        }
        return leftSide.concat(rightSide.reverse());
    }

    /* ------------------------------------------------------------------ */
    /*  تحقق ذاتي: حدود العرض، تداخل البطاقات، ومرور الأغصان فوق البطاقات       */
    /* ------------------------------------------------------------------ */
    function validate(result, tolerance) {
        const tol = tolerance === undefined ? 0.5 : tolerance;
        const issues = { outOfBounds: [], overlaps: [], branchThroughCard: [], branchCrossings: [] };
        const W = result.width;
        if (W > result.maxWidth + 1e-6) issues.outOfBounds.push({ type: 'world', width: W });
        for (const c of result.cards) if (c.x < -1e-6 || c.x + c.w > W + 1e-6) issues.outOfBounds.push({ type: 'card', id: c.id });
        for (const b of result.branches) {
            for (const p of outline(b, 8)) if (p[0] < -1e-6 || p[0] > W + 1e-6) { issues.outOfBounds.push({ type: 'branch', to: b.to }); break; }
        }
        // شبكة مكانية
        const cell = 120;
        const grid = new Map();
        const keyOf = (i, j) => i * 100003 + j;
        result.cards.forEach((c, idx) => {
            for (let i = Math.floor(c.x / cell); i <= Math.floor((c.x + c.w) / cell); i++)
                for (let j = Math.floor(c.y / cell); j <= Math.floor((c.y + c.h) / cell); j++) {
                    const k = keyOf(i, j);
                    if (!grid.has(k)) grid.set(k, []);
                    grid.get(k).push(idx);
                }
        });
        const seen = new Set();
        for (const list of grid.values()) {
            for (let a = 0; a < list.length; a++) for (let b = a + 1; b < list.length; b++) {
                const A = result.cards[list[a]], B = result.cards[list[b]];
                const pk = list[a] < list[b] ? list[a] + ':' + list[b] : list[b] + ':' + list[a];
                if (seen.has(pk)) continue;
                seen.add(pk);
                if (A.x + tol < B.x + B.w && B.x + tol < A.x + A.w && A.y + tol < B.y + B.h && B.y + tol < A.y + A.h) issues.overlaps.push([A.id, B.id]);
            }
        }
        for (const br of result.branches) {
            const pts = centerline(br, 16);
            for (const p of pts) {
                const list = grid.get(keyOf(Math.floor(p[0] / cell), Math.floor(p[1] / cell)));
                if (!list) continue;
                let hit = null;
                for (const idx of list) {
                    const c = result.cards[idx];
                    if (c.id === br.from || c.id === br.to) continue;
                    if (p[0] > c.x + 1.5 && p[0] < c.x + c.w - 1.5 && p[1] > c.y + 1.5 && p[1] < c.y + c.h - 1.5) { hit = c.id; break; }
                }
                if (hit !== null) { issues.branchThroughCard.push({ from: br.from, to: br.to, card: hit }); break; }
            }
        }
        // تقاطع الأغصان (عدا الأغصان المتصلة بنفس الشخص)
        const segGrid = new Map();
        const segs = [];
        result.branches.forEach((br, bi) => {
            const pts = centerline(br, 10);
            for (let i = 0; i + 1 < pts.length; i++) {
                const s = { a: pts[i], b: pts[i + 1], bi };
                const si = segs.push(s) - 1;
                const x0 = Math.min(s.a[0], s.b[0]), x1 = Math.max(s.a[0], s.b[0]), y0 = Math.min(s.a[1], s.b[1]), y1 = Math.max(s.a[1], s.b[1]);
                for (let gi = Math.floor(x0 / cell); gi <= Math.floor(x1 / cell); gi++)
                    for (let gj = Math.floor(y0 / cell); gj <= Math.floor(y1 / cell); gj++) {
                        const k = keyOf(gi, gj);
                        if (!segGrid.has(k)) segGrid.set(k, []);
                        segGrid.get(k).push(si);
                    }
            }
        });
        const related = (A, B) => A.from === B.from || A.to === B.to || A.from === B.to || A.to === B.from ||
            (A.kind === 'trunk' && B.from === A.to) || (B.kind === 'trunk' && A.from === B.to);
        const cross = (p, q, r, s) => {
            const d = (q[0] - p[0]) * (s[1] - r[1]) - (q[1] - p[1]) * (s[0] - r[0]);
            if (Math.abs(d) < 1e-9) return false;
            const t = ((r[0] - p[0]) * (s[1] - r[1]) - (r[1] - p[1]) * (s[0] - r[0])) / d;
            const u = ((r[0] - p[0]) * (q[1] - p[1]) - (r[1] - p[1]) * (q[0] - p[0])) / d;
            return t > 1e-6 && t < 1 - 1e-6 && u > 1e-6 && u < 1 - 1e-6;
        };
        const reported = new Set();
        for (const list of segGrid.values()) {
            for (let a = 0; a < list.length; a++) for (let b = a + 1; b < list.length; b++) {
                const SA = segs[list[a]], SB = segs[list[b]];
                if (SA.bi === SB.bi) continue;
                const A = result.branches[SA.bi], B = result.branches[SB.bi];
                if (related(A, B)) continue;
                const key = Math.min(SA.bi, SB.bi) + ':' + Math.max(SA.bi, SB.bi);
                if (reported.has(key)) continue;
                if (cross(SA.a, SA.b, SB.a, SB.b)) { reported.add(key); issues.branchCrossings.push([A.to, B.to]); }
            }
        }
        issues.ok = !issues.outOfBounds.length && !issues.overlaps.length && !issues.branchThroughCard.length && !issues.branchCrossings.length;
        return issues;
    }

    return { DEFAULTS, buildForest, topRootOf, layout, validate, centerline, outline };
});
