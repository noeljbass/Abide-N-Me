import { isNative } from './native.js';

export function initPwa() {
  // The native shell already carries the app files inside its package. A second
  // cache layer there only creates a way for a stale build to outlive an app
  // update, so the service worker stays a web-only concern.
  if (isNative) return;
  if (!('serviceWorker' in navigator)) return;

  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./service-worker.js').catch(() => {
      // The application remains usable if service workers are unavailable.
    });
  });
}
