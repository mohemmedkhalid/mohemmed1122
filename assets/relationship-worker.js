/* Search runs off the UI thread and resumes from its last position. */
'use strict';
importScripts('relationship-core.js?v=3');
let session = null;
let requestId = 0;
let snapshot = null;
let pageToken = 0;
self.onmessage = function (event) {
    const message = event.data;
    if (message.type === 'cancel') { pageToken++; session = null; return; }
    try {
        if (message.type === 'start') {
            requestId = message.requestId;
            pageToken++;
            session = self.RelationshipCore.createSearch(message.first, message.second, message.people, message.marriages, message.mode, message.lang, { maxDepth: message.maxDepth });
            snapshot = null;
        }
        if (!session || message.requestId !== requestId) return;
        const token = ++pageToken;
        const targets = Object.fromEntries(session.categories.map(type => [type,
            message.targets && Number.isSafeInteger(message.targets[type]) && message.targets[type] >= 0
                ? message.targets[type]
                : ((snapshot && snapshot.categories.find(item => item.type === type).count) || 0) + (session.pageSize || 4)
        ]));
        const started = Date.now();
        const before = snapshot ? snapshot.expanded : 0;
        function pump() {
            if (token !== pageToken || !session) return;
            try {
                snapshot = session.advance(targets, 4000, 14);
                const pageDone = snapshot.pageComplete || snapshot.complete || snapshot.expanded - before >= 120000 || Date.now() - started >= 1600;
                self.postMessage(Object.assign({}, snapshot, { type: pageDone ? 'page' : 'progress', requestId }));
                if (!pageDone) setTimeout(pump, 0);
            } catch (error) { self.postMessage({ type: 'error', requestId, error: error.message }); }
        }
        pump();
    } catch (error) { self.postMessage({ type: 'error', requestId: message.requestId, error: error.message }); }
};
