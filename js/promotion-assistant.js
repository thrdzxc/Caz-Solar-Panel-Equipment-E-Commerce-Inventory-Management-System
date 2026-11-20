(() => {
    'use strict';
    const root = document.getElementById('caz-assistant');
    if (!root) return;
    const launcher = document.getElementById('caz-assistant-launcher');
    const panel = document.getElementById('caz-assistant-panel');
    const close = document.getElementById('caz-assistant-close');
    const content = document.getElementById('caz-assistant-content');
    const questions = document.getElementById('caz-assistant-questions');
    const history = document.getElementById('caz-assistant-history');
    const status = document.getElementById('caz-assistant-status');
    const handoff = document.getElementById('caz-assistant-handoff');
    const handoffNote = document.getElementById('caz-assistant-handoff-note');
    const notice = document.getElementById('caz-assistant-notice');
    const noticeText = document.getElementById('caz-assistant-notice-text');
    const retry = document.getElementById('caz-assistant-retry');
    let state = null;
    let busy = false;
    let pending = null;
    let retryAction = null;
    let loaded = false;

    function setOpen(open) {
        panel.hidden = !open;
        root.classList.toggle('is-open', open);
        launcher.setAttribute('aria-expanded', String(open));
        if (open) {
            close.focus({ preventScroll: true });
            if (!loaded && !busy) load();
        } else launcher.focus({ preventScroll: true });
    }

    function showNotice(message, action = null) {
        noticeText.textContent = message;
        notice.hidden = !message;
        retryAction = action;
        retry.hidden = !action;
        retry.disabled = busy;
    }

    function refreshControls() {
        const answered = new Set((state?.history || []).map(item => item.questionId));
        questions.querySelectorAll('button').forEach(button => {
            const done = answered.has(button.dataset.questionId);
            button.disabled = busy || Boolean(pending) || !state || state.remaining <= 0 || done;
            button.classList.toggle('is-answered', done);
            if (done) button.setAttribute('aria-label', `${button.dataset.questionText} — answered`);
        });
        retry.disabled = busy;
        panel.setAttribute('aria-busy', String(busy));
        status.textContent = busy
            ? (pending ? 'Preparing your answer…' : 'Loading questions…')
            : state
                ? (state.remaining > 0 ? `${state.remaining} of ${state.limit} answers remaining in this session` : 'You have used your 10 answers. The CAZ admin can help with more questions.')
                : 'Questions are temporarily unavailable.';
    }

    function render(next, revealAnswer = false) {
        if (!next || !Array.isArray(next.questions) || !Array.isArray(next.history)
            || !Number.isInteger(next.remaining) || !Number.isInteger(next.limit)
            || typeof next.csrf !== 'string') throw new Error('invalid-response');
        state = next;
        const oldLength = history.childElementCount;
        questions.replaceChildren();
        next.questions.forEach((item, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'caz-assistant-question';
            button.dataset.questionId = item.id;
            button.dataset.questionText = item.question;
            const number = document.createElement('span');
            number.textContent = String(index + 1).padStart(2, '0');
            number.setAttribute('aria-hidden', 'true');
            const label = document.createElement('span');
            label.textContent = item.question;
            button.append(number, label);
            button.addEventListener('click', () => ask(item.id));
            questions.append(button);
        });
        history.replaceChildren();
        next.history.forEach(item => {
            const exchange = document.createElement('article');
            exchange.className = 'caz-assistant-exchange';
            exchange.tabIndex = -1;
            const asked = document.createElement('p');
            asked.className = 'caz-assistant-asked';
            asked.textContent = item.question;
            const answer = document.createElement('p');
            answer.className = 'caz-assistant-answer';
            const label = document.createElement('span');
            label.className = 'caz-assistant-answer-label';
            label.textContent = 'CAZ · Chatbot';
            answer.append(label, document.createTextNode(item.answer));
            exchange.append(asked, answer);
            history.append(exchange);
        });
        // Accept only a same-origin route even if the endpoint is misconfigured.
        if (next.handoff && typeof next.handoff.url === 'string') {
            try {
                const destination = new URL(next.handoff.url, window.location.href);
                if (destination.origin === window.location.origin && /^https?:$/.test(destination.protocol)) {
                    handoff.href = destination.href;
                    handoff.textContent = `${next.handoff.label} →`;
                    handoffNote.textContent = next.handoff.requiresLogin
                        ? 'A customer login is required to contact the admin. Quick help stays public.'
                        : 'Need personal help? Continue in Messages. Nothing is sent automatically.';
                }
            } catch (_) { /* Keep the server-rendered login link if configuration is invalid. */ }
        }
        refreshControls();
        if (revealAnswer && next.history.length > oldLength && !panel.hidden) {
            const last = history.lastElementChild;
            last.focus({ preventScroll: true });
            content.scrollTop = last.offsetTop - content.offsetTop - 10;
        }
    }

    async function request(options = {}) {
        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 15000);
        try {
            const response = await fetch(root.dataset.api, {
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                ...options
            });
            const data = await response.json();
            return { response, data };
        } finally { window.clearTimeout(timeout); }
    }

    async function load() {
        if (busy) return;
        busy = true;
        showNotice('');
        refreshControls();
        try {
            const { response, data } = await request();
            if (!response.ok || !data.success) throw new Error('unavailable');
            render(data);
            loaded = true;
        } catch (_) {
            showNotice('Quick help could not connect. Please try again. You can still use the admin contact link below.', load);
        } finally {
            busy = false;
            refreshControls();
        }
    }

    function requestId() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();
        const bytes = new Uint8Array(18);
        window.crypto.getRandomValues(bytes);
        return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    }

    function ask(questionId) {
        if (busy || pending || !state || state.remaining <= 0) return;
        pending = { question_id: questionId, request_id: requestId() };
        send();
    }

    async function send() {
        if (busy || !pending || !state) return;
        busy = true;
        showNotice('');
        refreshControls();
        try {
            const { response, data } = await request({
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrf },
                body: JSON.stringify(pending)
            });
            if (response.ok && data.success) {
                render(data, true);
                pending = null;
            } else if (response.status === 429) {
                render(data);
                pending = null;
                showNotice(data.message || 'You have reached the 10-answer limit. Contact the admin for more help.');
            } else if (response.status === 403) {
                pending = null;
                state = null;
                loaded = false;
                showNotice('Your session changed. Reload the questions to continue.', load);
            } else if (response.status >= 400 && response.status < 500) {
                pending = null;
                showNotice(data.message || 'That question could not be answered. Please choose another question.');
            } else throw new Error('unavailable');
        } catch (_) {
            // Keep the same request id: a lost response must not charge another answer.
            showNotice('We could not confirm your answer. Try again to safely recover it without using an extra answer.', send);
        } finally {
            busy = false;
            refreshControls();
        }
    }

    launcher.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false));
    retry.addEventListener('click', () => { if (retryAction && !busy) retryAction(); });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); setOpen(false); }
    });
})();
