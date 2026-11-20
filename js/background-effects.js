/* Decorative effects only: no shopping or account behavior is changed. */
(() => {
  const button = document.querySelector('.caz-effects-toggle');
  if (!button) return;
  // Keep this control in normal flow so it cannot cover product prices or actions.
  const heading = document.querySelector('.portal-container, .caz-page-heading');
  const catalogIntro = document.querySelector('.cctv-page .catalog-description');
  if (heading) heading.append(button);
  else if (catalogIntro) {
    const toolbar = document.createElement('div');
    toolbar.className = 'caz-effects-toolbar';
    toolbar.append(button);
    catalogIntro.after(toolbar);
  } else document.body.append(button);
  const preference = 'caz-background-paused';
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let paused = false;
  try { paused = localStorage.getItem(preference) === 'true'; } catch (_) {}
  function render() {
    document.body.classList.toggle('caz-effects-paused', paused);
    button.hidden = reducedMotion.matches;
    button.setAttribute('aria-pressed', String(paused));
    button.setAttribute('aria-label', paused ? 'Resume background effects' : 'Pause background effects');
    button.querySelector('span').textContent = paused ? 'Resume effects' : 'Pause effects';
    button.querySelector('path').setAttribute('d', paused ? 'M5 3l11 7-11 7z' : 'M5 4h3v12H5zm7 0h3v12h-3z');
  }
  button.addEventListener('click', () => {
    paused = !paused;
    try { localStorage.setItem(preference, String(paused)); } catch (_) {}
    render();
  });
  reducedMotion.addEventListener('change', render);
  render();
})();
