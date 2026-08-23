import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../assets/js/bible.js', import.meta.url), 'utf8');

assert.match(source, /hiddenTranslationCodes=new Set\(\['DRA1899'\]\)/);
assert.match(source, /data\.translations\.filter\(t=>!hiddenTranslationCodes\.has\(t\.code\)\)/);
