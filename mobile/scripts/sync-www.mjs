#!/usr/bin/env node
/**
 * Copies the web app into mobile/www so Capacitor can bundle it.
 *
 * The native shell ships the front end only. Everything under api/, src/,
 * config/, database/ and storage/ stays on the server at abiden.me and is
 * reached over HTTPS at runtime, so none of it belongs inside the app package.
 *
 * The service worker is deliberately excluded: inside the native shell the
 * assets are already local, and a second cache layer only creates a way for a
 * stale build to survive an app update.
 */
import { cp, mkdir, rm, readFile, writeFile, stat } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const MOBILE_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const REPO_ROOT = resolve(MOBILE_DIR, '..');
const WWW_DIR = join(MOBILE_DIR, 'www');

// Files and folders copied verbatim from the repository root.
const INCLUDE = [
  'index.html',
  'privacy.html',
  'terms.html',
  'assets',
];

// Never copied, even if a future INCLUDE entry would pull them in.
const EXCLUDE = new Set([
  'service-worker.js',
  'offline.html',
  'manifest.webmanifest',
]);

async function exists(path) {
  try { await stat(path); return true; } catch { return false; }
}

async function main() {
  await rm(WWW_DIR, { recursive: true, force: true });
  await mkdir(WWW_DIR, { recursive: true });

  for (const entry of INCLUDE) {
    if (EXCLUDE.has(entry)) continue;
    const source = join(REPO_ROOT, entry);
    if (!(await exists(source))) {
      throw new Error(`Expected ${entry} at the repository root but it is missing.`);
    }
    await cp(source, join(WWW_DIR, entry), {
      recursive: true,
      filter: (src) => !EXCLUDE.has(src.slice(REPO_ROOT.length + 1)),
    });
  }

  // The PWA manifest and the service worker registration are web-only concerns.
  // Leaving the <link rel="manifest"> in place makes the WebView request a file
  // that is not shipped, which shows up as a console error on every launch.
  const indexPath = join(WWW_DIR, 'index.html');
  let html = await readFile(indexPath, 'utf8');
  html = html.replace(/^\s*<link rel="manifest"[^>]*>\s*$/m, '');
  await writeFile(indexPath, html, 'utf8');

  console.log(`Synced web app into ${WWW_DIR}`);
}

main().catch((error) => {
  console.error(`sync-www failed: ${error.message}`);
  process.exitCode = 1;
});
