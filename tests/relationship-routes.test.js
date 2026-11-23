const test = require('node:test');
const assert = require('node:assert/strict');
const Core = require('../assets/relationship-core.js');

const people = [
    { id: 1, full_name: 'محمد', father_id: 3, mother_id: 4, gender: 'Male' },
    { id: 2, full_name: 'عيسا', father_id: 5, mother_id: 6, gender: 'Male' },
    { id: 3, full_name: 'خالد', father_id: 7, mother_id: 8, gender: 'Male' },
    { id: 4, full_name: 'سارة', gender: 'Female' },
    { id: 5, full_name: 'سعيد', father_id: 7, mother_id: 8, gender: 'Male' },
    { id: 6, full_name: 'ليلى', gender: 'Female' },
    { id: 7, full_name: 'حسن', gender: 'Male' }, { id: 8, full_name: 'فاطمة', gender: 'Female' }
];
const marriages = [{ husband_id: 3, wife_id: 6 }];
const search = (mode, data = people, unions = marriages, options = {}) => Core.findAllPathsBetween(1, 2, data, unions, mode, 'ar', { maxDepth: 6, perType: 1000, ...options });

test('محمد خالد حسن and عيسا سعيد حسن retain the grandfather and grandmother routes together', () => {
    const paths = search('parents');
    assert.equal(paths.length, 2);
    assert.deepEqual(paths.map(path => path.pathType).sort(), ['female', 'male']);
    assert.deepEqual(new Set(paths.map(path => path.commonAncestor.id)), new Set(['7', '8']));
    assert.equal(paths.complete, true);
});

test('all mode includes marriage routes as well as both parent routes', () => {
    const paths = search('all');
    assert.deepEqual(new Set(paths.map(path => path.pathType)), new Set(['male', 'female', 'spouse']));
    assert.deepEqual(paths.find(path => path.pathType === 'spouse').steps.map(step => step.id), ['1', '3', '6', '2']);
    assert.equal(paths.complete, true);
});

test('each category includes exactly the requested links', () => {
    assert(search('males').every(path => path.pathType === 'male'));
    assert(search('females').every(path => path.pathType === 'female'));
    assert(search('spouses').every(path => path.pathType === 'spouse'));
    assert.equal(search('males').length, 1);
    assert.equal(search('females').length, 1);
    assert.equal(search('spouses').length, 1);
});

test('a wife can connect through her parents without a blood relationship between the selected people', () => {
    const rows = [{ id: 1, gender: 'Male' }, { id: 2, gender: 'Male' }, { id: 3, gender: 'Female', father_id: 2 }];
    const paths = search('spouses', rows, [{ husband_id: 1, wife_id: 3 }]);
    assert.deepEqual(paths[0].steps.map(step => step.id), ['1', '3', '2']);
    assert.equal(paths[0].commonAncestor, null);
    assert.equal(search('parents', rows, []).length, 0);
});

test('co-parenting does not invent a marriage or a common ancestor', () => {
    const rows = [{ id: 1, gender: 'Male' }, { id: 2, gender: 'Female' }, { id: 3, father_id: 1, mother_id: 2 }];
    assert.equal(search('spouses', rows, []).length, 0);
    const paths = search('all', rows, []);
    assert.equal(paths.length, 1);
    assert.equal(paths[0].commonAncestor, null);
    assert.equal(paths[0].pathType, 'female');
});

test('parallel recorded relationships are distinct but duplicate marriage rows are not', () => {
    const rows = [{ id: 1, father_id: 2 }, { id: 2 }];
    const paths = search('all', rows, [{ husband_id: 2, wife_id: 1 }, { husband_id: 2, wife_id: 1 }]);
    assert.equal(paths.length, 2);
    assert.equal(new Set(paths.map(path => path.signature)).size, 2);
});

test('pagination resumes, finds all categories and never repeats a route', () => {
    const session = Core.createSearch(1, 2, people, marriages, 'all', 'ar', { maxDepth: 6 });
    let snapshot, targets = { male: 1, female: 1, spouse: 1 }, collected = [], cycles = 0;
    do {
        snapshot = session.advance(targets, 10, 30);
        collected.push(...snapshot.paths);
        if (snapshot.pageComplete) for (const category of snapshot.categories) targets[category.type] = category.count + 1;
        assert(++cycles < 2000);
    } while (!snapshot.complete);
    assert.deepEqual(new Set(collected.map(path => path.signature)), new Set(search('all').map(path => path.signature)));
    assert.equal(new Set(collected.map(path => path.signature)).size, collected.length);
});

test('the depth bound is explicit and reports the minimum distance', () => {
    const paths = search('males', people, marriages, { maxDepth: 3 });
    assert.equal(paths.length, 0);
    assert.equal(paths.complete, true);
    assert.equal(paths.categories[0].minimum, 4);
    assert.equal(search('males', people, marriages, { maxDepth: 4 }).length, 1);
});

test('missing links, self links and cycles cannot repeat a person within a path', () => {
    const rows = [{ id: 1, father_id: 3, mother_id: 999 }, { id: 2, father_id: 3 }, { id: 3, father_id: 1 }];
    for (const path of search('all', rows, [])) assert.equal(new Set(path.steps.map(step => step.id)).size, path.steps.length);
    assert.equal(search('all', [{ id: 1 }, { id: 2 }], []).length, 0);
    assert.equal(Core.findAllPathsBetween(1, 1, people, [], 'all', 'ar')[0].steps.length, 1);
    assert.throws(() => Core.createSearch(1, 999, people, [], 'all', 'ar'));
});

test('normalization ignores بن and accepts Arabic and Kurdish name variants', () => {
    assert.equal(Core.normalizeSearchText('مُحَمَّد بن خَالِد بن حسن'), Core.normalizeSearchText('محمد خالد حسن'));
    assert.equal(Core.normalizeSearchText('عيسا سعيد حسن'), Core.normalizeSearchText('عيسى سعيد حسن'));
    assert.equal(Core.normalizeSearchText('كريم'), Core.normalizeSearchText('کریم'));
    const map = Core.preparePeopleMap(people, marriages);
    assert.equal(map['1'].triple_name, 'محمد خالد حسن');
});

test('bounded search agrees with independent exhaustive enumeration on small connected graphs', () => {
    let random = 8127;
    const rng = () => { random = (Math.imul(random, 1664525) + 1013904223) >>> 0; return random / 4294967296; };
    for (let round = 0; round < 15; round++) {
        const rows = Array.from({ length: 7 }, (_, index) => ({ id: index + 1, father_id: rng() < .7 ? Math.floor(rng() * 7) + 1 : null, mother_id: rng() < .6 ? Math.floor(rng() * 7) + 1 : null }));
        const unions = Array.from({ length: 3 }, () => ({ husband_id: Math.floor(rng() * 7) + 1, wife_id: Math.floor(rng() * 7) + 1 }));
        const graph = Core.buildGraph(Core.preparePeopleMap(rows, unions), unions, 'all', 'ar');
        const expected = new Set();
        function visit(id, visited, signature, remaining) {
            if (id === '2') { expected.add(signature); return; }
            if (!remaining) return;
            for (const edge of graph[id]) if (!visited.has(edge.to)) visit(edge.to, new Set([...visited, edge.to]), signature + '|' + edge.key + '>' + edge.to, remaining - 1);
        }
        visit('1', new Set(['1']), '1', 5);
        const actual = search('all', rows, unions, { maxDepth: 5, perType: 20000, workBudget: 5000000 });
        assert.equal(actual.complete, true);
        assert.deepEqual(new Set(actual.map(path => path.signature)), expected);
    }
});

test('four thousand generations do not overflow the call stack', () => {
    const rows = Array.from({ length: 4000 }, (_, index) => ({ id: index + 1, father_id: index === 3999 ? null : index + 2 }));
    const paths = Core.findAllPathsBetween(1, 4000, rows, [], 'males', 'ar', { maxDepth: 3999, perType: 2 });
    assert.equal(paths[0].steps.length, 4000);
    assert.equal(paths.complete, true);
});
