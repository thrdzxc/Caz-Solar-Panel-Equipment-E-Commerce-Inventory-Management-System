(() => {
  'use strict';
  const config = window.CAZ_MESSAGE_STATUS;
  const link = document.querySelector('[data-message-link]');
  if (!config || !config.viewerId || !link) return;
  const badge = link.querySelector('[data-message-unread]');
  const count = link.querySelector('[data-message-unread-count]');
  const announcement = link.querySelector('[data-message-announcement]');
  let current = null;
  let request = null;
  let revision = 0;
  let expired = false;
  const channelKey = `caz:messages:${config.admin ? 'admin' : 'customer'}:${config.viewerId}`;
  function paint(value) {
    if (!Number.isSafeInteger(value) || value < 0 || value === current) return;
    current = value;
    badge.hidden = value === 0;
    count.textContent = value > 99 ? '99+' : String(value);
    const label = value ? `${value} unread ${value === 1 ? 'message' : 'messages'}` : 'No unread messages';
    link.setAttribute('aria-label', `Messages, ${label.toLowerCase()}`);
    announcement.textContent = label;
  }
  async function poll() {
    if (request || expired || document.hidden) return;
    const controller = new AbortController();
    request = controller;
    const version = revision;
    const timeout = setTimeout(() => controller.abort(), 12000);
    try {
      const url = new URL(config.endpoint, location.href);
      url.searchParams.set('action', 'status');
      const response = await fetch(url, {credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}, signal:controller.signal});
      if (version !== revision) return;
      if (response.status === 401 || response.status === 403) { expired = true; paint(0); return; }
      const result = await response.json();
      if (version === revision && response.ok && result.ok) paint(result.unread_total);
    } catch (_) { /* Keep the last confirmed count until the next successful poll. */ }
    finally { clearTimeout(timeout); if (request === controller) request = null; }
  }
  function refresh() { revision++; if (request) { request.abort(); request = null; } poll(); }
  window.addEventListener('caz:messages-status', event => {
    const detail = event.detail || {};
    if (detail.viewerId !== config.viewerId || detail.admin !== config.admin) return;
    revision++; // A response started before this read receipt must not restore an old count.
    paint(detail.unread_total);
    try { localStorage.setItem(channelKey, `${Date.now()}:${Math.random()}`); } catch (_) {}
  });
  window.addEventListener('storage', event => { if (event.key === channelKey) refresh(); });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  window.addEventListener('focus', refresh);
  window.addEventListener('pageshow', refresh);
  window.addEventListener('online', refresh);
  window.addEventListener('pagehide', () => { if (request) request.abort(); });
  poll();
  setInterval(poll, 8000);
})();
