import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const account = readFileSync(new URL('../index.html', import.meta.url), 'utf8');
const privacy = readFileSync(new URL('../privacy.html', import.meta.url), 'utf8');
const terms = readFileSync(new URL('../terms.html', import.meta.url), 'utf8');
const serviceWorker = readFileSync(new URL('../service-worker.js', import.meta.url), 'utf8');

assert.match(account, /href="privacy\.html">Privacy Policy<\/a>/);
assert.match(account, /href="terms\.html">Terms of Use<\/a>/);
assert.match(account, /href="mailto:info@abiden\.me">info@abiden\.me<\/a>/);

assert.match(privacy, /We do not use your email address for marketing\./);
assert.match(privacy, /href="mailto:info@abiden\.me"/);
assert.match(terms, /href="privacy\.html">Privacy Policy<\/a>/);
assert.match(terms, /href="mailto:info@abiden\.me"/);

assert.match(serviceWorker, /'\.\/privacy\.html'/);
assert.match(serviceWorker, /'\.\/terms\.html'/);

console.log('legal page tests passed');
