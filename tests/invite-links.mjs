import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const groups=await readFile(new URL('../assets/js/groups.js',import.meta.url),'utf8');
const auth=await readFile(new URL('../assets/js/auth.js',import.meta.url),'utf8');
assert.match(groups,/new URLSearchParams\(location\.search\)\.get\('invite'\)/);
assert.match(groups,/url\.searchParams\.set\('invite', invite\.code\)/);
assert.match(groups,/url\.hash = 'group'/);
assert.match(auth,/if \(!localStorage\.getItem\('feedMySheep\.pendingInvite'\)\)/);
console.log('invite link tests passed');
