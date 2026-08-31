import { reminder } from './native.js';

/**
 * The morning reminder control on the account page.
 *
 * The whole section stays hidden unless the app can actually schedule one, so a
 * browser never shows a switch that would do nothing when tapped.
 */
export function initReminders() {
  const card = document.querySelector('[data-reminder]');
  if (!card || !reminder.available) return;

  const toggle = card.querySelector('[data-reminder-toggle]');
  const detail = card.querySelector('[data-reminder-detail]');
  const message = card.querySelector('[data-reminder-message]');
  const clock = `${reminder.hour > 12 ? reminder.hour - 12 : reminder.hour}:00 ${reminder.hour < 12 ? 'am' : 'pm'}`;

  const say = (value, success = false) => {
    message.textContent = value;
    message.hidden = !value;
    message.classList.toggle('is-success', success);
  };

  const paint = (state) => {
    // A refusal at the system level cannot be undone from inside the app, so the
    // button is replaced by the one instruction that will actually work.
    if (state.permission === 'denied') {
      toggle.hidden = true;
      detail.textContent = `Notifications for this app are switched off in your phone's settings. Turn them on there to use the ${clock} reminder.`;
      return;
    }
    toggle.hidden = false;
    toggle.textContent = state.enabled ? 'Turn the reminder off' : 'Remind me each morning';
    detail.textContent = state.enabled
      ? `Your phone will nudge you at ${clock} each morning.`
      : `A quiet nudge at ${clock} each morning to open the day's reading.`;
  };

  let busy = false;
  toggle.addEventListener('click', async () => {
    if (busy) return;
    busy = true;
    toggle.disabled = true;
    say('');

    try {
      const before = await reminder.status();
      let after;
      if (before.enabled) {
        await reminder.disable();
        after = await reminder.status();
      } else {
        after = await reminder.enable();
      }

      paint(after);
      if (after.enabled) say(`Reminder set for ${clock} each morning.`, true);
      else if (after.permission !== 'granted') say('Your phone declined the notification permission.');
      else say('Reminder turned off.', true);
    } catch {
      say('The reminder could not be changed. Please try again.');
    } finally {
      busy = false;
      toggle.disabled = false;
    }
  });

  reminder.status()
    .then((state) => { card.hidden = false; paint(state); })
    .catch(() => { card.hidden = true; });
}
