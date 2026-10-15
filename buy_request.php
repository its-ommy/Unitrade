<?php

require_once __DIR__ . '/auth.php';
$current_user = require_verified_user($conn);
$current_user = require_non_admin($current_user);
$buyer_id = (int) $current_user['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid listing ID.");
}

$listing_id = (int) $_GET['id'];

$stmt = $conn->prepare("SELECT listing_id, title, price, seller_id, status FROM listing WHERE listing_id = ?");
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Listing not found.");
}
$listing = $result->fetch_assoc();
$seller_id = (int) $listing['seller_id'];

if ($seller_id === $buyer_id) {
    die("You can't buy your own listing.");
}

if ($listing['status'] !== 'active') {
    die("This listing is no longer available.");
}

// Is there already a pending or approved request from this buyer?
$existing_stmt = $conn->prepare("SELECT request_id, status FROM purchase_request WHERE listing_id = ? AND buyer_id = ? ORDER BY created_at DESC LIMIT 1");
$existing_stmt->bind_param("ii", $listing_id, $buyer_id);
$existing_stmt->execute();
$existing = $existing_stmt->get_result()->fetch_assoc();

if ($existing && in_array($existing['status'], ['pending', 'approved'], true)) {
    header("Location: listing_details.php?id=" . $listing_id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $insert = $conn->prepare("INSERT INTO purchase_request (listing_id, buyer_id, seller_id, status) VALUES (?, ?, ?, 'pending')");
    $insert->bind_param("iii", $listing_id, $buyer_id, $seller_id);

    if ($insert->execute()) {
        header("Location: listing_details.php?id=" . $listing_id . "&requested=1");
        exit;
    } else {
        die("Failed to send purchase request. Please try again.");
    }
}

$page_title = 'Buy Item';
$narrow = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:480px;margin:30px auto 0;">
    <h1>Request to Buy</h1>
    <p class="muted">"<strong><?php echo htmlspecialchars($listing['title']); ?></strong>" — TZS <?php echo number_format($listing['price'], 2); ?></p>
    <p>This sends a purchase request to the seller. They'll review it and either approve or reject it — you'll see the status on your Requests page. Once approved, the item is marked sold and you can arrange payment/handover directly with the seller.</p>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <div class="btn-row">
            <button type="submit" class="btn btn-primary">Send Purchase Request</button>
            <a class="btn btn-secondary" href="listing_details.php?id=<?php echo $listing_id; ?>">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>