import { initNative } from './native.js';
import { initRouter } from './router.js';
import { initPwa } from './pwa.js';
import { initAuth } from './auth.js';
import { initGroups } from './groups.js';
import { initBibleReader } from './bible.js';
import { initPlans } from './plans.js';
import { initToday } from './today.js';
import { initAudio } from './audio.js';
import { initReminders } from './reminders.js';

initNative();
initRouter();
initPwa();
initAuth();
initGroups();
initBibleReader();
initPlans();
initToday();
initAudio();
initReminders();

const toast = document.querySelector('.toast');
let toastTimer;

document.addEventListener('click', (event) => {
  const demoControl = event.target.closest('[data-demo-message]');
  if (!demoControl) return;

  window.clearTimeout(toastTimer);
  toast.textContent = demoControl.dataset.demoMessage;
  toast.hidden = false;
  toastTimer = window.setTimeout(() => { toast.hidden = true; }, 3200);
});
