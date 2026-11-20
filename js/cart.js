class CartManager {
  constructor() {
    this.storageKey = 'caz-cart-selection:' + Number(window.CAZ_USER_ID || 0);
    this.items = [];
    this.pending = Promise.resolve();
  }
  getBasePath() {
    const path = window.location.pathname;
    return path.includes('/pages/') ? '' : path.includes('/admin/') ? '../pages/' : 'pages/';
  }
  async request(action, data) {
    const response = await fetch(this.getBasePath() + 'cart_handler.php?action=' + action, {
      credentials: 'same-origin', cache: 'no-store',
      ...(data === undefined ? {} : { method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CAZ_CSRF || '' }, body: JSON.stringify(data) })
    });
    const result = await response.json();
    if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Your cart could not be saved. Please try again.');
    return result;
  }
  // Queue changes so rapid clicks cannot overwrite a newer server response.
  enqueue(task) {
    const operation = this.pending.then(task).catch(error => {
      if (typeof cazAlert !== 'undefined') cazAlert.error(error.message || 'Unable to reach your cart. Please try again.');
      return false;
    });
    this.pending = operation.then(() => undefined);
    return operation;
  }
  apply(data) {
    let selected = {};
    try { selected = JSON.parse(localStorage.getItem(this.storageKey) || '{}') || {}; } catch (_) {}
    this.items = data.items.map(item => ({
      id: Number(item.product_id), cart_item_id: Number(item.id), title: item.product_name,
      price: Number(item.price), img: item.image, category: item.category,
      qty: Number(item.quantity), stock: Number(item.stock), selected: selected[item.product_id] !== false
    }));
    this.saveCart(this.items);
    if (typeof renderCart === 'function') renderCart();
    return this.getCart();
  }
  loadFromDB() { return this.enqueue(async () => this.apply(await this.request('get'))); }
  whenIdle() { return this.pending; }
  getCart() { return this.items.map(item => ({ ...item })); }
  saveCart(items) {
    this.items = items.map(item => ({ ...item }));
    // Only selection preferences are cached. Quantities come from this account's database cart.
    try { localStorage.setItem(this.storageKey, JSON.stringify(Object.fromEntries(items.map(item => [item.id, item.selected])))); } catch (_) {}
    window.dispatchEvent(new CustomEvent('caz:cart-changed', { detail: {
      count: items.reduce((sum, item) => sum + item.qty, 0), products: items.length
    } }));
  }
  addItem(product, quantity = 1) {
    return this.enqueue(async () => this.apply(await this.request('add', { product_id: Number(product.id), quantity: Number(quantity) })));
  }
  updateQuantity(index, delta) {
    const id = this.items[index]?.cart_item_id;
    if (!id) return Promise.resolve(false);
    return this.enqueue(async () => this.apply(await this.request('update', { item_id: id, delta: Number(delta) })));
  }
  removeItem(index) {
    const id = this.items[index]?.cart_item_id;
    if (!id) return Promise.resolve(false);
    return this.enqueue(async () => this.apply(await this.request('remove', { item_id: id })));
  }
  deleteCategory(category) {
    const ids = this.items.filter(item => item.category === category).map(item => item.cart_item_id);
    return this.enqueue(async () => {
      for (const id of ids) this.apply(await this.request('remove', { item_id: id }));
      return this.getCart();
    });
  }
  deleteAll() { return this.enqueue(async () => this.apply(await this.request('clear', {}))); }
  toggleItem(index) {
    const items = this.getCart();
    if (items[index]) items[index].selected = !items[index].selected;
    this.saveCart(items);
    return items;
  }
  toggleSelectAll(selected) {
    const items = this.getCart().map(item => ({ ...item, selected }));
    this.saveCart(items);
    return items;
  }
  getSelectedItems() { return this.getCart().filter(item => item.selected); }
  calculateTotal() { return this.getSelectedItems().reduce((sum, item) => sum + item.price * item.qty, 0); }
  getSelectedCount() { return this.getSelectedItems().reduce((sum, item) => sum + item.qty, 0); }
  async checkStock() {
    const items = await this.loadFromDB();
    if (!items) return;
    const unavailable = items.filter(item => item.qty > item.stock);
    if (unavailable.length && typeof cazAlert !== 'undefined') cazAlert.warning('Stock has changed for ' + unavailable.map(item => item.title).join(', ') + '. Please adjust these quantities before checkout.', 6000);
  }
}
const cart = new CartManager();
document.addEventListener('DOMContentLoaded', () => cart.loadFromDB());
window.addEventListener('focus', () => cart.loadFromDB());
if (typeof module !== 'undefined' && module.exports) module.exports = CartManager;
