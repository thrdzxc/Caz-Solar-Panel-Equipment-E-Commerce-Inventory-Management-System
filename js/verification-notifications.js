(() => {
  'use strict';
  const link = document.querySelector('[data-verification-link]');
  if (!link) return;
  const badge = link.querySelector('[data-verification-badge]');
  const count = link.querySelector('[data-verification-count]');
  const announcement = link.querySelector('[data-verification-announcement]');
  const list = document.querySelector('[data-verification-list]');
  const live = document.querySelector('[data-verification-live]');
  let current = null, revision = null, request = null, expired = false;

  function paint(value) {
    if (value === current) return;
    current = value;
    badge.hidden = value === 0;
    count.textContent = value > 99 ? '99+' : String(value);
    const label = `${value} ${value === 1 ? 'customer' : 'customers'} awaiting verification`;
    link.setAttribute('aria-label', `Verification, ${label}`);
    announcement.textContent = label;
  }

  async function refreshList(signal) {
    if (!list) return true;
    // A background update must never erase a review note or confirmation in progress.
    if ([...list.querySelectorAll('textarea')].some(el => el.value !== el.defaultValue) ||
        list.querySelector('input[name="review_confirmed"]:checked')) {
      live.textContent = 'Updates are waiting. Finish your current review to show them automatically.';
      return false;
    }
    const response = await fetch(location.href, {credentials:'same-origin', cache:'no-store', signal});
    if (!response.ok || response.redirected) throw new Error('Review list unavailable');
    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const fresh = page.querySelector('[data-verification-list]');
    if (!fresh) throw new Error('Review list unavailable');
    // Reuse unchanged cards so focus and opened ID links remain stable.
    const existing = new Map([...list.querySelectorAll('[data-verification-key]')].map(el => [el.dataset.verificationKey, el]));
    const nodes = [...fresh.children].map(el => existing.get(el.dataset.verificationKey) || document.importNode(el, true));
    [...list.children].filter(el => !nodes.includes(el)).forEach(el => el.remove());
    nodes.forEach((el, index) => { if (list.children[index] !== el) list.insertBefore(el, list.children[index] || null); });
    live.textContent = 'Up to date · New submissions appear automatically.';
    return true;
  }

  async function poll() {
    if (request || expired || document.hidden) return;
    const controller = new AbortController();
    request = controller;
    const timeout = setTimeout(() => controller.abort(), 12000);
    try {
      const response = await fetch(new URL('verification_status.php', link.href), {
        credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}, signal:controller.signal
      });
      if (response.status === 401 || response.status === 403) {
        expired = true;
        badge.hidden = true;
        link.setAttribute('aria-label', 'Verification, sign in to view updates');
        if (live) live.textContent = 'Your session has ended. Sign in again to receive updates.';
        return;
      }
      const data = await response.json();
      if (!response.ok || !data.ok || !Number.isSafeInteger(data.pending) || data.pending < 0 ||
          typeof data.revision !== 'string' || !/^[a-f0-9]{64}$/.test(data.revision)) throw new Error('Invalid status');
      paint(data.pending);
      if (data.revision !== revision && await refreshList(controller.signal)) revision = data.revision;
      else if (data.revision === revision && live) live.textContent = 'Up to date · New submissions appear automatically.';
    } catch (_) {
      if (live) live.textContent = 'Updates interrupted · Retrying automatically.';
    } finally {
      clearTimeout(timeout);
      if (request === controller) request = null;
    }
  }
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  window.addEventListener('focus', poll);
  window.addEventListener('online', poll);
  window.addEventListener('pageshow', poll);
  window.addEventListener('pagehide', () => { if (request) request.abort(); });
  poll();
  setInterval(poll, 5000);
})();
