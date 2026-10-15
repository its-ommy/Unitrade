<div class=""></div><?php

require_once __DIR__ . '/auth.php';
require_login();
$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT password_hash FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("User not found.");
}
$user = $result->fetch_assoc();

$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (!password_verify($current_password, $user['password_hash'])) {
        $message = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $message = "New password must be at least 8 characters.";
    } else {

        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE user SET password_hash = ? WHERE user_id = ?");
        $update_stmt->bind_param("si", $new_password_hash, $user_id);

        if ($update_stmt->execute()) {
            $message = "Password changed successfully.";
            $message_type = 'success';
        } else {
            $message = "Failed to change password.";
        }
    }
}

$page_title = 'Change Password';
$narrow = true;
require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:440px;margin:10px auto 0;">
    <h1>Change Password</h1>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>

        <div class="field">
            <label>Current Password</label>
            <div class="password-field">
                <input type="password" name="current_password" id="currentPw" required>

                <button type="button" onclick="togglePw('currentPw')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>

        </div>

        <div class="field">
            <label>New Password</label>
            <div class="password-field">
                <input type="password" name="new_password" id="newPw" required minlength="8">

                <button type="button" onclick="togglePw('newPw')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>

        </div>

        <div class="field">
            <label>Confirm New Password</label>
            <div class="password-field">
                <input type="password" name="confirm_password" id="confirmPw" required minlength="8">
                
                <button type="button" onclick="togglePw('newPw')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
                
            </div>

            <p id="pwMatchMsg" class="field-msg"></p>
        </div>

        <button type="submit" class="btn btn-primary btn-block" id="changePwSubmit">Change Password</button>
    </form>

    <p class="text-center mt-16"><a href="profile.php">← Back to My Profile</a></p>
</div>

<script src="assets/auth.js"></script>
<script>
    initPasswordMatchValidation('newPw', 'confirmPw', 'pwMatchMsg');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>