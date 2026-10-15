<?php

require_once __DIR__ . '/auth.php';
$admin = require_admin($conn);

$pending_reports = $conn->query("SELECT COUNT(*) AS c FROM report WHERE status = 'pending'")->fetch_assoc()['c'];
$total_users = $conn->query("SELECT COUNT(*) AS c FROM user")->fetch_assoc()['c'];
$unverified_users = $conn->query("SELECT COUNT(*) AS c FROM user WHERE verification_status != 'verified'")->fetch_assoc()['c'];
$active_listings = $conn->query("SELECT COUNT(*) AS c FROM listing WHERE status = 'active'")->fetch_assoc()['c'];

$page_title = 'Admin Console';
$active_nav = 'admin';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Admin Console</h1>
</div>

<div class="listing-grid">
    <a class="card" href="admin_reports.php" style="text-align:center;">
        <div style="font-size:26px;"><?php echo $pending_reports > 0 ? '🚩' : '✅'; ?></div>
        <h2><?php echo (int) $pending_reports; ?> Pending Report<?php echo $pending_reports == 1 ? '' : 's'; ?></h2>
        <p class="muted" style="margin:0;">Review reported listings</p>
    </a>
    <a class="card" href="admin_users.php" style="text-align:center;">
        <div style="font-size:26px;">👥</div>
        <h2><?php echo (int) $total_users; ?> Users</h2>
        <p class="muted" style="margin:0;"><?php echo (int) $unverified_users; ?> unverified</p>
    </a>
    <div class="card" style="text-align:center;">
        <div style="font-size:26px;">🛍️</div>
        <h2><?php echo (int) $active_listings; ?> Active Listings</h2>
        <p class="muted" style="margin:0;">Currently live on the platform</p>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
