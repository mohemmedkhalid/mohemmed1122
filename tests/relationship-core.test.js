// Run from the project folder: node --test tests/relationship-core.test.js
const test = require('node:test');
const assert = require('node:assert/strict');
const Core = require('../assets/relationship-core.js');

const people = Core.buildIndex([
    { id: 1, full_name: 'Grandfather' }, { id: 2, full_name: 'Grandmother' },
    { id: 3, father_id: 1, mother_id: 2 }, { id: 4, father_id: 1, mother_id: 2 },
    { id: 5, father_id: 3 }, { id: 6, mother_id: 4 }, { id: 7 },
    { id: 8, father_id: 999 }, { id: 9, mother_id: 2 }, { id: 10, father_id: 9 }
]);

test('mixed maternal and paternal links reach both shared grandparents', () => {
    const result = Core.connect(people, 5, 6, 'both');
    assert.deepEqual(result.common.map(person => person.id), [1, 2]);
    assert.deepEqual(result.path(1).first.map(person => person.id), [5, 3, 1]);
    assert.deepEqual(result.path(1).second.map(person => person.id), [6, 4, 1]);
    assert.equal(result.path(1).second[1].via, 'mother');
});

test('father-only and mother-only modes follow only the requested parent', () => {
    assert.equal(Core.connect(people, 5, 6, 'father').common.length, 0);
    assert.deepEqual(Core.connect(people, 6, 9, 'mother').common.map(person => person.id), [2]);
});

test('direct ancestors and identical selections include the selected person', () => {
    assert.deepEqual(Core.connect(people, 1, 5).path(1).first.map(person => person.id), [1]);
    assert.equal(Core.connect(people, 5, 5).common[0].id, 5);
});

test('disconnected families and missing parents never invent a connection', () => {
    assert.equal(Core.connect(people, 5, 7).common.length, 0);
    assert.equal(Core.connect(people, 8, 7).common.length, 0);
    assert.equal(Core.connect(people, 5, 6).path(999), null);
    assert.throws(() => Core.connect(people, 999, 5));
});

test('the closest ancestor is chosen before an earlier shared ancestor', () => {
    const graph = Core.buildIndex([{ id: 1 }, { id: 2, father_id: 1 }, { id: 3, father_id: 2 }, { id: 4, father_id: 2 }]);
    assert.deepEqual(Core.connect(graph, 3, 4).common.map(person => person.id), [2, 1]);
});

test('cycles terminate and four thousand generations do not overflow the stack', () => {
    const cycle = Core.buildIndex([{ id: 11, father_id: 12 }, { id: 12, father_id: 11 }]);
    assert.equal(Core.ancestorMap(cycle, 11, 'both').size, 2);
    const deep = Core.buildIndex(Array.from({ length: 4000 }, (_, index) => ({ id: index + 1, father_id: index === 3999 ? null : index + 2 })));
    assert.equal(Core.connect(deep, 1, 4000).path(4000).first.length, 4000);
});

test('search accepts Arabic diacritics and Kurdish letter variants', () => {
    assert.equal(Core.normalizeSearch('مُحَمَّد كَريم'), Core.normalizeSearch('محمد کریم'));
});
