import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const read = (path) => readFile(new URL(`../${path}`, import.meta.url), 'utf8');
const [html, auth, login, register, session, schema, clientAuth, serviceWorker] = await Promise.all([
  read('index.html'), read('src/Auth.php'), read('api/auth/login.php'),
  read('api/auth/register.php'), read('src/Session.php'), read('database/schema.sql'),
  read('assets/js/auth.js'),
  read('service-worker.js'),
]);

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
assert.match(serviceWorker, /feed-my-sheep-shell-v16/);

console.log('Authentication contract checks passed.');
