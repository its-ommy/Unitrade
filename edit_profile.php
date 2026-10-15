<?php

require_once __DIR__ . '/auth.php';
require_login();
$user_id = (int) $_SESSION['user_id'];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $full_name = trim($_POST['full_name']);

    if ($full_name === '') {
        $error = "Full name is required.";
    } else {

        $update_stmt = $conn->prepare("UPDATE user SET full_name = ? WHERE user_id = ?");
        $update_stmt->bind_param("si", $full_name, $user_id);

        if ($update_stmt->execute()) {
            $_SESSION['full_name'] = $full_name;
            header("Location: profile.php");
            exit;
        } else {
            $error = "Failed to update profile.";
        }
    }
}

$sql = "SELECT full_name, campus_email, registration_number, college_id FROM user WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}
$user = $result->fetch_assoc();

$page_title = 'Edit Profile';
$narrow = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:480px;margin:10px auto 0;">
    <h1>Edit Profile</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>

        <div class="field">
            <label>Full Name</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
        </div>

        <p class="muted" style="font-size:13px;">Campus email and registration number are tied to your verification record and can't be changed here. Contact an administrator if these need correcting.</p>

        <div class="info-row"><span class="label">Campus Email</span><span class="value"><?php echo htmlspecialchars($user['campus_email']); ?></span></div>
        <div class="info-row"><span class="label">Registration Number</span><span class="value"><?php echo htmlspecialchars($user['registration_number']); ?></span></div>

        <div class="btn-row mt-16">
            <button type="submit" class="btn btn-primary">Update Profile</button>
            <a class="btn btn-secondary" href="profile.php">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
