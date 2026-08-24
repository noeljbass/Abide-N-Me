/**
 * Native shell integration.
 *
 * The same files are served three ways: from abiden.me in a browser, from the
 * Android package, and from the iOS package. Only the first of those has the
 * PHP API on its own origin, so anything that depends on where the app is
 * running is resolved here rather than sprinkled through the feature modules.
 *
 * Every branch degrades to the existing web behaviour when window.Capacitor is
 * absent, so loading this module in a plain browser changes nothing.
 */

/**
 * The origin that serves the PHP API and the public web app.
 *
 * The native builds have no server of their own: their pages load from
 * https://localhost (Android) or capacitor://localhost (iOS), so every API call
 * has to name this host explicitly. Change it here and nowhere else if the
 * domain ever moves.
 */
export const WEB_ORIGIN = 'https://abiden.me';

const bridge = globalThis.Capacitor;

/** True inside the Android or iOS package, false in any browser. */
export const isNative = Boolean(bridge?.isNativePlatform?.());

/** 'android', 'ios', or 'web'. */
export const platform = bridge?.getPlatform?.() ?? 'web';

const plugin = (name) => (isNative ? bridge?.Plugins?.[name] : undefined);

/**
 * Where API requests should be sent.
 *
 * On the web this keeps the existing behaviour of resolving /api/ relative to
 * this module's own URL, which is what makes deep links such as /join/CODE work
 * without every request resolving to /join/api/.
 */
export function apiBaseFor(moduleUrl) {
  return isNative ? new URL('/api/', WEB_ORIGIN) : new URL('../../api/', moduleUrl);
}

/**
 * The base URL to use when building a link meant to be opened by someone else.
 *
 * An invitation generated inside the app must point at the website, never at
 * the app's internal localhost origin, or it is useless the moment it is shared.
 */
export function shareBaseUrl() {
  if (isNative) return new URL('/', WEB_ORIGIN);
  const url = new URL(location.href);
  url.pathname = url.pathname.replace(/\/join\/.*$/, '/').replace(/index\.html$/, '');
  url.search = '';
  url.hash = '';
  return url;
}

/** Pulls an invitation code out of either link shape: /join/CODE or ?invite=CODE. */
export function inviteCodeFrom(href) {
  try {
    const url = new URL(href, WEB_ORIGIN);
    const query = url.searchParams.get('invite');
    if (query) return query;
    return url.pathname.match(/\/join\/([A-Za-z0-9-]+)/)?.[1] ?? null;
  } catch {
    return null;
  }
}

/* ------------------------------------------------------------- session ---- */

const SESSION_KEY = 'abideNMe.sessionId';
const APP_CLIENT = 'abiden-native';

/**
 * Headers that identify this request as coming from the packaged app.
 *
 * Inside the shell the pages load from the app's own origin while the session
 * belongs to abiden.me, and the session cookie does not reliably survive that
 * boundary — which shows up as "Your session could not be verified", because
 * the request that fetched the CSRF token and the request that used it landed
 * in two different sessions. Carrying the identifier explicitly removes the
 * dependency on cookie behaviour altogether.
 */
export function appClientHeaders() {
  if (!isNative) return {};

  const headers = { 'X-App-Client': APP_CLIENT };
  try {
    const id = localStorage.getItem(SESSION_KEY);
    if (id) headers['X-Session-Id'] = id;
  } catch {
    // Storage can be unavailable; the request still works, it just starts a
    // new session.
  }
  return headers;
}

/** Stores the session identifier the server reported, if it sent one. */
export function rememberSession(payload) {
  if (!isNative) return;
  const id = payload?.session;
  if (typeof id !== 'string' || id === '') return;
  try {
    localStorage.setItem(SESSION_KEY, id);
  } catch {
    // Nothing to do: the next request simply starts a new session.
  }
}

/* -------------------------------------------------------------- speech ---- */

/**
 * Reading a chapter aloud.
 *
 * Android's WebView does not implement the Web Speech API, so `speechSynthesis`
 * is simply absent inside the app even though the device has a perfectly good
 * text-to-speech engine. That engine is reached through a Capacitor plugin
 * instead. On the web nothing changes.
 */
function createSpeaker() {
  let session = 0;
  let speaking = false;

  const nativeEngine = () => plugin('TextToSpeech');
  const webEngine = () => (typeof window !== 'undefined' && 'speechSynthesis' in window ? window.speechSynthesis : null);

  async function speakNatively(chunks, rate, handlers, token) {
    for (const chunk of chunks) {
      if (token !== session) return;
      try {
        await nativeEngine().speak({ text: chunk, lang: 'en-US', rate, pitch: 1, volume: 1, category: 'playback' });
      } catch (error) {
        if (token !== session) return;
        speaking = false;
        handlers.onError?.(error);
        return;
      }
    }
    if (token !== session) return;
    speaking = false;
    handlers.onEnd?.();
  }

  function speakInBrowser(chunks, rate, handlers, token) {
    const engine = webEngine();
    let index = 0;
    const next = () => {
      if (token !== session) return;
      if (index >= chunks.length) {
        speaking = false;
        handlers.onEnd?.();
        return;
      }
      const utterance = new SpeechSynthesisUtterance(chunks[index++]);
      utterance.rate = rate;
      utterance.onend = next;
      utterance.onerror = (event) => {
        if (event.error === 'canceled' || event.error === 'interrupted') return;
        speaking = false;
        handlers.onError?.(event);
      };
      engine.speak(utterance);
    };
    next();
  }

  return {
    get available() {
      return Boolean(isNative ? nativeEngine() : webEngine());
    },
    get speaking() {
      return speaking;
    },
    speak(chunks, rate, handlers = {}) {
      const token = ++session;
      speaking = true;
      if (isNative && nativeEngine()) speakNatively(chunks, rate, handlers, token);
      else if (webEngine()) speakInBrowser(chunks, rate, handlers, token);
      else {
        speaking = false;
        handlers.onError?.(new Error('No speech engine is available.'));
      }
    },
    stop() {
      session++;
      speaking = false;
      if (isNative) nativeEngine()?.stop().catch(() => {});
      else webEngine()?.cancel();
    },
  };
}

export const speaker = createSpeaker();

/**
 * Wires up the pieces of native behaviour a web page gets for free:
 * dismissing the launch screen, tinting the status bar, the Android back
 * button, and links opened from outside the app.
 */
export function initNative() {
  if (!isNative) return;

  document.documentElement.dataset.platform = platform;

  const splash = plugin('SplashScreen');
  const hideSplash = () => splash?.hide({ fadeOutDuration: 200 }).catch(() => {});
  if (document.readyState === 'complete') hideSplash();
  else window.addEventListener('load', hideSplash, { once: true });
  // A failed API call must never leave the launch screen up forever.
  window.setTimeout(hideSplash, 3000);

  const statusBar = plugin('StatusBar');
  statusBar?.setStyle({ style: 'LIGHT' }).catch(() => {});
  if (platform === 'android') {
    statusBar?.setBackgroundColor({ color: '#f7f3ea' }).catch(() => {});
  }

  const app = plugin('App');

  // Android's system back button. Hash routing means history.back() is the
  // right move almost everywhere; at the first screen the expected behaviour is
  // to leave the app rather than to show a blank page.
  app?.addListener('backButton', ({ canGoBack }) => {
    const dialog = document.querySelector('dialog[open]');
    if (dialog) { dialog.close(); return; }
    if (canGoBack && window.history.length > 1) window.history.back();
    else app.exitApp();
  });

  // A tapped invitation link that the system handed to the app instead of the
  // browser. The group view listens for this and shows the join card.
  app?.addListener('appUrlOpen', ({ url }) => {
    const code = inviteCodeFrom(url);
    if (!code) return;
    window.dispatchEvent(new CustomEvent('invite:incoming', { detail: { code } }));
  });
}
