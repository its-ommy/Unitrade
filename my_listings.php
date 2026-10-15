<?php

require_once __DIR__ . '/auth.php';

require_login();
$user_id = (int) $_SESSION['user_id'];

$sql = "SELECT
            l.listing_id, l.title, l.description, l.price, l.condition_type,
            l.status, l.posted_at, c.name AS category_name,
            (SELECT photo_url FROM listing_photo lp WHERE lp.listing_id = l.listing_id ORDER BY lp.photo_id ASC LIMIT 1) AS thumb,
            (SELECT COUNT(*) FROM report r WHERE r.listing_id = l.listing_id AND r.status = 'pending') AS pending_reports,
            (SELECT COUNT(*) FROM purchase_request pr WHERE pr.listing_id = l.listing_id AND pr.status = 'pending') AS pending_requests
        FROM listing l
        JOIN category c ON l.category_id = c.category_id
        WHERE l.seller_id = ?
        ORDER BY l.posted_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$page_title = 'My Listings';
$active_nav = 'my_listings';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>My Listings</h1>
    <a href="create_listing.php" class="btn btn-primary">+ New Listing</a>
</div>

<?php if ($result->num_rows > 0): ?>

    <?php while ($listing = $result->fetch_assoc()): ?>
        <div class="card">
            <div class="flex-between">
                <h2 class="mt-0"><?php echo htmlspecialchars($listing['title']); ?></h2>
                <div>
                    <?php if ($listing['status'] === 'active'): ?>
                        <span class="badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="badge badge-muted">Sold</span>
                    <?php endif; ?>
                    <?php if ($listing['pending_reports'] > 0): ?>
                        <span class="badge badge-danger">Under Review</span>
                    <?php endif; ?>
                    <?php if ($listing['pending_requests'] > 0): ?>
                        <span class="badge badge-warn"><?php echo (int) $listing['pending_requests']; ?> Buy Request<?php echo $listing['pending_requests'] == 1 ? '' : 's'; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <p class="listing-meta"><?php echo htmlspecialchars($listing['category_name']); ?> · <?php echo htmlspecialchars(ucfirst($listing['condition_type'])); ?> · Posted <?php echo htmlspecialchars($listing['posted_at']); ?></p>
            <p><?php echo htmlspecialchars($listing['description']); ?></p>
            <p class="listing-price">TZS <?php echo number_format($listing['price'], 2); ?></p>

            <?php if ($listing['pending_reports'] > 0): ?>
                <div class="alert alert-error">This listing has been reported and is hidden from public browsing pending admin review.</div>
            <?php endif; ?>

            <div class="btn-row">
                <a class="btn btn-secondary btn-sm" href="listing_details.php?id=<?php echo $listing['listing_id']; ?>">View</a>
                <a class="btn btn-secondary btn-sm" href="edit_listing.php?id=<?php echo $listing['listing_id']; ?>">Edit</a>
                <?php if ($listing['pending_requests'] > 0): ?>
                    <a class="btn btn-primary btn-sm" href="requests.php">View Requests</a>
                <?php endif; ?>
                <a class="btn btn-danger btn-sm" href="delete_listing.php?id=<?php echo $listing['listing_id']; ?>">Delete</a>
            </div>
        </div>
    <?php endwhile; ?>

<?php else: ?>
    <div class="empty-state card">
        <div class="icon">📦</div>
        <p>You haven't created any listings yet.</p>
        <a class="btn btn-primary" href="create_listing.php">Create your first listing</a>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>