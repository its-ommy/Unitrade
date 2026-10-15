// UniTrade — requests.php live approve/reject. Intercepts the
// approve/reject buttons, calls ajax.php, and updates the affected
// request card(s) in place — including any sibling requests on the same
// listing that get auto-rejected when one is approved.

(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function setDecided(card, status) {
        card.setAttribute('data-status', status);

        var badge = card.querySelector('.js-request-badge');
        if (badge) {
            badge.className = 'badge js-request-badge ' + (status === 'approved' ? 'badge-success' : 'badge-muted');
            badge.textContent = status === 'approved' ? 'Approved' : 'Rejected';
        }

        var actions = card.querySelector('.js-request-actions');
        if (actions) actions.remove();
    }

    function handleClick(e) {
        var btn = e.target.closest('.js-request-action');
        if (!btn) return;
        e.preventDefault();

        var requestId = btn.getAttribute('data-request-id');
        var action = btn.getAttribute('data-action'); // 'approve' or 'reject'
        var card = document.querySelector('.js-request-card[data-request-id="' + requestId + '"]');

        if (action === 'reject' && !confirm('Reject this purchase request?')) return;

        var buttons = btn.closest('.js-request-actions').querySelectorAll('button');
        buttons.forEach(function (b) { b.disabled = true; });

        var params = new URLSearchParams();
        params.set('action', action === 'approve' ? 'approve_request' : 'reject_request');
        params.set('request_id', requestId);

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
                if (data.error) {
                    alert(data.error);
                    buttons.forEach(function (b) { b.disabled = false; });
                    return;
                }

                if (card) setDecided(card, data.status);

                // Approving one request auto-rejects any other pending
                // requests on the same listing — reflect that too.
                if (data.auto_rejected && data.auto_rejected.length > 0) {
                    data.auto_rejected.forEach(function (siblingId) {
                        var siblingCard = document.querySelector('.js-request-card[data-request-id="' + siblingId + '"]');
                        if (siblingCard) setDecided(siblingCard, 'rejected');
                    });
                }
            })
            .catch(function () {
                alert('Something went wrong — check your connection and try again.');
                buttons.forEach(function (b) { b.disabled = false; });
            });
    }

    document.addEventListener('click', handleClick);
})();