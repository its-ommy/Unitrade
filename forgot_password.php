<?php
require_once __DIR__ . '/auth.php';

$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $campus_email = trim($_POST['campus_email'] ?? '');

    // Always show the same response so email addresses cannot be enumerated.
    $message = 'If an account exists for that email, a password reset link has been sent. The link expires in 30 minutes.';
    $message_type = 'success';

    if (filter_var($campus_email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare('SELECT user_id, full_name, campus_email FROM user WHERE campus_email = ? LIMIT 1');
        $stmt->bind_param('s', $campus_email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $conn->query("DELETE FROM password_resets WHERE expires_at < NOW() OR used_at IS NOT NULL");

            $reset = $conn->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
            );

            $reset->bind_param('is', $user['user_id'], $token_hash);

            $reset->execute();

            $mail_config_file = __DIR__ . '/config.mail.php';
            $autoload = __DIR__ . '/vendor/autoload.php';
            if (is_file($mail_config_file) && is_file($autoload)) {
                $mail_config = require $mail_config_file;
                require_once $autoload;

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $base_url = rtrim($mail_config['base_url'] ?: $scheme . '://' . $host, '/');
                $reset_link = $base_url . '/reset_password.php?token=' . urlencode($token);

                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mailer->isSMTP();
                    $mailer->Host = $mail_config['host'];
                    $mailer->SMTPAuth = true;
                    $mailer->Username = $mail_config['username'];
                    $mailer->Password = $mail_config['password'];
                    $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mailer->Port = (int) $mail_config['port'];
                    $mailer->setFrom($mail_config['from_email'], $mail_config['from_name']);
                    $mailer->addAddress($user['campus_email'], $user['full_name']);
                    $mailer->isHTML(true);
                    $mailer->Subject = 'Reset your UniTrade password';
                    $mailer->Body = '<p>Hello ' . htmlspecialchars($user['full_name']) . ',</p>'
                        . '<p>Click the button below to create a new UniTrade password. This link expires in 30 minutes.</p>'
                        . '<p><a href="' . htmlspecialchars($reset_link) . '" style="display:inline-block;padding:12px 18px;background:#2f8fa8;color:#fff;text-decoration:none;border-radius:8px;">Reset My Password</a></p>'
                        . '<p>If you did not request this, you can ignore this email.</p>';
                    $mailer->AltBody = "Reset your UniTrade password: $reset_link\nThis link expires in 30 minutes.";
                    $mailer->send();
                } catch (Throwable $e) {
                    error_log('UniTrade reset email failed: ' . $e->getMessage());
                }
            } else {
                error_log('Forgot password requested but config.mail.php or vendor/autoload.php is missing.');
            }
        }
    }
}

$page_title = 'Forgot Password';
$minimal_nav = true;
$narrow = true;

require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:480px;margin:40px auto 0;">
    <h1 class="text-center">Forgot Password?</h1>
    <p class="muted text-center">Enter your campus email and we will send you a secure reset link.</p>

    <?php if ($message !== ''): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'info'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrf_field(); ?>
        <div class="field">
            <label for="campus_email">Campus Email</label>
            <input type="email" id="campus_email" name="campus_email" required autofocus placeholder="Enter your campus email">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
    </form>

    <p class="text-center mt-16"><a href="login.php">← Back to login</a></p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>