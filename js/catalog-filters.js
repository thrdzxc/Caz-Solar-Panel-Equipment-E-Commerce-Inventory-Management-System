/* Progressive enhancement: the original selects remain the filter data source. */
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.querySelector('.catalog-filters');
  if (!panel) return;
  const controls = [];
  let opened = null;
  const refreshCount = () => {
    const cards = [...document.querySelectorAll('#product-grid .product-card')];
    const count = cards.filter(card => !card.classList.contains('hidden')).length;
    const status = panel.querySelector('.catalog-filter-count');
    status.textContent = `${count} of ${cards.length} products`;
    const empty = document.getElementById('catalog-filter-empty');
    if (empty) empty.hidden = count !== 0;
  };
  panel.querySelectorAll('select.filter-select').forEach(select => {
    const field = select.closest('.filter-field');
    const label = field.querySelector('label');
    const name = label.textContent.trim();
    const wrapper = document.createElement('div');
    wrapper.className = 'catalog-select';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.id = `${select.id}-trigger`;
    trigger.className = 'catalog-select-trigger';
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    const value = document.createElement('span');
    const arrow = document.createElement('span');
    arrow.className = 'catalog-select-arrow';
    arrow.setAttribute('aria-hidden', 'true');
    arrow.textContent = '⌄';
    trigger.append(value, arrow);
    const list = document.createElement('div');
    list.className = 'catalog-select-menu';
    list.id = `${select.id}-options`;
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', name);
    list.hidden = true;
    trigger.setAttribute('aria-controls', list.id);
    let active = select.selectedIndex;
    const options = [...select.options].map((option, index) => {
      const item = document.createElement('div');
      item.className = 'catalog-select-option';
      item.id = `${select.id}-option-${index}`;
      item.setAttribute('role', 'option');
      item.textContent = option.textContent.trim();
      item.addEventListener('click', () => choose(index));
      list.append(item);
      return item;
    });
    const highlight = index => {
      active = Math.max(0, Math.min(options.length - 1, index));
      options.forEach((item, i) => item.classList.toggle('is-active', i === active));
      trigger.setAttribute('aria-activedescendant', options[active].id);
      options[active].scrollIntoView({block:'nearest'});
    };
    const close = () => {
      list.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
      trigger.removeAttribute('aria-activedescendant');
      wrapper.classList.remove('is-open');
      if (opened === control) opened = null;
    };
    const open = () => {
      if (opened && opened !== control) opened.close();
      list.hidden = false;
      trigger.setAttribute('aria-expanded', 'true');
      wrapper.classList.add('is-open');
      opened = control;
      highlight(select.selectedIndex);
    };
    const sync = () => {
      value.textContent = select.options[select.selectedIndex]?.textContent.trim() || '';
      trigger.setAttribute('aria-label', `${name}: ${value.textContent}`);
      options.forEach((item, i) => item.setAttribute('aria-selected', String(i === select.selectedIndex)));
      field.classList.toggle('has-filter', select.value !== '');
    };
    function choose(index) {
      select.selectedIndex = index;
      select.dispatchEvent(new Event('change', {bubbles:true}));
      close();
      trigger.focus();
    }
    const control = {close, sync, wrapper};
    controls.push(control);
    trigger.addEventListener('click', () => list.hidden ? open() : close());
    let typeAhead = '', typedAt = 0;
    trigger.addEventListener('keydown', event => {
      if (event.key === 'Escape' || event.key === 'Tab') { close(); return; }
      if (['ArrowDown','ArrowUp','Home','End'].includes(event.key)) {
        event.preventDefault();
        if (list.hidden) { open(); return; }
        highlight(event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : active + (event.key === 'ArrowDown' ? 1 : -1));
      } else if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        list.hidden ? open() : choose(active);
      } else if (event.key.length === 1 && !event.ctrlKey && !event.altKey && !event.metaKey) {
        if (list.hidden) open();
        typeAhead = Date.now() - typedAt > 800 ? event.key : typeAhead + event.key;
        typedAt = Date.now();
        const match = options.findIndex(item => item.textContent.toLowerCase().startsWith(typeAhead.toLowerCase()));
        if (match >= 0) highlight(match);
      }
    });
    select.addEventListener('change', sync);
    wrapper.append(trigger, list);
    select.after(wrapper);
    label.htmlFor = trigger.id;
    select.hidden = true;
    sync();
  });
  document.addEventListener('click', event => { if (opened && !opened.wrapper.contains(event.target)) opened.close(); });
  document.addEventListener('focusin', event => { if (opened && !opened.wrapper.contains(event.target)) opened.close(); });
  const refresh = () => { controls.forEach(control => control.sync()); refreshCount(); };
  panel.addEventListener('change', refresh);
  panel.querySelector('.filter-reset').addEventListener('click', () => { controls.forEach(control => control.close()); refresh(); });
  document.getElementById('productSearch')?.addEventListener('keyup', refreshCount);
  // Also catches search reset, back/forward restoration, and existing page handlers.
  new MutationObserver(refreshCount).observe(document.getElementById('product-grid'), {subtree:true,attributes:true,attributeFilter:['class']});
  window.addEventListener('pageshow', refresh);
  refresh();
});
