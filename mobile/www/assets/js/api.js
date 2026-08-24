import { apiBaseFor, appClientHeaders, isNative, rememberSession } from './native.js';

// Resolve the API root from this module's own URL (assets/js/api.js -> /api/).
// Page-relative URLs break on deep links such as /join/CODE, where every request
// would resolve to /join/api/... and return an HTML 404 instead of JSON.
// In the native shell there is no local API to resolve against, so apiBaseFor
// returns the absolute origin instead.
const API_BASE = apiBaseFor(import.meta.url);

// The native shell runs on its own localhost origin, which makes every API call
// cross-origin. Cookies have to be sent explicitly there; on the web the
// stricter same-origin rule is kept.
const CREDENTIALS = isNative ? 'include' : 'same-origin';

let csrfToken = '';

export async function initializeCsrf() { const data = await request('auth/csrf.php', { csrf: false }); csrfToken = data.csrf_token; return csrfToken; }
export async function api(path, options = {}) { if (!csrfToken && options.method && options.method !== 'GET') await initializeCsrf(); const data = await request(path, options); if (data && data.csrf_token) csrfToken = data.csrf_token; return data; }

function failure(message, { status = 0, code = 'request_failed', retryable = false } = {}) {
  const error = new Error(message);
  error.status = status; error.code = code; error.retryable = retryable;
  return error;
}

async function request(path, { method = 'GET', body, csrf = true } = {}) {
  const headers = { Accept: 'application/json', ...appClientHeaders() };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (csrf && csrfToken) headers['X-CSRF-Token'] = csrfToken;

  let response;
  try {
    response = await fetch(new URL(path, API_BASE), { method, headers, credentials: CREDENTIALS, cache: 'no-store', body: body === undefined ? undefined : JSON.stringify(body) });
  } catch {
    // Offline, a dropped mobile connection, or a service worker that could not reach the network.
    throw failure('We could not reach the server. Check your connection and try again.', { status: 0, code: 'network_unavailable', retryable: true });
  }

  let payload = null;
  try { payload = await response.json(); } catch { payload = null; }
  // Kept before the status checks: an error response still reports which
  // session it was answered in, and losing that would strand the app.
  rememberSession(payload);
  const transient = response.status === 0 || response.status === 429 || response.status >= 500;

  if (payload === null || typeof payload !== 'object') {
    throw failure('The server returned an unreadable response.', { status: response.status, code: 'unreadable_response', retryable: transient });
  }
  if (!response.ok || !payload.success) {
    throw failure(payload.error?.message || 'The request could not be completed.', { status: response.status, code: payload.error?.code || 'request_failed', retryable: transient });
  }
  return payload.data;
}
