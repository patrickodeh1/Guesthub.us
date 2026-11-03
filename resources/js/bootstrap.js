import axios from 'axios';
window.axios = axios;
const APP_BASE = document.querySelector('meta[name="app-base"]')?.content ?? '';
window.APP_BASE = APP_BASE;
if (APP_BASE) { window.axios.defaults.baseURL = APP_BASE; }

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Compatibility layer for components carried over from the old site:
// resolves with the parsed JSON body ({success, data, ...}) instead of the axios response.
// Errors are rethrown unchanged, so error.response.status still works.
const unwrap = (promise) => promise.then((r) => r.data);
window.api = {
    get: (url, config) => unwrap(window.axios.get(url, config)),
    delete: (url, config) => unwrap(window.axios.delete(url, config)),
    post: (url, data, config) => unwrap(window.axios.post(url, data, config)),
    put: (url, data, config) => unwrap(window.axios.put(url, data, config)),
    patch: (url, data, config) => unwrap(window.axios.patch(url, data, config)),
};
window.axios.defaults.headers.common['Accept'] = 'application/json';

// Track in-flight api calls so "Close Report" can wait for pending saves/uploads.
(function () {
    if (!window.api || window.api.__tracked) return;
    window.__ghPending = new Set();
    Object.keys(window.api).forEach(function (k) {
        var fn = window.api[k];
        if (typeof fn !== 'function') return;
        window.api[k] = function () {
            var p = fn.apply(this, arguments);
            if (p && typeof p.then === 'function') {
                window.__ghPending.add(p);
                var done = function () { window.__ghPending.delete(p); };
                p.then(done, done);
            }
            return p;
        };
    });
    window.api.__tracked = true;
})();
