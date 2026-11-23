// Run from the project folder: node --test tests/natural-tree-layout.test.js
const test = require('node:test');
const assert = require('node:assert/strict');
const Layout = require('../assets/natural-tree-layout.js');

function rng(seed) {
    let s = seed >>> 0 || 1;
    return () => { s ^= s << 13; s >>>= 0; s ^= s >>> 17; s ^= s << 5; s >>>= 0; return s / 4294967296; };
}
const NAMES = ['محمد', 'أحمد', 'علي', 'حسن', 'فاطمة', 'زينب', 'ئاراس', 'هاوژین', 'شڤان', 'عبدالكريم الطويل', 'نور'];
function family({ seed = 1, generations = 6, max = 500, kids }) {
    const r = rng(seed);
    const people = [{ id: 1, name: 'المؤسس', gender: 'Male', is_primary_ancestor: 1 }];
    let frontier = [people[0]];
    for (let g = 1; g < generations && people.length < max && frontier.length; g++) {
        const next = [];
        for (const parent of frontier) {
            const count = kids(r, g);
            for (let i = 0; i < count && people.length < max; i++) {
                const child = { id: people.length + 1, name: NAMES[Math.floor(r() * NAMES.length)], gender: r() < 0.5 ? 'Male' : 'Female', father_id: parent.id, birth_date: `${1900 + g * 25 + i}-01-01` };
                people.push(child);
                next.push(child);
            }
        }
        frontier = next;
    }
    return people;
}

function assertHealthy(result, label) {
    const report = Layout.validate(result);
    assert.ok(result.width <= result.maxWidth, `${label}: width ${result.width} exceeds ${result.maxWidth}`);
    assert.deepEqual(report.outOfBounds, [], `${label}: something left the ${result.maxWidth}px frame`);
    assert.deepEqual(report.overlaps, [], `${label}: cards overlap`);
    assert.deepEqual(report.branchThroughCard, [], `${label}: a branch runs under another card`);
    assert.deepEqual(report.branchCrossings, [], `${label}: branches cross`);
}

test('the tree never exceeds 2000px, whatever the family size or shape', () => {
    const shapes = [
        { seed: 2, generations: 4, max: 40, kids: (r, g) => (g === 1 ? 5 : Math.floor(r() * 4)) },
        { seed: 3, generations: 8, max: 600, kids: (r, g) => (g === 1 ? 6 : Math.floor(r() * r() * 7)) },
        { seed: 4, generations: 12, max: 2500, kids: (r, g) => (g === 1 ? 7 : Math.floor(r() * r() * 8)) },
        { seed: 5, generations: 10, max: 1023, kids: () => 2 },
        { seed: 6, generations: 3, max: 700, kids: (r, g) => (g === 1 ? 50 : 10) },
        { seed: 7, generations: 30, max: 30, kids: () => 1 }
    ];
    for (const shape of shapes) {
        const people = family(shape);
        const result = Layout.layout(people);
        assert.equal(result.cards.length, people.length);
        assertHealthy(result, `seed ${shape.seed}`);
    }
});

test('one huge generation is folded into the crown instead of a single horizontal line', () => {
    const people = family({ seed: 9, generations: 2, max: 501, kids: () => 500 });
    const result = Layout.layout(people);
    assertHealthy(result, '500 siblings');
    const children = result.cards.filter(card => !card.isRoot);
    const rows = new Set(children.map(card => Math.round(card.y)));
    assert.ok(rows.size >= 10, `siblings use ${rows.size} vertical levels`);
    const usedWidth = Math.max(...children.map(c => c.x + c.w)) - Math.min(...children.map(c => c.x));
    assert.ok(usedWidth > result.width * 0.6, 'the crown spreads across the available width');
});

test('the founder sits on the trunk at the bottom and branches thin out towards the young', () => {
    const people = family({ seed: 11, generations: 7, max: 400, kids: (r, g) => (g === 1 ? 5 : Math.floor(r() * 5)) });
    const result = Layout.layout(people);
    const founder = result.cards.find(card => card.isRoot);
    assert.equal(founder.id, 1);
    for (const card of result.cards) assert.ok(card.y <= founder.y + 1e-6, `card ${card.id} is below the founder`);
    const trunk = result.branches.find(b => b.kind === 'trunk');
    assert.ok(trunk.w0 > trunk.w1, 'trunk is thicker at the ground');
    const incoming = new Map();
    for (const b of result.branches) if (b.kind !== 'stem' && b.kind !== 'knot' && b.to !== null) incoming.set(b.to, b);
    for (const b of result.branches) {
        if (b.kind === 'trunk' || b.kind === 'knot') continue;
        assert.ok(b.w0 >= b.w1 - 1e-6, 'each branch tapers along its length');
        assert.ok(b.w0 <= trunk.w0, 'no branch is thicker than the trunk');
        const parentBranch = incoming.get(b.from);
        if (parentBranch && b.kind !== 'stem') assert.ok(b.w0 <= parentBranch.w0 + 1e-6, `branch to ${b.to} is thicker than its parent branch`);
    }
});

test('left and right sides of the crown carry a similar number of people', () => {
    for (const seed of [21, 22, 23, 24, 25, 26]) {
        const people = family({ seed, generations: 7, max: 500, kids: (r, g) => (g === 1 ? 3 + Math.floor(r() * 4) : Math.floor(r() * 4)) });
        if (people.length < 25) continue;   // عائلة صغيرة جداً لا يمكن قياس توازنها بعدد الأفراد
        const result = Layout.layout(people);
        assert.ok(result.stats.balance <= 0.2, `seed ${seed}: balance ${result.stats.balance.toFixed(2)}`);
    }
});

test('adding and deleting people re-lays the tree and stays inside the width limit', () => {
    let people = family({ seed: 31, generations: 6, max: 300, kids: (r, g) => (g === 1 ? 4 : Math.floor(r() * 5)) });
    const before = Layout.layout(people);
    assertHealthy(before, 'before');
    const parent = people[5];
    for (let i = 0; i < 40; i++) people.push({ id: 10000 + i, name: 'مولود جديد', father_id: parent.id });
    const grown = Layout.layout(people);
    assertHealthy(grown, 'after adding');
    assert.equal(grown.cards.length, before.cards.length + 40);
    // حذف شخص: delete_person.php يفصل روابط أبنائه فيصبحون بداية شجرة مستقلة
    const removed = people[3].id;
    people = people.filter(p => p.id !== removed).map(p => (p.father_id === removed ? Object.assign({}, p, { father_id: null }) : p));
    const shrunk = Layout.layout(people);
    assertHealthy(shrunk, 'after deleting');
    assert.ok(!shrunk.cards.some(card => card.id === removed));
    assert.ok(shrunk.forest.roots.length > 1, 'orphaned children become their own tree');
});

test('a custom maximum width is respected too', () => {
    const people = family({ seed: 41, generations: 8, max: 800, kids: (r, g) => (g === 1 ? 6 : Math.floor(r() * r() * 7)) });
    const result = Layout.layout(people, { maxWidth: 1200 });
    assert.ok(result.width <= 1200);
    assertHealthy(result, 'maxWidth 1200');
});

test('messy data: cycles, missing parents, duplicates, mother-only links and text ids', () => {
    const people = [
        { id: '1', name: 'جد', is_primary_ancestor: '1' },
        { id: 2, name: 'ابن', father_id: '1' },
        { id: 2, name: 'نسخة مكررة', father_id: 1 },
        { id: 3, name: 'ابنة', father_id: 1, gender: 'Female' },
        { id: 4, name: 'حفيد من الأم', father_id: 999, mother_id: 3 },
        { id: 5, name: 'أ', father_id: 6 },
        { id: 6, name: 'ب', father_id: 5 },
        { id: 7, name: 'لنفسه', father_id: 7 },
        { id: 0, name: 'بلا رقم' }
    ];
    const forest = Layout.buildForest(people);
    assert.equal(forest.byId.size, 7);
    assert.equal(forest.primary.id, 1);
    assert.equal(forest.byId.get(4).parent.id, 3, 'child attached to the mother when the father is unknown');
    assert.ok(!forest.byId.get(5).parent || !forest.byId.get(6).parent, 'the 5↔6 cycle is broken');
    assert.equal(forest.byId.get(7).parent, null);
    const result = Layout.layout(people, { forest });
    assert.deepEqual(result.cards.map(c => c.id).sort(), [1, 2, 3, 4]);
    assertHealthy(result, 'messy');
});

test('empty data and a single person still produce a valid drawing', () => {
    const empty = Layout.layout([]);
    assert.equal(empty.cards.length, 0);
    assert.ok(empty.width <= 2000);
    const single = Layout.layout([{ id: 1, name: 'وحيد' }]);
    assert.equal(single.cards.length, 1);
    assertHealthy(single, 'single');
});
