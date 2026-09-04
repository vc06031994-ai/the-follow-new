(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof tfpChatSettings === 'undefined') return;

        var items       = Array.prototype.slice.call(document.querySelectorAll('[data-tfp-ticket-item]'));
        var searchInput = document.querySelector('[data-tfp-ticket-search]');
        var tabsWrap    = document.querySelector('[data-tfp-ticket-tabs]');
        var emptyPanel  = document.querySelector('[data-tfp-chat-empty]');
        var threadPanel = document.querySelector('[data-tfp-chat-thread]');
        var titleEl     = document.querySelector('[data-tfp-chat-title]');
        var subtitleEl  = document.querySelector('[data-tfp-chat-subtitle]');
        var statusEl    = document.querySelector('[data-tfp-chat-status]');
        var messagesEl  = document.querySelector('[data-tfp-chat-messages]');
        var replyForm   = document.querySelector('[data-tfp-chat-reply]');
        var replyInput  = document.querySelector('[data-tfp-chat-input]');
        var resolveBtn  = document.querySelector('[data-tfp-mark-resolved]');
        var newTicketBtn    = document.querySelector('[data-tfp-new-ticket]');
        var newTicketModal  = document.querySelector('[data-tfp-new-ticket-modal]');
        var newTicketForm   = document.querySelector('[data-tfp-new-ticket-form]');
        var modalCloseBtns  = document.querySelectorAll('[data-tfp-modal-close]');

        var state = {
            activeTicketId: null,
            lastMessageId: 0,
            pollTimer: null,
        };

        function ajax(action, data) {
            var body = new URLSearchParams(Object.assign({
                action: action,
                tfp_chat_nonce: tfpChatSettings.nonce,
            }, data));

            return fetch(tfpChatSettings.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            }).then(function (res) { return res.json(); });
        }

        function renderMessage(msg) {
            var wrap = document.createElement('div');
            wrap.className = 'tfp-dash-msg ' + (msg.is_mine ? 'tfp-dash-msg--mine' : 'tfp-dash-msg--theirs');
            wrap.dataset.id = msg.id;

            var bubble = document.createElement('div');
            bubble.className = 'tfp-dash-msg__bubble';
            bubble.textContent = msg.message;

            var time = document.createElement('div');
            time.className = 'tfp-dash-msg__time';
            time.textContent = (msg.is_mine ? '' : msg.sender_name + ' · ') + msg.created_at;

            wrap.appendChild(bubble);
            wrap.appendChild(time);
            return wrap;
        }

        function scrollMessagesToBottom() {
            if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        function setStatusBadge(status) {
            if (!statusEl) return;
            var labels = { open: 'Open', pending: 'Pending Reply', resolved: 'Resolved' };
            statusEl.textContent = labels[status] || status;
            statusEl.dataset.status = status;
        }

        function stopPolling() {
            if (state.pollTimer) {
                clearInterval(state.pollTimer);
                state.pollTimer = null;
            }
        }

        function poll() {
            if (!state.activeTicketId) return;
            ajax('tfp_chat_get_messages', {
                ticket_id: state.activeTicketId,
                after_id: state.lastMessageId,
            }).then(function (res) {
                if (!res.success) return;
                (res.messages || []).forEach(function (msg) {
                    messagesEl.appendChild(renderMessage(msg));
                    state.lastMessageId = Math.max(state.lastMessageId, msg.id);
                });
                if (res.messages && res.messages.length) scrollMessagesToBottom();
                setStatusBadge(res.status);
            });
        }

        function openTicket(ticketId, name, category) {
            stopPolling();
            state.activeTicketId = ticketId;
            state.lastMessageId = 0;

            items.forEach(function (el) {
                el.classList.toggle('is-active', el.getAttribute('data-ticket-id') === String(ticketId));
            });

            if (emptyPanel) emptyPanel.hidden = true;
            if (threadPanel) threadPanel.hidden = false;
            if (titleEl) titleEl.textContent = name || '';
            if (subtitleEl) subtitleEl.textContent = category || '';
            messagesEl.innerHTML = '';

            ajax('tfp_chat_get_messages', { ticket_id: ticketId, after_id: 0 }).then(function (res) {
                if (!res.success) return;
                (res.messages || []).forEach(function (msg) {
                    messagesEl.appendChild(renderMessage(msg));
                    state.lastMessageId = Math.max(state.lastMessageId, msg.id);
                });
                scrollMessagesToBottom();
                setStatusBadge(res.status);
                state.pollTimer = setInterval(poll, tfpChatSettings.pollInterval || 4000);
            });
        }

        items.forEach(function (item) {
            item.addEventListener('click', function () {
                var name = item.querySelector('.tfp-dash-ticketlist__name');
                var cat  = item.querySelector('.tfp-dash-ticketlist__category');
                openTicket(
                    item.getAttribute('data-ticket-id'),
                    name ? name.textContent : '',
                    cat ? cat.textContent : ''
                );
            });
        });

        // Search filter
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                var term = searchInput.value.trim().toLowerCase();
                items.forEach(function (item) {
                    var haystack = item.getAttribute('data-search') || '';
                    item.style.display = haystack.indexOf(term) !== -1 ? '' : 'none';
                });
            });
        }

        // Tabs filter (All / Open / Access / Technical)
        if (tabsWrap) {
            tabsWrap.addEventListener('click', function (e) {
                var btn = e.target.closest('button[data-filter]');
                if (!btn) return;

                tabsWrap.querySelectorAll('button').forEach(function (b) { b.classList.remove('is-active'); });
                btn.classList.add('is-active');

                var filter = btn.getAttribute('data-filter');
                items.forEach(function (item) {
                    if (filter === 'all') {
                        item.style.display = '';
                        return;
                    }
                    var matches = item.getAttribute('data-status') === filter || item.getAttribute('data-category') === filter;
                    item.style.display = matches ? '' : 'none';
                });
            });
        }

        // Reply form
        if (replyForm) {
            replyForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var message = replyInput.value.trim();
                if (!message || !state.activeTicketId) return;

                replyInput.value = '';
                replyInput.disabled = true;

                ajax('tfp_chat_send_message', {
                    ticket_id: state.activeTicketId,
                    message: message,
                }).then(function (res) {
                    replyInput.disabled = false;
                    replyInput.focus();
                    if (!res.success) return;
                    messagesEl.appendChild(renderMessage(res.message));
                    state.lastMessageId = Math.max(state.lastMessageId, res.message.id);
                    scrollMessagesToBottom();
                    setStatusBadge(res.status);
                });
            });
        }

        // Mark resolved
        if (resolveBtn) {
            resolveBtn.addEventListener('click', function () {
                if (!state.activeTicketId) return;
                ajax('tfp_chat_mark_resolved', { ticket_id: state.activeTicketId }).then(function (res) {
                    if (res.success) setStatusBadge('resolved');
                });
            });
        }

        // New ticket modal
        if (newTicketBtn && newTicketModal) {
            newTicketBtn.addEventListener('click', function () {
                newTicketModal.hidden = false;
            });
        }

        modalCloseBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                newTicketModal.hidden = true;
            });
        });

        if (newTicketForm) {
            newTicketForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var formData = new FormData(newTicketForm);

                ajax('tfp_chat_create_ticket', {
                    subject: formData.get('subject'),
                    category: formData.get('category'),
                    message: formData.get('message'),
                }).then(function (res) {
                    if (!res.success) {
                        window.alert(res.message || 'Could not create ticket.');
                        return;
                    }
                    // Simplest reliable way to show the new ticket in the
                    // list with correct server-rendered markup.
                    window.location.reload();
                });
            });
        }
    });
})();
