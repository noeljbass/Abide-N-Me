import { api } from './api.js';

const pendingKey = 'feedMySheep.pendingInvite';
export function initGroups() {
  const list = document.querySelector('[data-groups-list]'); const empty = document.querySelector('[data-groups-empty]'); const detail = document.querySelector('[data-group-detail]');
  const joinCard = document.querySelector('[data-join-card]'); const joinMessage = document.querySelector('[data-join-message]'); let currentUser = null; let selectedGroup = null;
  const pathMatch = location.pathname.match(/\/join\/([A-Za-z0-9-]+)/); const queryCode = new URLSearchParams(location.search).get('invite'); const incomingCode = queryCode || pathMatch?.[1];
  if (incomingCode) {
    localStorage.setItem(pendingKey, incomingCode);
    location.hash = 'group';
    if (queryCode) history.replaceState(null, '', `${location.pathname}#group`);
  }
  const showMessage = (element, value, success = false) => { element.textContent = value; element.hidden = !value; element.classList.toggle('is-success', success); };
  const pendingCode = () => localStorage.getItem(pendingKey);

  async function preview(code) {
    localStorage.setItem(pendingKey, code.toUpperCase()); joinCard.hidden = false; showMessage(joinMessage, '');
    try { const { invite } = await api('groups/preview-invite.php', { method: 'POST', body: { code } }); document.querySelector('[data-join-name]').textContent = `Join ${invite.name}`; document.querySelector('[data-join-description]').textContent = invite.description || 'You have been invited to read Scripture with this private group.'; }
    catch (error) { showMessage(joinMessage, error.message); }
  }
  async function loadGroups() {
    if (!currentUser) { list.replaceChildren(); empty.hidden = false; return; }
    try { const data = await api('groups/index.php'); list.replaceChildren(...data.groups.map(groupCard)); empty.hidden = data.groups.length > 0; }
    catch { list.replaceChildren(); }
  }
  function groupCard(group) { const button = document.createElement('button'); button.type = 'button'; button.className = 'compact-card group-list-card'; const text = document.createElement('span'); const title = document.createElement('strong'); title.textContent = group.name; const meta = document.createElement('small'); meta.textContent = `${group.member_count} ${group.member_count === 1 ? 'member' : 'members'} · ${group.role}`; text.append(title, meta); button.append(text, document.createTextNode('›')); button.addEventListener('click', () => openGroup(group)); return button; }
  async function openGroup(group) { selectedGroup = group; list.hidden = true; empty.hidden = true; document.querySelector('[data-join-code-form]').hidden = true; document.querySelector('[data-group-name]').textContent = group.name; document.querySelector('[data-group-description]').textContent = group.description || 'A private Scripture reading group.'; document.querySelector('[data-group-role]').textContent = group.role; document.querySelector('[data-delete-group]').hidden = group.role !== 'owner'; document.querySelector('[data-invite-result]').hidden = true; showMessage(document.querySelector('[data-group-detail-message]'), ''); detail.hidden = false; try { const data = await api(`groups/members.php?group=${encodeURIComponent(group.id)}`); const members = document.querySelector('[data-group-members]'); members.replaceChildren(...data.members.map((member) => { const item = document.createElement('li'); const avatar = member.avatar ? document.createElement('img') : document.createElement('span'); avatar.className = member.avatar ? 'avatar member-avatar-image' : 'avatar avatar-sage'; if (member.avatar) { avatar.src = member.avatar; avatar.alt = `${member.name}'s profile picture`; } else { avatar.textContent = member.name.charAt(0); } const name = document.createElement('span'); name.textContent = member.name; const role = document.createElement('span'); role.className = 'status-muted'; role.textContent = member.role; item.append(avatar, name, role); return item; })); } catch (error) { showMessage(document.querySelector('[data-group-detail-message]'), error.message); } }

  document.querySelector('[data-join-code-form]').addEventListener('submit', (event) => { event.preventDefault(); preview(new FormData(event.currentTarget).get('code')); });
  document.querySelector('[data-join-submit]').addEventListener('click', async () => { const code = pendingCode(); if (!currentUser) { location.hash = 'account'; showMessage(joinMessage, 'Create an account or sign in to join automatically.'); return; } try { const { group } = await api('groups/join.php', { method: 'POST', body: { code } }); localStorage.removeItem(pendingKey); joinCard.hidden = true; location.hash = 'group'; await loadGroups(); openGroup(group); } catch (error) { showMessage(joinMessage, error.message); } });
  document.querySelector('[data-create-group]').addEventListener('submit', async (event) => { event.preventDefault(); const form = event.currentTarget; const message = document.querySelector('[data-group-form-message]'); try { const { group } = await api('groups/index.php', { method: 'POST', body: Object.fromEntries(new FormData(form)) }); form.reset(); form.closest('details').open = false; await loadGroups(); await openGroup(group); document.querySelector('[data-group-created-dialog]').showModal(); } catch (error) { showMessage(message, error.message); } });
  document.querySelector('[data-group-back]').addEventListener('click', () => { detail.hidden = true; list.hidden = false; document.querySelector('[data-join-code-form]').hidden = false; loadGroups(); });
  document.querySelector('[data-create-invite]').addEventListener('click', async () => { try { const { invite } = await api('groups/invite.php', { method: 'POST', body: { group_id: selectedGroup.id, role: 'member', expires_in_days: 30 } }); const url = new URL(location.href); url.pathname = url.pathname.replace(/\/join\/.*$/, '/').replace(/index\.html$/, ''); url.search = ''; url.searchParams.set('invite', invite.code); url.hash = 'group'; document.querySelector('[data-invite-link]').value = url.toString(); document.querySelector('[data-invite-result]').hidden = false; } catch (error) { showMessage(document.querySelector('[data-group-detail-message]'), error.message); } });
  document.querySelector('[data-delete-group]').addEventListener('click', async () => { if (!selectedGroup || !window.confirm(`Delete “${selectedGroup.name}”? This cannot be undone.`)) return; try { await api('groups/index.php', { method: 'DELETE', body: { group_id: selectedGroup.id } }); selectedGroup = null; detail.hidden = true; list.hidden = false; document.querySelector('[data-join-code-form]').hidden = false; await loadGroups(); } catch (error) { showMessage(document.querySelector('[data-group-detail-message]'), error.message); } });
  document.querySelector('[data-copy-invite]').addEventListener('click', async () => { await navigator.clipboard.writeText(document.querySelector('[data-invite-link]').value); showMessage(document.querySelector('[data-group-detail-message]'), 'Invitation link copied.', true); });
  window.addEventListener('auth:changed', async (event) => { currentUser = event.detail.user; await loadGroups(); if (pendingCode()) { await preview(pendingCode()); if (currentUser) document.querySelector('[data-join-submit]').click(); } });
  if (pendingCode()) { location.hash = 'group'; preview(pendingCode()); }
}
