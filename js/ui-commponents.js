
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

/**
 * Create cart item HTML
 */
function createCartItem(item, index, isOrderView) {
    const safe = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    item = { ...item, title: safe(item.title), img: safe(item.img) };
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

/**
 * Create category header with delete button
 */
function createCategoryHeader(category) {
    return '<div class="category-header">' +
        '<h3>' + category + '</h3>' +
        '<button class="delete-btn" onclick="cart.deleteCategory(\'' + category + '\'); renderCart();">Delete</button>' +
        '</div>';
}

/**
 * Create profile overlay HTML
 * Reads session data injected via PHP into window.userProfile
 */
function createProfileOverlay() {
    const u = window.userProfile || {};
    const customerId = u.id      || '';
    const name       = u.name    || '';
    const email      = u.email   || '';
    const contact    = u.contact || '';
    const address    = u.address || '';

    return `
    <div class="profile-backdrop" id="profile-backdrop" onclick="toggleProfile()"></div>
    <div id="profile-panel" class="profile-overlay">

        <div class="profile-icon-wrap">
            <div class="profile-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="profile-customer-id">
                Customer ID: <strong>#${customerId}</strong>
            </div>
        </div>
        <hr>

        <div class="profile-field">
            <label>Full Name:</label>
            <span id="txt-user">${name}</span>
            <input type="text" id="inp-user" value="${name}" class="hidden-input">
        </div>

        <div class="profile-field">
            <label>Email:</label>
            <span id="txt-email">${email}</span>
            <input type="email" id="inp-email" value="${email}" class="hidden-input">
        </div>

        <div class="profile-field">
            <label>Contact Number:</label>
            <span id="txt-contact">${contact}</span>
            <input type="tel" id="inp-contact" value="${contact}" class="hidden-input" maxlength="11" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')">
        </div>

        <div class="profile-field">
            <label>Address:</label>
            <span id="txt-addr">${address}</span>
            <input type="text" id="inp-addr" value="${address}" class="hidden-input">
        </div>

        <div id="profile-msg" style="font-size:13px; margin-bottom:10px; display:none;"></div>

        <div class="button-row">
            <button class="btn-black" id="edit-save-btn" onclick="enableEdit()">Edit</button>
            <button class="btn-red" id="save-logout-btn" onclick="handleLogout()">Logout</button>
        </div>
    </div>`;
}

/**
 * Create cart overlay HTML
 */
function createCartOverlay() {
    return `
    <div class="cart-backdrop" id="cart-backdrop" onclick="toggleCart()"></div>
    <div class="cart-overlay" id="cart-overlay">
        <div class="cart-header">
            <div class="cart-tabs">
                <div class="tab active" onclick="switchTab('cart')">CCTV & Solar</div>
                <div class="tab" onclick="switchTab('pending')">Pending</div>
                <div class="tab" onclick="switchTab('approved')">Approved</div>
                <div class="tab" onclick="switchTab('shipped')">Shipped</div>
                <div class="tab" onclick="switchTab('canceled')">Canceled</div>
            </div>
        </div>
        <div class="cart-body" id="cart-body"></div>
        <div class="cart-footer" id="cart-footer">
            <div class="footer-top">
                <div class="select-all">
                    <input type="checkbox" id="selectAll" onchange="cart.toggleSelectAll(this.checked); renderCart();">
                    <label for="selectAll">Select All</label>
                </div>
                <div class="total-price" id="total-price">₱0</div>
            </div>
            <button class="checkout-btn" onclick="checkout()">
                <span>Check Out</span>
                <span id="checkout-count">(0)</span>
            </button>
        </div>
    </div>`;
}

// Profile actions are defined once in ui-handlers.js.

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        createProductCard,
        createCartItem,
        createCategoryHeader,
        createProfileOverlay,
        createCartOverlay
    };
}
