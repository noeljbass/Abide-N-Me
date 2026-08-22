// Resolve the API root from this module's own URL (assets/js/api.js -> /api/).
// Page-relative URLs break on deep links such as /join/CODE, where every request
// would resolve to /join/api/... and return an HTML 404 instead of JSON.
const API_BASE = new URL('../../api/', import.meta.url);

let csrfToken = '';

export async function initializeCsrf() { const data = await request('auth/csrf.php', { csrf: false }); csrfToken = data.csrf_token; return csrfToken; }
export async function api(path, options = {}) { if (!csrfToken && options.method && options.method !== 'GET') await initializeCsrf(); const data = await request(path, options); if (data && data.csrf_token) csrfToken = data.csrf_token; return data; }

function failure(message, { status = 0, code = 'request_failed', retryable = false } = {}) {
  const error = new Error(message);
  error.status = status; error.code = code; error.retryable = retryable;
  return error;
}

async function request(path, { method = 'GET', body, csrf = true } = {}) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (csrf && csrfToken) headers['X-CSRF-Token'] = csrfToken;

  let response;
  try {
    response = await fetch(new URL(path, API_BASE), { method, headers, credentials: 'same-origin', cache: 'no-store', body: body === undefined ? undefined : JSON.stringify(body) });
  } catch {
    // Offline, a dropped mobile connection, or a service worker that could not reach the network.
    throw failure('We could not reach the server. Check your connection and try again.', { status: 0, code: 'network_unavailable', retryable: true });
  }

  let payload = null;
  try { payload = await response.json(); } catch { payload = null; }
  const transient = response.status === 0 || response.status === 429 || response.status >= 500;

  if (payload === null || typeof payload !== 'object') {
    throw failure('The server returned an unreadable response.', { status: response.status, code: 'unreadable_response', retryable: transient });
  }
  if (!response.ok || !payload.success) {
    throw failure(payload.error?.message || 'The request could not be completed.', { status: response.status, code: payload.error?.code || 'request_failed', retryable: transient });
  }
  return payload.data;
}
