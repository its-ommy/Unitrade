<?php

require_once __DIR__ . '/auth.php';
$admin = require_admin($conn);
$admin_id = (int) $admin['user_id'];

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $target_id = (int) $_POST['user_id'];
    $action = $_POST['action'];

    if ($target_id === $admin_id) {
        $message = "You can't change your own account here.";
    } elseif ($action === 'verify') {
        $stmt = $conn->prepare("UPDATE user SET verification_status = 'verified' WHERE user_id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $message = "User verified.";
    } elseif ($action === 'unverify') {
        $stmt = $conn->prepare("UPDATE user SET verification_status = 'unverified' WHERE user_id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $message = "User set to unverified.";
    } elseif ($action === 'make_admin') {
        $stmt = $conn->prepare("UPDATE user SET role = 'admin' WHERE user_id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $message = "User promoted to administrator.";
    } elseif ($action === 'make_student') {
        $stmt = $conn->prepare("UPDATE user SET role = 'student' WHERE user_id = ?");
        $stmt->bind_param("i", $target_id);
        $stmt->execute();
        $message = "User demoted to student.";
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT u.user_id, u.full_name, u.campus_email, u.verification_status, u.role, c.name AS college_name
        FROM user u
        JOIN college c ON u.college_id = c.college_id
        WHERE u.full_name LIKE ? OR u.campus_email LIKE ?
        ORDER BY u.user_id DESC";
$stmt = $conn->prepare($sql);
$like = "%$search%";
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$users = $stmt->get_result();

$page_title = 'Manage Users';
$active_nav = 'admin';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Manage Users</h1>
    <a href="admin_dashboard.php" class="btn btn-secondary btn-sm">← Admin Console</a>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="filter-bar">
    <form method="GET">
        <div class="filter-grid">
            <div class="field">
                <input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="field">
                <button type="submit" class="btn btn-primary btn-block">Search</button>
            </div>
        </div>
    </form>
</div>

<?php while ($u = $users->fetch_assoc()): ?>
    <div class="card">
        <div class="flex-between">
            <div>
                <strong><?php echo htmlspecialchars($u['full_name']); ?></strong>
                <span class="muted"> · <?php echo htmlspecialchars($u['campus_email']); ?></span>
                <p class="listing-meta mt-8" style="margin-bottom:0;"><?php echo htmlspecialchars($u['college_name']); ?></p>
            </div>
            <div>
                <?php if ($u['verification_status'] === 'verified'): ?>
                    <span class="badge badge-success">Verified</span>
                <?php else: ?>
                    <span class="badge badge-warn">Unverified</span>
                <?php endif; ?>
                <?php if ($u['role'] === 'admin'): ?>
                    <span class="badge badge-muted">Admin</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ((int) $u['user_id'] !== $admin_id): ?>
        <div class="btn-row mt-16">
            <form method="POST" style="display:inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                <?php if ($u['verification_status'] === 'verified'): ?>
                    <input type="hidden" name="action" value="unverify">
                    <button type="submit" class="btn btn-secondary btn-sm">Unverify</button>
                <?php else: ?>
                    <input type="hidden" name="action" value="verify">
                    <button type="submit" class="btn btn-secondary btn-sm">Verify</button>
                <?php endif; ?>
            </form>
            <form method="POST" style="display:inline;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="user_id" value="<?php echo $u['user_id']; ?>">
                <?php if ($u['role'] === 'admin'): ?>
                    <input type="hidden" name="action" value="make_student">
                    <button type="submit" class="btn btn-secondary btn-sm">Remove Admin</button>
                <?php else: ?>
                    <input type="hidden" name="action" value="make_admin">
                    <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Grant admin access to this user?');">Make Admin</button>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>
    </div>
<?php endwhile; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
