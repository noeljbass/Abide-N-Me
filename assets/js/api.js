let csrfToken = '';
export async function initializeCsrf() { const data = await request('auth/csrf.php', { csrf: false }); csrfToken = data.csrf_token; return csrfToken; }
export async function api(path, options = {}) { if (!csrfToken && options.method && options.method !== 'GET') await initializeCsrf(); const data = await request(path, options); if (data.csrf_token) csrfToken = data.csrf_token; return data; }
async function request(path, { method = 'GET', body, csrf = true } = {}) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (csrf && csrfToken) headers['X-CSRF-Token'] = csrfToken;
  const response = await fetch(`api/${path}`, { method, headers, credentials: 'same-origin', body: body === undefined ? undefined : JSON.stringify(body) });
  let payload; try { payload = await response.json(); } catch { throw new Error('The server returned an unreadable response.'); }
  if (!response.ok || !payload.success) { const error = new Error(payload.error?.message || 'The request could not be completed.'); error.code = payload.error?.code; error.status = response.status; throw error; }
  return payload.data;
}
