document.addEventListener('DOMContentLoaded', () => {
  const select = document.getElementById('cctv-filterType');
  if (!select) return;
  const row = select.closest('.filter-row');
  if (!row) return;
  const group = document.createElement('fieldset');
  group.className = 'camera-types';
  const legend = document.createElement('legend');
  legend.textContent = 'Camera type';
  const options = document.createElement('div');
  options.className = 'camera-type-options';
  group.append(legend, options);

  const drawings = {
    all: '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
    bullet: '<path d="m3 5 14 3-2 8-13-4zM16 10h4v8h-5M12 15l-1 5H7"/><path d="m4 7 10 2"/>',
    dome: '<path d="M3 10h18M5 10a7 7 0 0 1 14 0M3 10a9 9 0 0 0 18 0"/><circle cx="12" cy="14" r="3"/>'
  };
  Array.from(select.options).forEach(option => {
    const label = document.createElement('label');
    label.className = 'camera-type-option';
    const radio = document.createElement('input');
    radio.type = 'radio';
    radio.name = 'cctv-camera-type';
    radio.value = option.value;
    const card = document.createElement('span');
    card.className = 'camera-type-card';
    const symbol = document.createElement('span');
    symbol.className = 'camera-type-symbol';
    symbol.setAttribute('aria-hidden', 'true');
    const kind = /dome/i.test(option.value) ? 'dome' : /bullet/i.test(option.value) ? 'bullet' : 'all';
    symbol.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + drawings[kind] + '</svg>';
    const name = document.createElement('span');
    name.className = 'camera-type-name';
    name.textContent = option.text;
    const check = document.createElement('span');
    check.className = 'camera-type-check';
    check.textContent = '✓';
    check.setAttribute('aria-hidden', 'true');
    card.append(symbol, name, check);
    label.append(radio, card);
    options.append(label);
    radio.addEventListener('change', () => {
      if (!radio.checked) return;
      select.value = radio.value;
      select.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });
  const sync = () => options.querySelectorAll('input').forEach(radio => { radio.checked = radio.value === select.value; });
  select.addEventListener('change', sync);
  row.before(group);
  row.classList.add('camera-secondary-filters');
  select.hidden = true;
  sync();
});
