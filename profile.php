<?php

require_once __DIR__ . '/auth.php';
require_login();
$user_id = (int) $_SESSION['user_id'];

$sql = "SELECT
            u.full_name, u.campus_email, u.registration_number,
            u.verification_status, u.role,
            c.name AS college_name, un.name AS university_name
        FROM user u
        JOIN college c ON u.college_id = c.college_id
        JOIN university un ON c.university_id = un.university_id
        WHERE u.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User profile not found.");
}
$user = $result->fetch_assoc();

$rating_stmt = $conn->prepare("SELECT AVG(score) AS avg_score, COUNT(*) AS total FROM rating WHERE seller_id = ?");
$rating_stmt->bind_param("i", $user_id);
$rating_stmt->execute();
$rating_summary = $rating_stmt->get_result()->fetch_assoc();

$page_title = 'My Profile';
$active_nav = 'profile';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>My Profile</h1>
    <a href="view_profile.php?id=<?php echo $user_id; ?>" class="btn btn-secondary btn-sm">See public view</a>
</div>

<div class="card">
    <h3 class="mt-0">Personal Information</h3>
    <div class="info-row"><span class="label">Full Name</span><span class="value"><?php echo htmlspecialchars($user['full_name']); ?></span></div>
    <div class="info-row"><span class="label">Campus Email</span><span class="value"><?php echo htmlspecialchars($user['campus_email']); ?></span></div>
    <div class="info-row"><span class="label">Registration Number</span><span class="value"><?php echo htmlspecialchars($user['registration_number']); ?></span></div>
</div>

<div class="card">
    <h3 class="mt-0">University Information</h3>
    <div class="info-row"><span class="label">University</span><span class="value"><?php echo htmlspecialchars($user['university_name']); ?></span></div>
    <div class="info-row"><span class="label">College</span><span class="value"><?php echo htmlspecialchars($user['college_name']); ?></span></div>
</div>

<div class="card">
    <h3 class="mt-0">Account</h3>
    <div class="info-row">
        <span class="label">Verification Status</span>
        <span class="value">
            <?php if ($user['verification_status'] === 'verified'): ?>
                <span class="badge badge-success">Verified</span>
            <?php else: ?>
                <span class="badge badge-warn">Unverified</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="info-row"><span class="label">Role</span><span class="value"><?php echo htmlspecialchars(ucfirst($user['role'])); ?></span></div>
    <div class="info-row">
        <span class="label">Seller Rating</span>
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

<div class="btn-row">
    <a href="edit_profile.php" class="btn btn-primary">Edit Profile</a>
    <a href="change_password.php" class="btn btn-secondary">Change Password</a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
