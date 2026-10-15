<?php
require_once __DIR__ . '/auth.php';
require_login();
 
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid listing ID.");
}
 
$listing_id = (int) $_GET['id'];
$viewer_id = (int) $_SESSION['user_id'];
$is_verified = isset($_SESSION['verification_status']) && $_SESSION['verification_status'] === 'verified';
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

$sql = "SELECT
            l.listing_id, l.seller_id, l.title, l.description, l.price,
            l.condition_type, l.status, l.posted_at,
            u.full_name AS seller_name,
            c.name AS category_name
        FROM listing l
        JOIN user u ON l.seller_id = u.user_id
        JOIN category c ON l.category_id = c.category_id
        WHERE l.listing_id = ?";
 
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $listing_id);
$stmt->execute();
$result = $stmt->get_result();
 
if ($result->num_rows !== 1) {
    die("Listing not found.");
}
$listing = $result->fetch_assoc();
 
$photo_stmt = $conn->prepare("SELECT photo_url FROM listing_photo WHERE listing_id = ? ORDER BY photo_id ASC");
$photo_stmt->bind_param("i", $listing_id);
$photo_stmt->execute();
$photos = $photo_stmt->get_result();
 
$rating_stmt = $conn->prepare("SELECT AVG(score) AS avg_score, COUNT(*) AS total FROM rating WHERE seller_id = ?");
$rating_stmt->bind_param("i", $listing['seller_id']);
$rating_stmt->execute();
$rating_summary = $rating_stmt->get_result()->fetch_assoc();
 
$is_owner = $viewer_id === (int) $listing['seller_id'];
 
$my_request = null;
if (!$is_owner) {
    $req_stmt = $conn->prepare("SELECT request_id, status FROM purchase_request WHERE listing_id = ? AND buyer_id = ? ORDER BY created_at DESC LIMIT 1");
    $req_stmt->bind_param("ii", $listing_id, $viewer_id);
    $req_stmt->execute();
    $my_request = $req_stmt->get_result()->fetch_assoc();
}
 

$can_rate = false;
if (!$is_owner && $my_request && $my_request['status'] === 'approved' && $listing['status'] === 'sold') {
    $already = $conn->prepare("SELECT rating_id FROM rating WHERE listing_id = ? AND buyer_id = ?");
    $already->bind_param("ii", $listing_id, $viewer_id);
    $already->execute();
    $can_rate = $already->get_result()->num_rows === 0;
}
 
$page_title = $listing['title'];
require_once __DIR__ . '/includes/header.php';
?>
 
<?php if (isset($_GET['created'])): ?>
    <div class="alert alert-success">Listing created successfully!</div>
<?php endif; ?>
<?php if (isset($_GET['reported'])): ?>
    <div class="alert alert-success">Thanks — this listing has been reported and hidden pending review.</div>
<?php endif; ?>
<?php if (isset($_GET['rated'])): ?>
    <div class="alert alert-success">Thanks for rating the seller!</div>
<?php endif; ?>
<?php if (isset($_GET['requested'])): ?>
    <div class="alert alert-success">Purchase request sent! You'll see the seller's response on your Requests page.</div>
<?php endif; ?>
 
<div class="page-header">
    <h1><?php echo htmlspecialchars($listing['title']); ?></h1>
    <span class="badge <?php echo $listing['status'] === 'active' ? 'badge-success' : 'badge-muted'; ?>">
        <?php echo htmlspecialchars(ucfirst($listing['status'])); ?>
    </span>
</div>
 
<?php if (!$is_admin && !$is_verified && !$is_owner): ?>
    <div class="alert alert-info">Your account isn't verified yet, so you can browse but can't message, buy, rate, or report listings.</div>
<?php endif; ?>
 
<div class="card">
    <?php if ($photos->num_rows > 0): ?>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
            <?php while ($photo = $photos->fetch_assoc()): ?>
                <img src="<?php echo htmlspecialchars($photo['photo_url']); ?>" alt="<?php echo htmlspecialchars($listing['title']); ?>" style="width:220px;height:220px;object-fit:cover;border-radius:10px;border:1px solid var(--border);">
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="listing-thumb" style="height:200px;margin-bottom:16px;">No photo available</div>
    <?php endif; ?>
 
    <p class="listing-price" style="font-size:22px;">TZS <?php echo number_format($listing['price'], 2); ?></p>
 
    <div class="info-row"><span class="label">Category</span><span class="value"><?php echo htmlspecialchars($listing['category_name']); ?></span></div>
    <div class="info-row"><span class="label">Condition</span><span class="value"><?php echo htmlspecialchars(ucfirst($listing['condition_type'])); ?></span></div>
    <div class="info-row"><span class="label">Posted</span><span class="value"><?php echo htmlspecialchars($listing['posted_at']); ?></span></div>
 
    <h3>Description</h3>
    <p><?php echo nl2br(htmlspecialchars($listing['description'])); ?></p>
</div>
 
<div class="card">
    <h3 class="mt-0">Seller</h3>
    <div class="flex-between">
        <div>
            <p style="font-weight:700;margin:0;"><?php echo htmlspecialchars($listing['seller_name']); ?></p>
            <?php if ($rating_summary['total'] > 0): ?>
                <p class="muted" style="margin:4px 0 0;">
                    <span class="stars"><?php echo str_repeat('★', round($rating_summary['avg_score'])) . str_repeat('☆', 5 - round($rating_summary['avg_score'])); ?></span>
                    <?php echo number_format($rating_summary['avg_score'], 1); ?> · Total Ratings: <?php echo $rating_summary['total']; ?>
                </p>
            <?php else: ?>
                <p class="muted" style="margin:4px 0 0;">No reviews yet</p>
            <?php endif; ?>
        </div>
        <a class="btn btn-secondary btn-sm" href="view_profile.php?id=<?php echo $listing['seller_id']; ?>">View Profile</a>
    </div>
 
    <?php if ($is_owner): ?>
        <p class="muted mt-16">This is your listing. Manage buy requests from <a href="requests.php">Requests</a>.</p>
        <?php elseif ($is_admin): ?>
            <p class="muted mt-16">
                Administrator view: buying, messaging sellers, and reporting listings are disabled for admin accounts.
            </p>
        <?php elseif (!$is_verified): ?>
            <p class="muted mt-16">
                Verify your account to message the seller, buy this item, or leave a report.
            </p>
        <?php else: ?>

        <div class="btn-row">
            <a href="send_message.php?id=<?php echo $listing['listing_id']; ?>" class="btn btn-secondary">Message Seller</a>
 
            <?php if ($listing['status'] === 'active'): ?>
                <?php if (!$my_request || $my_request['status'] === 'rejected'): ?>
                    <a href="buy_request.php?id=<?php echo $listing['listing_id']; ?>" class="btn btn-primary">Buy</a>
                <?php elseif ($my_request['status'] === 'pending'): ?>
                    <span class="badge badge-warn">Request Pending</span>
                <?php endif; ?>
            <?php elseif ($my_request && $my_request['status'] === 'approved'): ?>
                <span class="badge badge-success">You bought this item</span>
            <?php endif; ?>
 
            <?php if ($can_rate): ?>
                <a href="rate_seller.php?listing_id=<?php echo $listing['listing_id']; ?>" class="btn btn-secondary">Rate Seller</a>
            <?php endif; ?>
 
            <a href="report_listing.php?id=<?php echo $listing['listing_id']; ?>" class="btn btn-danger">Report Listing</a>
        </div>
    <?php endif; ?>
</div>
 
<a href="browse_listings.php">← Back to Listings</a>
 
<?php require_once __DIR__ . '/includes/footer.php'; ?>