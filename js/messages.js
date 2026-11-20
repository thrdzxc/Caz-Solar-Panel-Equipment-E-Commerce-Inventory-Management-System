(() => {
  'use strict';
  const app = document.getElementById('messages-app');
  if (!app) return;
  const config = JSON.parse(app.dataset.config);
  const el = id => document.getElementById(`messages-${id}`);
  const history = el('history');
  const items = el('items');
  const body = el('body');
  const send = el('send');
  const ownRole = config.admin ? 'admin' : 'customer';
  const adminName = 'Caz admin';
  let selected = config.admin ? null : Number(config.viewerId);
  let messages = [];
  let conversations = [];
  let lastReadId = 0;
  let lastAcknowledged = 0;
  let generation = 0;
  let sending = false;
  let polling = false;
  let pollAgain = false;
  let acknowledging = false;
  let retry = null;
  let errorSource = '';
  let customerProfile = null;
  let inboxSignature = '';
  let refreshRecipients = null;
  const drafts = new Map();
  const avatarStates = new WeakMap();

  function error(message = '', source = 'user') {
    el('error').textContent = message;
    el('error').hidden = !message;
    errorSource = message ? source : '';
  }
  function connection(online) {
    el('connection').textContent = online ? 'Up to date' : 'Reconnecting…';
    el('connection').classList.toggle('is-offline', !online);
  }
  async function api(action, data = {}, post = false) {
    const params = new URLSearchParams({action, ...data});
    const options = {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}};
    let url = config.endpoint;
    if (post) {
      options.method = 'POST';
      options.headers['X-CSRF-Token'] = config.csrf;
      options.body = params;
    } else url += `?${params}`;
    const controller = new AbortController();
    options.signal = controller.signal;
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(url, options);
      let data;
      try { data = await response.json(); } catch (_) { throw new Error('Messages could not be loaded. Please try again.'); }
      if (!response.ok || !data.ok) throw new Error(data.error || data.message || 'Please try again.');
      return data;
    } catch (err) {
      if (err.name === 'AbortError') throw new Error('The connection timed out. Please try again.');
      throw err;
    } finally { clearTimeout(timeout); }
  }
  const date = value => new Date(`${value.replace(' ', 'T')}Z`);
  const time = value => value ? date(value).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}) : '';
  const day = value => date(value).toLocaleDateString([], {month: 'short', day: 'numeric', year: 'numeric'});
  const initials = name => String(name || 'Customer').trim().split(/\s+/).slice(0,2).map(word => word[0]).join('').toUpperCase();
  function photoUrl(value) {
    if (typeof value !== 'string' || !value) return '';
    try {
      const url = new URL(value, window.location.href);
      const expected = new URL(config.admin ? '../admin/customer_photo.php' : '../pages/profile_photo.php', window.location.href);
      if (url.origin !== expected.origin || url.pathname !== expected.pathname || url.username || url.password) return '';
      if (!/^[a-f0-9]{32}$/i.test(url.searchParams.get('v') || '')) return '';
      if (config.admin && !/^[1-9]\d*$/.test(url.searchParams.get('user_id') || '')) return '';
      return url.href;
    } catch (_) { return ''; }
  }
  function updateAvatar(avatar, name, value) {
    const url = photoUrl(value);
    const key = `${name || ''}\n${url}`;
    const previous = avatarStates.get(avatar);
    if (previous?.key === key && !previous.failed) return;
    const state = {key, name, value};
    avatarStates.set(avatar, state);
    avatar.classList.remove('has-photo');
    avatar.textContent = initials(name);
    if (!url) return;
    const image = document.createElement('img');
    image.className = 'messages-customer-photo';
    image.alt = '';
    image.decoding = 'async';
    image.addEventListener('load', () => {
      if (avatarStates.get(avatar) !== state) return;
      avatar.replaceChildren(image);
      avatar.classList.add('has-photo');
    }, {once: true});
    image.addEventListener('error', () => {
      if (avatarStates.get(avatar) !== state) return;
      state.failed = true; // Retry an unchanged photo after a temporary image/network failure.
      avatar.classList.remove('has-photo');
      avatar.textContent = initials(name);
    }, {once: true});
    image.src = url;
  }
  function customerAvatar(name, value, sender = false) {
    const avatar = document.createElement('span');
    avatar.className = `messages-avatar${sender ? ' messages-sender-avatar' : ''}`;
    avatar.setAttribute('aria-hidden', 'true');
    if (sender) avatar.dataset.customerAvatar = '';
    updateAvatar(avatar, name, value);
    return avatar;
  }
  function updateConversationProfile(profile) {
    customerProfile = profile;
    if (config.admin) updateAvatar(el('avatar'), profile.name, profile.photo_url);
    items.querySelectorAll('[data-customer-avatar]').forEach(avatar => updateAvatar(avatar, profile.name, profile.photo_url));
  }
  const atBottom = () => history.scrollHeight - history.scrollTop - history.clientHeight < 90;
  function showEmpty(admin = false) {
    items.replaceChildren();
    const container = document.createElement('div');
    container.className = 'messages-empty';
    const icon = document.createElement('span'); icon.textContent = '✉'; icon.setAttribute('aria-hidden','true');
    const title = document.createElement('h3'); title.textContent = admin ? 'Start the conversation' : 'Welcome to your CAZ conversation';
    const text = document.createElement('p'); text.textContent = admin ? 'Send a helpful hello or answer a question about products and orders.' : 'Ask a question below. Our team will see it in their inbox.';
    container.append(icon,title,text); items.append(container);
  }
  function renderMessages() {
    if (!messages.length) { showEmpty(config.admin); return; }
    const fragment = document.createDocumentFragment();
    let previousDay = '';
    messages.forEach(message => {
      const currentDay = day(message.sent_at);
      if (currentDay !== previousDay) {
        const divider = document.createElement('p'); divider.className = 'messages-day'; divider.textContent = currentDay; fragment.append(divider); previousDay = currentDay;
      }
      const own = message.sender_role === ownRole;
      const row = document.createElement('article'); row.className = `messages-message ${own ? 'is-own' : 'is-incoming'}`; row.dataset.id = message.id;
      const bubble = document.createElement('div'); bubble.className = 'messages-bubble';
      const senderName = document.createElement('span'); senderName.className = 'messages-sender'; senderName.textContent = own ? (config.admin ? `${adminName} · ${message.sender_name || 'Admin'}` : 'You') : (message.sender_role === 'admin' ? adminName : message.sender_name || 'Customer');
      if (message.sender_role === 'admin') {
        const logo = document.createElement('img');
        logo.className = 'messages-sender-logo'; logo.src = '../images/cazlogo.png'; logo.alt = '';
        logo.setAttribute('aria-hidden', 'true'); senderName.prepend(logo);
      } else {
        senderName.prepend(customerAvatar(customerProfile?.name || message.sender_name, customerProfile?.photo_url, true));
      }
      const text = document.createElement('p'); text.textContent = message.body;
      const meta = document.createElement('div'); meta.className = 'messages-meta';
      const timestamp = document.createElement('time'); timestamp.textContent = time(message.sent_at); timestamp.dateTime = date(message.sent_at).toISOString(); meta.append(timestamp);
      if (own) { const receipt = document.createElement('span'); receipt.className = 'messages-receipt'; receipt.textContent = message.read_at || message.id <= lastReadId ? 'Read' : 'Sent'; meta.append(receipt); }
      bubble.append(senderName,text,meta); row.append(bubble); fragment.append(row);
    });
    items.replaceChildren(fragment);
  }
  function renderInbox() {
    if (!config.admin) return;
    const query = el('search').value.toLowerCase().trim();
    const filtered = conversations.filter(item => `${item.name} ${item.last_body || ''}`.toLowerCase().includes(query));
    const total = conversations.reduce((sum,item) => sum + Number(item.unread),0);
    el('unread-total').textContent = total;
    el('unread-total').hidden = !total;
    const signature = JSON.stringify([selected, query, filtered]);
    if (signature === inboxSignature) return;
    inboxSignature = signature;
    const fragment = document.createDocumentFragment();
    if (!filtered.length) {
      const text = document.createElement('p'); text.className = 'messages-hint'; text.textContent = query ? 'No matching conversations.' : 'No conversations yet. Use + to contact a customer.'; fragment.append(text);
    }
    filtered.forEach(item => {
      const button = document.createElement('button'); button.type = 'button'; button.className = `messages-inbox-item${Number(item.customer_id) === selected ? ' is-selected' : ''}`;
      button.setAttribute('aria-pressed', String(Number(item.customer_id) === selected));
      const avatar = customerAvatar(item.name, item.photo_url);
      const content = document.createElement('span'); content.className = 'messages-inbox-content';
      const title = document.createElement('strong'); title.textContent = item.name;
      const preview = document.createElement('span'); preview.className = 'messages-preview'; preview.textContent = item.last_body || 'Start the conversation'; content.append(title,preview);
      const meta = document.createElement('span'); meta.className = 'messages-inbox-meta';
      const stamp = document.createElement('time'); stamp.textContent = item.last_sent_at ? date(item.last_sent_at).toLocaleDateString([], {month:'short',day:'numeric'}) : ''; meta.append(stamp);
      if (Number(item.unread)) { const count = document.createElement('span'); count.className = 'messages-count'; count.textContent = item.unread; count.setAttribute('aria-label', `${item.unread} unread messages`); meta.append(count); }
      button.append(avatar,content,meta); button.addEventListener('click', () => selectCustomer(Number(item.customer_id), item.name, item.photo_url)); fragment.append(button);
    });
    el('inbox').replaceChildren(fragment);
  }
  async function inbox() {
    if (!config.admin) return;
    const result = await api('inbox'); conversations = result.conversations; renderInbox();
  }
  async function acknowledge() {
    if (acknowledging || !selected || !messages.length || document.hidden || !atBottom()) return;
    const latest = messages[messages.length-1].id;
    if (latest <= lastAcknowledged) return;
    const customer = selected;
    const version = generation;
    acknowledging = true;
    try {
      const result = await api('read', {customer_id: customer, last_id: latest}, true);
      if (selected === customer && version === generation) lastAcknowledged = Math.max(lastAcknowledged, latest);
      const entry = conversations.find(item => Number(item.customer_id) === customer);
      // Messages newer than the displayed page can still be unread.
      if (entry) { entry.unread = result.conversation_unread; renderInbox(); }
      window.dispatchEvent(new CustomEvent('caz:messages-status', {detail:{viewerId:config.viewerId, admin:config.admin, unread_total:result.unread_total}}));
    } finally { acknowledging = false; }
  }
  async function thread(initial = false) {
    if (!selected) return;
    const version = generation;
    const current = selected;
    const query = {customer_id: current};
    if (!initial && messages.length) query.after = messages[messages.length-1].id;
    const result = await api('thread', query);
    if (version !== generation || current !== selected) return;
    const bottom = initial || atBottom();
    const oldCount = messages.length;
    const previousRead = lastReadId;
    if (initial) messages = result.messages;
    else { const ids = new Set(messages.map(item => item.id)); messages.push(...result.messages.filter(item => !ids.has(item.id))); }
    messages.sort((a,b) => a.id-b.id);
    lastReadId = result.last_read_id;
    el('thread-name').textContent = config.admin ? result.conversation.name : adminName;
    updateConversationProfile(result.conversation);
    el('thread-subtitle').textContent = config.admin ? 'Customer conversation · shared with your team' : 'Your messages stay here for your next visit';
    if (initial || !query.after) el('load-earlier').hidden = !result.has_earlier;
    if (initial || messages.length !== oldCount || previousRead !== lastReadId) renderMessages();
    if (bottom) history.scrollTop = history.scrollHeight;
    connection(true);
    await acknowledge();
  }
  async function selectCustomer(customer, name, photo = null) {
    if (sending || customer === selected) return;
    if (selected) drafts.set(selected, body.value);
    generation++; selected = customer; messages = []; lastReadId = 0; lastAcknowledged = 0;
    body.value = drafts.get(customer) || ''; body.disabled = false; send.disabled = false; updateCount();
    el('thread-name').textContent = name;
    updateConversationProfile({name, photo_url: photo});
    el('thread-subtitle').textContent = 'Loading conversation…'; el('load-earlier').hidden = true;
    el('recipient-picker').hidden = true;
    showEmpty(true); renderInbox(); error();
    try { await thread(true); } catch (err) { error(err.message); connection(false); }
  }
  function updateCount() { el('character-count').textContent = `${body.value.length.toLocaleString()} / 4,000`; }
  body.addEventListener('input', updateCount);
  body.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); if (!sending && body.value.trim()) el('compose').requestSubmit(); } });
  document.querySelectorAll('[data-message-topic]').forEach(button => button.addEventListener('click', () => { body.value = button.dataset.messageTopic; updateCount(); body.focus(); }));
  el('compose').addEventListener('submit', async event => {
    event.preventDefault();
    const text = body.value.trim();
    if (!selected || sending || !text) return;
    const customer = selected;
    if (!retry || retry.customer !== customer || retry.body !== text) {
      const bytes = new Uint8Array(24); crypto.getRandomValues(bytes);
      retry = {customer, body: text, nonce: Array.from(bytes, byte => byte.toString(16).padStart(2,'0')).join('')};
    }
    sending = true; send.disabled = true; body.disabled = true; send.querySelector('span').textContent = 'Sending…'; error();
    try {
      await api('send', {customer_id: customer, body: text, nonce: retry.nonce}, true);
      retry = null; body.value = ''; drafts.delete(customer); updateCount();
      history.scrollTop = history.scrollHeight;
      await thread(); await inbox(); error();
    } catch (err) { error(err.message); connection(false); }
    finally { sending = false; send.disabled = false; body.disabled = false; send.querySelector('span').textContent = 'Send'; body.focus(); }
  });
  el('load-earlier').addEventListener('click', async () => {
    if (!selected || !messages.length) return;
    const customer = selected; const version = generation; const height = history.scrollHeight;
    el('load-earlier').disabled = true;
    try {
      const result = await api('thread', {customer_id: customer, before: messages[0].id});
      if (customer !== selected || version !== generation) return;
      const ids = new Set(messages.map(item => item.id)); messages.unshift(...result.messages.filter(item => !ids.has(item.id)));
      el('load-earlier').hidden = !result.has_earlier; renderMessages(); history.scrollTop += history.scrollHeight - height;
    } catch (err) { error(err.message); }
    finally { el('load-earlier').disabled = false; }
  });
  let scrollTimer;
  history.addEventListener('scroll', () => { clearTimeout(scrollTimer); scrollTimer = setTimeout(() => acknowledge().catch(() => {}),250); });
  if (config.admin) {
    el('search').addEventListener('input',renderInbox);
    let recipientVersion = 0;
    let recipientSignature = '';
    async function recipients() {
      const version = ++recipientVersion;
      try {
        const result = await api('customers', {q: el('recipient-search').value});
        if (version !== recipientVersion) return;
        const signature = JSON.stringify(result.customers);
        if (signature === recipientSignature) return;
        recipientSignature = signature;
        const fragment = document.createDocumentFragment();
        result.customers.forEach(customer => {
          const button = document.createElement('button'); button.type = 'button'; button.className = 'messages-recipient';
          const name = document.createElement('span'); name.className = 'messages-recipient-name'; name.textContent = customer.name;
          button.append(customerAvatar(customer.name, customer.photo_url), name);
          button.addEventListener('click', () => selectCustomer(Number(customer.id),customer.name,customer.photo_url)); fragment.append(button);
        });
        if (!result.customers.length) { const text = document.createElement('p'); text.className = 'messages-hint'; text.textContent = 'No customers found.'; fragment.append(text); }
        el('recipient-list').replaceChildren(fragment);
      } catch (err) { error(err.message); }
    }
    refreshRecipients = recipients;
    el('new').addEventListener('click', () => { el('recipient-picker').hidden = false; el('recipient-search').focus(); recipients(); });
    el('picker-close').addEventListener('click', () => { el('recipient-picker').hidden = true; });
    let searchTimer;
    el('recipient-search').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(recipients,220); });
  }
  async function poll() {
    if (polling || document.hidden) return;
    polling = true;
    try {
      await inbox(); await thread();
      if (refreshRecipients && !el('recipient-picker').hidden) await refreshRecipients();
      app.querySelectorAll('.messages-avatar').forEach(avatar => {
        const state = avatarStates.get(avatar);
        if (state?.failed) updateAvatar(avatar, state.name, state.value);
      });
      connection(true); if (errorSource === 'poll') error();
    }
    catch (err) { if (!errorSource || errorSource === 'poll') error(err.message, 'poll'); connection(false); }
    finally {
      polling = false;
      if (pollAgain) { pollAgain = false; poll(); }
    }
  }
  function refresh() { if (polling) pollAgain = true; else poll(); }
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  window.addEventListener('online', refresh);
  window.addEventListener('focus', refresh);
  window.addEventListener('pageshow', refresh);
  window.addEventListener('caz:profile-photo-changed', refresh);
  window.addEventListener('storage', event => { if (event.key === 'caz:profile-photo-changed') refresh(); });
  poll();
  setInterval(poll,6000);
})();
