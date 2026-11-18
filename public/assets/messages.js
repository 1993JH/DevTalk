// DevTalk Messages (beta) — JavaScript client for the DevTalk JSON API.
//
// After signing in, the API returns a JSON Web Token (JWT). The client keeps it
// in localStorage and sends it on every call in an "Authorization: Bearer" header.

(() => {
    'use strict';

    const TOKEN_KEY = 'devtalk_token';
    const $ = (id) => document.getElementById(id);

    async function api(path, options = {}) {
        const headers = { Accept: 'application/json', ...(options.headers || {}) };
        const token = localStorage.getItem(TOKEN_KEY);
        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }
        const response = await fetch('/api' + path, { ...options, headers });
        const data = await response.json().catch(() => ({}));
        if (response.status === 401 && token) {
            signOut();   // token expired or invalid
        }
        if (!response.ok) {
            throw new Error(data.error || 'Request failed (' + response.status + ')');
        }
        return data;
    }

    function show(id, visible) {
        $(id).classList.toggle('d-none', !visible);
    }

    function signOut() {
        localStorage.removeItem(TOKEN_KEY);
        show('inbox', false);
        show('login-card', true);
    }

    async function signIn(event) {
        event.preventDefault();
        const form = event.target;
        show('login-error', false);
        try {
            const data = await api('/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username: form.username.value, password: form.password.value }),
            });
            localStorage.setItem(TOKEN_KEY, data.token);
            form.reset();
            await loadInbox();
        } catch (err) {
            $('login-error').textContent = err.message;
            show('login-error', true);
        }
    }

    async function openMessage(id) {
        const m = await api('/messages/' + encodeURIComponent(id));
        $('msg-subject').textContent = m.subject;
        $('msg-from').textContent = m.from;
        $('msg-to').textContent = m.to;
        $('msg-date').textContent = m.created_at;
        $('msg-body').textContent = m.body;   // textContent, never innerHTML
        show('message-empty', false);
        show('message-view', true);
    }

    async function loadInbox() {
        const me = await api('/me');
        $('me-name').textContent = me.username;
        $('me-role').textContent = me.role;

        const { messages } = await api('/messages');
        const list = $('message-list');
        list.replaceChildren();
        if (messages.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-body-secondary';
            empty.textContent = 'No messages yet.';
            list.append(empty);
        }
        for (const m of messages) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action';
            const subject = document.createElement('div');
            subject.className = 'fw-semibold';
            subject.textContent = m.subject;
            const meta = document.createElement('div');
            meta.className = 'small text-body-secondary';
            meta.textContent = m.from + ' → ' + m.to;
            item.append(subject, meta);
            item.addEventListener('click', () => openMessage(m.id));
            list.append(item);
        }

        show('login-card', false);
        show('inbox', true);
    }

    document.addEventListener('DOMContentLoaded', () => {
        $('login-form').addEventListener('submit', signIn);
        $('logout').addEventListener('click', signOut);
        if (localStorage.getItem(TOKEN_KEY)) {
            loadInbox().catch(signOut);
        } else {
            show('login-card', true);
        }
    });
})();
