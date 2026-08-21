import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const client = await readFile(new URL('../assets/js/plans.js', import.meta.url), 'utf8');

assert.match(client, /function syncMode\(\)/);
assert.match(client, /manualFields\.querySelectorAll\([^)]*\).*control\.disabled=automatic/);
assert.match(client, /automaticFields\.querySelectorAll\([^)]*\).*control\.disabled=!automatic/);
assert.match(client, /addDay\(\);syncMode\(\)/);

console.log('plan builder mode tests passed');
