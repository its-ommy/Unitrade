<?php
require_once __DIR__ . '/auth.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$message_type = 'error';

if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $message = 'Your password has been reset. You can now log in.';
    $message_type = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $campus_email = trim($_POST['campus_email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);

    $sql = "SELECT
                user_id,
                full_name,
                campus_email,
                password_hash,
                verification_status,
                role
            FROM user
            WHERE campus_email = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $campus_email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password_hash'])) {

            set_auth_session($user);
            session_regenerate_id(true);

            if ($remember_me) {
                $selector = bin2hex(random_bytes(16));
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires_at = date('Y-m-d H:i:s', time() + (86400 * 30));

                $remember_stmt = $conn->prepare('INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at) VALUES (?, ?, ?, ?)');
                $remember_stmt->bind_param('isss', $user['user_id'], $selector, $token_hash, $expires_at);
                $remember_stmt->execute();

                $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                setcookie('unitrade_remember', $selector . ':' . $token, time() + (86400 * 30), '/', '', $secure, true);
            }

            header("Location: dashboard.php");
            exit;

        } else {
            $message = "Incorrect password.";
        }

    } else {
        $message = "Account not found.";
    }
}

$page_title = 'Login';
$active_nav = 'login';
$minimal_nav = true;
$narrow = true;

require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:420px;margin:40px auto 0;">
    <h1 class="text-center" style="margin-bottom:4px;">Welcome back</h1>
    <p class="muted text-center" style="margin-top:0;">Sign in to your UniTrade account</p>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>

        <div class="field">
            <label>Email</label>
            <input type="email" name="campus_email" placeholder="Enter your Email" required autofocus>
        </div>

        <div class="field">
            <label>Password</label>
            <div class="auth-input-wrap has-toggle">
             <input
                type="password"
                name="password"
                id="loginPassword"
                placeholder="Enter your password"
                required
             >

            <button
                type="button"
                class="toggle-visibility"
                onclick="togglePw('loginPassword', this)"
                aria-label="Show password"
             >
            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
             >
            <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/>
            <circle cx="12" cy="12" r="3"/>
        </svg>
    </button>
</div>

        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin:4px 0 16px;font-size:13px;">
            <label style="display:flex;align-items:center;gap:7px;margin:0;font-weight:500;">
                <input type="checkbox" name="remember_me" value="1" style="width:auto;margin:0;">
                Remember me
            </label>
            <a href="forgot_password.php">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Log In</button>
    </form>

    <p class="text-center mt-16 muted">
        Don't have an account? <a href="Register.php">Create one</a>
    </p>
</div>
<p class="text-center mt-16"><a href="index.php" class="muted">← Back to home</a></p>

<script src="assets/auth.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>