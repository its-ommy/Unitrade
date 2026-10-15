<?php

require_once __DIR__ . '/auth.php';
$admin = require_admin($conn);

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $report_id = (int) $_POST['report_id'];
    $action = $_POST['action'];

    $rstmt = $conn->prepare("SELECT listing_id FROM report WHERE report_id = ?");
    $rstmt->bind_param("i", $report_id);
    $rstmt->execute();
    $rresult = $rstmt->get_result();

    if ($rresult->num_rows === 1) {
        $report = $rresult->fetch_assoc();

        if ($action === 'dismiss') {
            $upd = $conn->prepare("UPDATE report SET status = 'dismissed' WHERE report_id = ?");
            $upd->bind_param("i", $report_id);
            $upd->execute();
            $message = "Report dismissed. The listing is visible again.";

        } elseif ($action === 'remove') {
            $upd = $conn->prepare("UPDATE report SET status = 'resolved' WHERE report_id = ?");
            $upd->bind_param("i", $report_id);
            $upd->execute();

            $del = $conn->prepare("DELETE FROM listing WHERE listing_id = ?");
            $del->bind_param("i", $report['listing_id']);
            $del->execute();

            $message = "Listing removed and report resolved.";
        }
    }
}

$sql = "SELECT r.report_id, r.reason, r.status, r.created_at,
               l.listing_id, l.title AS listing_title,
               reporter.full_name AS reporter_name,
               seller.full_name AS seller_name
        FROM report r
        JOIN listing l ON r.listing_id = l.listing_id
        JOIN user reporter ON r.reporter_id = reporter.user_id
        JOIN user seller ON l.seller_id = seller.user_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC";

$reports = $conn->query($sql);

$page_title = 'Reported Listings';
$active_nav = 'admin';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Reported Listings</h1>
    <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">← Admin Console</a>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<?php if ($reports->num_rows > 0): ?>

    <?php while ($r = $reports->fetch_assoc()): ?>
        <div class="card">
            <div class="flex-between">
                <h2 class="mt-0"><?php echo htmlspecialchars($r['listing_title']); ?></h2>
                <span class="badge badge-danger">Pending Review</span>
            </div>
            <p class="listing-meta">Seller: <?php echo htmlspecialchars($r['seller_name']); ?> · Reported by <?php echo htmlspecialchars($r['reporter_name']); ?> on <?php echo htmlspecialchars($r['created_at']); ?></p>
            <p><strong>Reason:</strong> <?php echo htmlspecialchars($r['reason']); ?></p>

            <div class="btn-row">
                <a class="btn btn-secondary btn-sm" href="listing_details.php?id=<?php echo $r['listing_id']; ?>" target="_blank">View Listing</a>
                <form method="POST" style="display:inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="report_id" value="<?php echo $r['report_id']; ?>">
                    <input type="hidden" name="action" value="dismiss">
                    <button type="submit" class="btn btn-secondary btn-sm">Dismiss Report</button>
                </form>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently remove this listing?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="report_id" value="<?php echo $r['report_id']; ?>">
                    <input type="hidden" name="action" value="remove">
                    <button type="submit" class="btn btn-danger btn-sm">Remove Listing</button>
                </form>
            </div>
        </div>
    <?php endwhile; ?>

<?php else: ?>
    <div class="empty-state card">
        <div class="icon">✅</div>
        <p>No pending reports. Nice and clean!</p>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
