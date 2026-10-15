<?php

require_once __DIR__ . '/auth.php';
$current_user = require_verified_user($conn);
$current_user = require_non_admin($current_user);
$user_id = (int) $current_user['user_id'];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $request_id = (int) $_POST['request_id'];
    $action = $_POST['action'];

    // Only the seller on this request may approve/reject it
    $rstmt = $conn->prepare("SELECT request_id, listing_id, buyer_id, seller_id, status FROM purchase_request WHERE request_id = ? AND seller_id = ?");
    $rstmt->bind_param("ii", $request_id, $user_id);
    $rstmt->execute();
    $rresult = $rstmt->get_result();

    if ($rresult->num_rows === 1) {
        $req = $rresult->fetch_assoc();

        if ($req['status'] === 'pending') {

            if ($action === 'approve') {

                $conn->begin_transaction();
                try {
                    $upd = $conn->prepare("UPDATE purchase_request SET status = 'approved' WHERE request_id = ?");
                    $upd->bind_param("i", $request_id);
                    $upd->execute();

                    // Mark the listing sold
                    $updListing = $conn->prepare("UPDATE listing SET status = 'sold' WHERE listing_id = ?");
                    $updListing->bind_param("i", $req['listing_id']);
                    $updListing->execute();

                    // Auto-reject any other pending requests on this listing
                    $rejectOthers = $conn->prepare("UPDATE purchase_request SET status = 'rejected' WHERE listing_id = ? AND request_id != ? AND status = 'pending'");
                    $rejectOthers->bind_param("ii", $req['listing_id'], $request_id);
                    $rejectOthers->execute();

                    $conn->commit();
                    $message = "Request approved — the listing is now marked sold.";
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Something went wrong approving this request.";
                }

            } elseif ($action === 'reject') {
                $upd = $conn->prepare("UPDATE purchase_request SET status = 'rejected' WHERE request_id = ?");
                $upd->bind_param("i", $request_id);
                $upd->execute();
                $message = "Request rejected.";
            }
        }
    }
}

// Requests received as a seller
$received_stmt = $conn->prepare(
    "SELECT pr.request_id, pr.status, pr.created_at, l.title AS listing_title, l.price,
            u.full_name AS buyer_name
     FROM purchase_request pr
     JOIN listing l ON pr.listing_id = l.listing_id
     JOIN user u ON pr.buyer_id = u.user_id
     WHERE pr.seller_id = ?
     ORDER BY (pr.status = 'pending') DESC, pr.created_at DESC"
);
$received_stmt->bind_param("i", $user_id);
$received_stmt->execute();
$received = $received_stmt->get_result();

// Requests sent as a buyer
$sent_stmt = $conn->prepare(
    "SELECT pr.request_id, pr.listing_id, pr.status, pr.created_at, l.title AS listing_title, l.price, l.status AS listing_status,
            u.full_name AS seller_name,
            (SELECT COUNT(*) FROM rating rt WHERE rt.listing_id = pr.listing_id AND rt.buyer_id = pr.buyer_id) AS already_rated
     FROM purchase_request pr
     JOIN listing l ON pr.listing_id = l.listing_id
     JOIN user u ON pr.seller_id = u.user_id
     WHERE pr.buyer_id = ?
     ORDER BY (pr.status = 'pending') DESC, pr.created_at DESC"
);
$sent_stmt->bind_param("i", $user_id);
$sent_stmt->execute();
$sent = $sent_stmt->get_result();

$page_title = 'Purchase Requests';
$active_nav = 'requests';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Purchase Requests</h1>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<h3>Requests Received (as Seller)</h3>
<?php if ($received->num_rows > 0): ?>
    <?php while ($r = $received->fetch_assoc()): ?>
        <div class="card js-request-card" data-request-id="<?php echo $r['request_id']; ?>" data-status="<?php echo $r['status']; ?>">
            <div class="flex-between">
                <h2 class="mt-0"><?php echo htmlspecialchars($r['listing_title']); ?></h2>
                <?php if ($r['status'] === 'pending'): ?>
                    <span class="badge badge-warn js-request-badge">Pending</span>
                <?php elseif ($r['status'] === 'approved'): ?>
                    <span class="badge badge-success js-request-badge">Approved</span>
                <?php else: ?>
                    <span class="badge badge-muted js-request-badge">Rejected</span>
                <?php endif; ?>
            </div>
            <p class="listing-meta">Buyer: <?php echo htmlspecialchars($r['buyer_name']); ?> · TZS <?php echo number_format($r['price'], 2); ?> · <?php echo htmlspecialchars($r['created_at']); ?></p>

            <?php if ($r['status'] === 'pending'): ?>
                <div class="btn-row js-request-actions">
                    <button type="button" class="btn btn-primary btn-sm js-request-action" data-request-id="<?php echo $r['request_id']; ?>" data-action="approve">Approve</button>
                    <button type="button" class="btn btn-danger btn-sm js-request-action" data-request-id="<?php echo $r['request_id']; ?>" data-action="reject">Reject</button>
                </div>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p class="muted">No purchase requests received yet.</p>
<?php endif; ?>

<h3>Requests Sent (as Buyer)</h3>
<?php if ($sent->num_rows > 0): ?>
    <?php while ($s = $sent->fetch_assoc()): ?>
        <div class="card">
            <div class="flex-between">
                <h2 class="mt-0"><?php echo htmlspecialchars($s['listing_title']); ?></h2>
                <?php if ($s['status'] === 'pending'): ?>
                    <span class="badge badge-warn">Pending</span>
                <?php elseif ($s['status'] === 'approved'): ?>
                    <span class="badge badge-success">Approved</span>
                <?php else: ?>
                    <span class="badge badge-muted">Rejected</span>
                <?php endif; ?>
            </div>
            <p class="listing-meta">Seller: <?php echo htmlspecialchars($s['seller_name']); ?> · TZS <?php echo number_format($s['price'], 2); ?> · <?php echo htmlspecialchars($s['created_at']); ?></p>

            <div class="btn-row">
                <a class="btn btn-secondary btn-sm" href="listing_details.php?id=<?php echo $s['listing_id']; ?>">View Listing</a>
                <?php if ($s['status'] === 'approved' && $s['listing_status'] === 'sold' && $s['already_rated'] == 0): ?>
                    <a class="btn btn-primary btn-sm" href="rate_seller.php?listing_id=<?php echo $s['listing_id']; ?>">Rate Seller</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p class="muted">You haven't sent any purchase requests yet.</p>
<?php endif; ?>

<script src="assets/requests.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>