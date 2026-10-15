<?php

require_once __DIR__ . '/auth.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

$message = '';
$message_type = 'error';
$reset_row = null;


if ($token !== '') {
    $token_hash = hash('sha256', $token);

    $stmt = $conn->prepare(
        "SELECT
            pr.reset_id,
            pr.user_id,
            u.campus_email
         FROM password_resets pr
         JOIN user u ON u.user_id = pr.user_id
         WHERE pr.token_hash = ?
           AND pr.used_at IS NULL
           AND pr.expires_at > NOW()
         LIMIT 1"
    );

    $stmt->bind_param('s', $token_hash);
    $stmt->execute();

    $reset_row = $stmt->get_result()->fetch_assoc();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset_row) {

    csrf_verify();

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';

    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';

    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $conn->begin_transaction();

        try {
            $update = $conn->prepare(
                'UPDATE user
                 SET password_hash = ?
                 WHERE user_id = ?'
            );

            $user_id = (int) $reset_row['user_id'];

            $update->bind_param(
                'si',
                $password_hash,
                $user_id
            );

            $update->execute();

        
            $use_token = $conn->prepare(
                'UPDATE password_resets
                 SET used_at = NOW()
                 WHERE reset_id = ?'
            );

            $reset_id = (int) $reset_row['reset_id'];

            $use_token->bind_param(
                'i',
                $reset_id
            );

            $use_token->execute();

            $revoke = $conn->prepare(
                'DELETE FROM remember_tokens
                 WHERE user_id = ?'
            );

            $revoke->bind_param(
                'i',
                $user_id
            );

            $revoke->execute();

            $conn->commit();

            header('Location: login.php?reset=1');
            exit;

        } catch (Throwable $e) {
            $conn->rollback();

            $message = 'Unable to reset the password right now. Please try again.';
        }
    }

} elseif ($token === '' || !$reset_row) {
    $message = 'This reset link is invalid, expired, or already used.';
}

$page_title = 'Reset Password';
$minimal_nav = true;
$narrow = true;

require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:480px;margin:40px auto 0;">

    <h1 class="text-center">Create a New Password</h1>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($reset_row): ?>

        <form method="POST">
            <?php echo csrf_field(); ?>

            <input
                type="hidden"
                name="token"
                value="<?php echo htmlspecialchars($token); ?>"
            >

            <div class="field">
                <label for="password">New Password</label>

                <div class="auth-input-wrap has-toggle">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        minlength="8"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-visibility"
                        onclick="togglePw('password', this)"
                        aria-label="Show or hide new password"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm New Password</label>

                <div class="auth-input-wrap has-toggle">
                    <input
                        type="password"
                        name="confirm_password"
                        id="confirm_password"
                        minlength="8"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-visibility"
                        onclick="togglePw('confirm_password', this)"
                        aria-label="Show or hide confirmed password"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            aria-hidden="true"
                        >
                            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button
                type="submit"
                class="btn btn-primary btn-block"
            >
                Save New Password
            </button>
        </form>

    <?php endif; ?>

    <p class="text-center mt-16">
        <a href="login.php">← Back to login</a>
    </p>

</div>

<script src="assets/auth.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
