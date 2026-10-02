import axios from 'axios';
window.axios = axios;

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
