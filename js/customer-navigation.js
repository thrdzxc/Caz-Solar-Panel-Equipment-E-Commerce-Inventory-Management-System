document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-caz-product-search]');
    const panel = document.getElementById('search-bar-overlay');
    if (!button || !panel) return;
    const sync = () => button.setAttribute('aria-expanded', String(panel.classList.contains('active')));
    new MutationObserver(sync).observe(panel, { attributes: true, attributeFilter: ['class'] });
    if (new URLSearchParams(location.search).get('search') === '1' && !panel.classList.contains('active') && typeof window.toggleSearch === 'function') {
        window.toggleSearch();
    }
    panel.addEventListener('keydown', event => {
        if (event.key === 'Escape' && panel.classList.contains('active') && typeof window.toggleSearch === 'function') {
            event.preventDefault();
            window.toggleSearch();
        }
    });
    sync();
});
