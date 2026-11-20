const cazAlert = (() => {

  const injectStyles = () => {
    if (document.getElementById('caz-alert-styles')) return;
    const style = document.createElement('style');
    style.id = 'caz-alert-styles';
    style.textContent = `
      #caz-toast-container {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 99999;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        pointer-events: none;
        width: max-content;
      }

      .caz-toast {
        display: flex;
        align-items: center;
        gap: 14px;
        background: #0a0a0a;
        border: 1px solid #1a1a1a;
        border-left: 3px solid #e53935;
        padding: 16px 20px;
        min-width: 320px;
        max-width: 460px;
        pointer-events: all;
        font-family: 'Space Grotesk', sans-serif;
        font-size: 15px;
        color: #ffffff;
        box-shadow: 0 8px 32px rgba(0,0,0,0.6);
        transform: translateY(-20px);
        opacity: 0;
        transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease;
      }

      .caz-toast.show { transform: translateY(0); opacity: 1; }
      .caz-toast.hide { transform: translateY(-20px); opacity: 0; }

      .caz-toast.success { border-left-color: #4caf50; }
      .caz-toast.error   { border-left-color: #e53935; }
      .caz-toast.warning { border-left-color: #ff9800; }
      .caz-toast.info    { border-left-color: #2196f3; }

      .caz-toast-icon { font-size: 18px; flex-shrink: 0; line-height: 1; }
      .caz-toast.success .caz-toast-icon { color: #4caf50; }
      .caz-toast.error   .caz-toast-icon { color: #e53935; }
      .caz-toast.warning .caz-toast-icon { color: #ff9800; }
      .caz-toast.info    .caz-toast-icon { color: #2196f3; }

      .caz-toast-msg { flex: 1; line-height: 1.4; color: #dddddd; }

      .caz-toast-close {
        background: none; border: none; color: #444444; cursor: pointer;
        font-size: 16px; padding: 0; line-height: 1;
        transition: color 0.2s ease; flex-shrink: 0;
      }
      .caz-toast-close:hover { color: #ffffff; }

      /* ── SHARED MODAL BASE ── */
      .caz-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.80);
        z-index: 99998;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.25s ease;
        pointer-events: none;
      }
      .caz-modal-overlay.show { opacity: 1; pointer-events: all; }

      .caz-modal-box {
        background: #0a0a0a;
        border: 1px solid #1a1a1a;
        border-top: 3px solid #e53935;
        padding: 32px 28px 24px;
        width: 100%;
        max-width: 400px;
        font-family: 'Space Grotesk', sans-serif;
        transform: translateY(-12px);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        box-shadow: 0 24px 64px rgba(0,0,0,0.7);
      }
      .caz-modal-overlay.show .caz-modal-box { transform: translateY(0); }

      .caz-modal-label {
        font-family: 'Michroma', sans-serif;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        color: #e53935;
        margin: 0 0 16px 0;
      }

      .caz-modal-btn-row {
        display: flex;
        gap: 10px;
        margin-top: 20px;
      }

      .caz-modal-btn {
        flex: 1;
        height: 44px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: bold;
        border: 1px solid #222;
        cursor: pointer;
        transition: all 0.2s ease;
      }
      .caz-modal-btn.secondary { background: transparent; color: #666; }
      .caz-modal-btn.secondary:hover { background: #141414; color: #fff; border-color: #333; }
      .caz-modal-btn.primary { background: #e53935; color: #fff; border-color: #e53935; }
      .caz-modal-btn.primary:hover { background: #ff3333; border-color: #ff3333; }

      /* ── CART MODAL ── */
      .caz-cart-product {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 14px 0;
        border-top: 1px solid #141414;
        border-bottom: 1px solid #141414;
        margin-bottom: 4px;
      }
      .caz-cart-product img {
        width: 72px;
        height: 72px;
        object-fit: cover;
        border: 1px solid #1a1a1a;
        flex-shrink: 0;
      }
      .caz-cart-product-info { flex: 1; }
      .caz-cart-product-title {
        font-family: 'Michroma', sans-serif;
        font-size: 13px;
        color: #ffffff;
        margin: 0 0 4px 0;
      }
      .caz-cart-product-meta { font-size: 12px; color: #555; margin: 0; }

      .caz-cart-qty-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0;
        border-top: 1px solid #141414;
      }
      .caz-cart-qty-label {
        font-family: 'Michroma', sans-serif;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #555;
      }
      .caz-cart-qty-ctrl {
        display: flex;
        align-items: center;
        border: 1px solid #222;
      }
      .caz-cart-qty-ctrl button {
        width: 34px;
        height: 34px;
        background: #0a0a0a;
        border: none;
        color: #fff;
        font-size: 18px;
        cursor: pointer;
        transition: background 0.2s;
        font-family: 'JetBrains Mono', monospace;
      }
      .caz-cart-qty-ctrl button:hover { background: #e53935; }
      .caz-cart-qty-ctrl span {
        width: 40px;
        text-align: center;
        font-family: 'JetBrains Mono', monospace;
        font-size: 14px;
        color: #fff;
        border-left: 1px solid #222;
        border-right: 1px solid #222;
        line-height: 34px;
        display: inline-block;
      }

      .caz-cart-total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0 4px;
        border-top: 1px solid #141414;
      }
      .caz-cart-total-label {
        font-family: 'Michroma', sans-serif;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #555;
      }
      .caz-cart-total-amount {
        font-family: 'JetBrains Mono', monospace;
        font-size: 18px;
        color: #e53935;
        font-weight: bold;
      }

      /* ── CHECKOUT MODAL ── */
      #caz-checkout-modal .caz-modal-box { max-width: 440px; }

      .caz-checkout-items {
        max-height: 220px;
        overflow-y: auto;
        margin-bottom: 4px;
        scrollbar-width: none;
      }
      .caz-checkout-items::-webkit-scrollbar { display: none; }

      .caz-checkout-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #141414;
      }
      .caz-checkout-item img {
        width: 48px;
        height: 48px;
        object-fit: cover;
        border: 1px solid #1a1a1a;
        flex-shrink: 0;
      }
      .caz-checkout-item-info { flex: 1; }
      .caz-checkout-item-title {
        font-family: 'Michroma', sans-serif;
        font-size: 12px;
        color: #ffffff;
        margin: 0 0 3px 0;
      }
      .caz-checkout-item-qty { font-size: 12px; color: #555; }
      .caz-checkout-item-price {
        font-family: 'JetBrains Mono', monospace;
        font-size: 13px;
        color: #ffffff;
        white-space: nowrap;
      }

      .caz-checkout-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 0 0;
        margin-top: 4px;
        border-top: 1px solid #1a1a1a;
      }
      .caz-checkout-total-label {
        font-family: 'Michroma', sans-serif;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #555;
      }
      .caz-checkout-total-amount {
        font-family: 'JetBrains Mono', monospace;
        font-size: 18px;
        color: #e53935;
        font-weight: bold;
      }

      /* ── CONFIRM MODAL ── */
      #caz-confirm-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.75);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.25s ease;
        pointer-events: none;
      }
      #caz-confirm-overlay.show { opacity: 1; pointer-events: all; }

      #caz-confirm-box {
        background: #0a0a0a;
        border: 1px solid #1a1a1a;
        border-top: 3px solid #e53935;
        padding: 32px 28px 24px;
        width: 100%;
        max-width: 360px;
        font-family: 'Space Grotesk', sans-serif;
        transform: translateY(-10px);
        transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      }
      #caz-confirm-overlay.show #caz-confirm-box { transform: translateY(0); }

      #caz-confirm-icon { font-size: 28px; color: #e53935; margin-bottom: 14px; display: block; }

      #caz-confirm-title {
        font-family: 'Michroma', sans-serif;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #ffffff;
        margin: 0 0 8px 0;
      }

      #caz-confirm-msg {
        font-size: 14px;
        color: #888888;
        margin: 0 0 24px 0;
        line-height: 1.5;
      }

      #caz-confirm-buttons { display: flex; gap: 12px; }

      .caz-confirm-btn {
        flex: 1;
        height: 42px;
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: bold;
        border: 1px solid #222222;
        cursor: pointer;
        transition: all 0.2s ease;
      }
      .caz-confirm-btn.cancel { background: transparent; color: #666666; }
      .caz-confirm-btn.cancel:hover { background: #141414; color: #ffffff; border-color: #333333; }
      .caz-confirm-btn.confirm { background: #e53935; color: #ffffff; border-color: #e53935; }
      .caz-confirm-btn.confirm:hover { background: #ff3333; border-color: #ff3333; }
    `;
    document.head.appendChild(style);
  };

  const getContainer = () => {
    let c = document.getElementById('caz-toast-container');
    if (!c) {
      c = document.createElement('div');
      c.id = 'caz-toast-container';
      document.body.appendChild(c);
    }
    return c;
  };

  const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };

  const show = (type, message, duration = 3500) => {
    injectStyles();
    const container = getContainer();
    const toast = document.createElement('div');
    toast.className = `caz-toast ${type}`;
    toast.innerHTML = `
      <span class="caz-toast-icon">${icons[type] || 'ℹ'}</span>
      <span class="caz-toast-msg">${message}</span>
      <button class="caz-toast-close" aria-label="Close">&times;</button>
    `;
    container.appendChild(toast);
    requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));
    toast.querySelector('.caz-toast-close').onclick = () => dismiss(toast);
    const timer = setTimeout(() => dismiss(toast), duration);
    toast._timer = timer;
  };

  const dismiss = (toast) => {
    clearTimeout(toast._timer);
    toast.classList.remove('show');
    toast.classList.add('hide');
    setTimeout(() => toast.remove(), 400);
  };

  /* ── CART MODAL ── */
  const cartModal = ({ id, image, title, price, category, stock }) => {
    injectStyles();

    // stock defaults to a large number if not provided (backwards compat)
    const maxStock = (typeof stock === 'number' && stock > 0) ? stock : 9999;

    let overlay = document.getElementById('caz-cart-modal');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id        = 'caz-cart-modal';
      overlay.className = 'caz-modal-overlay';
      overlay.innerHTML = `
        <div class="caz-modal-box">
          <p class="caz-modal-label">Add to Cart</p>
          <div class="caz-cart-product">
            <img id="caz-cart-img" src="" alt="">
            <div class="caz-cart-product-info">
              <p class="caz-cart-product-title" id="caz-cart-title"></p>
              <p class="caz-cart-product-meta"  id="caz-cart-unit-price"></p>
            </div>
          </div>
          <p id="caz-cart-stock-label" style="font-size:11px;font-family:'JetBrains Mono',monospace;letter-spacing:0.5px;margin:4px 0 8px;"></p>
          <div class="caz-cart-qty-row">
            <span class="caz-cart-qty-label">Quantity</span>
            <div class="caz-cart-qty-ctrl">
              <button id="caz-qty-minus">−</button>
              <span   id="caz-qty-val">1</span>
              <button id="caz-qty-plus">+</button>
            </div>
          </div>
          <div class="caz-cart-total-row">
            <span class="caz-cart-total-label">Total</span>
            <span class="caz-cart-total-amount" id="caz-cart-total"></span>
          </div>
          <div class="caz-modal-btn-row">
            <button class="caz-modal-btn secondary" id="caz-cart-cancel">Cancel</button>
            <button class="caz-modal-btn secondary" id="caz-cart-add">Add to Cart</button>
            <button class="caz-modal-btn primary"   id="caz-cart-checkout">Buy Now</button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
    }

    // Populate
    const imgPath = window.location.pathname.includes('/pages/') ? '../images/' : 'images/';
    document.getElementById('caz-cart-img').src               = imgPath + image;
    document.getElementById('caz-cart-title').textContent     = title;
    document.getElementById('caz-cart-unit-price').textContent = '₱' + parseFloat(price).toLocaleString('en-PH', { minimumFractionDigits: 2 }) + ' each';

    // Stock label
    const stockLabel = document.getElementById('caz-cart-stock-label');
    if (stockLabel) {
      stockLabel.textContent = maxStock < 9999 ? (maxStock <= 3 ? '⚠ Only ' + maxStock + ' in stock' : maxStock + ' in stock') : '';
      stockLabel.style.color = maxStock <= 3 ? '#e53935' : '#555';
    }

    // Qty
    let qty = 1;
    const qtyEl   = document.getElementById('caz-qty-val');
    const totalEl = document.getElementById('caz-cart-total');

    const updateTotal = () => {
      qtyEl.textContent   = qty;
      totalEl.textContent = '₱' + (price * qty).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    };
    updateTotal();

    document.getElementById('caz-qty-minus').onclick = () => { if (qty > 1) { qty--; updateTotal(); } };
    document.getElementById('caz-qty-plus').onclick  = () => {
      if (qty < maxStock) { qty++; updateTotal(); }
      else { cazAlert.warning('Only ' + maxStock + ' unit(s) available in stock!'); }
    };

    const close = () => overlay.classList.remove('show');

    // Add to Cart
    document.getElementById('caz-cart-add').onclick = async (event) => {
      const button = event.currentTarget;
      button.disabled = true;
      const saved = await cart.addItem({ id, title, price, img: image, category }, qty);
      button.disabled = false;
      if (saved === false) return;
      renderCart(); close();
      cazAlert.success(qty + 'x ' + title + ' saved in your cart!');
    };

    // Buy Now
    document.getElementById('caz-cart-checkout').onclick = () => {
      close();
      const items = [{ id, title, image, qty, price, stock: maxStock < 9999 ? maxStock : undefined }];
      const total = price * qty;
      const isInPages = window.location.pathname.includes('/pages/');
      fetch((isInPages ? '' : 'pages/') + 'get_addresses.php')
        .then(r => r.json())
        .then(freshAddr => {
          const defaultAddr  = window.userProfile?.address || '';
          const allAddresses = [
            { label: 'Default', address: defaultAddr },
            ...(Array.isArray(freshAddr) ? freshAddr : [])
          ].filter(a => a.address);
          cazAlert.checkoutModal(items, total, (shippingAddress, updatedItems, deliveryLocation) => {
            // Use updatedItems so qty changes made inside checkout modal are respected
            const finalItems = (updatedItems || items).map(item => ({
              id:       item.id,
              title:    item.title,
              quantity: item.qty,
              price:    item.price
            }));
            fetch((isInPages ? '' : 'pages/') + 'place_order.php', {
              method:  'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' },
              body:    JSON.stringify({ items: finalItems, shipping_address: shippingAddress, delivery_location: deliveryLocation })
            })
            .then(r => r.json())
            .then(data => {
              if (data.status === 'success') {
                window.CAZCartStatus.checkoutComplete(data);
                if (!document.getElementById('cart-overlay')?.classList.contains('active')) toggleCart();
                switchTab('pending');
              } else {
                cazAlert.error('Order failed: ' + (data.message || 'Unknown error'));
              }
            })
            .catch(e => cazAlert.error('Error: ' + e.message));
          }, allAddresses);
        })
        .catch(() => cazAlert.error('Failed to load addresses. Try again.'));
    };

    document.getElementById('caz-cart-cancel').onclick = close;
    overlay.onclick = (e) => { if (e.target === overlay) close(); };

    // Reset qty on open
    qty = 1;
    updateTotal();

    requestAnimationFrame(() => requestAnimationFrame(() => overlay.classList.add('show')));
  };

/* ── CHECKOUT MODAL ── */
const checkoutModal = (items, total, onConfirm, addresses = []) => {
    injectStyles();
    document.getElementById('caz-checkout-modal')?.remove();
    const escape = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const money = value => '₱' + Number(value).toLocaleString('en-PH', {minimumFractionDigits:2,maximumFractionDigits:2});
    const overlay = document.createElement('div');
    overlay.id = 'caz-checkout-modal';
    overlay.className = 'caz-modal-overlay caz-checkout-with-map';
    overlay.setAttribute('role','dialog');
    overlay.setAttribute('aria-modal','true');
    overlay.setAttribute('aria-labelledby','caz-checkout-heading');
    const addrHTML = addresses.length ? `<fieldset class="checkout-addresses"><legend>Delivery address</legend>${addresses.map((a,i)=>`<label><input type="radio" name="caz-addr" value="${i}" ${i===0?'checked':''}><span><strong>${escape(a.label)}</strong><br>${escape(a.address)}</span></label>`).join('')}</fieldset>` : '<p class="caz-quote-status">Add a delivery address in your account before placing an order.</p>';
    let checkoutItems = items.map(item => ({...item,qty:Number(item.qty || item.quantity || 1)}));
    let version=0, closed=false, quoted=false;
    overlay.innerHTML = `<div class="caz-modal-box"><h2 class="caz-modal-label" id="caz-checkout-heading">Order summary</h2><div class="caz-checkout-items" id="caz-checkout-items"></div><p class="caz-quote-status" id="caz-quote-status" role="status" aria-live="polite"></p><div class="caz-checkout-total"><span class="caz-checkout-total-label">Total</span><span class="caz-checkout-total-amount" id="caz-checkout-total">—</span></div>${addrHTML}<div class="caz-modal-btn-row"><button class="caz-modal-btn secondary" id="caz-checkout-cancel">Cancel</button><button class="caz-modal-btn primary" id="caz-checkout-confirm" disabled>Place order</button></div></div>`;
    document.body.appendChild(overlay);
    const list=overlay.querySelector('#caz-checkout-items');
    const status=overlay.querySelector('#caz-quote-status');
    const totalEl=overlay.querySelector('#caz-checkout-total');
    const confirm=overlay.querySelector('#caz-checkout-confirm');
    const deliveryHost=document.createElement('section');
    overlay.querySelector('.caz-modal-btn-row').before(deliveryHost);
    const deliveryPicker=window.CAZDeliveryMap?.mountPicker(deliveryHost);
    overlay.querySelectorAll('input[name="caz-addr"]').forEach(input=>input.addEventListener('change',()=>deliveryPicker?.clear()));
    const base=location.pathname.includes('/pages/')?'':'pages/';
    const imgPath=location.pathname.includes('/pages/')?'../images/':'images/';
    const render=()=>{
      list.innerHTML=checkoutItems.map((item,index)=>`<div class="caz-checkout-item"><img src="${imgPath}${escape(item.image||item.img||'')}" alt="${escape(item.title)}"><div class="caz-checkout-item-info"><p class="caz-checkout-item-title">${escape(item.title)}</p><div class="checkout-quantity"><button type="button" data-index="${index}" data-delta="-1" aria-label="Decrease ${escape(item.title)} quantity">−</button><span>${item.qty}</span><button type="button" data-index="${index}" data-delta="1" aria-label="Increase ${escape(item.title)} quantity">+</button></div>${item.promotion?`<div class="caz-promo-notice"><strong>${escape(item.promotion.title)}</strong><br>${escape(item.promotion.benefit)}</div>`:''}</div><span class="caz-checkout-item-price">${quoted?money(item.price*item.qty):'—'}</span></div>`).join('');
    };
    const refreshQuote=async()=>{
      const request=++version; quoted=false; confirm.disabled=true;
      status.textContent='Checking current prices, stock and eligible promotions…'; totalEl.textContent='—';
      render();
      try {
        const response=await fetch(base+'promotion_quote.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':window.CAZ_CSRF||''},body:JSON.stringify({items:checkoutItems.map(item=>({id:item.id,quantity:item.qty}))})});
        const data=await response.json();
        if(closed||request!==version)return;
        if(!response.ok||!data.success)throw new Error(data.message||'Unable to check your order.');
        checkoutItems=data.items.map(item=>({...item,qty:Number(item.quantity)}));
        quoted=true; render(); totalEl.textContent=money(data.total_amount);
        status.textContent='Current prices confirmed. Eligible offers are included below each product.';
        confirm.disabled=addresses.length===0;
      } catch(error) {
        if(closed||request!==version)return;
        status.textContent=error.message; totalEl.textContent='—';
        const retry=document.createElement('button');retry.className='quote-retry';retry.textContent='Try again';retry.onclick=refreshQuote;status.append(' ',retry);
      }
    };
    list.addEventListener('click',event=>{
      const button=event.target.closest('button[data-index]');if(!button)return;
      const item=checkoutItems[Number(button.dataset.index)];const qty=item.qty+Number(button.dataset.delta);
      if(qty<1)return;
      if(item.stock!==undefined&&qty>Number(item.stock)){status.textContent=`Only ${item.stock} unit(s) available.`;return;}
      item.qty=qty; refreshQuote();
    });
    const previousFocus=document.activeElement;
    const close=()=>{closed=true;version++;deliveryPicker?.destroy();overlay.remove();previousFocus?.focus();};
    overlay.querySelector('#caz-checkout-cancel').onclick=close;
    confirm.onclick=()=>{
      if(!quoted||confirm.disabled)return;
      const selected=overlay.querySelector('input[name="caz-addr"]:checked');
      const address=selected?addresses[Number(selected.value)]?.address:'';
      if(!address){status.textContent='Choose a delivery address.';return;}
      if(deliveryPicker&&!deliveryPicker.validate())return;
      const deliveryLocation=deliveryPicker?.getValue()||null;
      const finalItems=checkoutItems.map(item=>({...item})); close();onConfirm(address,finalItems,deliveryLocation);
    };
    overlay.onclick=event=>{if(event.target===overlay)close();};
    overlay.addEventListener('keydown',event=>{
      if(event.key==='Escape')close();
      if(event.key==='Tab'){
        const controls=[...overlay.querySelectorAll('button:not(:disabled),input:not(:disabled),a[href],summary,[tabindex]:not([tabindex="-1"])')].filter(el=>el.getClientRects().length);const first=controls[0],last=controls.at(-1);
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}
        else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}
      }
    });
    refreshQuote();
    requestAnimationFrame(()=>{overlay.classList.add('show');overlay.querySelector('#caz-checkout-cancel').focus();});
};


  /* ── CONFIRM MODAL ── */
  const confirm = (message, onConfirm, onCancel = null, title = 'Are you sure?') => {
    injectStyles();

    let overlay = document.getElementById('caz-confirm-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'caz-confirm-overlay';
      overlay.innerHTML = `
        <div id="caz-confirm-box">
          <span id="caz-confirm-icon">⚠</span>
          <p id="caz-confirm-title"></p>
          <p id="caz-confirm-msg"></p>
          <div id="caz-confirm-buttons">
            <button class="caz-confirm-btn cancel"  id="caz-cancel-btn">Cancel</button>
            <button class="caz-confirm-btn confirm" id="caz-ok-btn">Confirm</button>
          </div>
        </div>
      `;
      document.body.appendChild(overlay);
    }

    document.getElementById('caz-confirm-title').textContent = title;
    document.getElementById('caz-confirm-msg').textContent   = message;

    const close = () => overlay.classList.remove('show');

    document.getElementById('caz-cancel-btn').onclick = () => { close(); if (onCancel) onCancel(); };
    document.getElementById('caz-ok-btn').onclick     = () => { close(); if (onConfirm) onConfirm(); };
    overlay.onclick = (e) => { if (e.target === overlay) close(); };

    requestAnimationFrame(() => requestAnimationFrame(() => overlay.classList.add('show')));
  };

  return {
    success:       (msg, duration)           => show('success', msg, duration),
    error:         (msg, duration)           => show('error',   msg, duration),
    warning:       (msg, duration)           => show('warning', msg, duration),
    info:          (msg, duration)           => show('info',    msg, duration),
    confirm:       (msg, onOk, onNo, title)  => confirm(msg, onOk, onNo, title),
    cartModal:     (opts)                    => cartModal(opts),
    checkoutModal: (items, total, onConfirm, addresses) => checkoutModal(items, total, onConfirm, addresses),
  };

})();
