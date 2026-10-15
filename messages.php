<?php
require_once __DIR__ . '/auth.php';
require_login();
$receiver_id = (int) $_SESSION['user_id'];

// Latest message per conversation (per listing + other participant)
$sql = "SELECT
            m.message_id, m.sender_id, m.receiver_id, m.listing_id,
            m.message_body, m.sent_at, m.is_read,
            l.title AS listing_title,
            u.full_name AS other_name
        FROM message m
        JOIN listing l ON m.listing_id = l.listing_id
        JOIN user u ON u.user_id = IF(m.sender_id = ?, m.receiver_id, m.sender_id)
        WHERE m.sender_id = ? OR m.receiver_id = ?
        ORDER BY m.sent_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $receiver_id, $receiver_id, $receiver_id);
$stmt->execute();
$result = $stmt->get_result();

// De-duplicate into one row per (listing_id, other_user) conversation, keeping the latest
$conversations = [];
while ($row = $result->fetch_assoc()) {
    $other_id = ($row['sender_id'] == $receiver_id) ? $row['receiver_id'] : $row['sender_id'];
    $key = $row['listing_id'] . '-' . $other_id;
    if (!isset($conversations[$key])) {
        $row['other_id'] = $other_id;
        $conversations[$key] = $row;
    }
}

$page_title = 'Messages';
$active_nav = 'messages';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>My Messages</h1>
</div>

<?php if (count($conversations) > 0): ?>

    <?php foreach ($conversations as $c): ?>
        <?php $unread = ((int) $c['receiver_id'] === $receiver_id && (int) $c['is_read'] === 0); ?>
        <a class="card" style="display:block;<?php echo $unread ? ' border-left:3px solid var(--brand);' : ''; ?>" href="conversation.php?listing_id=<?php echo $c['listing_id']; ?>&user_id=<?php echo $c['other_id']; ?>">
            <div class="flex-between">
                <strong><?php echo htmlspecialchars($c['other_name']); ?></strong>
                <span>
                    <?php if ($unread): ?><span class="badge badge-danger">New</span><?php endif; ?>
                    <span class="muted" style="font-size:12px;"><?php echo htmlspecialchars($c['sent_at']); ?></span>
                </span>
            </div>
            <p class="listing-meta mt-8" style="margin-bottom:2px;">Re: <?php echo htmlspecialchars($c['listing_title']); ?></p>
            <p class="mt-0" style="color:var(--text-muted);<?php echo $unread ? ' font-weight:600;' : ''; ?>"><?php echo htmlspecialchars(mb_strimwidth($c['message_body'], 0, 90, '…')); ?></p>
        </a>
    <?php endforeach; ?>

<?php else: ?>
    <div class="empty-state card">
        <div class="icon">💬</div>
        <p>You have no messages yet.</p>
        <a class="btn btn-primary" href="browse_listings.php">Browse listings</a>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>