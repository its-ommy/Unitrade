<?php

require_once __DIR__ . '/auth.php';

$current_user = require_verified_user($conn);
$current_user = require_non_admin($current_user);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid listing ID.");
}

$listing_id = (int) $_GET['id'];

$sql = "SELECT l.listing_id, l.title, l.seller_id, u.full_name AS seller_name
        FROM listing l
        JOIN user u ON l.seller_id = u.user_id
        WHERE l.listing_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Listing not found.");
}
$listing = $result->fetch_assoc();

if ((int) $listing['seller_id'] === (int) $current_user['user_id']) {
    die("You can't message yourself about your own listing.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $message_body = trim($_POST['message_body']);

    if ($message_body === '') {
        die("Message cannot be empty.");
    }

    $sender_id = (int) $current_user['user_id'];
    $receiver_id = $listing['seller_id'];

    $sql = "INSERT INTO message (sender_id, receiver_id, listing_id, message_body) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $sender_id, $receiver_id, $listing_id, $message_body);

    if ($stmt->execute()) {
        header("Location: conversation.php?listing_id=" . $listing_id . "&user_id=" . $receiver_id);
        exit;
    } else {
        die("Failed to send message.");
    }
}

$page_title = 'Message Seller';
$narrow = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:520px;margin:10px auto 0;">
    <h1>Message Seller</h1>

    <div class="info-row"><span class="label">Listing</span><span class="value"><?php echo htmlspecialchars($listing['title']); ?></span></div>
    <div class="info-row"><span class="label">Seller</span><span class="value"><?php echo htmlspecialchars($listing['seller_name']); ?></span></div>

    <form method="POST" class="mt-16">
        <?php echo csrf_field(); ?>
        <div class="field">
            <label>Your Message</label>
            <textarea name="message_body" rows="6" placeholder="Write your message here..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send Message</button>
    </form>

    <p class="text-center mt-16"><a href="listing_details.php?id=<?php echo $listing['listing_id']; ?>">← Back to Listing</a></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>