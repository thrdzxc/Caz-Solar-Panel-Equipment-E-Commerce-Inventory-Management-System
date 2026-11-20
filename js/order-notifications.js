(() => {
  'use strict';
  const config = window.CAZ_ORDER_STATUS;
  const link = document.querySelector('[data-order-link]');
  if (!config || !config.viewerId || !link) return;
  const badge = link.querySelector('[data-order-badge]');
  const count = link.querySelector('[data-order-count]');
  const announcement = link.querySelector('[data-order-announcement]');
  if (!badge || !count) return;
  let latest = null, lastCount = null, request = null, expired = false, reading = false, readWhenReady = false;
  let quietOrder = 0, toastTimer = null;
  const channelKey = `caz:orders:${config.admin ? 'admin' : 'customer'}:${config.viewerId}`;
  function paint(value) {
    if (value === lastCount) return;
    lastCount = value;
    badge.hidden = value === 0;
    count.textContent = value > 99 ? '99+' : String(value);
    const label = config.admin ? `${value} pending ${value === 1 ? 'order' : 'orders'}` : `${value} unread order ${value === 1 ? 'update' : 'updates'}`;
    link.setAttribute('aria-label', `${config.admin ? 'Orders' : 'Cart & orders'}, ${label}`);
    if (announcement) announcement.textContent = label;
  }
  function showNotice(event) {
    let notice = document.getElementById('caz-live-order-notice');
    if (!notice) {
      notice = document.createElement('section');
      notice.id = 'caz-live-order-notice'; notice.className = 'caz-live-order-notice';
      notice.setAttribute('role', 'status'); notice.setAttribute('aria-live', 'polite');
      const title = document.createElement('strong'); title.dataset.orderNoticeTitle = '';
      const message = document.createElement('p'); message.dataset.orderNoticeMessage = '';
      const actions = document.createElement('div');
      const view = document.createElement('button'); view.type = 'button'; view.textContent = 'View orders';
      view.onclick = () => { notice.remove(); link.click(); if (!config.admin && typeof window.switchTab === 'function') window.switchTab(event.status || 'pending'); };
      const dismiss = document.createElement('button'); dismiss.type = 'button'; dismiss.className = 'caz-order-notice-dismiss'; dismiss.textContent = 'Dismiss'; dismiss.onclick = () => notice.remove();
      actions.append(view, dismiss); notice.append(title, message, actions); document.body.appendChild(notice);
    }
    notice.querySelector('[data-order-notice-title]').textContent = config.admin ? 'New customer order' : 'Your order has an update';
    notice.querySelector('[data-order-notice-message]').textContent = String(event.message || 'Open your orders to view the latest update.');
    // Retain the current status when several updates arrive before this notice is opened.
    notice.querySelector('button').onclick = () => { notice.remove(); link.click(); if (!config.admin && typeof window.switchTab === 'function') window.switchTab(event.status || 'pending'); };
    clearTimeout(toastTimer); toastTimer = setTimeout(() => notice.remove(), 10000);
  }
  function valid(data) { return data && data.ok && Number.isSafeInteger(data.count) && data.count >= 0 && Number.isSafeInteger(data.latest_id) && data.latest_id >= 0 && data.admin === config.admin; }
  function apply(data, notify = true) {
    if (!valid(data)) throw new Error('Invalid order status');
    paint(data.count);
    const checkoutAlreadyShown = data.latest && data.latest.order_id === quietOrder && data.latest.status === 'pending';
    if (latest !== null && data.latest_id > latest && data.latest && notify && !checkoutAlreadyShown) showNotice(data.latest);
    latest = Math.max(latest || 0, data.latest_id);
    window.dispatchEvent(new CustomEvent('caz:order-status-updated', {detail:{admin:config.admin,count:data.count,latest:data.latest}}));
  }
  async function poll() {
    if (request || expired || document.hidden || reading) return;
    const controller = new AbortController(); request = controller;
    const timeout = setTimeout(() => controller.abort(), 12000);
    try {
      const response = await fetch(new URL(config.endpoint, location.href), {credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},signal:controller.signal});
      if (response.status === 401 || response.status === 403) { expired = true; paint(0); return; }
      const data = await response.json(); if (!response.ok) throw new Error('Orders unavailable');
      apply(data);
    } catch (_) { /* Keep the confirmed badge during brief connection interruptions. */ }
    finally { clearTimeout(timeout); if (request === controller) request = null; }
    if (readWhenReady && latest !== null) { readWhenReady = false; markRead(); }
  }
  async function markRead() {
    if (config.admin || expired || reading) return;
    if (latest === null || request) { readWhenReady = true; poll(); return; }
    if (!lastCount) return;
    reading = true;
    const controller = new AbortController(); const timeout = setTimeout(() => controller.abort(), 12000);
    try {
      const response = await fetch(new URL(config.endpoint, location.href), {method:'POST',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json','Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':window.CAZ_CSRF || ''},body:new URLSearchParams({seen_through:String(latest)}),signal:controller.signal});
      if (response.ok) { apply(await response.json(), false); try { localStorage.setItem(channelKey, `${Date.now()}:${Math.random()}`); } catch (_) {} }
    } catch (_) { /* Failed acknowledgement stays unread and can be retried. */ }
    finally { clearTimeout(timeout); reading = false; }
  }
  if (!config.admin) link.addEventListener('click', () => setTimeout(() => {
    if (document.getElementById('cart-overlay')?.classList.contains('active')) markRead();
  }, 0));
  // Links from pages without a drawer open Home with ?panel=cart. Observe that
  // opening too, while leaving later updates unread if the drawer stays open.
  function observeCartOpening() {
    if (config.admin || typeof MutationObserver === 'undefined') return;
    const overlay = document.getElementById('cart-overlay');
    if (!overlay) return;
    let open = overlay.classList.contains('active');
    if (open) markRead();
    const observer = new MutationObserver(() => {
      const next = overlay.classList.contains('active');
      if (next && !open) markRead();
      open = next;
    });
    observer.observe(overlay, {attributes:true,attributeFilter:['class']});
    window.addEventListener('pagehide', () => observer.disconnect(), {once:true});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', observeCartOpening, {once:true});
  else observeCartOpening();
  window.addEventListener('caz:order-placed', event => { quietOrder = Number(event.detail?.order_id || 0); poll(); });
  window.addEventListener('storage', event => { if (event.key === channelKey) poll(); });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  window.addEventListener('focus', poll); window.addEventListener('pageshow', poll); window.addEventListener('online', poll);
  window.addEventListener('pagehide', () => { if (request) request.abort(); });
  poll(); setInterval(poll, 5000);
})();
