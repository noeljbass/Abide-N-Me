import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const source = await readFile(new URL('../assets/js/audio.js', import.meta.url), 'utf8');
assert.match(source, /speechChunks\(text,maxLength=180\)/);
assert.match(source, /utterance\.onend=next/);
assert.match(source, /speechSession/);
assert.doesNotMatch(source, /new SpeechSynthesisUtterance\(context\.text\)/);
console.log('mobile audio tests passed');
