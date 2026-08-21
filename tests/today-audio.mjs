import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const source=await readFile(new URL('../assets/js/today.js',import.meta.url),'utf8');
assert.match(source,/api\(`bible\/chapter\.php\?translation=\$\{encodeURIComponent\(translation\)\}&book=\$\{encodeURIComponent\(book\)\}&chapter=\$\{chapter\}`\)/);
assert.match(source,/text:data\.verses\.map\(item=>item\.text\)\.join\(' '\)/);
assert.match(source,/detail:\{translation,book,chapter,/);
console.log('today audio tests passed');
