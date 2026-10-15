// UniTrade — live notification badges. Polls ajax.php every 15s and
// refreshes every [data-notif] element on the current page (works for
// both the nav-bar pills and the dashboard's corner badges, since both
// share the same data-notif="unread_messages" / "pending_requests"
// markup convention).

(function () {
    var POLL_INTERVAL_MS = 15000;

    function applyCount(key, count) {
        document.querySelectorAll('[data-notif="' + key + '"]').forEach(function (el) {
            el.textContent = count;
            el.style.display = count > 0 ? '' : 'none';
        });
    }

    function refresh() {
        fetch('ajax.php?action=notification_counts', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                applyCount('unread_messages', data.unread_messages || 0);
                applyCount('pending_requests', data.pending_requests || 0);
            })
            .catch(function () { /* silent — a missed poll just tries again later */ });
    }

    refresh();
    setInterval(refresh, POLL_INTERVAL_MS);
})();