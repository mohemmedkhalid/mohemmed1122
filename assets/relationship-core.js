/**
 * assets/relationship-core.js
 * محرك البحث الشامل عن جميع مسارات القرابة (زەلام + ژن + هەڤژین) باللهجة الكردية البادينية والعربية
 */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.RelationshipCore = factory();
    }
})(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /**
     * قاموس المصطلحات باللغة الكردية البادينية (بحروف عربية) واللغة العربية
     */
    const I18N = {
        ku: {
            father: 'باب',
            mother: 'دایک',
            son: 'کوڕ',
            daughter: 'کچ',
            husband: 'هەڤژین (مێر)',
            wife: 'هەڤژین (ژن)',
            badgeMale: 'رێکا زەلامان (باب و باپیر)',
            badgeFemale: 'رێکا ژنان (دایک و داپیر)',
            badgeSpouse: 'رێکا هەڤژینیێ و خزمایەتیێ',
            fromFatherSide: 'ژ لایێ بابی',
            fromMotherSide: 'ژ لایێ دایکێ',
            fromGrandfatherSide: 'ژ لایێ باپیری',
            fromGrandmotherSide: 'ژ لایێ داپیرێ',
            grandfather: 'باپیر',
            grandmother: 'داپیر',
            greatGrandfather: 'باپیرێ مەزن',
            greatGrandmother: 'داپیرا مەزن',
            grandson: 'نەڤی (کوڕ)',
            granddaughter: 'نەڤی (کچ)',
            brother: 'برا',
            sister: 'خۆشک',
            nephew: 'برازایێ / خۆشکەزایێ (کوڕ)',
            niece: 'برازایێ / خۆشکەزایێ (کچ)',
            paternalUncle: 'مام',
            paternalAunt: 'مەت',
            maternalUncle: 'خال',
            maternalAunt: 'خالتی',
            paternalUncleSon: 'پسمام (کوڕێ مامی)',
            paternalUncleDaughter: 'دۆتمام (کچا مامی)',
            paternalAuntSon: 'کوڕێ مەتێ',
            paternalAuntDaughter: 'کچا مەتێ',
            maternalUncleSon: 'کوڕێ خالی',
            maternalUncleDaughter: 'کچا خالی',
            maternalAuntSon: 'کوڕێ خالتیێ',
            maternalAuntDaughter: 'کچا خالتیێ',
            stepsWord: 'گاڤ',
            marriageKinship: 'خزمایەتیا ب رێکا هەڤژینیێ',
            bloodMeetAt: 'خزمایەتیا خوینێ دگەهنە ئێک ل نک',
            viaFemales: 'ب رێکا دایک و داپیران',
            viaMales: 'ب رێکا باب و باپیران'
        },
        ar: {
            father: 'أب',
            mother: 'أم',
            son: 'ابن',
            daughter: 'ابنة',
            husband: 'زوج',
            wife: 'زوجة',
            badgeMale: 'طريق الذكور (الأب والجد)',
            badgeFemale: 'طريق الإناث (الأم والجدة)',
            badgeSpouse: 'طريق الأزواج والمصاهرة',
            fromFatherSide: 'من جهة الأب',
            fromMotherSide: 'من جهة الأم',
            fromGrandfatherSide: 'من جهة الجد',
            fromGrandmotherSide: 'من جهة الجدة',
            grandfather: 'جد',
            grandmother: 'جدة',
            greatGrandfather: 'جد أعلى',
            greatGrandmother: 'جدة عليا',
            grandson: 'حفيد',
            granddaughter: 'حفيدة',
            brother: 'أخ',
            sister: 'أخت',
            nephew: 'ابن أخ / أخت',
            niece: 'ابنة أخ / أخت',
            paternalUncle: 'عم',
            paternalAunt: 'عمة',
            maternalUncle: 'خال',
            maternalAunt: 'خالة',
            paternalUncleSon: 'ابن عم',
            paternalUncleDaughter: 'ابنة عم',
            paternalAuntSon: 'ابن عمة',
            paternalAuntDaughter: 'ابنة عمة',
            maternalUncleSon: 'ابن خال',
            maternalUncleDaughter: 'ابنة خال',
            maternalAuntSon: 'ابن خالة',
            maternalAuntDaughter: 'ابنة خالة',
            stepsWord: 'خطوات',
            marriageKinship: 'قرابة مصاهرة وزواج',
            bloodMeetAt: 'قرابة دم تلتقي عند',
            viaFemales: 'عبر الأمهات والجدات',
            viaMales: 'عبر الآباء والأجداد'
        }
    };

    function normalizeSearchText(value) {
        return String(value || '').normalize('NFKD').toLowerCase()
            .replace(/[\u064B-\u065F\u0670\u0640]/g, '')
            .replace(/[أإآٱ]/g, 'ا').replace(/[ىيیئ]/g, 'ي')
            .replace(/[ةهە]/g, 'ه').replace(/[كک]/g, 'ك')
            .replace(/[ؤوۆ]/g, 'و').replace(/[رڕ]/g, 'ر').replace(/[لڵ]/g, 'ل')
            .replace(/عيسا/g, 'عيسي').replace(/[^\p{L}\p{N}]+/gu, ' ')
            .split(' ').filter(word => word && !['بن', 'ابن', 'بنت', 'کوڕێ', 'كورا', 'کچا', 'كچا'].includes(word)).join(' ');
    }

    function normalizeGender(value) {
        const gender = String(value || '').toLowerCase();
        if (['male', 'm', 'ذكر', 'نێر', 'زەلام', '1'].includes(gender)) return 'M';
        if (['female', 'f', 'أنثى', 'انثى', 'مێ', 'ژن', '2'].includes(gender)) return 'F';
        return '';
    }

    function idOf(value) {
        const number = Number(value);
        return Number.isSafeInteger(number) && number > 0 ? String(number) : null;
    }

    function preparePeopleMap(rows, marriages) {
        const map = Object.create(null);
        for (const row of rows || []) {
            const id = idOf(row.id);
            if (!id) continue;
            map[id] = Object.assign({}, row, {
                id, name: String(row.name || row.full_name || '#' + id).trim(),
                father_id: idOf(row.father_id), mother_id: idOf(row.mother_id),
                gender: normalizeGender(row.gender || row.sex)
            });
        }
        for (const person of Object.values(map)) {
            if (map[person.father_id] && !map[person.father_id].gender) map[person.father_id].gender = 'M';
            if (map[person.mother_id] && !map[person.mother_id].gender) map[person.mother_id].gender = 'F';
        }
        for (const marriage of marriages || []) {
            const husband = map[idOf(marriage.husband_id)];
            const wife = map[idOf(marriage.wife_id)];
            if (husband && !husband.gender) husband.gender = 'M';
            if (wife && !wife.gender) wife.gender = 'F';
        }
        for (const person of Object.values(map)) {
            const parts = [person.name];
            const seen = new Set([person.id]);
            let current = person;
            for (let depth = 0; depth < 2 && map[current.father_id]; depth++) {
                const father = map[current.father_id];
                if (seen.has(father.id)) break;
                seen.add(father.id);
                const base = ' ' + normalizeSearchText(person.name) + ' ';
                if (!base.includes(' ' + normalizeSearchText(father.name) + ' ')) parts.push(father.name);
                current = father;
            }
            person.triple_name = parts.join(' ');
            person.lineage_name = parts.join(' بن ');
            person.mother_name = map[person.mother_id] ? map[person.mother_id].name : '';
            person.search_index = normalizeSearchText([person.id, person.name, person.full_name_en, person.triple_name, person.mother_name].join(' '));
        }
        return map;
    }

    function buildGraph(map, marriages, mode, lang) {
        const t = I18N[lang] || I18N.ku;
        const graph = Object.create(null);
        for (const id of Object.keys(map)) graph[id] = [];
        const add = (from, to, type, kind, label, key) => {
            if (from === to || !graph[from] || !graph[to]) return;
            if (graph[from].some(edge => edge.key === key && edge.to === to)) return;
            graph[from].push({ from, to, type, kind, label, key, category: kind === 'marriage' ? 'spouse' : kind === 'mother' ? 'female' : 'male' });
        };
        for (const person of Object.values(map)) {
            for (const kind of ['father', 'mother']) {
                if ((mode === 'males' || mode === 'male') && kind === 'mother') continue;
                const parent = person[kind + '_id'];
                if (!map[parent]) continue;
                const key = kind + ':' + person.id + ':' + parent;
                add(person.id, parent, kind, kind, t[kind], key);
                add(parent, person.id, person.gender === 'F' ? 'daughter' : 'son', kind, person.gender === 'F' ? t.daughter : t.son, key);
            }
        }
        if (!['parents', 'males', 'male', 'female', 'females'].includes(mode)) {
            for (const marriage of marriages || []) {
                const husband = idOf(marriage.husband_id), wife = idOf(marriage.wife_id);
                if (!map[husband] || !map[wife]) continue;
                const key = 'marriage:' + husband + ':' + wife;
                add(husband, wife, 'wife', 'marriage', t.wife, key);
                add(wife, husband, 'husband', 'marriage', t.husband, key);
            }
        }
        // A shared child is a parent link, not evidence for inventing a marriage.
        for (const edges of Object.values(graph)) edges.sort((a, b) => Number(a.to) - Number(b.to) || a.key.localeCompare(b.key));
        return graph;
    }

    function analyzePath(path, map, lang) {
        const t = I18N[lang] || I18N.ku;
        let female = false, spouse = false, upSteps = 0, downSteps = 0, phase = 'up';
        let ancestor = path[0].person;
        for (let index = 1; index < path.length; index++) {
            const step = path[index];
            if (step.edgeCategory === 'spouse') { spouse = true; phase = 'complex'; }
            if (step.parentKind === 'mother') female = true;
            if (['father', 'mother'].includes(step.edgeType)) {
                if (phase === 'up') { upSteps++; ancestor = step.person; }
                else phase = 'complex';
            } else if (['son', 'daughter'].includes(step.edgeType)) {
                if (phase === 'up') phase = 'down';
                if (phase === 'down') downSteps++; else phase = 'complex';
            }
        }
        const pathType = spouse ? 'spouse' : female ? 'female' : 'male';
        let title;
        if (path.length === 1) title = lang === 'ar' ? 'الشخص نفسه' : 'هەمان کەس';
        else if (path.length === 2) title = path[1].edgeLabel;
        else if (spouse) title = t.marriageKinship;
        else if (phase === 'complex') title = lang === 'ar' ? 'صلة عائلية عبر الوالدين والأبناء' : 'پەیوەندییا خێزانی ب ڕێکا دەیباب و زارۆکان';
        else title = computeKinshipTitle(path, phase, upSteps, downSteps, ancestor, female, false, lang);
        return {
            signature: path.map((step, index) => index ? step.edgeKey + '>' + step.id : step.id).join('|'),
            pathType, badgeText: pathType === 'spouse' ? t.badgeSpouse : pathType === 'female' ? t.badgeFemale : t.badgeMale,
            badgeClass: 'badge-' + pathType, relationshipTitle: title,
            commonAncestor: !spouse && phase !== 'complex' && (upSteps > 0 || downSteps > 0) ? ancestor : null,
            ancestorIndex: !spouse && phase !== 'complex' ? upSteps : null,
            stepsCount: path.length - 1, steps: path
        };
    }

    function computeKinshipTitle(path, phase, upSteps, downSteps, commonAncestor, hasFemaleLink, hasSpouseLink, lang = 'ku') {
        const t = I18N[lang] || I18N.ku;
        const target = path[path.length - 1].person;
        const isTargetFemale = target && target.gender === 'F';
        const len = path.length - 1;

        if (len === 1) {
            return path[1].edgeLabel;
        }

        if (hasSpouseLink) {
            return `${t.marriageKinship} (${len} ${t.stepsWord})`;
        }

        if (phase !== 'complex') {
            if (downSteps === 0 && upSteps > 0) {
                const side = path[1].edgeType === 'mother' ? t.fromMotherSide : t.fromFatherSide;
                if (upSteps === 2) return `${isTargetFemale ? t.grandmother : t.grandfather} (${side})`;
                return `${isTargetFemale ? t.greatGrandmother : t.greatGrandfather} (${side})`;
            }

            if (upSteps === 0 && downSteps > 0) {
                return isTargetFemale ? t.granddaughter : t.grandson;
            }

            if (upSteps === 1) {
                const via = path[1].edgeType === 'mother' ? t.fromMotherSide : t.fromFatherSide;
                if (downSteps === 1) return `${isTargetFemale ? t.sister : t.brother} (${via})`;
                return `${isTargetFemale ? t.niece : t.nephew} (${via})`;
            }

            if (upSteps === 2) {
                const firstParent = path[1].edgeType;
                const ancestorType = path[2].edgeType;
                const viaAncestor = ancestorType === 'mother' ? t.fromGrandmotherSide : t.fromGrandfatherSide;

                if (downSteps === 1) {
                    if (firstParent === 'father') {
                        return `${isTargetFemale ? t.paternalAunt : t.paternalUncle} (${viaAncestor})`;
                    } else {
                        return `${isTargetFemale ? t.maternalAunt : t.maternalUncle} (${viaAncestor})`;
                    }
                }
                if (downSteps === 2) {
                    const uncleOrAunt = path[path.length - 2].person;
                    const isUncleFemale = uncleOrAunt && uncleOrAunt.gender === 'F';
                    if (firstParent === 'father') {
                        if (!isUncleFemale) {
                            return `${isTargetFemale ? t.paternalUncleDaughter : t.paternalUncleSon} (${viaAncestor})`;
                        } else {
                            return `${isTargetFemale ? t.paternalAuntDaughter : t.paternalAuntSon} (${viaAncestor})`;
                        }
                    } else {
                        if (!isUncleFemale) {
                            return `${isTargetFemale ? t.maternalUncleDaughter : t.maternalUncleSon} (${viaAncestor})`;
                        } else {
                            return `${isTargetFemale ? t.maternalAuntDaughter : t.maternalAuntSon} (${viaAncestor})`;
                        }
                    }
                }
            }

            if (upSteps >= 3 && downSteps >= 1) {
                const ancName = commonAncestor ? commonAncestor.triple_name : '';
                const viaText = hasFemaleLink ? t.viaFemales : t.viaMales;
                return `${t.bloodMeetAt} (${ancName}) - ${viaText}`;
            }
        }

        return hasFemaleLink
            ? `${t.badgeFemale} (${len} ${t.stepsWord})`
            : `${t.badgeMale} (${len} ${t.stepsWord})`;
    }


    // Distances in a two-state graph are lower bounds for simple paths.
    // State 0 still needs a maternal or marriage link. State 1 already has it.
    function distances(graph, target, required) {
        const dist = [Object.create(null), Object.create(null)];
        dist[1][target] = 0;
        const queue = [{ id: target, state: 1 }];
        for (let cursor = 0; cursor < queue.length; cursor++) {
            const current = queue[cursor];
            for (const edge of graph[current.id] || []) {
                const qualifies = required === null || edge.kind === required;
                const states = current.state === 1 ? (qualifies ? [1, 0] : [1]) : (qualifies ? [] : [0]);
                for (const state of states) {
                    if (dist[state][edge.to] !== undefined) continue;
                    dist[state][edge.to] = dist[current.state][current.id] + 1;
                    queue.push({ id: edge.to, state });
                }
            }
        }
        return dist;
    }

    class RouteIterator {
        constructor(start, target, map, graph, type, maxDepth, lang) {
            Object.assign(this, { start, target, map, graph, type, maxDepth, lang });
            this.required = type === 'female' ? 'mother' : type === 'spouse' ? 'marriage' : null;
            this.distances = distances(graph, target, this.required);
            this.minimum = this.distances[this.required ? 0 : 1][start];
            this.nextDepth = this.minimum;
            this.done = this.minimum === undefined || this.minimum > maxDepth;
            this.stack = []; this.path = []; this.visited = new Set(); this.count = 0;
        }

        pop() {
            const frame = this.stack.pop();
            this.visited.delete(frame.id);
            this.path.pop();
        }

        next(budget, deadline) {
            let work = 0;
            while (!this.done && work < budget && Date.now() < deadline) {
                if (!this.stack.length) {
                    if (this.nextDepth > this.maxDepth) { this.done = true; break; }
                    this.stack.push({ id: this.start, remaining: this.nextDepth++, state: this.required ? 0 : 1, index: 0, entered: false });
                    this.path = [{ id: this.start, person: this.map[this.start], edgeType: null, edgeLabel: '', edgeCategory: null }];
                    this.visited = new Set([this.start]);
                }
                const frame = this.stack[this.stack.length - 1];
                work++;
                if (!frame.entered) {
                    frame.entered = true;
                    if (frame.id === this.target) {
                        const found = frame.remaining === 0 && frame.state === 1 ? this.path.slice() : null;
                        this.pop();
                        if (found) { this.count++; return { path: analyzePath(found, this.map, this.lang), work }; }
                        continue;
                    }
                    const distance = this.distances[frame.state][frame.id];
                    if (!frame.remaining || distance === undefined || distance > frame.remaining) { this.pop(); continue; }
                }
                const neighbors = this.graph[frame.id] || [];
                if (frame.index >= neighbors.length) { this.pop(); continue; }
                const edge = neighbors[frame.index++];
                if (this.visited.has(edge.to)) continue;
                const state = frame.state || (edge.kind === this.required ? 1 : 0);
                const distance = this.distances[state][edge.to];
                if (distance === undefined || distance > frame.remaining - 1) continue;
                this.visited.add(edge.to);
                this.path.push({ id: edge.to, person: this.map[edge.to], edgeType: edge.type, edgeLabel: edge.label, edgeCategory: edge.category, parentKind: edge.kind, edgeKey: edge.key });
                this.stack.push({ id: edge.to, remaining: frame.remaining - 1, state, index: 0, entered: false });
            }
            return { path: null, work };
        }
    }

    // A lineage route ascends to one common ancestor, then descends to the
    // other person. It can change between father and mother at EVERY level.
    function lineageDistances(start, graph, firstKind) {
        const result = Object.create(null), queue = [start];
        result[start] = 0;
        for (let i = 0; i < queue.length; i++) {
            for (const edge of graph[queue[i]] || []) {
                if (queue[i] === start && firstKind && edge.kind !== firstKind) continue;
                if (result[edge.to] !== undefined) continue;
                result[edge.to] = result[queue[i]] + 1;
                queue.push(edge.to);
            }
        }
        return result;
    }

    // Two units of flow with vertex capacity one prove that the branches
    // can meet at this ancestor WITHOUT already sharing a younger person.
    // This removes false meeting points above a forced earlier intersection.
    function independentBranches(start, target, pivot, up, toPivot) {
        if (pivot === start || pivot === target) return true;
        const ids = Object.keys(toPivot), index = new Map(ids.map((id, i) => [id, i * 2]));
        const source = ids.length * 2, sink = source + 1;
        const network = Array.from({ length: sink + 1 }, () => []);
        function add(from, to, capacity) {
            const forward = { to, capacity, reverse: network[to].length };
            const back = { to: from, capacity: 0, reverse: network[from].length };
            network[from].push(forward); network[to].push(back);
        }
        for (const id of ids) {
            const node = index.get(id);
            add(node, node + 1, id === pivot ? 2 : 1);
            for (const edge of up[id]) if (index.has(edge.to)) add(node + 1, index.get(edge.to), 2);
        }
        add(source, index.get(start), 1); add(source, index.get(target), 1);
        add(index.get(pivot) + 1, sink, 2);
        for (let flow = 0; flow < 2; flow++) {
            const previous = new Array(network.length), queue = [source];
            previous[source] = { from: -1 };
            for (let i = 0; i < queue.length && !previous[sink]; i++) {
                const from = queue[i];
                network[from].forEach((edge, position) => {
                    if (!edge.capacity || previous[edge.to]) return;
                    previous[edge.to] = { from, position }; queue.push(edge.to);
                });
            }
            if (!previous[sink]) return false;
            for (let node = sink; node !== source;) {
                const step = previous[node], edge = network[step.from][step.position];
                edge.capacity--; network[node][edge.reverse].capacity++; node = step.from;
            }
        }
        return true;
    }

    class AncestorIterator {
        constructor(start, target, pivot, map, up, down, toPivot, toTarget, maxDepth, lang, origin) {
            Object.assign(this, { start, target, pivot, map, up, down, toPivot, toTarget, maxDepth, lang, origin });
            this.type = 'ancestor:' + pivot + ':' + origin;
            this.minimum = toPivot[start] + toTarget[pivot];
            this.count = 0; this.done = this.minimum > maxDepth;
            this.stack = [{ id: start, down: start === pivot, index: 0, entered: false }];
            this.path = [{ id: start, person: map[start], edgeType: null, edgeLabel: '', edgeCategory: null }];
            this.visited = new Set([start]);
        }
        pop() { const frame = this.stack.pop(); this.visited.delete(frame.id); this.path.pop(); }
        next(budget, deadline) {
            let work = 0;
            while (!this.done && work < budget && Date.now() < deadline) {
                if (!this.stack.length) { this.done = true; break; }
                work++;
                const frame = this.stack[this.stack.length - 1];
                if (!frame.entered) {
                    frame.entered = true;
                    if (frame.id === this.target) {
                        const found = frame.down ? this.path.slice() : null;
                        this.pop();
                        if (found) {
                            const path = analyzePath(found, this.map, this.lang);
                            path.commonAncestor = this.map[this.pivot];
                            path.ancestorIndex = found.findIndex(step => step.id === this.pivot);
                            path.firstSide = path.ancestorIndex === 0 ? 'self' : found[1].parentKind;
                            path.secondSide = path.ancestorIndex === found.length - 1 ? 'self' : found[found.length - 1].parentKind;
                            path.originCategory = path.firstSide === 'self' || path.secondSide === 'self' ? 'direct'
                                : (path.firstSide === 'father' ? 'f' : 'm') + (path.secondSide === 'father' ? 'f' : 'm');
                            path.ancestorRoute = true;
                            this.count++;
                            if (!this.stack.length) this.done = true;
                            return { path, work };
                        }
                        continue;
                    }
                }
                const edges = (frame.down ? this.down : this.up)[frame.id];
                if (frame.index >= edges.length || this.path.length - 1 >= this.maxDepth) { this.pop(); continue; }
                const edge = edges[frame.index++];
                if (this.visited.has(edge.to)) continue;
                if (this.origin !== 'direct') {
                    if (frame.id === this.start && edge.kind !== (this.origin[0] === 'f' ? 'father' : 'mother')) continue;
                    if (edge.to === this.target && edge.kind !== (this.origin[1] === 'f' ? 'father' : 'mother')) continue;
                }
                const goingDown = frame.down || edge.to === this.pivot;
                const distance = goingDown ? this.toTarget[edge.to]
                    : this.toPivot[edge.to] === undefined ? undefined : this.toPivot[edge.to] + this.toTarget[this.pivot];
                if (distance === undefined || this.path.length + distance > this.maxDepth) continue;
                this.visited.add(edge.to);
                this.path.push({ id: edge.to, person: this.map[edge.to], edgeType: edge.type, edgeLabel: edge.label, edgeCategory: edge.category, parentKind: edge.kind, edgeKey: edge.key });
                this.stack.push({ id: edge.to, down: goingDown, index: 0, entered: false });
            }
            return { path: null, work };
        }
    }

    function createAncestorSearch(startId, targetId, rows, lang, options) {
        const start = idOf(startId), target = idOf(targetId), map = preparePeopleMap(rows, []);
        if (!map[start] || !map[target]) throw new Error('unknown_person');
        const graph = buildGraph(map, [], 'parents', lang);
        const up = {}, down = {};
        for (const [id, edges] of Object.entries(graph)) {
            up[id] = edges.filter(edge => edge.type === 'father' || edge.type === 'mother');
            down[id] = edges.filter(edge => edge.type === 'son' || edge.type === 'daughter');
        }
        const first = lineageDistances(start, up), second = lineageDistances(target, up);
        const firstBySide = { f: lineageDistances(start, up, 'father'), m: lineageDistances(start, up, 'mother') };
        const secondBySide = { f: lineageDistances(target, up, 'father'), m: lineageDistances(target, up, 'mother') };
        const maximum = Math.max(0, Object.keys(map).length - 1);
        const requested = options && options.maxDepth;
        const maxDepth = Number.isInteger(requested) ? Math.min(maximum, Math.max(0, requested)) : maximum;
        const pivots = start === target ? [start] : Object.keys(first).filter(id => second[id] !== undefined)
            .sort((a, b) => first[a] + second[a] - first[b] - second[b] || Number(a) - Number(b));
        const iterators = [];
        for (const pivot of pivots) {
            const toPivot = lineageDistances(pivot, down);
            if (start !== target && !independentBranches(start, target, pivot, up, toPivot)) continue;
            const origins = pivot === start || pivot === target ? ['direct'] : ['ff', 'fm', 'mf', 'mm'];
            for (const origin of origins) {
                if (origin !== 'direct' && (firstBySide[origin[0]][pivot] === undefined || secondBySide[origin[1]][pivot] === undefined)) continue;
                iterators.push(new AncestorIterator(start, target, pivot, map, up, down, toPivot,
                    origin === 'direct' ? second : secondBySide[origin[1]], maxDepth, lang, origin));
            }
        }
        let cursor = 0, expanded = 0;
        return {
            maxDepth, categories: iterators.map(iterator => iterator.type), pageSize: 2,
            advance(targets, budget, milliseconds) {
                const paths = [], deadline = Date.now() + (milliseconds || 15);
                let work = 0, idle = 0;
                while (work < (budget || 4000) && Date.now() < deadline && idle < iterators.length) {
                    const iterator = iterators[cursor++ % iterators.length];
                    if (iterator.done || iterator.count >= targets[iterator.type]) { idle++; continue; }
                    idle = 0;
                    const result = iterator.next(Math.min(250, (budget || 4000) - work), deadline);
                    work += result.work;
                    if (result.path) paths.push(result.path);
                }
                expanded += work;
                const categories = iterators.map(iterator => ({ type: iterator.type, count: iterator.count, complete: iterator.done, minimum: iterator.minimum }));
                return {
                    paths, maxDepth, expanded, searchKind: 'ancestors',
                    complete: iterators.every(iterator => iterator.done),
                    pageComplete: iterators.every(iterator => iterator.done || iterator.count >= targets[iterator.type]),
                    categories,
                    ancestors: pivots.map(id => {
                        const group = iterators.filter(iterator => iterator.pivot === id);
                        return { person: map[id], count: group.reduce((count, iterator) => count + iterator.count, 0),
                            complete: group.every(iterator => iterator.done) };
                    }).filter(item => iterators.some(iterator => iterator.pivot === item.person.id))
                };
            }
        };
    }

    function createSearch(startId, targetId, rows, marriages, mode, lang, options) {
        if (mode === 'parents' || mode === 'ancestors') return createAncestorSearch(startId, targetId, rows, lang, options);
        const start = idOf(startId), target = idOf(targetId);
        const map = preparePeopleMap(rows, marriages);
        if (!map[start] || !map[target]) throw new Error('unknown_person');
        const categories = { all: ['male', 'female', 'spouse'], parents: ['male', 'female'], males: ['male'], females: ['female'], spouses: ['spouse'] }[mode || 'all'];
        if (!categories) throw new Error('unknown_mode');
        const maximum = Math.max(0, Object.keys(map).length - 1);
        const requested = options && Number(options.maxDepth);
        const maxDepth = Math.min(maximum, Math.max(0, Number.isInteger(requested) ? requested : 8));
        const full = buildGraph(map, marriages || [], 'all', lang);
        const graphFor = type => Object.fromEntries(Object.entries(full).map(([id, edges]) => [id, edges.filter(edge => type === 'male' ? edge.kind === 'father' : type === 'female' ? edge.kind !== 'marriage' : true)]));
        const iterators = categories.map(type => new RouteIterator(start, target, map, graphFor(type), type, maxDepth, lang));
        let cursor = 0, expanded = 0;
        return {
            maxDepth, categories,
            advance(targets, budget, milliseconds) {
                const paths = [], deadline = Date.now() + (milliseconds || 15);
                let work = 0, idle = 0;
                while (work < (budget || 4000) && Date.now() < deadline && idle < iterators.length) {
                    const iterator = iterators[cursor++ % iterators.length];
                    if (iterator.done || iterator.count >= targets[iterator.type]) { idle++; continue; }
                    idle = 0;
                    const result = iterator.next(Math.min(250, (budget || 4000) - work), deadline);
                    work += result.work;
                    if (result.path) paths.push(result.path);
                }
                expanded += work;
                return {
                    paths, maxDepth, expanded,
                    complete: iterators.every(iterator => iterator.done),
                    pageComplete: iterators.every(iterator => iterator.done || iterator.count >= targets[iterator.type]),
                    categories: iterators.map(iterator => ({ type: iterator.type, count: iterator.count, complete: iterator.done, minimum: iterator.minimum === undefined ? null : iterator.minimum }))
                };
            }
        };
    }

    // Synchronous convenience API for tests and small graphs. Metadata is explicit.
    function findAllPathsBetween(start, target, rows, marriages, mode, lang, options) {
        options = options || {};
        const session = createSearch(start, target, rows, marriages || [], mode || 'all', lang || 'ku', options);
        const targets = Object.fromEntries(session.categories.map(type => [type, options.perType || 30]));
        const paths = [];
        let snapshot, work = 0;
        do {
            snapshot = session.advance(targets, 5000, 30);
            paths.push(...snapshot.paths);
            work = snapshot.expanded;
        } while (!snapshot.pageComplete && !snapshot.complete && work < (options.workBudget || 200000));
        paths.sort((a, b) => a.stepsCount - b.stepsCount || a.signature.localeCompare(b.signature));
        paths.complete = snapshot.complete;
        paths.maxDepth = session.maxDepth;
        paths.categories = snapshot.categories;
        paths.ancestors = snapshot.ancestors || [];
        return paths;
    }

    return { I18N, normalizeSearchText, normalizeGender, preparePeopleMap, buildGraph, createAncestorSearch, createSearch, findAllPathsBetween };
});
