/* assets/natural-tree-worker.js — يحسب توزيع الشجرة في الخلفية حتى تبقى الصفحة سريعة الاستجابة */
/* global NaturalTreeLayout */
importScripts('natural-tree-layout.js?v=1');

self.onmessage = function (event) {
    const msg = event.data || {};
    try {
        const widths = msg.widths || {};
        const result = NaturalTreeLayout.layout(msg.people || [], Object.assign({}, msg.options || {}, {
            measure: person => widths[person.id] || 0
        }));
        delete result.forest;
        self.postMessage({ token: msg.token, result });
    } catch (error) {
        self.postMessage({ token: msg.token, error: String(error && error.message || error) });
    }
};
