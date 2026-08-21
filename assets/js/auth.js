import { api, initializeCsrf } from './api.js';
export function initAuth() {
  const guest = document.querySelector('[data-auth-guest]'); const member = document.querySelector('[data-auth-user]');
  const authMessage = document.querySelector('[data-auth-message]'); const profileMessage = document.querySelector('[data-profile-message]');
  const message = (element, value, success = false) => { element.textContent = value; element.classList.toggle('is-success', success); element.hidden = !value; };
  const render = (user) => { guest.hidden = Boolean(user); member.hidden = !user; if (!user) return; document.querySelector('[data-profile-name]').textContent = user.name; document.querySelector('[data-profile-email]').textContent = user.email; document.querySelector('[data-profile-initial]').textContent = user.name.charAt(0).toUpperCase(); document.querySelector('[data-profile-form] [name="name"]').value = user.name; };
  document.querySelectorAll('[data-auth-tab]').forEach((tab) => tab.addEventListener('click', () => { document.querySelectorAll('[data-auth-tab]').forEach((item) => item.setAttribute('aria-selected', String(item === tab))); document.querySelectorAll('[data-auth-form]').forEach((form) => { form.hidden = form.dataset.authForm !== tab.dataset.authTab; }); message(authMessage, ''); }));
  document.querySelectorAll('[data-auth-form]').forEach((form) => form.addEventListener('submit', async (event) => {
    event.preventDefault(); message(authMessage, ''); const submit = form.querySelector('[type="submit"]'); submit.disabled = true;
    try { const data = await api(`auth/${form.dataset.authForm}.php`, { method: 'POST', body: Object.fromEntries(new FormData(form)) }); render(data.user); form.reset(); window.location.hash = 'today'; }
    catch (error) { message(authMessage, error.message); } finally { submit.disabled = false; }
  }));
  document.querySelector('[data-profile-form]').addEventListener('submit', async (event) => { event.preventDefault(); try { const data = await api('user/profile.php', { method: 'PATCH', body: { name: new FormData(event.currentTarget).get('name') } }); render(data.user); message(profileMessage, 'Profile updated.', true); } catch (error) { message(profileMessage, error.message); } });
  document.querySelector('[data-logout]').addEventListener('click', async () => { try { await api('auth/logout.php', { method: 'POST', body: {} }); render(null); await initializeCsrf(); } catch (error) { message(profileMessage, error.message); } });
  initializeCsrf().then(() => api('auth/me.php')).then((data) => render(data.user)).catch((error) => { if (error.status !== 401) message(authMessage, 'Account services are temporarily unavailable.'); render(null); });
}
