// UniTrade — conversation.php live messaging. Sends messages via fetch()
// instead of a full-page POST, and polls for new incoming messages every
// few seconds. Reads its config from data-* attributes on #chatBody so no
// PHP variables need to be inlined into this file.

(function () {
    var chatBody = document.getElementById('chatBody');
    var form = document.getElementById('messageForm');
    var textarea = document.getElementById('messageBody');
    if (!chatBody || !form || !textarea) return;

    var listingId = chatBody.getAttribute('data-listing-id');
    var otherUserId = chatBody.getAttribute('data-other-user-id');
    var currentUserId = parseInt(chatBody.getAttribute('data-current-user-id'), 10);
    var lastMessageId = parseInt(chatBody.getAttribute('data-last-message-id'), 10) || 0;
    var POLL_INTERVAL_MS = 4000;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function renderMessage(msg) {
        var row = document.createElement('div');
        row.className = 'msg-row ' + (msg.is_mine ? 'mine' : 'other');
        row.innerHTML =
            '<div class="bubble">' +
                '<div class="sender">' + escapeHtml(msg.sender_name) + '</div>' +
                '<div>' + escapeHtml(msg.message_body) + '</div>' +
                '<small class="time">' + escapeHtml(msg.sent_at) + '</small>' +
            '</div>';
        var emptyState = chatBody.querySelector('.muted');
        if (emptyState) emptyState.remove();
        chatBody.appendChild(row);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function sendMessage(e) {
        e.preventDefault();
        var body = textarea.value.trim();
        if (body === '') return;

        var submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        var params = new URLSearchParams();
        params.set('action', 'send_message');
        params.set('listing_id', listingId);
        params.set('receiver_id', otherUserId);
        params.set('message_body', body);

        fetch('ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': csrfToken()
            },
            credentials: 'same-origin',
            body: params.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                submitBtn.disabled = false;
                if (data.error) {
                    alert(data.error);
                    return;
                }
                renderMessage(data.message);
                lastMessageId = data.message.id;
                textarea.value = '';
                textarea.focus();
            })
            .catch(function () {
                submitBtn.disabled = false;
                alert('Could not send the message — check your connection and try again.');
            });
    }

    function pollNewMessages() {
        var url = 'ajax.php?action=get_messages&listing_id=' + encodeURIComponent(listingId) +
                   '&other_user_id=' + encodeURIComponent(otherUserId) +
                   '&after_id=' + lastMessageId;

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.messages || data.messages.length === 0) return;
                data.messages.forEach(function (msg) {
                    renderMessage(msg);
                    if (msg.id > lastMessageId) lastMessageId = msg.id;
                });
            })
            .catch(function () { /* silent — next poll retries */ });
    }

    form.addEventListener('submit', sendMessage);
    textarea.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            sendMessage(e);
        }
    });

    setInterval(pollNewMessages, POLL_INTERVAL_MS);
})();