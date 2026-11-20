let currentTab = 'cart';
let cartRenderVersion = 0;
const orderDeliveryPins = new Map();

/* ═══════════════════════════════════════════════════════════
   CART / ORDERS
═══════════════════════════════════════════════════════════ */
function toggleCart() {
    const overlay  = document.getElementById('cart-overlay');
    const backdrop = document.getElementById('cart-backdrop');
    if (overlay && backdrop) {
        overlay.classList.toggle('active');
        backdrop.classList.toggle('active');
        if (overlay.classList.contains('active')) {
            renderCart();
            cart.checkStock();
        }
    }
}

function toggleProfile() {
    document.getElementById('profile-panel').classList.toggle('active');
    document.getElementById('profile-backdrop').classList.toggle('active');
    loadSavedAddresses();
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.cart-tabs .tab').forEach(t => {
        const target = t.dataset.tab || t.getAttribute('onclick')?.match(/switchTab\(['"]([^'"]+)/)?.[1];
        t.classList.toggle('active', target === tab);
        t.setAttribute('aria-pressed', String(target === tab));
    });
    renderCart();
}

function renderCart() {
    const renderVersion = ++cartRenderVersion;
    const body   = document.getElementById('cart-body');
    const footer = document.getElementById('cart-footer');
    if (!body) return;
    body.querySelectorAll('[id^="addr-edit-"]').forEach(panel => panel._deliveryPicker?.destroy());
    body.innerHTML = '';

    if (currentTab === 'cart') {
        if (footer) footer.style.display = 'block';
        const cartItems = cart.getCart();

        if (cartItems.length === 0) {
            body.innerHTML = '<div style="text-align:center;padding:50px;color:#999;">Cart is empty.</div>';
            const totalEl = document.getElementById('total-price');
            const countEl = document.getElementById('checkout-count');
            if (totalEl) totalEl.textContent = '₱0';
            if (countEl) countEl.textContent = '(0)';
            const selectAll = document.getElementById('selectAll');
            if (selectAll) selectAll.checked = false;
        } else {
            ['CCTV', 'Solar'].forEach(category => {
                const items = cartItems.filter(i => i.category === category);
                if (items.length > 0) {
                    body.innerHTML += createCategoryHeader(category);
                    items.forEach(item => {
                        const idx = cartItems.findIndex(i => i.id === item.id);
                        body.innerHTML += createCartItem(item, idx, false);
                    });
                }
            });
            const totalEl = document.getElementById('total-price');
            const countEl = document.getElementById('checkout-count');
            if (totalEl) totalEl.innerText = '₱' + cart.calculateTotal().toLocaleString();
            if (countEl) countEl.innerText = '(' + cart.getSelectedCount() + ')';
            const selectAll = document.getElementById('selectAll');
            if (selectAll) {
                selectAll.checked = cartItems.every(item => item.selected);
                selectAll.indeterminate = !selectAll.checked && cartItems.some(item => item.selected);
            }
        }
    } else {
        if (footer) footer.style.display = 'none';

        const isInPages = window.location.pathname.includes('/pages/');
        const url = (isInPages ? '' : 'pages/') + 'get_orders.php?status=' + currentTab;

        body.innerHTML = '<div style="text-align:center;padding:50px;color:#999;">Loading...</div>';

        fetch(url)
            .then(r => r.json())
            .then(data => {
                if (renderVersion !== cartRenderVersion) return;
                body.innerHTML = '';
                if (data.success && data.orders && data.orders.length > 0) {
                    const grouped = {};
                    data.orders.forEach(item => {
                        if (!grouped[item.order_id]) {
                            grouped[item.order_id] = { ...item, items: [] };
                        }
                        grouped[item.order_id].items.push(item);
                    });
                    Object.values(grouped).forEach(order => {
                        body.innerHTML += createOrderCard(order, currentTab);
                    });
                } else {
                    body.innerHTML = '<div style="text-align:center;padding:50px;color:#999;">No ' + currentTab + ' orders.</div>';
                }
            })
            .catch(() => {
                if (renderVersion !== cartRenderVersion) return;
                body.innerHTML = '<div style="text-align:center;padding:50px;color:#f00;">Error loading orders.</div>';
            });
    }
}

function createOrderCard(order, status) {
    const safe = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const colors  = { pending:'#ff9800', approved:'#4caf50', shipped:'#2196f3', canceled:'#f44336' };
    const color   = colors[status] || '#999';
    const dateStr = new Date(order.order_date).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
    const pin = order.delivery_latitude !== null && order.delivery_latitude !== undefined && order.delivery_longitude !== null && order.delivery_longitude !== undefined
        ? {latitude:Number(order.delivery_latitude),longitude:Number(order.delivery_longitude)} : null;
    orderDeliveryPins.set(Number(order.order_id), pin);
    const pinLink = pin ? '<p class="delivery-location-help"><a class="delivery-location-link" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&amp;query=' + encodeURIComponent(pin.latitude + ',' + pin.longitude) + '">View saved delivery pin ↗</a></p>' : '<p class="delivery-location-help">No delivery pin saved.</p>';

    const itemsHTML = (order.items || []).map(i =>
        '<div style="display:flex;align-items:center;gap:10px;margin-top:8px;">' +
        '<img src="../images/' + i.image + '" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">' +
        '<div style="font-size:13px;color:#555;">' +
        safe(i.product_name) + ' &times; ' + i.quantity + ' &mdash; &#8369;' + parseFloat(i.price).toFixed(2) +
        (i.promotion_title ? '<div class="caz-promo-notice">' + safe(i.promotion_title) + ' · ' + safe(i.promotion_benefit) + '</div>' : '') + '</div></div>'
    ).join('');

    const shippingAddr = (order.shipping_address || '').trim();

    const addressHTML =
        '<div id="addr-display-' + order.order_id + '" style="margin-top:12px;padding-top:12px;border-top:1px solid #141414;">' +
            '<p style="margin:0 0 6px;font-size:10px;color:#555;text-transform:uppercase;letter-spacing:1.5px;font-family:\'Michroma\',sans-serif;">Ship to</p>' +
            '<div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;">' +
            '<span id="addr-text-' + order.order_id + '" style="font-size:13px;color:#ccc;line-height:1.5;">' +
                (safe(shippingAddr) || '<em style="color:#555;font-size:12px;">Not recorded</em>') +
            '</span>' +
            (status === 'pending'
                ? '<button onclick="showChangeAddress(' + order.order_id + ')" ' +
                  'style="background:none;border:none;padding:0;color:#b71c1c;font-size:12px;' +
                  'font-family:\'JetBrains Mono\',monospace;cursor:pointer;white-space:nowrap;' +
                  'transition:color 0.2s;flex-shrink:0;" ' +
                  'onmouseover="this.style.color=\'#e53935\';" ' +
                  'onmouseout="this.style.color=\'#b71c1c\';">' + (shippingAddr ? 'Change Address' : 'Set Address') + '</button>'
                : '') +
            '</div>' +
        pinLink + '</div>' +
        (status === 'pending'
            ? '<div id="addr-edit-' + order.order_id + '" style="display:none;margin-top:10px;padding:12px;background:#0d0d0d;border:1px solid #1a1a1a;">' +
                  '<p style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:1.5px;' +
                  'font-family:\'Michroma\',sans-serif;margin:0 0 10px;">Select Shipping Address</p>' +
                  '<div id="addr-radios-' + order.order_id + '" style="margin-bottom:10px;">' +
                      '<p style="font-size:11px;color:#555;font-style:italic;">Loading addresses…</p>' +
                  '</div>' +
                  '<div id="delivery-edit-' + order.order_id + '"></div>' +
                  '<div style="display:flex;gap:8px;margin-top:12px;">' +
                      '<button data-address-save disabled onclick="saveOrderAddress(' + order.order_id + ')" ' +
                      'style="flex:1;height:40px;background:#e53935;color:#fff;border:none;' +
                      'font-family:\'JetBrains Mono\',monospace;font-size:11px;text-transform:uppercase;' +
                      'letter-spacing:1.5px;font-weight:bold;cursor:pointer;">Save</button>' +
                      '<button onclick="cancelChangeAddress(' + order.order_id + ')" ' +
                      'style="flex:1;height:40px;background:transparent;color:#666;border:1px solid #222;' +
                      'font-family:\'JetBrains Mono\',monospace;font-size:11px;text-transform:uppercase;' +
                      'letter-spacing:1.5px;font-weight:bold;cursor:pointer;">Cancel</button>' +
                  '</div>' +
              '</div>'
            : '');

    const cancelBtn = status === 'pending'
        ? '<button onclick="cancelOrder(' + order.order_id + ')" style="margin-top:8px;background:none;border:1px solid #f44336;color:#f44336;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:12px;">Cancel Order</button>'
        : '';

    return '<div style="border-bottom:1px solid #eee;padding:15px;">' +
        '<div style="display:flex;justify-content:space-between;align-items:flex-start;">' +
        '<div style="flex:1;">' +
        '<p style="margin:0;font-weight:bold;font-size:14px;display:none;">Order #CZ-' + order.order_id + '</p>' +
        itemsHTML + addressHTML +
        '<p style="margin:6px 0 0;font-size:12px;color:#999;">Ordered: ' + dateStr + '</p>' +
        cancelBtn +
        '</div>' +
        '<div style="text-align:right;margin-left:10px;">' +
        '<p style="margin:0;font-weight:bold;font-size:15px;">&#8369;' + parseFloat(order.total_amount).toLocaleString('en-PH', {minimumFractionDigits:2}) + '</p>' +
        '<span style="font-size:12px;color:' + color + ';font-weight:bold;text-transform:uppercase;">' + status + '</span>' +
        '</div></div></div>';
}

/* ─── Change Address (order card) ─────────────────────── */
function showChangeAddress(orderId) {
    const editPanel = document.getElementById('addr-edit-' + orderId);
    const displayPanel = document.getElementById('addr-display-' + orderId);
    if (!editPanel || !displayPanel) return;
    const request = (editPanel._addressRequest || 0) + 1;
    editPanel._addressRequest = request;
    editPanel._addressReady = false;
    editPanel._addresses = [];
    editPanel._deliveryPicker?.destroy();
    editPanel._deliveryPicker = null;
    const radiosEl = document.getElementById('addr-radios-' + orderId);
    const pinHost = document.getElementById('delivery-edit-' + orderId);
    const saveButton = editPanel.querySelector('[data-address-save]');
    if (saveButton) saveButton.disabled = true;
    if (radiosEl) radiosEl.textContent = 'Loading addresses…';
    if (pinHost) pinHost.replaceChildren();
    const isCurrent = () => editPanel.isConnected && editPanel.style.display !== 'none' && editPanel._addressRequest === request;
    const safe = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    displayPanel.style.display = 'none';
    editPanel.style.display = 'block';

    const isInPages = window.location.pathname.includes('/pages/');
    fetch((isInPages ? '' : 'pages/') + 'get_addresses.php')
        .then(r => { if (!r.ok) throw new Error('Failed to load addresses. Close this panel and try again.'); return r.json(); })
        .then(data => {
            if (!isCurrent()) return;
            if (!Array.isArray(data)) throw new Error('Failed to load addresses. Close this panel and try again.');
            const saved        = data;
            const defaultAddr  = window.userProfile?.address || '';
            const allAddresses = [{ label: 'Default', address: defaultAddr }, ...saved].filter(a => a && typeof a.address === 'string' && a.address.trim());
            if (!radiosEl) return;

            if (allAddresses.length === 0) {
                radiosEl.innerHTML = '<p style="font-size:12px;color:#555;font-style:italic;">No saved addresses. Add one in your profile.</p>';
                return;
            }

            const currentAddr = document.getElementById('addr-text-' + orderId)?.textContent?.trim() || '';
            const matchIdx    = allAddresses.findIndex(a => a.address.trim() === currentAddr);
            const defaultIdx  = matchIdx >= 0 ? matchIdx : 0;

            radiosEl.innerHTML = allAddresses.map((a, i) =>
                '<label style="display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;cursor:pointer;padding:8px;' +
                'border:1px solid ' + (i === defaultIdx ? '#333' : '#1a1a1a') + ';transition:border-color 0.2s;" ' +
                'onmouseover="this.style.borderColor=\'#333\';" onmouseout="this.style.borderColor=\'' + (i === defaultIdx ? '#333' : '#1a1a1a') + '\';">' +
                    '<input type="radio" name="caz-addr-' + orderId + '" value="' + i + '" ' + (i === defaultIdx ? 'checked' : '') +
                    ' style="margin-top:3px;accent-color:#e53935;flex-shrink:0;">' +
                    '<span style="font-size:12px;color:#aaa;line-height:1.5;">' +
                        '<strong style="color:#fff;font-family:\'Michroma\',sans-serif;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;">' + safe(a.label) + '</strong><br>' +
                        safe(a.address) + '</span></label>'
            ).join('');
            if (!pinHost || !window.CAZDeliveryMap?.mountPicker) throw new Error('Delivery location controls could not load. Refresh the page before changing this address.');
            const currentPin = matchIdx >= 0 ? orderDeliveryPins.get(Number(orderId)) : null;
            editPanel._deliveryPicker = window.CAZDeliveryMap.mountPicker(pinHost, {initial:currentPin});
            if (!editPanel._deliveryPicker) throw new Error('Delivery location controls could not load. Refresh the page before changing this address.');
            radiosEl.querySelectorAll('input[type=radio]').forEach(input => input.addEventListener('change', () => editPanel._deliveryPicker?.clear()));
            editPanel._addresses = allAddresses;
            editPanel._addressReady = true;
            if (saveButton) saveButton.disabled = false;
        })
        .catch(error => {
            if (!isCurrent()) return;
            editPanel._addressReady = false;
            if (saveButton) saveButton.disabled = true;
            if (radiosEl) radiosEl.textContent = error.message || 'Failed to load addresses. Close this panel and try again.';
        });
}

function cancelChangeAddress(orderId) {
    const panel = document.getElementById('addr-edit-' + orderId);
    panel?._deliveryPicker?.destroy();
    if (panel) {
        panel._addressRequest = (panel._addressRequest || 0) + 1;
        panel._addressReady = false;
        panel._addresses = [];
        panel._deliveryPicker = null;
        panel.style.display = 'none';
        const saveButton = panel.querySelector('[data-address-save]');
        if (saveButton) saveButton.disabled = true;
    }
    const displayPanel = document.getElementById('addr-display-' + orderId);
    if (displayPanel) displayPanel.style.display = 'block';
}

function saveOrderAddress(orderId) {
    const editPanel    = document.getElementById('addr-edit-' + orderId);
    if (!editPanel?._addressReady || !editPanel._deliveryPicker) { cazAlert.error('Wait for the delivery address and location controls to finish loading.'); return; }
    const sel          = editPanel ? editPanel.querySelector('input[name="caz-addr-' + orderId + '"]:checked') : null;
    const allAddresses = editPanel?._addresses || [];
    const address      = sel ? (allAddresses[parseInt(sel.value)]?.address || '') : '';
    if (!address) { cazAlert.error('Please select a shipping address.'); return; }
    if (editPanel._deliveryPicker && !editPanel._deliveryPicker.validate()) return;
    const deliveryLocation = editPanel._deliveryPicker?.getValue() || null;

    const isInPages = window.location.pathname.includes('/pages/');
    fetch((isInPages ? '' : 'pages/') + 'update_order_address.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' },
        body: JSON.stringify({ order_id: orderId, shipping_address: address, delivery_location: deliveryLocation })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const textEl = document.getElementById('addr-text-' + orderId);
            if (textEl) textEl.textContent = address;
            cancelChangeAddress(orderId);
            cazAlert.success('Shipping address updated!');
            renderCart();
        } else cazAlert.error(data.message || 'Failed to update address.');
    })
    .catch(e => cazAlert.error('Error: ' + e.message));
}

function cancelOrder(orderId) {
    cazAlert.confirm('Are you sure you want to cancel this order?', () => {
        const isInPages = window.location.pathname.includes('/pages/');
        fetch((isInPages ? '' : 'pages/') + 'get_orders.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' },
            body: JSON.stringify({ order_id: orderId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { cazAlert.success('Order canceled.'); renderCart(); }
            else cazAlert.error('Failed: ' + (data.message || 'Unknown error'));
        })
        .catch(e => cazAlert.error('Error: ' + e.message));
    });
}

/* ─── Checkout ────────────────────────────────────────── */
async function checkout() {
    await cart.whenIdle();
    const selectedItems = cart.getSelectedItems();
    if (selectedItems.length === 0) { cazAlert.warning('Please select items to checkout!'); return; }

    const items = selectedItems.map(item => ({
        id: item.id, title: item.title, image: item.img || '',
        qty: item.qty, quantity: item.qty, price: item.price
    }));

    const total     = cart.calculateTotal();
    const isInPages = window.location.pathname.includes('/pages/');

    fetch((isInPages ? '' : 'pages/') + 'get_addresses.php')
        .then(r => r.json())
        .then(data => {
            savedAddresses = Array.isArray(data) ? data : [];
            const defaultAddr  = window.userProfile?.address || '';
            const allAddresses = [{ label: 'Default', address: defaultAddr }, ...savedAddresses].filter(a => a.address);

            cazAlert.checkoutModal(items, total, (shippingAddress, updatedItems, deliveryLocation) => {
                const finalItems = (updatedItems || items).map(item => ({
                    id: item.id, title: item.title, image: item.img || item.image || '',
                    qty: item.qty, quantity: item.qty, price: item.price
                }));
                fetch((isInPages ? '' : 'pages/') + 'place_order.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' },
                    body: JSON.stringify({ items: finalItems, shipping_address: shippingAddress, delivery_location: deliveryLocation })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.CAZCartStatus.checkoutComplete(data);
                        renderCart(); switchTab('pending');
                    } else cazAlert.error('Checkout failed: ' + (data.message || 'Unknown error'));
                })
                .catch(e => cazAlert.error('Checkout failed: ' + e.message));
            }, allAddresses);
        })
        .catch(() => cazAlert.error('Failed to load addresses. Please try again.'));
}

/* ═══════════════════════════════════════════════════════════
   ADDRESS UTILITIES
═══════════════════════════════════════════════════════════ */
let savedAddresses = [];

const PH_PROVINCES = [
    "Abra","Agusan del Norte","Agusan del Sur","Aklan","Albay",
    "Antique","Apayao","Aurora","Basilan","Bataan","Batanes","Batangas",
    "Benguet","Biliran","Bohol","Bukidnon","Bulacan","Cagayan","Camarines Norte",
    "Camarines Sur","Camiguin","Capiz","Catanduanes","Cavite","Cebu",
    "Compostela Valley","Cotabato","Davao de Oro","Davao del Norte",
    "Davao del Sur","Davao Occidental","Davao Oriental","Dinagat Islands",
    "Eastern Samar","Guimaras","Ifugao","Ilocos Norte","Ilocos Sur","Iloilo",
    "Isabela","Kalinga","La Union","Laguna","Lanao del Norte","Lanao del Sur",
    "Leyte","Maguindanao","Maguindanao del Norte","Maguindanao del Sur",
    "Marinduque","Masbate","Metro Manila","Misamis Occidental","Misamis Oriental",
    "Mountain Province","Negros Occidental","Negros Oriental","Northern Samar",
    "Nueva Ecija","Nueva Vizcaya","Occidental Mindoro","Oriental Mindoro",
    "Palawan","Pampanga","Pangasinan","Quezon","Quirino","Rizal","Romblon",
    "Samar","Sarangani","Siquijor","Sorsogon","South Cotabato","Southern Leyte",
    "Sultan Kudarat","Sulu","Surigao del Norte","Surigao del Sur","Tarlac",
    "Tawi-Tawi","Zambales","Zamboanga del Norte","Zamboanga del Sur",
    "Zamboanga Sibugay"
];

/* ─── Special character sanitizer for address text fields ─── */
/* Allows: letters (including accented), digits, spaces, hyphens, periods, and # for unit/house numbers */
function sanitizeAddressField(value) {
    return value.replace(/[^a-zA-ZÀ-ÿ0-9 .#\-]/g, '');
}

/* Street field allows letters, digits, spaces, hyphens, periods (for "St.", "Ave.", etc.) */
function sanitizeStreetField(value) {
    return value.replace(/[^a-zA-ZÀ-ÿ0-9 .\-]/g, '');
}

function parseAddressParts(str) {
    const parts = (str || '').split(',').map(s => s.trim());
    if (parts.length >= 6) {
        return {
            house:    parts[0] || '',
            street:   parts[1] || '',
            barangay: parts[2].replace(/^Brgy\.\s*/i, ''),
            city:     parts[3] || '',
            province: parts[4] || '',
            zip:      parts[5] || ''
        };
    }
    if (parts.length === 5) {
        return { house:'', street: parts[0], barangay: parts[1].replace(/^Brgy\.\s*/i,''), city: parts[2], province: parts[3], zip: parts[4] };
    }
    return { house:'', street: str || '', barangay:'', city:'', province:'', zip:'' };
}

function combineAddressParts(house, street, barangay, city, province, zip) {
    const parts = [];
    if (house)    parts.push(house);
    if (street)   parts.push(street);
    if (barangay) parts.push('Brgy. ' + barangay);
    if (city)     parts.push(city);
    if (province) parts.push(province);
    if (zip)      parts.push(zip);
    return parts.join(', ');
}

function buildAddressFormHTML(prefix, pf) {
    pf = pf || {};

    /* oninput handler strips special characters in real time */
    const inp = (id, placeholder, val, sanitizerFn) => {
        const oninput = sanitizerFn === 'street'
            ? `this.value=sanitizeStreetField(this.value);`
            : `this.value=sanitizeAddressField(this.value);`;
        return `<input id="${prefix}-${id}" type="text" placeholder="${placeholder}" value="${val || ''}" autocomplete="off"
         oninput="${oninput}"
         style="width:100%;padding:10px 12px;background:#0a0a0a;border:1px solid #2a2a2a;color:#fff;
         font-family:'Space Grotesk',sans-serif;font-size:13px;box-sizing:border-box;outline:none;
         transition:border-color 0.2s;"
         onfocus="this.style.borderColor='#e53935'" onblur="this.style.borderColor='#2a2a2a'">`;
    };

    const lbl = (text, req) =>
        `<div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:1px;
         margin-bottom:6px;font-family:'JetBrains Mono',monospace;">
         ${text}${req ? ' <span style="color:#e53935;">*</span>' : ''}</div>`;

    const zipInput =
        `<input id="${prefix}-zip" type="text" inputmode="numeric" pattern="[0-9]*"
         placeholder="e.g. 3500" value="${pf.zip || ''}" autocomplete="off" maxlength="10"
         oninput="this.value=this.value.replace(/[^0-9]/g,'')"
         style="width:100%;padding:10px 12px;background:#0a0a0a;border:1px solid #2a2a2a;color:#fff;
         font-family:'Space Grotesk',sans-serif;font-size:13px;box-sizing:border-box;outline:none;
         transition:border-color 0.2s;"
         onfocus="this.style.borderColor='#e53935'" onblur="this.style.borderColor='#2a2a2a'">`;

    const streetInput =
        `<input id="${prefix}-street" type="text" placeholder="e.g. Rizal" value="${pf.street || ''}" autocomplete="off"
         oninput="this.value=sanitizeStreetField(this.value);"
         style="width:100%;padding:10px 12px;background:#0a0a0a;border:1px solid #2a2a2a;color:#fff;
         font-family:'Space Grotesk',sans-serif;font-size:13px;box-sizing:border-box;outline:none;
         transition:border-color 0.2s;"
         onfocus="this.style.borderColor='#e53935'"
         onblur="
             this.style.borderColor='#2a2a2a';
             var v=this.value.trim();
             if(v && !/St\.?\s*$/i.test(v)) this.value=v+' St.';
         ">`;

    const provinceOptions = PH_PROVINCES.map(p =>
        `<option value="${p}" ${(pf.province || '') === p ? 'selected' : ''}>${p}</option>`
    ).join('');

    const provinceDropdown =
        `<select id="${prefix}-province"
         style="width:100%;padding:10px 12px;background:#0a0a0a;border:1px solid #2a2a2a;
         color:${pf.province ? '#fff' : '#666'};
         font-family:'Space Grotesk',sans-serif;font-size:13px;box-sizing:border-box;outline:none;
         transition:border-color 0.2s;appearance:none;cursor:pointer;
         background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E\");
         background-repeat:no-repeat;background-position:right 12px center;"
         onfocus="this.style.borderColor='#e53935';this.style.color='#fff'"
         onblur="this.style.borderColor='#2a2a2a'"
         onchange="this.style.color='#fff'">
             <option value="" disabled ${!pf.province ? 'selected' : ''} style="color:#666;">Select province…</option>
             ${provinceOptions}
         </select>`;

    return `
        <div style="display:flex;gap:10px;margin-bottom:12px;">
            <div style="flex:1;">
                ${lbl('House / Unit')}
                ${inp('house', 'e.g. 12B', pf.house, 'address')}
            </div>
            <div style="flex:1;">
                ${lbl('ZIP Code', true)}
                ${zipInput}
            </div>
        </div>
        <div style="margin-bottom:12px;">
            ${lbl('Street', true)}
            ${streetInput}
        </div>
        <div style="display:flex;gap:10px;margin-bottom:12px;">
            <div style="flex:1;">
                ${lbl('Barangay', true)}
                ${inp('barangay', 'e.g. San Antonio', pf.barangay, 'address')}
            </div>
            <div style="flex:1;">
                ${lbl('City / Municipality', true)}
                ${inp('city', 'e.g. Tuguegarao City', pf.city, 'address')}
            </div>
        </div>
        <div style="margin-bottom:4px;">
            ${lbl('Province', true)}
            ${provinceDropdown}
        </div>`;
}

function readAddressForm(prefix) {
    const g = id => {
        const el = document.getElementById(prefix + '-' + id);
        if (!el) {
            console.warn(`Element not found: ${prefix}-${id}`);
            return '';
        }
        return el.value.trim();
    };

    const house    = g('house');
    const street   = g('street');
    const barangay = g('barangay');
    const city     = g('city');
    const province = g('province');
    const zip      = g('zip');

    if (!street || !barangay || !city || !province || !zip) {
        console.error('Validation failed: missing required fields', { street, barangay, city, province, zip });
        return null;
    }

    return combineAddressParts(house, street, barangay, city, province, zip);
}

/* ═══════════════════════════════════════════════════════════
   SAVED ADDRESSES — profile panel list (view mode)
═══════════════════════════════════════════════════════════ */
function loadSavedAddresses(callback) {
    const isInPages = window.location.pathname.includes('/pages/');
    fetch((isInPages ? '' : 'pages/') + 'get_addresses.php')
        .then(r => r.json())
        .then(data => {
            savedAddresses = Array.isArray(data) ? data : [];
            renderSavedAddresses();
            if (callback) callback();
        })
        .catch(() => { savedAddresses = []; renderSavedAddresses(); if (callback) callback(); });
}

function renderSavedAddresses() {
    const list = document.getElementById('saved-addresses-list');
    if (!list) return;
    if (!savedAddresses.length) {
        list.innerHTML = '<p style="font-size:12px;color:#444;margin:8px 0 4px;font-style:italic;">None saved yet.</p>';
        return;
    }
    list.innerHTML = savedAddresses.map(a => `
        <div style="padding:10px 0;border-bottom:1px solid #1a1a1a;display:flex;align-items:flex-start;gap:8px;">
            <div style="width:4px;height:4px;background:#e53935;border-radius:50%;flex-shrink:0;margin-top:6px;"></div>
            <span style="font-size:12px;color:#aaa;line-height:1.6;">${a.address}</span>
        </div>`
    ).join('');
}

/* ═══════════════════════════════════════════════════════════
   MANAGE ADDRESSES MODAL
═══════════════════════════════════════════════════════════ */
function showAddressModal() {
    const existing = document.getElementById('caz-addr-manage-modal');
    if (existing) existing.remove();

    const isInPages = window.location.pathname.includes('/pages/');
    const mainAddr  = window.userProfile?.address || document.getElementById('txt-addr')?.textContent?.trim() || '';
    const mainPf    = parseAddressParts(mainAddr);

    const microlabel = (t, color) =>
        `<div style="font-size:10px;letter-spacing:1.5px;text-transform:uppercase;
         font-family:'Michroma',sans-serif;color:${color || '#555'};margin-bottom:8px;">${t}</div>`;

    function buildOtherCards() {
        if (!savedAddresses.length) {
            return `<p style="font-size:12px;color:#444;font-style:italic;margin:0 0 12px;">No other addresses saved yet.</p>`;
        }
        return savedAddresses.map((a, i) => `
            <div id="ocard-${i}" style="border:1px solid #1e1e1e;margin-bottom:8px;background:#0d0d0d;">
                <div id="ocard-row-${i}" style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding:12px 14px;">
                    <span style="font-size:12px;color:#bbb;line-height:1.6;flex:1;">${a.address}</span>
                    <div style="display:flex;gap:6px;flex-shrink:0;margin-top:2px;">
                        <button onclick="openOtherEdit(${i})"
                            title="Edit"
                            style="width:30px;height:30px;background:transparent;border:1px solid #2a2a2a;
                            color:#888;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;
                            transition:border-color 0.2s,color 0.2s;"
                            onmouseover="this.style.borderColor='#555';this.style.color='#fff'"
                            onmouseout="this.style.borderColor='#2a2a2a';this.style.color='#888'">✎</button>
                        <button onclick="modalDeleteAddress(${a.id})"
                            title="Delete"
                            style="width:30px;height:30px;background:transparent;border:1px solid #2a2a2a;
                            color:#888;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;
                            transition:border-color 0.2s,color 0.2s;"
                            onmouseover="this.style.borderColor='#e53935';this.style.color='#e53935'"
                            onmouseout="this.style.borderColor='#2a2a2a';this.style.color='#888'">✕</button>
                    </div>
                </div>
                <div id="ocard-form-${i}" style="display:none;padding:14px;border-top:1px solid #1a1a1a;">
                    ${buildAddressFormHTML('other-' + i, parseAddressParts(a.address))}
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <button onclick="modalSaveOther(${a.id},${i})"
                            style="flex:1;padding:10px;background:#e53935;color:#fff;border:none;cursor:pointer;
                            font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:bold;
                            text-transform:uppercase;letter-spacing:1.5px;">Save</button>
                        <button onclick="closeOtherEdit(${i})"
                            style="flex:1;padding:10px;background:transparent;color:#666;border:1px solid #2a2a2a;cursor:pointer;
                            font-family:'JetBrains Mono',monospace;font-size:11px;
                            text-transform:uppercase;letter-spacing:1.5px;">Cancel</button>
                    </div>
                </div>
            </div>`
        ).join('');
    }

    const modal = document.createElement('div');
    modal.id = 'caz-addr-manage-modal';
    modal.style.cssText = `
        position:fixed;inset:0;z-index:9999;
        background:rgba(0,0,0,0.82);
        display:flex;align-items:center;justify-content:center;
        padding:24px 16px;backdrop-filter:blur(3px);`;

    modal.innerHTML = `
        <div style="background:#111;border:1px solid #1e1e1e;border-top:3px solid #e53935;
             width:100%;max-width:560px;max-height:90vh;display:flex;flex-direction:column;">

            <div style="display:flex;justify-content:space-between;align-items:center;
                 padding:20px 24px 16px;border-bottom:1px solid #1a1a1a;flex-shrink:0;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <i class="fas fa-map-marker-alt" style="color:#e53935;font-size:14px;"></i>
                    <span style="font-family:'Michroma',sans-serif;font-size:12px;
                    text-transform:uppercase;letter-spacing:1.5px;color:#fff;">Manage Addresses</span>
                </div>
                <button onclick="document.getElementById('caz-addr-manage-modal').remove()"
                    style="background:none;border:none;color:#444;font-size:20px;cursor:pointer;
                    line-height:1;transition:color 0.2s;padding:0;"
                    onmouseover="this.style.color='#fff'" onmouseout="this.style.color='#444'">✕</button>
            </div>

            <div style="overflow-y:auto;padding:20px 24px;flex:1;">

                ${microlabel('Default Address', '#e53935')}
                <div style="background:#0d0d0d;border:1px solid #1a1a1a;padding:16px;margin-bottom:24px;">
                    ${buildAddressFormHTML('main', mainPf)}
                    <button onclick="modalSaveMain()"
                        style="margin-top:14px;width:100%;padding:11px;background:#e53935;color:#fff;border:none;
                        cursor:pointer;font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:bold;
                        text-transform:uppercase;letter-spacing:1.5px;transition:background 0.2s;"
                        onmouseover="this.style.background='#ff3333'" onmouseout="this.style.background='#e53935'">
                        Save default address</button>
                </div>

                ${microlabel('Other Addresses')}
                <div id="modal-others-list">${buildOtherCards()}</div>

                <div id="modal-add-form" style="display:none;background:#0d0d0d;border:1px solid #1a1a1a;
                     padding:16px;margin-bottom:10px;">
                    ${microlabel('Add New Address')}
                    ${buildAddressFormHTML('new')}
                    <div style="margin-bottom:12px;">
                        <div style="font-size:10px;color:#555;text-transform:uppercase;letter-spacing:1px;
                             margin-bottom:6px;font-family:'JetBrains Mono',monospace;">Label (optional)</div>
                        <input id="new-label" type="text" placeholder="e.g. Home, Office" autocomplete="off"
                            oninput="this.value=sanitizeAddressField(this.value);"
                            style="width:100%;padding:10px 12px;background:#0a0a0a;border:1px solid #2a2a2a;color:#fff;
                            font-family:'Space Grotesk',sans-serif;font-size:13px;box-sizing:border-box;outline:none;"
                            onfocus="this.style.borderColor='#e53935'" onblur="this.style.borderColor='#2a2a2a'">
                    </div>
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <button onclick="modalSaveNew()"
                            style="flex:1;padding:11px;background:#e53935;color:#fff;border:none;cursor:pointer;
                            font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:bold;
                            text-transform:uppercase;letter-spacing:1.5px;">+ Add address</button>
                        <button onclick="document.getElementById('modal-add-form').style.display='none';
                                document.getElementById('modal-add-btn').style.display='flex';"
                            style="flex:1;padding:11px;background:transparent;color:#666;border:1px solid #2a2a2a;cursor:pointer;
                            font-family:'JetBrains Mono',monospace;font-size:11px;
                            text-transform:uppercase;letter-spacing:1.5px;">Cancel</button>
                    </div>
                </div>

                <button id="modal-add-btn"
                    onclick="document.getElementById('modal-add-form').style.display='block';this.style.display='none';"
                    style="width:100%;padding:11px;background:transparent;color:#e53935;
                    border:1px solid #2a2a2a;cursor:pointer;font-size:12px;letter-spacing:1.5px;
                    font-family:'JetBrains Mono',monospace;transition:border-color 0.2s;
                    display:flex;align-items:center;justify-content:center;gap:6px;"
                    onmouseover="this.style.borderColor='#e53935'" onmouseout="this.style.borderColor='#2a2a2a'">
                    + Add address</button>
            </div>
        </div>`;

    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });

    window.openOtherEdit = function(i) {
        document.getElementById('ocard-row-'  + i).style.display = 'none';
        document.getElementById('ocard-form-' + i).style.display = 'block';
    };
    window.closeOtherEdit = function(i) {
        document.getElementById('ocard-row-'  + i).style.display = 'flex';
        document.getElementById('ocard-form-' + i).style.display = 'none';
    };

    window.modalSaveMain = function () {
        const address = readAddressForm('main');
        if (!address) {
            cazAlert.error('Please fill in all required fields (Street, Barangay, City, Province, ZIP).');
            return;
        }

        const name    = document.getElementById('inp-user')?.value.trim()    || window.userProfile?.name    || '';
        const email   = document.getElementById('inp-email')?.value.trim()   || window.userProfile?.email   || '';
        const contact = document.getElementById('inp-contact')?.value.trim() || window.userProfile?.contact || '';

        const isInPages = window.location.pathname.includes('/pages/');

        fetch((isInPages ? '' : 'pages/') + 'update_profile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' },
            body: JSON.stringify({ name, address, email, contact })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const txtAddr = document.getElementById('txt-addr');
                if (txtAddr) txtAddr.textContent = address;
                if (window.userProfile) window.userProfile.address = address;
                cazAlert.success('Default address updated!');
            } else {
                cazAlert.error(data.message || 'Failed to update.');
            }
        })
        .catch(() => cazAlert.error('Connection error.'));
    };

    window.modalSaveOther = function (id, i) {
        const address = readAddressForm('other-' + i);
        if (!address) { cazAlert.error('Please fill in all required fields.'); return; }

        fetch((isInPages ? '' : 'pages/') + 'edit_address.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' },
            body: JSON.stringify({ id, address })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                savedAddresses[i].address = address;
                renderSavedAddresses();
                const row = document.getElementById('ocard-row-' + i);
                if (row) row.querySelector('span').textContent = address;
                closeOtherEdit(i);
                cazAlert.success('Address updated!');
            } else cazAlert.error(data.message || 'Failed to update.');
        })
        .catch(() => cazAlert.error('Connection error.'));
    };

    window.modalDeleteAddress = function (id) {
        cazAlert.confirm('Delete this address?', () => {
            fetch((isInPages ? '' : 'pages/') + 'delete_address.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' },
                body: JSON.stringify({ id })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const idx = savedAddresses.findIndex(a => a.id == id);
                    if (idx > -1) savedAddresses.splice(idx, 1);
                    renderSavedAddresses();
                    const othersEl = document.getElementById('modal-others-list');
                    if (othersEl) othersEl.innerHTML = buildOtherCards();
                    cazAlert.success('Address deleted!');
                } else cazAlert.error(data.message || 'Failed to delete.');
            })
            .catch(() => cazAlert.error('Connection error.'));
        });
    };

    window.modalSaveNew = function () {
        const address = readAddressForm('new');
        if (!address) { cazAlert.error('Please fill in all required fields.'); return; }
        const label = document.getElementById('new-label')?.value.trim() || 'Address';

        fetch((isInPages ? '' : 'pages/') + 'save_address.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' },
            body: JSON.stringify({ label, address })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                savedAddresses.push({ id: data.id, label: data.label || label, address });
                renderSavedAddresses();
                const othersEl = document.getElementById('modal-others-list');
                if (othersEl) othersEl.innerHTML = buildOtherCards();
                document.getElementById('modal-add-form').style.display = 'none';
                document.getElementById('modal-add-btn').style.display  = 'flex';
                ['new-house','new-street','new-barangay','new-city','new-province','new-zip','new-label'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                cazAlert.success('Address added!');
            } else cazAlert.error(data.message || 'Failed to save.');
        })
        .catch(() => cazAlert.error('Connection error.'));
    };
}

/* kept for backwards compat */
function showAddAddress()    { showAddressModal(); }
function saveNewAddress()    { /* no-op */ }
function editAddress()       { showAddressModal(); }
function cancelEditAddress() { /* no-op */ }
function saveEditAddress()   { /* no-op */ }
function deleteAddress(id)   { if (window.modalDeleteAddress) modalDeleteAddress(id); }

/* ═══════════════════════════════════════════════════════════
   PROFILE — EDIT / SAVE
═══════════════════════════════════════════════════════════ */
/* Profile editing never changes account passwords. */
function enableEdit() {
    const btn      = document.getElementById('edit-save-btn');
    const isSaving = btn.textContent.trim() === 'SAVE';

    if (isSaving) {
        const name    = document.getElementById('inp-user').value.trim();
        const email   = document.getElementById('inp-email').value.trim();
        const contact = document.getElementById('inp-contact').value.trim();

        if (name !== (window.userProfile?.name || '') && (!/^\p{L}[\p{L}\p{M}]*(?: +\p{L}[\p{L}\p{M}]*)*$/u.test(name) || Array.from(name).length > 100)) {
            showProfileMsg('Use letters and spaces only for your name (up to 100 characters).', false); return;
        }
        if (contact !== (window.userProfile?.contact || '') && !/^09[0-9]{9}$/.test(contact)) {
            showProfileMsg('Enter exactly 11 digits, starting with 09.', false); return;
        }

        const address   = window.userProfile?.address || document.getElementById('txt-addr')?.textContent?.trim() || '';
        const isInPages = window.location.pathname.includes('/pages/');

        fetch((isInPages ? '' : 'pages/') + 'update_profile.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' },
            body: JSON.stringify({ name, email, contact, address })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                document.getElementById('txt-user').textContent    = name;
                document.getElementById('txt-email').textContent   = email;
                document.getElementById('txt-contact').textContent = contact;
                document.querySelectorAll('[data-account-name]').forEach(element => {
                    if (!element.matches('input')) element.textContent = name;
                });
                if (window.userProfile) {
                    window.userProfile.name    = name;
                    window.userProfile.email   = email;
                    window.userProfile.contact = contact;
                }
                setEditMode(false);
                showProfileMsg('Profile updated successfully!', true);
            } else showProfileMsg(res.message || 'Update failed.', false);
        })
        .catch(() => showProfileMsg('Connection error. Try again.', false));

    } else {
        setEditMode(true);
    }
}

function cancelEdit() {
    setEditMode(false);
    document.getElementById('profile-msg').style.display = 'none';
}

function setEditMode(editing) {
    const btn          = document.getElementById('edit-save-btn');
    const logoutBtn    = document.getElementById('save-logout-btn');
    const editAddrBtn  = document.getElementById('edit-addresses-btn');
    const profilePanel = document.getElementById('profile-panel');

    const vals = document.querySelectorAll('#txt-user, #txt-email, #txt-contact');
    const inps = document.querySelectorAll('#inp-user, #inp-email, #inp-contact');

    if (editing) {
        vals.forEach(s => s.style.display = 'none');
        inps.forEach(i => i.style.display = 'block');

        if (editAddrBtn)  editAddrBtn.style.display = 'flex';
        if (profilePanel) profilePanel.classList.add('editing');

        const addrHint = document.getElementById('addr-hint');
        if (addrHint) addrHint.style.display = 'block';

        btn.textContent       = 'SAVE';
        logoutBtn.textContent = 'CANCEL';
        logoutBtn.onclick     = cancelEdit;

    } else {
        vals.forEach(s => s.style.display = 'block');
        inps.forEach(i => i.style.display = 'none');

        if (editAddrBtn)  editAddrBtn.style.display = 'none';
        if (profilePanel) profilePanel.classList.remove('editing');

        const addrHint = document.getElementById('addr-hint');
        if (addrHint) addrHint.style.display = 'none';

        btn.textContent       = 'EDIT';
        logoutBtn.textContent = 'LOGOUT';
        logoutBtn.onclick     = handleLogout;
    }
}

function showProfileMsg(text, success) {
    const msg       = document.getElementById('profile-msg');
    msg.textContent = text;
    msg.style.color = success ? '#4caf50' : '#ff5252';
    msg.style.display = 'block';
    if (success) setTimeout(() => msg.style.display = 'none', 3000);
}

function handleLogout() {
    cazAlert.confirm('Are you sure you want to logout?', () => {
        window.location.href = '../logout.php';
    });
}
