<?php
require_once __DIR__ . '/auth.php';

$current_user = get_current_user_data($conn);

$page_title = 'Dashboard';
$active_nav = 'dashboard';
require_once __DIR__ . '/includes/header.php';

$is_admin_dash = $current_user['role'] === 'admin';
$is_verified_dash = $current_user['verification_status'] === 'verified';
?>

<div class="page-header">
    <h1>Hi, <?php echo htmlspecialchars(explode(' ', $current_user['full_name'])[0]); ?> 👋</h1>
    <?php if ($current_user['verification_status'] === 'verified'): ?>
        <span class="badge badge-success">Verified</span>
    <?php else: ?>
        <span class="badge badge-warn">Unverified</span>
    <?php endif; ?>
</div>

<?php if ($current_user['verification_status'] !== 'verified'): ?>
    <div class="alert alert-info">
        Your account isn't verified yet, so you can browse listings but can't post items or message sellers.
        Verification is checked automatically against your college's student registry and usually clears within 48 hours.
    </div>
<?php endif; ?>

<div class="listing-grid">
    <a class="card" href="browse_listings.php" style="text-align:center;">
        <div style="font-size:28px;">🛍️</div>
        <h2>Browse Listings</h2>
        <p class="muted" style="margin:0;">See what's for sale on campus</p>
    </a>
    <?php if (!$is_admin_dash): ?>
    <a class="card" href="my_listings.php" style="text-align:center;">
        <div style="font-size:28px;">📦</div>
        <h2>My Listings</h2>
        <p class="muted" style="margin:0;">Manage items you're selling</p>
    </a>
    <a class="card" href="create_listing.php" style="text-align:center;">
        <div style="font-size:28px;">➕</div>
        <h2>Create Listing</h2>
        <p class="muted" style="margin:0;">Post something new for sale</p>
    </a>
    <?php endif; ?>
    <?php if ($is_verified_dash && !$is_admin_dash): ?>
    <a class="card" href="messages.php" style="text-align:center;">
        <span class="icon-badge-wrap">
            <span style="font-size:28px;">💬</span>
            <span class="icon-badge" data-notif="unread_messages" style="<?php echo $unread_message_count > 0 ? '' : 'display:none;'; ?>"><?php echo $unread_message_count; ?></span>
        </span>
        <h2>Messages</h2>
        <p class="muted" style="margin:0;">Chat with buyers and sellers</p>
    </a>
    <a class="card" href="requests.php" style="text-align:center;">
        <span class="icon-badge-wrap">
            <span style="font-size:28px;">📥</span>
            <span class="icon-badge" data-notif="pending_requests" style="<?php echo $pending_request_count > 0 ? '' : 'display:none;'; ?>"><?php echo $pending_request_count; ?></span>
        </span>
        <h2>Requests</h2>
        <p class="muted" style="margin:0;">Buy requests you've sent or received</p>
    </a>
    <?php endif; ?>
    <a class="card" href="profile.php" style="text-align:center;">
        <div style="font-size:28px;">👤</div>
        <h2>My Profile</h2>
        <p class="muted" style="margin:0;">View and edit your details</p>
    </a>
    <?php if ($is_admin_dash): ?>
    <a class="card" href="admin_dashboard.php" style="text-align:center;">
        <div style="font-size:28px;">🛠️</div>
        <h2>Admin Console</h2>
        <p class="muted" style="margin:0;">Review reports & manage users</p>
    </a>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>