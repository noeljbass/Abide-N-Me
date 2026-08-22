import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = (path) => readFile(new URL(`../${path}`, import.meta.url), 'utf8');
const [html, auth, login, register, session, schema, clientAuth, serviceWorker] = await Promise.all([
  read('index.html'), read('src/Auth.php'), read('api/auth/login.php'),
  read('api/auth/register.php'), read('src/Session.php'), read('database/schema.sql'),
  read('assets/js/auth.js'),
  read('service-worker.js'),
]);
const [clientApi, health] = await Promise.all([read('assets/js/api.js'), read('api/health.php')]);

assert.doesNotMatch(html, /name="email"/);
assert.match(html, /name="username"/);
assert.doesNotMatch(auth, /u\.email|email_verified/);
assert.match(login, /Validator::username/);
assert.match(register, /username_unavailable/);
assert.match(schema, /UNIQUE KEY uq_users_username/);
assert.doesNotMatch(schema, /email VARCHAR/);
assert.match(session, /private const LIFETIME = 31536000/);
assert.match(session, /setcookie\(session_name\(\), session_id\(\)/);
assert.match(clientAuth, /\[data-profile-username\], \[data-profile-email\]/);
assert.match(clientAuth, /if \(!element\) return/);
assert.match(serviceWorker, /feed-my-sheep-shell-v17/);

// Sign-in persistence: sessions must outlive the shared host's own garbage collection,
// and an unresolved account check must never be rendered as "signed out".
assert.match(session, /storage\/sessions/);
assert.match(session, /'samesite' => 'Lax'/);
assert.match(health, /'auth_sessions'/);
assert.doesNotMatch(health, /auth_tokens/);
assert.match(clientAuth, /error\.status === 401/);
assert.doesNotMatch(clientAuth, /Account services are temporarily unavailable/);
assert.match(clientAuth, /data-auth-status-card/);
assert.match(html, /data-auth-status-card/);
assert.match(clientApi, /import\.meta\.url/);
assert.match(serviceWorker, /pathname\.includes\('\/api\/'\)/);

console.log('Authentication contract checks passed.');
