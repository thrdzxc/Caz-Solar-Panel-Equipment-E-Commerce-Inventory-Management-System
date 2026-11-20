/* Accessible presentation enhancements; existing commerce handlers remain authoritative. */
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.dropdown').forEach((dropdown, index) => {
    const toggle = dropdown.querySelector('.dropdown-toggle');
    const menu = dropdown.querySelector('.dropdown-menu');
    if (!toggle || !menu) return;
    toggle.textContent = 'Products';
    toggle.setAttribute('role', 'button');
    menu.id = menu.id || `caz-products-menu-${index}`;
    toggle.setAttribute('aria-controls', menu.id);
    const setOpen = (open) => {
      dropdown.classList.toggle('is-open', open);
      toggle.setAttribute('aria-expanded', String(open));
    };
    setOpen(false);
    toggle.addEventListener('click', event => { event.preventDefault(); setOpen(!dropdown.classList.contains('is-open')); });
    toggle.addEventListener('keydown', event => {
      if (event.key === ' ') { event.preventDefault(); toggle.click(); }
      if (event.key === 'ArrowDown') { event.preventDefault(); setOpen(true); menu.querySelector('a')?.focus(); }
    });
    dropdown.addEventListener('keydown', event => {
      if (event.key === 'Escape') { setOpen(false); toggle.focus(); }
    });
    dropdown.addEventListener('focusout', event => { if (!dropdown.contains(event.relatedTarget)) setOpen(false); });
    document.addEventListener('click', event => { if (!dropdown.contains(event.target)) setOpen(false); });
  });
  const labels = { 'fa-search': 'Search products', 'fa-home': 'Home', 'fa-user': 'Your profile', 'fa-shopping-cart': 'Shopping cart', 'fa-times': 'Close' };
  document.querySelectorAll('i[onclick], .cart-tabs .tab[onclick]').forEach(control => {
    control.setAttribute('role', 'button');
    control.tabIndex = 0;
    const label = control.title || Object.entries(labels).find(([cls]) => control.classList.contains(cls))?.[1];
    if (label) control.setAttribute('aria-label', label);
    control.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); control.click(); }
    });
  });
  document.querySelectorAll('.filter-select').forEach(select => {
    if (!select.getAttribute('aria-label')) select.setAttribute('aria-label', select.options[0]?.textContent.trim() || 'Filter products');
  });
});
