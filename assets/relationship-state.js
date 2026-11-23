/* Browser navigation state. Store identifiers and view settings, never family records. */
(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else root.RelationshipState = factory();
})(typeof self !== 'undefined' ? self : this, function () {
    'use strict';
    const VERSION = 1;
    const modes = ['parents', 'all', 'males', 'females', 'spouses'];
    const categories = ['all', 'male', 'female', 'spouse', 'ff', 'fm', 'mf', 'mm', 'direct'];
    const id = value => /^[1-9]\d*$/.test(String(value)) && Number.isSafeInteger(Number(value)) ? String(value) : '';
    const number = (value, fallback, min, max) => Number.isFinite(Number(value)) ? Math.min(max, Math.max(min, Number(value))) : fallback;
    const string = (value, max) => typeof value === 'string' ? value.slice(0, max) : '';
    function clean(input, owner) {
        if (!input || input.version !== VERSION || input.owner !== owner) return null;
        const counts = {}, diagrams = {};
        for (const [key, value] of Object.entries(input.counts || {}).slice(0, 10000)) {
            if (/^(male|female|spouse|ancestor:\d+:(ff|fm|mf|mm|direct))$/.test(key)) counts[key] = Math.floor(number(value, 0, 0, 1000000));
        }
        for (const [key, value] of Object.entries(input.diagrams || {}).slice(0, 500)) {
            if (!value || key === '__proto__' || key === 'constructor' || key.length > 20000) continue;
            diagrams[key] = { scale: number(value.scale, .85, .1, 1.7), left: number(value.left, 0, -1000000, 1000000), top: number(value.top, 0, 0, 1000000) };
        }
        return {
            version: VERSION, owner, first: id(input.first), second: id(input.second),
            draftFirst: string(input.draftFirst, 200), draftSecond: string(input.draftSecond, 200),
            language: ['ar', 'ku'].includes(input.language) ? input.language : 'ku',
            mode: modes.includes(input.mode) ? input.mode : 'parents', depth: Math.floor(number(input.depth, 8, 1, 1000000)),
            view: input.view === 'chain' ? 'chain' : 'tree', speed: [.5, 1, 2].includes(Number(input.speed)) ? Number(input.speed) : 1,
            category: categories.includes(input.category) ? input.category : 'all', ancestor: id(input.ancestor),
            route: string(input.route, 20000), paused: input.paused === true, hasResults: input.hasResults === true,
            complete: input.complete === true, counts, diagrams, scroll: number(input.scroll, 0, 0, 10000000)
        };
    }
    function defaults(owner, language) { return clean({ version: VERSION, owner, language, depth: 8, speed: 1 }, owner); }
    function resolve(saved, url, owner, language) {
        const base = saved || defaults(owner, language), state = clean(base, owner), query = new URL(url).searchParams;
        const original = [state.first, state.second, state.mode, state.depth].join('|');
        if (query.has('first') || query.has('second')) {
            state.first = id(query.get('first')); state.second = id(query.get('second'));
            state.draftFirst = ''; state.draftSecond = '';
            // A matching entry may contain unfinished search text.
            if (saved && state.first === saved.first && state.second === saved.second) {
                state.draftFirst = saved.draftFirst; state.draftSecond = saved.draftSecond;
            }
        }
        if (query.has('mode')) state.mode = modes.includes(query.get('mode')) ? query.get('mode') : 'parents';
        if (query.has('depth')) state.depth = Math.floor(number(query.get('depth'), 8, 1, 1000000));
        if (['ar', 'ku'].includes(query.get('language'))) state.language = query.get('language');
        if (!saved || original !== [state.first, state.second, state.mode, state.depth].join('|')) {
            state.category = 'all'; state.ancestor = ''; state.route = ''; state.counts = {}; state.diagrams = {}; state.scroll = 0;
            state.complete = false; state.paused = false; state.hasResults = !!(state.first && state.second);
        }
        return state;
    }
    function create(win, owner) {
        owner = String(owner || '');
        const key = 'family-relationship:v4:' + win.location.pathname + ':' + owner;
        function readStorage(kind) {
            try { return clean(JSON.parse(win[kind].getItem(key)), owner); } catch (_) { return null; }
        }
        return {
            owner, key,
            read(language) {
                let entry = null;
                try { entry = clean(win.history.state && win.history.state.relationship, owner); } catch (_) {}
                return resolve(entry || readStorage('sessionStorage') || readStorage('localStorage'), win.location.href, owner, language);
            },
            save(input) {
                const state = clean(Object.assign({}, input, { version: VERSION, owner }), owner);
                if (!state) return;
                const json = JSON.stringify(state), url = new URL(win.location.href);
                for (const field of ['first', 'second', 'mode', 'depth', 'language']) url.searchParams.set(field, state[field]);
                try { win.history.replaceState(Object.assign({}, win.history.state, { relationship: state }), '', url); } catch (_) {}
                for (const kind of ['sessionStorage', 'localStorage']) {
                    try { win[kind].setItem(key, json); } catch (_) {}
                }
            }
        };
    }
    return { create, clean, resolve, defaults };
});
