(() => {
  'use strict';
  const nav = document.getElementById('caz-sidebar');
  if (!nav) return;
  const body = document.body;
  const mobile = matchMedia('(max-width:900px)');
  const toggle = document.querySelector('.caz-menu-toggle');
  const collapse = nav.querySelector('.caz-sidebar-collapse');
  const backdrop = document.querySelector('.caz-sidebar-backdrop');
  const sync = () => {
    const open = mobile.matches && body.classList.contains('caz-sidebar-open');
    nav.inert = mobile.matches && !open;
    backdrop.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
    const label = mobile.matches ? 'Close navigation' : body.classList.contains('caz-sidebar-collapsed') ? 'Expand navigation' : 'Collapse navigation';
    collapse.setAttribute('aria-label', label);
    collapse.title = label;
    collapse.setAttribute('aria-expanded', String(mobile.matches ? open : !body.classList.contains('caz-sidebar-collapsed')));
  };
  const close = (restoreFocus = true) => {
    const wasOpen = body.classList.contains('caz-sidebar-open');
    body.classList.remove('caz-sidebar-open');
    if (wasOpen && restoreFocus) toggle.focus({preventScroll:true});
    sync();
  };
  toggle.addEventListener('click', () => {
    if (body.classList.contains('caz-sidebar-open')) return close();
    body.classList.add('caz-sidebar-open'); sync();
    nav.getBoundingClientRect();
    collapse.focus({preventScroll:true});
    setTimeout(() => { if (body.classList.contains('caz-sidebar-open')) collapse.focus({preventScroll:true}); }, 0);
  });
  collapse.addEventListener('click', () => {
    if (mobile.matches) return close();
    body.classList.toggle('caz-sidebar-collapsed');
    try { localStorage.setItem('caz-sidebar-collapsed', body.classList.contains('caz-sidebar-collapsed') ? '1' : '0'); } catch (_) {}
    sync();
  });
  backdrop.addEventListener('click', () => close());
  nav.addEventListener('click', event => { if (mobile.matches && event.target.closest('a[href]')) close(false); });
  document.addEventListener('keydown', event => {
    if (!mobile.matches || !body.classList.contains('caz-sidebar-open')) return;
    if (event.key === 'Escape') { event.preventDefault(); close(); }
    if (event.key === 'Tab') {
      const focusable = [...nav.querySelectorAll('a[href],button:not([disabled])')].filter(el => el.getClientRects().length);
      const first = focusable[0], last = focusable[focusable.length - 1];
      if (!nav.contains(document.activeElement)) { event.preventDefault(); first.focus(); return; }
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
  });
  mobile.addEventListener('change', () => { close(false); sync(); });
  window.cazSidebarAction = action => {
    if (mobile.matches) close();
    const callback = {profile:'toggleProfile',cart:'toggleCart',search:'toggleSearch'}[action];
    if (callback && typeof window[callback] === 'function') window[callback]();
    else if (action === 'search') location.href = 'cctv.php?search=1';
    else if (action === 'profile' || action === 'cart') location.href = 'index.php?panel=' + action;
    if (action === 'search') document.querySelector('#search-bar-overlay input')?.focus();
  };
  sync();
})();
