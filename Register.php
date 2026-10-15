<?php
require_once __DIR__ . '/auth.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$message_type = 'info';

// FR-02: college list for the dropdown
$college_sql = "SELECT c.college_id, c.name AS college_name, u.name AS university_name
                 FROM college c
                 JOIN university u ON c.university_id = u.university_id
                 ORDER BY u.name, c.name";
$college_result = $conn->query($college_sql);
$colleges = $college_result ? $college_result->fetch_all(MYSQLI_ASSOC) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    csrf_verify();

    $full_name = trim($_POST['full_name']);
    $campus_email = trim($_POST['campus_email']);
    $registration_number = trim($_POST['registration_number']);
    $college_id = (int) $_POST['college_id'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name === '' || $campus_email === '' || $registration_number === '' || $college_id <= 0) {
        $message = "Please fill in all required fields, including your college.";
        $message_type = 'error';
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters.";
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = 'error';
    } else {

        // Prevent duplicate accounts on the same campus email
        $dupe_stmt = $conn->prepare("SELECT user_id FROM user WHERE campus_email = ?");
        $dupe_stmt->bind_param("s", $campus_email);
        $dupe_stmt->execute();
        $dupe_result = $dupe_stmt->get_result();

        if ($dupe_result->num_rows > 0) {
            $message = "An account with that campus email already exists.";
            $message_type = 'error';
        } else {

        
            $sql = "SELECT student_name, record_status
                    FROM student_record
                    WHERE registration_number = ?
                    AND college_id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $registration_number, $college_id);
            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $student = $result->fetch_assoc();
                $verification_status = ($student['record_status'] === 'active') ? 'verified' : 'unverified';
            } else {
                $verification_status = 'unverified';
            }

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $insert_sql = "INSERT INTO user
                (college_id, full_name, campus_email, registration_number,
                 password_hash, verification_status, role)
                VALUES (?, ?, ?, ?, ?, ?, 'student')";

            $insert_stmt = $conn->prepare($insert_sql);

            $insert_stmt->bind_param(
                "isssss",
                $college_id,
                $full_name,
                $campus_email,
                $registration_number,
                $password_hash,
                $verification_status
            );

            if ($insert_stmt->execute()) {
                header("Location: login.php?registered=1");
                    exit;
            }

            else {
                $message = "Registration failed. Please try again.";
                $message_type = 'error';
            }
        }
    }
}

$page_title = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - UniTrade</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/register.css">
</head>
<body>

<div class="auth-page">
    <div class="auth-blob"></div>

    <div class="auth-card">
        <div class="auth-logo">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 3L1 8l11 5 9-4.09V17h2V8L12 3z" fill="currentColor"/>
                <path d="M5 10.18V14c0 2.21 3.13 4 7 4s7-1.79 7-4v-3.82l-7 3.18-7-3.18z" fill="currentColor"/>
            </svg>
            <span>UniTrade</span>
        </div>

        <h1>Create Your Account</h1>
        <p class="auth-sub">Join UniTrade and start buying and selling with your university community.</p>

        <?php if ($message !== ''): ?>
            <div class="auth-alert <?php echo $message_type === 'success' ? 'success' : ($message_type === 'error' ? 'error' : 'info'); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php echo csrf_field(); ?>

            <div class="auth-field">
                <div class="auth-field-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Full Name
                </div>
                <input type="text" name="full_name" placeholder="Enter your full name" required value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
            </div>

            <div class="auth-field">
                <div class="auth-field-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-10 6L2 7"/></svg>
                    Email
                </div>
                <input type="email" name="campus_email" placeholder="Enter your Email" required value="<?php echo isset($_POST['campus_email']) ? htmlspecialchars($_POST['campus_email']) : ''; ?>">
            </div>

            <div class="auth-field">
                <div class="auth-field-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M4 21V9l8-6 8 6v12M9 21v-6h6v6"/></svg>
                    College
                </div>
                <select name="college_id" required>
                    <option value="">Select College</option>
                    <?php foreach ($colleges as $college): ?>
                        <option value="<?php echo (int) $college['college_id']; ?>"
                            <?php echo (isset($_POST['college_id']) && (int) $_POST['college_id'] === (int) $college['college_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($college['university_name'] . ' — ' . $college['college_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="auth-field">
                <div class="auth-field-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>
                    Registration Number
                </div>
                <input type="text" name="registration_number" placeholder="Enter your registration number" required value="<?php echo isset($_POST['registration_number']) ? htmlspecialchars($_POST['registration_number']) : ''; ?>">
            </div>

            <div class="auth-field">
                <div class="auth-field-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Password
                </div>
                <div class="auth-input-wrap has-toggle">
                    <input type="password" name="password" id="pw1" placeholder="Create a password" required minlength="8">
                    <button type="button" class="toggle-visibility" onclick="togglePw('pw1', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="auth-field">
                <div class="auth-field-label">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Confirm Password
                </div>
                <div class="auth-input-wrap has-toggle">
                    <input type="password" name="confirm_password" id="pw2" placeholder="Re-enter your password" required minlength="8">
                    <button type="button" class="toggle-visibility" onclick="togglePw('pw2', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
                <p id="pwMatchMsg" class="field-msg"></p>
            </div>

            <button type="submit" class="auth-submit" id="registerSubmit">Register</button>
        </form>

        <p class="auth-footer-link">Already have an account? <a href="login.php">Log in</a></p>
    </div>
</div>

<p class="auth-back"><a href="index.php">← Back to home</a></p>

<script src="assets/auth.js"></script>
<script>
    initPasswordMatchValidation('pw1', 'pw2', 'pwMatchMsg');
</script>

</body>
</html>
 
