import { api, initializeCsrf } from './api.js';
export function initAuth() {
  const guest = document.querySelector('[data-auth-guest]'); const member = document.querySelector('[data-auth-user]');
  const authMessage = document.querySelector('[data-auth-message]'); const profileMessage = document.querySelector('[data-profile-message]');
  const statusCard = document.querySelector('[data-auth-status-card]'); const statusMessage = document.querySelector('[data-auth-status]');
  const retryButton = document.querySelector('[data-auth-retry]');
  // The service worker can briefly pair a newly deployed script with an older
  // cached document. Treat optional account elements defensively so that one
  // renamed field cannot make a valid authenticated session look signed out.
  const message = (element, value, success = false) => {
    if (!element) return;
    element.textContent = value; element.classList.toggle('is-success', success); element.hidden = !value;
  };
  const render = (user) => {
    if (statusCard) statusCard.hidden = true;
    if (guest) guest.hidden = Boolean(user); if (member) member.hidden = !user;
    const initial = user?.name?.charAt(0).toUpperCase() || 'A';
    const navImage = document.querySelector('[data-nav-avatar]'); const navInitial = document.querySelector('[data-nav-initial]');
    if (navImage) { navImage.hidden = !user?.avatar; navImage.src = user?.avatar || ''; }
    if (navInitial) { navInitial.hidden = Boolean(user?.avatar); navInitial.textContent = initial; }
    window.dispatchEvent(new CustomEvent('auth:changed', { detail: { user } }));
    if (!user) return;
    const profileName = document.querySelector('[data-profile-name]');
    const profileUsername = document.querySelector('[data-profile-username], [data-profile-email]');
    if (profileName) profileName.textContent = user.name;
    if (profileUsername) profileUsername.textContent = `@${user.username}`;
    const profileInitial = document.querySelector('[data-profile-initial]'); const profileImage = document.querySelector('[data-profile-avatar]');
    if (profileInitial) { profileInitial.textContent = initial; profileInitial.hidden = Boolean(user.avatar); }
    if (profileImage) { profileImage.hidden = !user.avatar; profileImage.src = user.avatar || ''; }
    const nameInput = document.querySelector('[data-profile-form] [name="name"]');
    if (nameInput) nameInput.value = user.name;
  };
  document.querySelectorAll('[data-auth-tab]').forEach((tab) => tab.addEventListener('click', () => { document.querySelectorAll('[data-auth-tab]').forEach((item) => item.setAttribute('aria-selected', String(item === tab))); document.querySelectorAll('[data-auth-form]').forEach((form) => { form.hidden = form.dataset.authForm !== tab.dataset.authTab; }); message(authMessage, ''); }));
  document.querySelectorAll('[data-auth-form]').forEach((form) => form.addEventListener('submit', async (event) => {
    event.preventDefault(); message(authMessage, ''); const submit = form.querySelector('[type="submit"]'); submit.disabled = true;
    try { const data = await api(`auth/${form.dataset.authForm}.php`, { method: 'POST', body: Object.fromEntries(new FormData(form)) }); render(data.user); form.reset(); if (!localStorage.getItem('feedMySheep.pendingInvite')) window.location.hash = 'today'; }
    catch (error) { message(authMessage, error.message); } finally { submit.disabled = false; }
  }));
  const imageData = (file) => new Promise((resolve, reject) => {
    if (!file) { resolve(undefined); return; }
    if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) { reject(new Error('Choose a JPG, PNG, GIF, or WebP image.')); return; }
    const reader = new FileReader(); reader.onerror = () => reject(new Error('That picture could not be read.'));
    reader.onload = () => { const image = new Image(); image.onerror = () => reject(new Error('That picture could not be read.')); image.onload = () => { const size = 256; const canvas = document.createElement('canvas'); canvas.width = size; canvas.height = size; const context = canvas.getContext('2d'); const scale = Math.max(size / image.width, size / image.height); const width = image.width * scale; const height = image.height * scale; context.drawImage(image, (size - width) / 2, (size - height) / 2, width, height); resolve(canvas.toDataURL('image/jpeg', 0.82)); }; image.src = reader.result; };
    reader.readAsDataURL(file);
  });
  document.querySelector('[data-profile-form]')?.addEventListener('submit', async (event) => { event.preventDefault(); const form = event.currentTarget; const submit = form.querySelector('[type="submit"]'); submit.disabled = true; try { const formData = new FormData(form); const avatar = await imageData(formData.get('avatar')); const body = { name: formData.get('name') }; if (avatar !== undefined) body.avatar = avatar; const data = await api('user/profile.php', { method: 'PATCH', body }); render(data.user); form.elements.avatar.value = ''; message(profileMessage, 'Profile updated.', true); } catch (error) { message(profileMessage, error.message); } finally { submit.disabled = false; } });
  document.querySelector('[data-logout]')?.addEventListener('click', async () => { try { await api('auth/logout.php', { method: 'POST', body: {} }); render(null); await initializeCsrf(); } catch (error) { message(profileMessage, error.message); } });
  // An unresolved account check is not the same thing as being signed out. Showing the
  // sign-in form after a failed request left the account page contradicting the rest of
  // the app, which kept rendering group and plan data from a session that was still valid.
  const unresolved = (text) => {
    if (guest) guest.hidden = true; if (member) member.hidden = true;
    if (statusCard) statusCard.hidden = false;
    if (statusMessage) statusMessage.textContent = text;
    if (retryButton) retryButton.disabled = false;
  };

  const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));
  let checking = false;

  async function refreshAccount({ attempts = 3 } = {}) {
    if (checking) return;
    checking = true;
    // The CSRF token only matters for writes, so it must never decide how the account renders.
    const csrf = initializeCsrf().catch(() => null);
    try {
      for (let attempt = 1; attempt <= attempts; attempt += 1) {
        try {
          const data = await api('auth/me.php');
          await csrf;
          render(data.user);
          return;
        } catch (error) {
          if (error.status === 401) { await csrf; render(null); return; }
          if (attempt === attempts || error.retryable === false) {
            unresolved(error.status === 0
              ? 'You appear to be offline. Your account will reappear once you reconnect.'
              : 'We could not confirm your account just now. Your session is still saved.');
            return;
          }
          await wait(400 * attempt);
        }
      }
    } finally { checking = false; }
  }

  retryButton?.addEventListener('click', () => { if (retryButton) retryButton.disabled = true; message(authMessage, ''); refreshAccount(); });
  window.addEventListener('online', () => refreshAccount());
  // Mobile browsers freeze and restore the page instead of reloading it; re-check on return.
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && statusCard && !statusCard.hidden) refreshAccount(); });
  window.addEventListener('pageshow', (event) => { if (event.persisted) refreshAccount(); });

  refreshAccount();
}
