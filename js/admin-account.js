(() => {
  'use strict';
  const panel = document.querySelector('.admin-account-panel');
  const backdrop = document.querySelector('.admin-account-backdrop');
  if (!panel || !backdrop) return;
  let previousFocus = null;
  let wasOpen = false;
  const focusAccount = () => {
    if (panel.classList.contains('active') && !panel.contains(document.activeElement) && !document.querySelector('#caz-confirm-overlay.show')) {
      panel.querySelector('.drawer-close')?.focus({preventScroll:true});
    }
  };
  const sync = () => {
    const open = panel.classList.contains('active');
    panel.inert = !open;
    panel.setAttribute('aria-hidden', String(!open));
    backdrop.classList.toggle('active', open);
    const trigger = document.querySelector('.caz-nav-actions [aria-label="Your profile"]');
    trigger?.setAttribute('aria-expanded', String(open));
    trigger?.setAttribute('aria-controls', 'profile-panel');
    if (open && !wasOpen) {
      previousFocus = document.activeElement;
      // Flush the opened layout before focus; hidden preview tabs can throttle animation frames.
      panel.getBoundingClientRect();
      focusAccount();
      setTimeout(focusAccount, 0);
    } else if (!open && wasOpen && previousFocus?.isConnected) {
      previousFocus.focus();
    }
    wasOpen = open;
  };
  window.toggleProfile = () => {
    panel.classList.toggle('active');
    sync();
  };
  panel.addEventListener('transitionend', event => {
    if (event.target === panel && event.propertyName === 'transform') focusAccount();
  });
  window.cazAdminLogout = () => {
    const leave = () => { window.location.href = 'logout.php'; };
    if (typeof cazAlert !== 'undefined' && typeof cazAlert.confirm === 'function') {
      cazAlert.confirm('Are you sure you want to log out?', leave, () => panel.querySelector('.admin-account-logout')?.focus());
      const confirmation = document.getElementById('caz-confirm-overlay');
      confirmation?.setAttribute('role', 'dialog');
      confirmation?.setAttribute('aria-modal', 'true');
      confirmation?.setAttribute('aria-labelledby', 'caz-confirm-title');
      confirmation?.querySelector('#caz-cancel-btn')?.focus();
    } else if (window.confirm('Are you sure you want to log out?')) leave();
  };
  panel.addEventListener('keydown', event => {
    // Confirmation dialogs manage their own focus above this drawer.
    if (document.querySelector('#caz-confirm-overlay.show')) return;
    if (event.key === 'Escape') { event.preventDefault(); window.toggleProfile(); return; }
    if (event.key !== 'Tab') return;
    const controls = [...panel.querySelectorAll('button:not([disabled]),a[href]')];
    const first = controls[0], last = controls[controls.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
  });
  new MutationObserver(sync).observe(panel, {attributes:true, attributeFilter:['class']});
  sync();
})();
