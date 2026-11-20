(() => {
    const gallery = document.querySelector('[data-product-gallery]');
    if (!gallery) return;
    const main = gallery.querySelector('#product-img');
    const caption = gallery.querySelector('[data-gallery-caption]');
    const buttons = Array.from(gallery.querySelectorAll('[data-gallery-src]'));
    buttons.forEach((button, index) => {
        button.addEventListener('click', () => {
            main.src = button.dataset.gallerySrc;
            main.alt = button.dataset.galleryAlt;
            if (caption) caption.textContent = button.dataset.galleryLabel;
            buttons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
        });
        button.addEventListener('keydown', event => {
            const direction = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
            if (!direction) return;
            event.preventDefault();
            const next = buttons[(index + direction + buttons.length) % buttons.length];
            next.focus();
            next.click();
        });
    });
})();
