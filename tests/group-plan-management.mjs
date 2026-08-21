import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const [client, endpoint, service] = await Promise.all([
  readFile(new URL('../assets/js/groups.js', import.meta.url), 'utf8'),
  readFile(new URL('../api/plans/index.php', import.meta.url), 'utf8'),
  readFile(new URL('../src/ReadingPlanService.php', import.meta.url), 'utf8'),
]);
assert.match(client, /data-group-plans/);
assert.match(client, /method: 'PATCH'/);
assert.match(client, /method: 'DELETE'/);
assert.match(endpoint, /\$method==='PATCH'/);
assert.match(endpoint, /\$method==='DELETE'/);
assert.match(service, /gm\.role='owner'/);
assert.match(service, /UPDATE plan_days SET scheduled_date=DATE_ADD/);
console.log('group plan management tests passed');
