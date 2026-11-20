document.addEventListener('DOMContentLoaded', () => {
  const panel = new URLSearchParams(location.search).get('panel');
  if (panel === 'cart' && typeof toggleCart === 'function') toggleCart();
  if (panel === 'profile' && typeof toggleProfile === 'function') toggleProfile();
  document.querySelectorAll('.view-toggle-btn').forEach(button => {
    button.innerHTML = '<i class="fas fa-th" aria-hidden="true"></i> Compact / comfortable view';
    button.removeAttribute('onclick');
    button.addEventListener('click', () => {
      const grid = button.closest('.inventory-section')?.querySelector('.inventory-container');
      grid?.classList.toggle('compact-mode');
      button.setAttribute('aria-pressed', String(grid?.classList.contains('compact-mode')));
    });
  });
  // Match legacy inline content to its actual light or glass reading surface.
  const brighten = root => {
    const glass = document.body.classList.contains('caz-themed') && root.matches('.cart-overlay,.profile-overlay,.caz-modal-box,#caz-confirm-box');
    root.querySelectorAll('[style]').forEach(el => {
      if (!(el instanceof HTMLElement)) return;
      const color = el.style.color;
      if (/^rgb\((255, 255, 255|221, 221, 221|187, 187, 187|170, 170, 170|153, 153, 153|136, 136, 136|119, 119, 119|102, 102, 102|85, 85, 85|68, 68, 68)\)$/.test(color)) el.style.setProperty('color',glass ? '#c4e8e9' : '#455b73','important');
      if (/^rgb\((0, 0, 0|5, 5, 5|10, 10, 10|13, 13, 13|17, 17, 17|26, 26, 26)\)$/.test(el.style.backgroundColor)) el.style.setProperty('background-color',glass ? 'rgba(173,235,231,.08)' : '#f5f8fb','important');
      if (el.style.fontSize && parseFloat(el.style.fontSize) < 14) el.style.fontSize='14px';
    });
  };
  const targets = '.cart-overlay,.profile-overlay,.caz-modal-box,#caz-confirm-box,#address-modal,#change-password-modal';
  const scan = () => document.querySelectorAll(targets).forEach(brighten);
  scan();
  const observer = new MutationObserver(scan);
  observer.observe(document.body,{subtree:true,childList:true});
});
