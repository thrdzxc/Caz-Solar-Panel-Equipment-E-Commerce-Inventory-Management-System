(() => {
  const userId = Number(window.CAZ_USER_ID || 0);
  const noticeKey = 'caz-checkout-notice:' + userId;
  const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  function updateBadge(count) {
    count = Math.max(0, Number(count) || 0);
    document.querySelectorAll('[data-cart-count]').forEach(el => { el.textContent = count; });
    document.querySelectorAll('.nav-cart-action').forEach(el => el.setAttribute('aria-label', 'Shopping cart, ' + count + (count === 1 ? ' item' : ' items')));
  }
  function showNotice(message) {
    const body = document.getElementById('cart-body');
    if (!body) return;
    let notice = document.getElementById('cart-checkout-notice');
    if (!notice) {
      notice = document.createElement('div');
      notice.id = 'cart-checkout-notice'; notice.className = 'cart-checkout-notice';
      notice.setAttribute('role', 'status'); body.before(notice);
    }
    notice.innerHTML = '<i class="fas fa-check-circle" aria-hidden="true"></i><div><strong>Order received</strong><p>' + escape(message) + '</p><button type="button" class="cart-view-order">View pending orders</button></div><button type="button" class="cart-dismiss-notice" aria-label="Dismiss checkout notification">×</button>';
    notice.querySelector('.cart-view-order').onclick = () => { if (typeof switchTab === 'function') switchTab('pending'); };
    notice.querySelector('.cart-dismiss-notice').onclick = () => { notice.remove(); try { sessionStorage.removeItem(noticeKey); } catch (_) {} };
  }
  async function refresh() {
    if (!userId || typeof cart !== 'undefined') return;
    try {
      const response = await fetch('cart_handler.php?action=get', { cache: 'no-store' });
      const result = await response.json();
      if (response.ok && result.status === 'success') updateBadge(result.count);
    } catch (_) { /* Keep the server-rendered count if the connection drops. */ }
  }
  window.CAZCartStatus = { checkoutComplete(data) {
    const count = (data.items || []).reduce((sum, item) => sum + Number(item.quantity || item.qty || 0), 0);
    const message = 'Order #CZ-' + Number(data.order_id) + ' • ' + count + (count === 1 ? ' item' : ' items') + ' • Awaiting admin approval.';
    try { sessionStorage.setItem(noticeKey, message); } catch (_) {}
    showNotice(message);
    if (typeof cazAlert !== 'undefined') cazAlert.success(message, 6500);
    if (typeof cart !== 'undefined') cart.loadFromDB();
    window.dispatchEvent(new CustomEvent('caz:order-placed', {detail:{order_id:Number(data.order_id)}}));
  } };
  window.addEventListener('caz:cart-changed', event => updateBadge(event.detail.count));
  window.addEventListener('focus', refresh);
  document.addEventListener('DOMContentLoaded', () => {
    refresh();
    try { const notice = sessionStorage.getItem(noticeKey); if (notice) showNotice(notice); } catch (_) {}
  });
})();
