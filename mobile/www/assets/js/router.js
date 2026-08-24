const ROUTES = new Set(['today', 'bible', 'plans', 'group', 'account']);

export function initRouter() {
  const showRoute = () => {
    const requestedRoute = window.location.hash.slice(1);
    const route = ROUTES.has(requestedRoute) ? requestedRoute : 'today';

    document.querySelectorAll('[data-view]').forEach((view) => {
      const active = view.dataset.view === route;
      view.hidden = !active;
      view.classList.toggle('is-active', active);
    });

    document.querySelectorAll('[data-route]').forEach((link) => {
      if (link.dataset.route === route) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });

    document.title = `${route[0].toUpperCase()}${route.slice(1)} · Abide N Me`;
    window.scrollTo({ top: 0, behavior: 'instant' });
  };

  window.addEventListener('hashchange', showRoute);
  showRoute();
}
