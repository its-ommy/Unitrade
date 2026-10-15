<?php

require_once __DIR__ . '/auth.php';
require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID.");
}

$profile_user_id = (int) $_GET['id'];

// If viewing your own profile via this link, send to the full profile page
if ($profile_user_id === (int) $_SESSION['user_id']) {
    header("Location: profile.php");
    exit;
}

$sql = "SELECT u.user_id, u.full_name, u.verification_status, c.name AS college_name, un.name AS university_name
        FROM user u
        JOIN college c ON u.college_id = c.college_id
        JOIN university un ON c.university_id = un.university_id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $profile_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}
$user = $result->fetch_assoc();

// Active listings by this user (FR-13: view other users' public profile & listings)
$listing_stmt = $conn->prepare("SELECT listing_id, title, price, condition_type
                                 FROM listing
                                 WHERE seller_id = ? AND status = 'active'
                                 AND NOT EXISTS (SELECT 1 FROM report r WHERE r.listing_id = listing.listing_id AND r.status = 'pending')
                                 ORDER BY posted_at DESC");
$listing_stmt->bind_param("i", $profile_user_id);
$listing_stmt->execute();
$listings = $listing_stmt->get_result();

// Ratings received (FR-14)
$rating_summary_stmt = $conn->prepare("SELECT AVG(score) AS avg_score, COUNT(*) AS total FROM rating WHERE seller_id = ?");
$rating_summary_stmt->bind_param("i", $profile_user_id);
$rating_summary_stmt->execute();
$rating_summary = $rating_summary_stmt->get_result()->fetch_assoc();

$rating_list_stmt = $conn->prepare("SELECT r.score, r.review, r.created_at, u.full_name AS buyer_name
                                     FROM rating r
                                     JOIN user u ON r.buyer_id = u.user_id
                                     WHERE r.seller_id = ?
                                     ORDER BY r.created_at DESC
                                     LIMIT 10");
$rating_list_stmt->bind_param("i", $profile_user_id);
$rating_list_stmt->execute();
$ratings = $rating_list_stmt->get_result();

$page_title = $user['full_name'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
    <?php if ($user['verification_status'] === 'verified'): ?>
        <span class="badge badge-success">Verified Student</span>
    <?php endif; ?>
</div>

<div class="card">
    <div class="info-row"><span class="label">University</span><span class="value"><?php echo htmlspecialchars($user['university_name']); ?></span></div>
    <div class="info-row"><span class="label">College</span><span class="value"><?php echo htmlspecialchars($user['college_name']); ?></span></div>
    <div class="info-row">
        <span class="label">Rating</span>
        <span class="value">
            <?php if ($rating_summary['total'] > 0): ?>
                <span class="stars"><?php echo str_repeat('★', round($rating_summary['avg_score'])) . str_repeat('☆', 5 - round($rating_summary['avg_score'])); ?></span>
                <?php echo number_format($rating_summary['avg_score'], 1); ?> (<?php echo $rating_summary['total']; ?>)
            <?php else: ?>
                No reviews yet
            <?php endif; ?>
        </span>
    </div>
</div>

<h3>Active Listings</h3>
<?php if ($listings->num_rows > 0): ?>
    <div class="listing-grid">
        <?php while ($listing = $listings->fetch_assoc()): ?>
            <a class="listing-card" href="listing_details.php?id=<?php echo $listing['listing_id']; ?>">
                <div class="listing-body">
                    <p class="listing-title"><?php echo htmlspecialchars($listing['title']); ?></p>
                    <p class="listing-price">TZS <?php echo number_format($listing['price'], 2); ?></p>
                    <p class="listing-meta"><?php echo htmlspecialchars(ucfirst($listing['condition_type'])); ?></p>
                </div>
            </a>
        <?php endwhile; ?>
    </div>
<?php else: ?>
    <p class="muted">No active listings right now.</p>
<?php endif; ?>

<h3>Reviews</h3>
<div class="card">
    <?php if ($ratings->num_rows > 0): ?>
        <?php while ($r = $ratings->fetch_assoc()): ?>
            <div class="rating-item">
                <div class="flex-between">
                    <strong><?php echo htmlspecialchars($r['buyer_name']); ?></strong>
                    <span class="stars"><?php echo str_repeat('★', $r['score']) . str_repeat('☆', 5 - $r['score']); ?></span>
                </div>
                <?php if ($r['review'] !== ''): ?><p class="mt-8" style="margin-bottom:0;"><?php echo htmlspecialchars($r['review']); ?></p><?php endif; ?>
                <p class="muted" style="font-size:12px;margin:4px 0 0;"><?php echo htmlspecialchars($r['created_at']); ?></p>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="muted mt-0">No reviews yet.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
