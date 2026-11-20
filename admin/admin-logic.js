function filterInventory(containerId, query) {
    const container = document.getElementById(containerId);
    if (!container) {
        console.error("Container not found: " + containerId);
        return;
    }

    const products = container.getElementsByClassName('product-item');
    const filter = query.toLowerCase().trim();

    for (let i = 0; i < products.length; i++) {
        // This grabs ALL text inside the product box (Name, Brand, Price)
        const content = products[i].textContent || products[i].innerText;
        
        if (content.toLowerCase().indexOf(filter) > -1) {
            products[i].style.display = ""; // Show
        } else {
            products[i].style.display = "none"; // Hide
        }
    }
}