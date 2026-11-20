
function createProductCard(product, index, fromPage) {
    const linkHref = 'product.php?id=' + product.id + (fromPage ? '&from=' + fromPage : '');
    return '<a href="' + linkHref + '" class="product-link">' +
        '<div class="product-card">' +
        '<img src="../images/' + product.img + '" alt="' + product.title + '">' +
        '<p class="product-name">' + product.title + '</p>' +
        '<div class="product-bottom">' +
        '<span class="price">&#8369;' + product.price.toLocaleString() + '</span>' +
        '</div></div></a>';
}

function createCartItem(item, index, isOrderView) {
    const safe = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    item = { ...item, title: safe(item.title), img: safe(item.img) };
    // figure out the right image path depending on page depth
    const imgPath = window.location.pathname.includes('/pages/') ? '../images/' : 'images/';

    if (isOrderView) {
        return '<div class="cart-item">' +
            '<img src="' + imgPath + item.img + '" alt="' + item.title + '">' +
            '<div class="item-details"><p>' + item.title + '</p>' +
            '<div class="item-price-row"><span>&#8369;' + Number(item.price).toLocaleString() + ' x ' + item.qty + '</span></div>' +
            '</div></div>';
    }

    return '<div class="cart-item">' +
        '<input type="checkbox" ' + (item.selected ? 'checked' : '') + ' onchange="cart.toggleItem(' + index + '); renderCart();">' +
        '<img src="' + imgPath + item.img + '" alt="' + item.title + '">' +
        '<div class="item-details">' +
        '<p>' + item.title + '</p>' +
        '<div class="item-price-row">' +
        '<span>&#8369;' + Number(item.price).toLocaleString() + '</span>' +
        '<div class="qty-box">' +
        '<button ' + (item.qty <= 1 ? 'disabled ' : '') + 'aria-label="Decrease quantity" onclick="cart.updateQuantity(' + index + ', -1); renderCart();">-</button>' +
        '<span>' + item.qty + '</span>' +
        '<button ' + (item.qty >= item.stock ? 'disabled ' : '') + 'aria-label="Increase quantity" onclick="cart.updateQuantity(' + index + ', 1); renderCart();">+</button>' +
        '</div></div>' +
        '<button onclick="cart.removeItem(' + index + '); renderCart();" style="margin-top:6px;background:none;border:none;color:#e53935;cursor:pointer;font-size:12px;">Remove</button>' +
        '</div></div>';
}

function createCategoryHeader(category) {
    return '<div class="category-header">' +
        '<h3>' + category + '</h3>' +
        '<button class="delete-btn" onclick="cart.deleteCategory(\'' + category + '\'); renderCart();">Delete</button>' +
        '</div>';
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { createProductCard, createCartItem, createCategoryHeader };
}

function createCartOverlay() {
  return '<div class="cart-backdrop" id="cart-backdrop" onclick="toggleCart()"></div>' +
    '<div class="cart-overlay" id="cart-overlay">' +
    '<div class="cart-header"><div class="cart-tabs">' +
    '<div class="tab active" onclick="switchTab(\'cart\')">CCTV &amp; Solar</div>' +
    '<div class="tab" onclick="switchTab(\'pending\')">Pending</div>' +
    '<div class="tab" onclick="switchTab(\'approved\')">Approved</div>' +
    '<div class="tab" onclick="switchTab(\'shipped\')">Shipped</div>' +
    '<div class="tab" onclick="switchTab(\'canceled\')">Canceled</div>' +
    '</div></div>' +
    '<div class="cart-body" id="cart-body"></div>' +
    '<div class="cart-footer" id="cart-footer">' +
    '<div class="footer-top">' +
    '<div class="select-all"><input type="checkbox" id="selectAll" onchange="cart.toggleSelectAll(this.checked); renderCart();"><label for="selectAll">Select All</label></div>' +
    '<div class="total-price" id="total-price">&#8369;0</div>' +
    '</div>' +
    '<button class="checkout-btn" onclick="checkout()"><span>Check Out</span><span id="checkout-count">(0)</span></button>' +
    '</div></div>';
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { createProductCard, createCartItem, createCategoryHeader, createCartOverlay };
}
