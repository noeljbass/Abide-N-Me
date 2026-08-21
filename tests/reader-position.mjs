import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source=await readFile(new URL('../assets/js/bible.js',import.meta.url),'utf8');
assert.match(source,/localStorage\.setItem\('reader\.translation',translation\.value\)/);
assert.match(source,/api\('user\/reader\.php'/);
assert.match(source,/reference:`\$\{book\.options\[book\.selectedIndex\]\.text\} \$\{chapter\.value\}`/);
assert.doesNotMatch(source,/reference:activePassage\?\.display_reference/);
console.log('reader position tests passed');
