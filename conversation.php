<?php

require_once __DIR__ . '/auth.php';

$current_user = require_verified_user($conn);
$current_user = require_non_admin($current_user);

if (
    !isset($_GET['listing_id']) ||
    !isset($_GET['user_id']) ||
    !is_numeric($_GET['listing_id']) ||
    !is_numeric($_GET['user_id'])
) {
    die("Invalid conversation details.");
}

$listing_id = (int) $_GET['listing_id'];
$other_user_id = (int) $_GET['user_id'];
$current_user_id = (int) $current_user['user_id'];

$sql = "SELECT title FROM listing WHERE listing_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Listing not found.");
}
$listing = $result->fetch_assoc();

$sql = "SELECT full_name FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $other_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("User not found.");
}
$other_user = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $message_body = trim($_POST['message_body']);

    if ($message_body === '') {
        die("Message cannot be empty.");
    }

    $sql = "INSERT INTO message (sender_id, receiver_id, listing_id, message_body) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $current_user_id, $other_user_id, $listing_id, $message_body);

    if ($stmt->execute()) {
        header("Location: conversation.php?listing_id=" . $listing_id . "&user_id=" . $other_user_id);
        exit;
    } else {
        die("Failed to send message.");
    }
}

$sql = "SELECT
            m.message_id, m.sender_id, m.receiver_id, m.message_body, m.sent_at,
            u.full_name AS sender_name
        FROM message m
        JOIN user u ON m.sender_id = u.user_id
        WHERE m.listing_id = ?
        AND (
            (m.sender_id = ? AND m.receiver_id = ?)
            OR (m.sender_id = ? AND m.receiver_id = ?)
        )
        ORDER BY m.sent_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $listing_id, $current_user_id, $other_user_id, $other_user_id, $current_user_id);
$stmt->execute();
$messages = $stmt->get_result();

// Highest message id currently on the page — messaging.js polls for
// anything newer than this so it doesn't have to re-fetch everything.
$last_message_id = 0;
foreach ($messages->fetch_all(MYSQLI_ASSOC) as $row) {
    if ((int) $row['message_id'] > $last_message_id) {
        $last_message_id = (int) $row['message_id'];
    }
}
$messages->data_seek(0);

// Mark incoming messages in this thread as read now that the user has
// actually opened it (powers the unread-count badge in the nav bar).
$read_stmt = $conn->prepare("UPDATE message SET is_read = 1 WHERE listing_id = ? AND sender_id = ? AND receiver_id = ? AND is_read = 0");
$read_stmt->bind_param("iii", $listing_id, $other_user_id, $current_user_id);
$read_stmt->execute();

$page_title = 'Conversation';
$active_nav = 'messages';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .chat-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); }
    .chat-head { background: var(--brand); color: #fff; padding: 16px 20px; }
    .chat-head h1 { color: #fff; margin: 0 0 4px; font-size: 20px; }
    .chat-head p { margin: 2px 0; font-size: 13px; opacity: 0.9; }
    .chat-body { padding: 18px; min-height: 320px; max-height: 480px; overflow-y: auto; }
    .msg-row { display: flex; margin-bottom: 12px; }
    .msg-row.mine { justify-content: flex-end; }
    .bubble { max-width: 70%; padding: 10px 14px; border-radius: 16px; font-size: 14px; }
    .msg-row.other .bubble { background: var(--bg); border-bottom-left-radius: 4px; }
    .msg-row.mine .bubble { background: var(--brand); color: #fff; border-bottom-right-radius: 4px; }
    .bubble .sender { font-size: 12px; font-weight: 700; margin-bottom: 3px; }
    .bubble .time { display: block; font-size: 10.5px; margin-top: 5px; opacity: 0.7; }
    .chat-form { padding: 16px; border-top: 1px solid var(--border); }
</style>

<div class="chat-card">
    <div class="chat-head">
        <h1>Conversation</h1>
        <p>Listing: <strong><?php echo htmlspecialchars($listing['title']); ?></strong></p>
        <p>With: <strong><?php echo htmlspecialchars($other_user['full_name']); ?></strong></p>
    </div>

    <div class="chat-body" id="chatBody"
         data-listing-id="<?php echo $listing_id; ?>"
         data-other-user-id="<?php echo $other_user_id; ?>"
         data-current-user-id="<?php echo $current_user_id; ?>"
         data-last-message-id="<?php echo $last_message_id; ?>">
        <?php if ($messages->num_rows > 0): ?>
            <?php while ($message = $messages->fetch_assoc()): ?>
                <?php $is_mine = ($message['sender_id'] == $current_user_id); ?>
                <div class="msg-row <?php echo $is_mine ? 'mine' : 'other'; ?>">
                    <div class="bubble">
                        <div class="sender"><?php echo htmlspecialchars($message['sender_name']); ?></div>
                        <div><?php echo htmlspecialchars($message['message_body']); ?></div>
                        <small class="time"><?php echo htmlspecialchars($message['sent_at']); ?></small>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="muted">No messages yet. Say hello!</p>
        <?php endif; ?>
    </div>

    <div class="chat-form">
        <form method="POST" id="messageForm">
            <?php echo csrf_field(); ?>
            <textarea name="message_body" id="messageBody" rows="3" placeholder="Type your message here..." required></textarea>
            <button type="submit" class="btn btn-primary">Send Message</button>
        </form>
    </div>
</div>

<p class="mt-16"><a href="messages.php">← Back to Messages</a></p>

<script src="assets/messaging.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>