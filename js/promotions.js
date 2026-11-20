document.addEventListener('DOMContentLoaded', () => {
    const product = document.getElementById('promo-product');
    const preview = document.getElementById('promo-product-preview');
    if (product && preview) {
        const refresh = () => {
            const option = product.selectedOptions[0];
            preview.hidden = !product.value;
            if (preview.hidden) return;
            const price = Number(option.dataset.price);
            document.getElementById('promo-preview-price').textContent = new Intl.NumberFormat('en-PH', {style:'currency',currency:'PHP'}).format(price);
            document.getElementById('promo-preview-image').src = option.dataset.image;
            document.getElementById('promo-price').max = price.toFixed(2);
        };
        product.addEventListener('change', refresh);
        refresh();
    }
    const filters = Array.from(document.querySelectorAll('[data-promo-filter]'));
    const cards = Array.from(document.querySelectorAll('[data-promo-category]'));
    filters.forEach(button => button.addEventListener('click', () => {
        filters.forEach(item => {
            item.classList.toggle('active',item === button);
            item.setAttribute('aria-pressed',String(item === button));
        });
        let visible = 0;
        cards.forEach(card => {
            card.hidden = button.dataset.promoFilter !== 'all' && card.dataset.promoCategory !== button.dataset.promoFilter;
            if (!card.hidden) visible++;
        });
        document.getElementById('promo-no-offers').hidden = visible > 0;
    }));
});
